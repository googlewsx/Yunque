<?php
/**
 * 入群申请自动审批通知（最终版 - 显示申请人和操作人昵称）
 * - 使用 oiapi.net 获取昵称并缓存 24 小时
 * - 结果卡片显示昵称而非艾特
 * - 拒绝理由包含操作人昵称
 */

if (!in_array(消息来源, ["群聊", "互动", "入群申请"]) && raw['t'] != 'GROUP_JOIN_REQUEST') {
    return;
}

define('申请缓存时间', 3600);
define('状态缓存时间', 86400);

$群号 = 来源;

// ========== 通过 oiapi.net 获取昵称（带 24 小时缓存） ==========
function 获取昵称缓存($用户ID, $群号) {
    if (empty($用户ID) || empty($群号)) return null;
    
    $cacheKey = "昵称缓存/{$群号}/{$用户ID}";
    
    // 先尝试读取缓存
    $cached = 读($cacheKey, "info", null);
    if (is_array($cached) && isset($cached['time']) && isset($cached['name'])) {
        if (time() - $cached['time'] < 86400) {
            return $cached['name'];
        }
    }
    
    // 缓存失效，调用 oiapi.net 获取昵称
    $appid = defined('appid') ? appid : '';
    $url = "https://oiapi.net/api/Openid?openid=" . urlencode($用户ID) . "&appid=" . urlencode($appid);
    
    $response = curl($url, "GET", [], "");
    $data = json_decode($response, true);
    
    if (is_array($data) && isset($data['code']) && $data['code'] == 1 && isset($data['data']['nickname'])) {
        $name = $data['data']['nickname'];
        写($cacheKey, "info", [
            'time' => time(),
            'name' => $name
        ]);
        return $name;
    }
    
    // 如果 oiapi 获取失败，尝试从互动事件原始数据中提取
    global $raw;
    if (isset($raw['d']['member']['username']) && !empty($raw['d']['member']['username'])) {
        $name = $raw['d']['member']['username'];
        写($cacheKey, "info", [
            'time' => time(),
            'name' => $name
        ]);
        return $name;
    }
    
    return null;
}

// ========== 权限校验 ==========
function 获取群成员角色($群号, $用户ID) {
    $res = BOTAPI("/v2/groups/{$群号}/members/{$用户ID}", "GET", "");
    $data = json_decode($res, true);
    $role = $data["role"] ?? 4;
    switch ($role) {
        case 2: return "owner";
        case 3: return "admin";
        default: return "member";
    }
}

function 校验管理权限($用户ID, $群号, $发言身份) {
    if (in_array($发言身份, ["owner", "admin"])) return true;
    if ($用户ID === 主人ID) return true;
    $管理员列表 = 读("群管/管理员列表/".$群号, "list", []);
    return in_array($用户ID, $管理员列表);
}

$发言身份 = "member";
if (消息来源 === "群聊") {
    $发言身份 = raw["d"]["author"]["member_role"] ?? "member";
} elseif (消息来源 === "互动") {
    $角色数字 = raw["d"]["member"]["role"] ?? 4;
    $发言身份 = ($角色数字 == 2) ? "owner" : (($角色数字 == 3) ? "admin" : "member");
}

// ===================== 配置读写 =====================
function 获取群配置($群号) {
    $cfg = 读("入群配置/{$群号}", "config", []);
    if (!is_array($cfg)) $cfg = [];
    $default = [
        'auto_approve_invite' => false,
        'match_keywords'      => [],
        'blacklist'           => [],
    ];
    foreach ($default as $k => $v) {
        if (!isset($cfg[$k])) $cfg[$k] = $v;
    }
    return $cfg;
}

function 保存群配置($群号, $cfg) {
    写("入群配置/{$群号}", "config", $cfg);
}

// ===================== 发送结果卡片（使用昵称） =====================
function 发送结果卡片($群号, $用户ID, $结果, $原因, $操作人 = null) {
    // 获取申请人昵称
    $申请人昵称 = 获取昵称缓存($用户ID, $群号);
    if (empty($申请人昵称)) $申请人昵称 = $用户ID; // 若获取失败则显示 OpenID
    
    // 获取操作人昵称
    if ($操作人) {
        $操作人昵称 = 获取昵称缓存($操作人, $群号);
        if (empty($操作人昵称)) $操作人昵称 = $操作人;
    } else {
        $操作人昵称 = '系统自动';
    }
    
    $申请人头像 = 头像($用户ID);
    $操作人头像 = $操作人 ? 头像($操作人) : 头像(机器人ID);
    
    $结果标识 = [
        'approved'  => '✅ 已通过',
        'rejected'  => '❌ 已拒绝',
    ][$结果] ?? '未知';
    
    $md = "## 📋 入群审批结果\n\n";
    $md .= "![头像 #25px #25px]({$操作人头像}) **操作人**：{$操作人昵称}\n";
    $md .= "![头像 #25px #25px]({$申请人头像}) **申请人**：{$申请人昵称}\n";
    $md .= "**结果**：{$结果标识}\n";
    if ($原因) $md .= "**原因**：{$原因}";
    
    主动MD($群号, $md, '群聊');
}

// ===================== 自动审批逻辑 =====================
function 自动审批($申请ID, $用户ID, $问题, $答案, $群号, $邀请人 = null) {
    $cfg = 获取群配置($群号);
    $reason = '';

    // 1. 黑名单
    if (in_array($用户ID, $cfg['blacklist'])) {
        $reason = '用户已被拉黑';
        审批入群($用户ID, 'decline', $申请ID, $reason, false, $群号);
        写("入群申请/{$群号}/{$申请ID}", "status", "rejected");
        发送结果卡片($群号, $用户ID, 'rejected', $reason);
        return true;
    }

    // 2. 匹配词
    $searchText = $问题 . ' ' . $答案;
    foreach ($cfg['match_keywords'] as $kw) {
        if (mb_strpos($searchText, $kw) !== false) {
            $reason = '命中匹配词：' . $kw;
            审批入群($用户ID, 'approve', $申请ID, '', false, $群号);
            写("入群申请/{$群号}/{$申请ID}", "status", "approved");
            发送结果卡片($群号, $用户ID, 'approved', $reason);
            return true;
        }
    }

    // 3. 邀请自动同意
    if ($cfg['auto_approve_invite'] && !empty($邀请人)) {
        $reason = '邀请入群自动通过';
        审批入群($用户ID, 'approve', $申请ID, '', false, $群号);
        写("入群申请/{$群号}/{$申请ID}", "status", "approved");
        发送结果卡片($群号, $用户ID, 'approved', $reason);
        return true;
    }

    return false;
}

// ===================== 生成菜单（群管风格） =====================
function 生成菜单Markdown($标题, $额外信息 = "") {
    $randParam = "?nocache=" . time() . mt_rand(1000, 9999);
    $imageUrl = "https://api.elaina.cat/random/pc" . $randParam;
    $randomText = curl("https://api.oddfar.com/yl/q.php?c=2004&encode=text", "GET", [], "");
    $randomText = trim($randomText) ?: "远赴人间惊鸿宴，一睹人间盛世颜。";

    $md = "## {$标题}\n\n";
    $md .= "![封面 #800px #400px]($imageUrl)\n\n> ";
    $md .= $randomText . "\n\n";
    if (!empty($额外信息)) {
        $md .= $额外信息 . "\n";
    }
    return $md;
}

// ===================== 发送审批菜单 =====================
function 发送审批菜单($群号, $发言身份) {
    if (!校验管理权限(用户, $群号, $发言身份)) {
        文字('⚠️ 只有群主或管理员可以操作。');
        return;
    }

    $cfg = 获取群配置($群号);
    $auto = $cfg['auto_approve_invite'] ? '✅ 开启' : '❌ 关闭';
    $kwCount = count($cfg['match_keywords']);
    $blCount = count($cfg['blacklist']);

    $额外 .= "- 邀请自动同意：{$auto}\n";
    $额外 .= "- 匹配词数量：{$kwCount}\n";
    $额外 .= "- 黑名单数量：{$blCount}";

    $md = 生成菜单Markdown("🛠 入群审批管理", $额外);

    $rows = [
        [
            "buttons" => [
                [
                    "id" => "menu_add_keyword",
                    "render_data" => ["label" => "➕ 添加匹配词", "visited_label" => "添加匹配词", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "添加匹配词 ",
                        "at_bot_show_channel_list" => true,
                        "unsupport_tips" => "版本不支持"
                    ]
                ],
                [
                    "id" => "menu_del_keyword",
                    "render_data" => ["label" => "➖ 删除匹配词", "visited_label" => "删除匹配词", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "删除匹配词 ",
                        "at_bot_show_channel_list" => true,
                        "unsupport_tips" => "版本不支持"
                    ]
                ]
            ]
        ],
        [
            "buttons" => [
                [
                    "id" => "menu_add_blacklist",
                    "render_data" => ["label" => "🚫 添加黑名单", "visited_label" => "添加黑名单", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "添加黑名单 ",
                        "at_bot_show_channel_list" => true,
                        "unsupport_tips" => "版本不支持"
                    ]
                ],
                [
                    "id" => "menu_del_blacklist",
                    "render_data" => ["label" => "🚫 删除黑名单", "visited_label" => "删除黑名单", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "删除黑名单 ",
                        "at_bot_show_channel_list" => true,
                        "unsupport_tips" => "版本不支持"
                    ]
                ]
            ]
        ],
        [
            "buttons" => [
                [
                    "id" => "menu_list_keywords",
                    "render_data" => ["label" => "📋 查看匹配词", "visited_label" => "已查看", "style" => 1],
                    "action" => [
                        "type" => 1,
                        "permission" => ["type" => 1],
                        "data" => "menu_list_keywords",
                        "at_bot_show_channel_list" => true,
                        "unsupport_tips" => "版本不支持"
                    ]
                ],
                [
                    "id" => "menu_clear_keywords",
                    "render_data" => ["label" => "🗑 清空匹配词", "visited_label" => "已清空", "style" => 2],
                    "action" => [
                        "type" => 1,
                        "permission" => ["type" => 1],
                        "data" => "menu_clear_keywords",
                        "at_bot_show_channel_list" => true,
                        "unsupport_tips" => "版本不支持"
                    ]
                ]
            ]
        ],
        [
            "buttons" => [
                [
                    "id" => "menu_list_blacklist",
                    "render_data" => ["label" => "📋 查看黑名单", "visited_label" => "已查看", "style" => 1],
                    "action" => [
                        "type" => 1,
                        "permission" => ["type" => 1],
                        "data" => "menu_list_blacklist",
                        "at_bot_show_channel_list" => true,
                        "unsupport_tips" => "版本不支持"
                    ]
                ],
                [
                    "id" => "menu_clear_blacklist",
                    "render_data" => ["label" => "🗑 清空黑名单", "visited_label" => "已清空", "style" => 2],
                    "action" => [
                        "type" => 1,
                        "permission" => ["type" => 1],
                        "data" => "menu_clear_blacklist",
                        "at_bot_show_channel_list" => true,
                        "unsupport_tips" => "版本不支持"
                    ]
                ]
            ]
        ],
        [
            "buttons" => [
                [
                    "id" => "menu_toggle_auto",
                    "render_data" => ["label" => "🔄 切换自动同意", "visited_label" => "已切换", "style" => 1],
                    "action" => [
                        "type" => 1,
                        "permission" => ["type" => 1],
                        "data" => "menu_toggle_auto",
                        "at_bot_show_channel_list" => true,
                        "unsupport_tips" => "版本不支持"
                    ]
                ]
            ]
        ]
    ];

    $body = [
        "content" => "",
        "msg_type" => 2,
        "markdown" => ["content" => $md],
        "keyboard" => ["content" => ["rows" => $rows]]
    ];
    云雀API("群聊", $群号, $body, true);
}

// ===================== 处理菜单操作（执行型） =====================
function 处理菜单操作($群号, $btnData, $点击用户, $发言身份) {
    if (!校验管理权限($点击用户, $群号, $发言身份)) {
        主动文字($群号, "⚠️ 只有群主或管理员可以操作。");
        return;
    }

    $cfg = 获取群配置($群号);
    $action = $btnData;

    switch ($action) {
        case 'menu_toggle_auto':
            $cfg['auto_approve_invite'] = !$cfg['auto_approve_invite'];
            保存群配置($群号, $cfg);
            $status = $cfg['auto_approve_invite'] ? '✅ 开启' : '❌ 关闭';
            主动文字($群号, "🔄 邀请自动同意已切换为：{$status}");
            break;

        case 'menu_list_keywords':
            $list = $cfg['match_keywords'];
            if (empty($list)) {
                主动文字($群号, "📋 当前匹配词列表为空。");
            } else {
                $msg = "📋 匹配词列表（共 " . count($list) . " 个）：\n" . implode('、', $list);
                主动文字($群号, $msg);
            }
            break;

        case 'menu_list_blacklist':
            $list = $cfg['blacklist'];
            if (empty($list)) {
                主动文字($群号, "🚫 黑名单列表为空。");
            } else {
                $msg = "🚫 黑名单列表（共 " . count($list) . " 个）：\n" . implode("\n", $list);
                主动文字($群号, $msg);
            }
            break;

        case 'menu_clear_keywords':
            $cfg['match_keywords'] = [];
            保存群配置($群号, $cfg);
            主动文字($群号, "🗑 已清空所有匹配词。");
            break;

        case 'menu_clear_blacklist':
            $cfg['blacklist'] = [];
            保存群配置($群号, $cfg);
            主动文字($群号, "🗑 已清空黑名单。");
            break;

        default:
            主动文字($群号, "未知操作。");
            break;
    }
}

// ===================== 入群申请事件 =====================
if (raw['t'] == 'GROUP_JOIN_REQUEST') {
    $event = raw['d'];
    $申请ID = $event['join_request_id'] ?? '';
    $用户ID = $event['member_openid'] ?? '';
    $昵称   = $event['username'] ?? '未知';
    $邀请人 = $event['invitor_openid'] ?? '';

    if (empty($申请ID) || empty($用户ID)) return;

    $问题 = defined('申请问题') ? 申请问题 : '';
    $答案 = defined('申请答案') ? 申请答案 : '';

    // 缓存申请人昵称
    if ($昵称 !== '未知') {
        写("昵称缓存/{$群号}/{$用户ID}", "info", [
            'time' => time(),
            'name' => $昵称
        ]);
    }

    写("入群申请/{$群号}/{$申请ID}", "info", [
        'openid' => $用户ID, '昵称' => $昵称, '问题' => $问题, '答案' => $答案
    ]);
    写("入群申请/{$群号}/{$申请ID}", "status", "pending");

    $handled = 自动审批($申请ID, $用户ID, $问题, $答案, $群号, $邀请人);
    if ($handled) return;

    // 未自动处理，发送审批卡片
    $头像 = "https://q.qlogo.cn/qqapp/" . appid . "/{$用户ID}/640";
    $md = "## 📩 新的入群申请\n\n![头像 #40px #40px]({$头像}) **{$昵称}**\n\n";
    if ($问题) $md .= "**问题**：{$问题}\n";
    if ($答案) $md .= "**答案**：{$答案}\n";

    $rows = [[
        "buttons" => [
            [
                "id" => "approve_{$申请ID}",
                "render_data" => ["label" => "✅ 批准", "visited_label" => "已批准", "style" => 1],
                "action" => [
                    "type" => 1,
                    "permission" => ["type" => 1],
                    "data" => "approve_{$申请ID}",
                    "at_bot_show_channel_list" => true,
                    "unsupport_tips" => "版本不支持"
                ]
            ],
            [
                "id" => "reject_{$申请ID}",
                "render_data" => ["label" => "❌ 拒绝", "visited_label" => "已拒绝", "style" => 1],
                "action" => [
                    "type" => 1,
                    "permission" => ["type" => 1],
                    "data" => "reject_{$申请ID}",
                    "at_bot_show_channel_list" => true,
                    "unsupport_tips" => "版本不支持"
                ]
            ],
            [
                "id" => "ban_{$申请ID}",
                "render_data" => ["label" => "🚫 拉黑", "visited_label" => "已拉黑", "style" => 2],
                "action" => [
                    "type" => 1,
                    "permission" => ["type" => 1],
                    "data" => "ban_{$申请ID}",
                    "at_bot_show_channel_list" => true,
                    "unsupport_tips" => "版本不支持"
                ]
            ]
        ]
    ]];

    $body = [
        "content" => "",
        "msg_type" => 2,
        "markdown" => ["content" => $md],
        "keyboard" => ["content" => ["rows" => $rows]]
    ];
    云雀API("群聊", $群号, $body, true);
    return;
}

// ===================== 按钮互动处理 =====================
$btnData = "";
if (消息来源 === "互动") {
    $btnData = raw["d"]["data"]["resolved"]["button_data"] ?? (raw["d"]["data"]["data"] ?? "");
}

if (消息来源 === "互动" && $btnData !== "") {
    if (strpos($btnData, 'menu_') === 0) {
        处理菜单操作($群号, $btnData, $点击用户 = raw['d']['group_member_openid'] ?? '', $发言身份);
        return;
    }

    if (!preg_match('/^(approve|reject|ban)_(.+)$/', $btnData, $m)) {
        return;
    }
    $操作   = $m[1];
    $申请ID = $m[2];

    $状态 = 读("入群申请/{$群号}/{$申请ID}", "status", "pending");
    if ($状态 !== "pending") return;

    $点击用户 = raw['d']['group_member_openid'] ?? '';
    if (empty($点击用户)) return;

    $申请详情 = 读("入群申请/{$群号}/{$申请ID}", "info", []);
    $目标UID  = $申请详情['openid'] ?? '';
    $目标昵称 = $申请详情['昵称'] ?? '该用户';
    if (empty($目标UID)) return;

    // ---------- 拉黑并拒绝 ----------
    if ($操作 === 'ban') {
        $cfg = 获取群配置($群号);
        if (!in_array($目标UID, $cfg['blacklist'])) {
            $cfg['blacklist'][] = $目标UID;
            保存群配置($群号, $cfg);
        }
        // 获取操作人昵称
        $操作人昵称 = 获取昵称缓存($点击用户, $群号);
        $reason = $操作人昵称 ? "被管理员{$操作人昵称}拉黑！" : "管理员拉黑";
        审批入群($目标UID, 'decline', $申请ID, $reason, false, $群号);
        写("入群申请/{$群号}/{$申请ID}", "status", "rejected");
        发送结果卡片($群号, $目标UID, 'rejected', '已被拉黑', $点击用户);
        return;
    }

    // ---------- 批准 ----------
    if ($操作 === 'approve') {
        $结果 = 审批入群($目标UID, 'approve', $申请ID, '', false, $群号);
        $成功 = false;
        if (is_array($结果)) {
            if (!isset($结果['code']) || $结果['code'] == 0) $成功 = true;
        } elseif ($结果 === null) $成功 = true;
        $新状态 = 'approved';
        写("入群申请/{$群号}/{$申请ID}", "status", $新状态);
        if ($成功) {
            发送结果卡片($群号, $目标UID, 'approved', '', $点击用户);
        }
        return;
    }

    // ---------- 手动拒绝 ----------
    if ($操作 === 'reject') {
        // 获取操作人昵称
        $操作人昵称 = 获取昵称缓存($点击用户, $群号);
        $reason = $操作人昵称 ? "被管理员{$操作人昵称}拒绝！" : "管理员拒绝";
        $结果 = 审批入群($目标UID, 'decline', $申请ID, $reason, false, $群号);
        $成功 = false;
        if (is_array($结果)) {
            if (!isset($结果['code']) || $结果['code'] == 0) $成功 = true;
        } elseif ($结果 === null) $成功 = true;
        $新状态 = 'rejected';
        写("入群申请/{$群号}/{$申请ID}", "status", $新状态);
        if ($成功) {
            发送结果卡片($群号, $目标UID, 'rejected', $reason, $点击用户);
        }
        return;
    }
}

// ===================== 群消息命令处理 =====================
if (消息来源 === "群聊" && raw['t'] != 'GROUP_JOIN_REQUEST') {
    $content = trim(消息);
    $rawContent = raw["d"]["content"] ?? '';

    // ----- 快捷菜单 -----
    if ($content === '入群审批') {
        if (!校验管理权限(用户, $群号, $发言身份)) {
            文字('⚠️ 只有群主或管理员可以操作。');
            return;
        }
        发送审批菜单($群号, $发言身份);
        return;
    }

    // ----- 匹配词命令 -----
    if (preg_match('/^添加匹配词\s+(.+)/u', $content, $matches)) {
        if (!校验管理权限(用户, $群号, $发言身份)) {
            主动文字($群号, '⚠️ 只有群主或管理员可以操作。');
            return;
        }
        $keyword = trim($matches[1]);
        if (empty($keyword)) {
            主动文字($群号, '请指定要添加的关键词。');
            return;
        }
        $cfg = 获取群配置($群号);
        if (in_array($keyword, $cfg['match_keywords'])) {
            主动文字($群号, "匹配词「{$keyword}」已存在。");
            return;
        }
        $cfg['match_keywords'][] = $keyword;
        保存群配置($群号, $cfg);
        主动文字($群号, "✅ 已添加匹配词：{$keyword}");
        return;
    }

    if (preg_match('/^删除匹配词\s+(.+)/u', $content, $matches)) {
        if (!校验管理权限(用户, $群号, $发言身份)) {
            主动文字($群号, '⚠️ 只有群主或管理员可以操作。');
            return;
        }
        $keyword = trim($matches[1]);
        if (empty($keyword)) {
            主动文字($群号, '请指定要删除的关键词。');
            return;
        }
        $cfg = 获取群配置($群号);
        if (!in_array($keyword, $cfg['match_keywords'])) {
            主动文字($群号, "匹配词「{$keyword}」不存在。");
            return;
        }
        $cfg['match_keywords'] = array_values(array_diff($cfg['match_keywords'], [$keyword]));
        保存群配置($群号, $cfg);
        主动文字($群号, "✅ 已删除匹配词：{$keyword}");
        return;
    }

    // ----- 黑名单命令（使用原始内容） -----
    if (mb_strpos($rawContent, '添加黑名单') !== false) {
        if (!校验管理权限(用户, $群号, $发言身份)) {
            主动文字($群号, '⚠️ 只有群主或管理员可以操作。');
            return;
        }
        $pos = mb_strpos($rawContent, '添加黑名单') + mb_strlen('添加黑名单');
        $rest = trim(mb_substr($rawContent, $pos));
        if (empty($rest)) {
            主动文字($群号, '请指定要添加的用户（可 @ 或直接输入 OpenID）。');
            return;
        }
        if (preg_match('/<@!?([A-F0-9]+)>/', $rest, $atMatch)) {
            $openid = $atMatch[1];
        } else {
            $openid = trim($rest);
            if (!preg_match('/^[A-F0-9]+$/i', $openid)) {
                主动文字($群号, '未能识别用户，请 @ 或输入 OpenID。');
                return;
            }
        }
        if (empty($openid)) {
            主动文字($群号, '未能识别用户，请 @ 或输入 OpenID。');
            return;
        }
        $cfg = 获取群配置($群号);
        if (in_array($openid, $cfg['blacklist'])) {
            主动文字($群号, "该用户已在黑名单中。");
            return;
        }
        $cfg['blacklist'][] = $openid;
        保存群配置($群号, $cfg);
        // 预缓存被添加用户的昵称
        $昵称 = 获取昵称缓存($openid, $群号);
        $display = $昵称 ? $昵称 : $openid;
        主动文字($群号, "✅ 已添加黑名单：{$display}");
        return;
    }

    if (mb_strpos($rawContent, '删除黑名单') !== false) {
        if (!校验管理权限(用户, $群号, $发言身份)) {
            主动文字($群号, '⚠️ 只有群主或管理员可以操作。');
            return;
        }
        $pos = mb_strpos($rawContent, '删除黑名单') + mb_strlen('删除黑名单');
        $rest = trim(mb_substr($rawContent, $pos));
        if (empty($rest)) {
            主动文字($群号, '请指定要移除的用户（可 @ 或直接输入 OpenID）。');
            return;
        }
        if (preg_match('/<@!?([A-F0-9]+)>/', $rest, $atMatch)) {
            $openid = $atMatch[1];
        } else {
            $openid = trim($rest);
            if (!preg_match('/^[A-F0-9]+$/i', $openid)) {
                主动文字($群号, '未能识别用户，请 @ 或输入 OpenID。');
                return;
            }
        }
        if (empty($openid)) {
            主动文字($群号, '未能识别用户，请 @ 或输入 OpenID。');
            return;
        }
        $cfg = 获取群配置($群号);
        if (!in_array($openid, $cfg['blacklist'])) {
            主动文字($群号, "该用户不在黑名单中。");
            return;
        }
        $cfg['blacklist'] = array_values(array_diff($cfg['blacklist'], [$openid]));
        保存群配置($群号, $cfg);
        $昵称 = 获取昵称缓存($openid, $群号);
        $display = $昵称 ? $昵称 : $openid;
        主动文字($群号, "✅ 已移除黑名单：{$display}");
        return;
    }

    // ----- #入群 系列命令 -----
    if (!str_starts_with($content, '#入群')) return;

    if (!校验管理权限(用户, $群号, $发言身份)) {
        文字('⚠️ 只有群主或管理员可以设置入群配置。');
        return;
    }

    $parts = explode(' ', $content);
    $cmd = $parts[0] ?? '';
    $arg1 = $parts[1] ?? '';
    $arg2 = $parts[2] ?? '';

    $cfg = 获取群配置($群号);

    switch ($cmd) {
        case '#入群设置':
            $status = $cfg['auto_approve_invite'] ? '开启' : '关闭';
            $keywords = empty($cfg['match_keywords']) ? '无' : implode('、', $cfg['match_keywords']);
            $blacklist = empty($cfg['blacklist']) ? '无' : count($cfg['blacklist']) . ' 个';
            $msg = "📋 当前入群配置：\n";
            $msg .= "- 邀请自动同意：{$status}\n";
            $msg .= "- 匹配词：{$keywords}\n";
            $msg .= "- 黑名单数量：{$blacklist}";
            文字($msg);
            break;
        case '#入群自动同意':
            if (!in_array($arg1, ['开启', '关闭'])) {
                文字("用法： #入群自动同意 开启/关闭");
                break;
            }
            $cfg['auto_approve_invite'] = ($arg1 === '开启');
            保存群配置($群号, $cfg);
            文字("✅ 邀请自动同意已设置为：{$arg1}");
            break;
        case '#入群匹配词':
            if ($arg1 === '添加' && !empty($arg2)) {
                $words = array_map('trim', explode(',', $arg2));
                $cfg['match_keywords'] = array_values(array_unique(array_merge($cfg['match_keywords'], $words)));
                保存群配置($群号, $cfg);
                文字("✅ 已添加匹配词：" . implode('、', $words));
            } elseif ($arg1 === '删除' && !empty($arg2)) {
                $delWords = array_map('trim', explode(',', $arg2));
                $cfg['match_keywords'] = array_values(array_diff($cfg['match_keywords'], $delWords));
                保存群配置($群号, $cfg);
                文字("✅ 已删除匹配词：" . implode('、', $delWords));
            } else {
                文字("用法：\n#入群匹配词 添加 词1,词2\n#入群匹配词 删除 词1,词2");
            }
            break;
        case '#入群黑名单':
            if ($arg1 === '添加' && !empty($arg2)) {
                $ids = array_map('trim', explode(',', $arg2));
                $cfg['blacklist'] = array_values(array_unique(array_merge($cfg['blacklist'], $ids)));
                保存群配置($群号, $cfg);
                文字("✅ 已添加黑名单：" . implode('、', $ids));
            } elseif ($arg1 === '删除' && !empty($arg2)) {
                $ids = array_map('trim', explode(',', $arg2));
                $cfg['blacklist'] = array_values(array_diff($cfg['blacklist'], $ids));
                保存群配置($群号, $cfg);
                文字("✅ 已删除黑名单：" . implode('、', $ids));
            } else {
                文字("用法：\n#入群黑名单 添加 openid1,openid2\n#入群黑名单 删除 openid1,openid2");
            }
            break;
        default:
            文字("未知命令，可用命令：\n#入群设置\n#入群自动同意 开启/关闭\n#入群匹配词 添加/删除 词\n#入群黑名单 添加/删除 openid");
            break;
    }
    return;
}