<?php
/**
 * 群管系统（融合入群审核）- 云雀 3.6 适配版
 * - 系统开关仅控制自动撤回功能，不限制管理命令
 * - 支持撤回、针对、违禁词、链接/合并撤回、禁言、系统开关、管理员管理
 * - 入群审核：自动审批、匹配词、黑名单、审批卡片（独立于系统开关）
 * - 权限：群主/管理员、超级管理员（主人ID）、自定义机器人管理员
 */

if (!in_array(消息来源, ["群聊", "互动", "入群申请"]) && raw['t'] != 'GROUP_JOIN_REQUEST') {
    return;
}

define('缓存有效期', 120);
define('默认禁言秒数', 600);
define('申请缓存时间', 3600);
define('状态缓存时间', 86400);

$群号 = 来源 ?: '';
$发言用户 = 用户 ?: '';

if (empty($群号) || empty($发言用户) && 消息来源 !== "入群申请") {
    return;
}

// ---------- 提取消息ID与内容 ----------
if (消息来源 === "群聊") {
    $当前消息ID = raw["d"]["id"] ?? "";
    $原始消息内容 = raw["d"]["content"] ?? "";
    $发言身份 = raw["d"]["author"]["member_role"] ?? "member";
} elseif (消息来源 === "互动") {
    $当前消息ID = raw["d"]["id"] ?? "";
    $原始消息内容 = "";
    $角色数字 = raw["d"]["member"]["role"] ?? 4;
    $发言身份 = ($角色数字 == 2) ? "owner" : (($角色数字 == 3) ? "admin" : "member");
} else {
    $当前消息ID = "";
    $原始消息内容 = "";
    $发言身份 = "member";
}

$系统开关 = 读("群管/系统开关/".$群号, "status", "关闭");

// ========== 工具函数 ==========
function 提取被艾特用户($content) {
    preg_match_all('/<@!?([A-F0-9]+)>/i', $content, $matches);
    return $matches[1] ?? [];
}

function 获取群成员角色($群号, $用户ID) {
    if (empty($群号) || empty($用户ID)) return "member";
    try {
        $res = BOTAPI("/v2/groups/{$群号}/members/{$用户ID}", "GET", "");
        $data = json_decode($res, true);
        if (!is_array($data)) return "member";
        $role = $data["role"] ?? 4;
        switch ($role) {
            case 2: return "owner";
            case 3: return "admin";
            default: return "member";
        }
    } catch (Exception $e) {
        return "member";
    }
}

function 校验管理权限($用户ID, $群号, $发言身份) {
    if (in_array($发言身份, ["owner", "admin"])) return true;
    if ($用户ID === 主人ID) return true;
    $管理员列表 = 读("群管/管理员列表/".$群号, "list", []);
    if (!is_array($管理员列表)) $管理员列表 = [];
    return in_array($用户ID, $管理员列表);
}

function 校验全权限($用户ID, $发言身份) {
    if (in_array($发言身份, ["owner", "admin"])) return true;
    if ($用户ID === 主人ID) return true;
    return false;
}

// ========== 入群审核专用函数 ==========
function 获取昵称缓存($用户ID, $群号) {
    if (empty($用户ID) || empty($群号)) return null;
    $cacheKey = "昵称缓存/{$群号}/{$用户ID}";
    $cached = 读($cacheKey, "info", null);
    if (is_array($cached) && isset($cached['time']) && isset($cached['name'])) {
        if (time() - $cached['time'] < 86400) {
            return $cached['name'];
        }
    }
    $appid = defined('appid') ? appid : '';
    $url = "https://oiapi.net/api/Openid?openid=" . urlencode($用户ID) . "&appid=" . urlencode($appid);
    $response = curl($url, "GET", [], "");
    $data = json_decode($response, true);
    if (is_array($data) && isset($data['code']) && $data['code'] == 1 && isset($data['data']['nickname'])) {
        $name = $data['data']['nickname'];
        写($cacheKey, "info", ['time' => time(), 'name' => $name]);
        return $name;
    }
    global $raw;
    if (isset($raw['d']['member']['username']) && !empty($raw['d']['member']['username'])) {
        $name = $raw['d']['member']['username'];
        写($cacheKey, "info", ['time' => time(), 'name' => $name]);
        return $name;
    }
    return null;
}

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

function 发送结果卡片($群号, $用户ID, $结果, $原因, $操作人 = null) {
    $申请人昵称 = 获取昵称缓存($用户ID, $群号) ?: $用户ID;
    if ($操作人) {
        $操作人昵称 = 获取昵称缓存($操作人, $群号) ?: $操作人;
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

function 自动审批($申请ID, $用户ID, $问题, $答案, $群号, $邀请人 = null) {
    $cfg = 获取群配置($群号);
    $reason = '';
    if (in_array($用户ID, $cfg['blacklist'])) {
        $reason = '用户已被拉黑';
        审批入群($用户ID, 'decline', $申请ID, $reason, false, $群号);
        写("入群申请/{$群号}/{$申请ID}", "status", "rejected");
        发送结果卡片($群号, $用户ID, 'rejected', $reason);
        return true;
    }
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
    if ($cfg['auto_approve_invite'] && !empty($邀请人)) {
        $reason = '邀请入群自动通过';
        审批入群($用户ID, 'approve', $申请ID, '', false, $群号);
        写("入群申请/{$群号}/{$申请ID}", "status", "approved");
        发送结果卡片($群号, $用户ID, 'approved', $reason);
        return true;
    }
    return false;
}

function 发送审批菜单($群号, $发言身份) {
    if (!校验管理权限(用户, $群号, $发言身份)) {
        文字('⚠️ 只有群主或管理员可以操作。');
        return;
    }
    $cfg = 获取群配置($群号);
    $auto = $cfg['auto_approve_invite'] ? '✅ 开启' : '❌ 关闭';
    $kwCount = count($cfg['match_keywords']);
    $blCount = count($cfg['blacklist']);
    $额外 = "- 邀请自动同意：{$auto}\n- 匹配词数量：{$kwCount}\n- 黑名单数量：{$blCount}";
    $md = 生成菜单Markdown("🛠 入群审批管理", $额外);
    $rows = [
        [
            "buttons" => [
                ["id" => "menu_add_keyword", "render_data" => ["label" => "➕ 添加匹配词", "visited_label" => "添加匹配词", "style" => 1],
                 "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "添加匹配词 ", "unsupport_tips" => "版本不支持"]],
                ["id" => "menu_del_keyword", "render_data" => ["label" => "➖ 删除匹配词", "visited_label" => "删除匹配词", "style" => 1],
                 "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "删除匹配词 ", "unsupport_tips" => "版本不支持"]]
            ]
        ],
        [
            "buttons" => [
                ["id" => "menu_add_blacklist", "render_data" => ["label" => "🚫 添加黑名单", "visited_label" => "添加黑名单", "style" => 1],
                 "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "添加黑名单 ", "unsupport_tips" => "版本不支持"]],
                ["id" => "menu_del_blacklist", "render_data" => ["label" => "🚫 删除黑名单", "visited_label" => "删除黑名单", "style" => 1],
                 "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "删除黑名单 ", "unsupport_tips" => "版本不支持"]]
            ]
        ],
        [
            "buttons" => [
                ["id" => "menu_list_keywords", "render_data" => ["label" => "📋 查看匹配词", "visited_label" => "已查看", "style" => 1],
                 "action" => ["type" => 1, "permission" => ["type" => 1], "data" => "menu_list_keywords", "unsupport_tips" => "版本不支持"]],
                ["id" => "menu_clear_keywords", "render_data" => ["label" => "🗑 清空匹配词", "visited_label" => "已清空", "style" => 2],
                 "action" => ["type" => 1, "permission" => ["type" => 1], "data" => "menu_clear_keywords", "unsupport_tips" => "版本不支持"]]
            ]
        ],
        [
            "buttons" => [
                ["id" => "menu_list_blacklist", "render_data" => ["label" => "📋 查看黑名单", "visited_label" => "已查看", "style" => 1],
                 "action" => ["type" => 1, "permission" => ["type" => 1], "data" => "menu_list_blacklist", "unsupport_tips" => "版本不支持"]],
                ["id" => "menu_clear_blacklist", "render_data" => ["label" => "🗑 清空黑名单", "visited_label" => "已清空", "style" => 2],
                 "action" => ["type" => 1, "permission" => ["type" => 1], "data" => "menu_clear_blacklist", "unsupport_tips" => "版本不支持"]]
            ]
        ],
        [
            "buttons" => [
                ["id" => "menu_toggle_auto", "render_data" => ["label" => "🔄 切换自动同意", "visited_label" => "已切换", "style" => 1],
                 "action" => ["type" => 1, "permission" => ["type" => 1], "data" => "menu_toggle_auto", "unsupport_tips" => "版本不支持"]]
            ]
        ]
    ];
    $body = ["content" => "", "msg_type" => 2, "markdown" => ["content" => $md], "keyboard" => ["content" => ["rows" => $rows]]];
    云雀API("群聊", $群号, $body, true);
}

function 处理菜单操作($群号, $btnData, $点击用户, $发言身份) {
    if (!校验管理权限($点击用户, $群号, $发言身份)) {
        主动文字($群号, "⚠️ 只有群主或管理员可以操作。");
        return;
    }
    $cfg = 获取群配置($群号);
    switch ($btnData) {
        case 'menu_toggle_auto':
            $cfg['auto_approve_invite'] = !$cfg['auto_approve_invite'];
            保存群配置($群号, $cfg);
            主动文字($群号, "🔄 邀请自动同意已切换为：" . ($cfg['auto_approve_invite'] ? '✅ 开启' : '❌ 关闭'));
            break;
        case 'menu_list_keywords':
            $list = $cfg['match_keywords'];
            主动文字($群号, empty($list) ? "📋 当前匹配词列表为空。" : "📋 匹配词列表（共 " . count($list) . " 个）：\n" . implode('、', $list));
            break;
        case 'menu_list_blacklist':
            $list = $cfg['blacklist'];
            主动文字($群号, empty($list) ? "🚫 黑名单列表为空。" : "🚫 黑名单列表（共 " . count($list) . " 个）：\n" . implode("\n", $list));
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

// ========== 生成菜单Markdown（群管系统 + 入群审核共用） ==========
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

// ==================== 事件分支处理 ====================
// 1. 优先处理入群申请事件（不受系统开关影响）
if (raw['t'] == 'GROUP_JOIN_REQUEST') {
    $event = raw['d'];
    $申请ID = $event['join_request_id'] ?? '';
    $用户ID = $event['member_openid'] ?? '';
    $昵称   = $event['username'] ?? '未知';
    $邀请人 = $event['invitor_openid'] ?? '';
    if (empty($申请ID) || empty($用户ID)) return;
    $问题 = defined('申请问题') ? 申请问题 : '';
    $答案 = defined('申请答案') ? 申请答案 : '';
    if ($昵称 !== '未知') {
        写("昵称缓存/{$群号}/{$用户ID}", "info", ['time' => time(), 'name' => $昵称]);
    }
    写("入群申请/{$群号}/{$申请ID}", "info", ['openid' => $用户ID, '昵称' => $昵称, '问题' => $问题, '答案' => $答案]);
    写("入群申请/{$群号}/{$申请ID}", "status", "pending");
    if (自动审批($申请ID, $用户ID, $问题, $答案, $群号, $邀请人)) return;
    $头像 = "https://q.qlogo.cn/qqapp/" . appid . "/{$用户ID}/640";
    $md = "## 📩 新的入群申请\n\n![头像 #40px #40px]({$头像}) **{$昵称}**\n\n";
    if ($问题) $md .= "**问题**：{$问题}\n";
    if ($答案) $md .= "**答案**：{$答案}\n";
    $rows = [[
        "buttons" => [
            ["id" => "approve_{$申请ID}", "render_data" => ["label" => "✅ 批准", "visited_label" => "已批准", "style" => 1],
             "action" => ["type" => 1, "permission" => ["type" => 1], "data" => "approve_{$申请ID}", "unsupport_tips" => "版本不支持"]],
            ["id" => "reject_{$申请ID}", "render_data" => ["label" => "❌ 拒绝", "visited_label" => "已拒绝", "style" => 1],
             "action" => ["type" => 1, "permission" => ["type" => 1], "data" => "reject_{$申请ID}", "unsupport_tips" => "版本不支持"]],
            ["id" => "ban_{$申请ID}", "render_data" => ["label" => "🚫 拉黑", "visited_label" => "已拉黑", "style" => 2],
             "action" => ["type" => 1, "permission" => ["type" => 1], "data" => "ban_{$申请ID}", "unsupport_tips" => "版本不支持"]]
        ]
    ]];
    $body = ["content" => "", "msg_type" => 2, "markdown" => ["content" => $md], "keyboard" => ["content" => ["rows" => $rows]]];
    云雀API("群聊", $群号, $body, true);
    return;
}

// 2. 处理互动按钮回调（审批卡片按钮 + 入群审核菜单按钮）
$btnData = "";
if (消息来源 === "互动") {
    $btnData = raw["d"]["data"]["resolved"]["button_data"] ?? (raw["d"]["data"]["data"] ?? "");
}

if (消息来源 === "互动" && $btnData !== "") {
    // 入群审核菜单操作
    if (strpos($btnData, 'menu_') === 0) {
        处理菜单操作($群号, $btnData, raw['d']['group_member_openid'] ?? '', $发言身份);
        return;
    }
    // 审批卡片操作
    if (preg_match('/^(approve|reject|ban)_(.+)$/', $btnData, $m)) {
        $操作 = $m[1];
        $申请ID = $m[2];
        $状态 = 读("入群申请/{$群号}/{$申请ID}", "status", "pending");
        if ($状态 !== "pending") return;
        $点击用户 = raw['d']['group_member_openid'] ?? '';
        if (empty($点击用户)) return;
        $申请详情 = 读("入群申请/{$群号}/{$申请ID}", "info", []);
        $目标UID = $申请详情['openid'] ?? '';
        if (empty($目标UID)) return;
        if ($操作 === 'ban') {
            $cfg = 获取群配置($群号);
            if (!in_array($目标UID, $cfg['blacklist'])) {
                $cfg['blacklist'][] = $目标UID;
                保存群配置($群号, $cfg);
            }
            $操作人昵称 = 获取昵称缓存($点击用户, $群号);
            $reason = $操作人昵称 ? "被管理员{$操作人昵称}拉黑！" : "管理员拉黑";
            审批入群($目标UID, 'decline', $申请ID, $reason, false, $群号);
            写("入群申请/{$群号}/{$申请ID}", "status", "rejected");
            发送结果卡片($群号, $目标UID, 'rejected', '已被拉黑', $点击用户);
            return;
        }
        if ($操作 === 'approve') {
            $结果 = 审批入群($目标UID, 'approve', $申请ID, '', false, $群号);
            $成功 = false;
            if (is_array($结果) && (!isset($结果['code']) || $结果['code'] == 0)) $成功 = true;
            elseif ($结果 === null) $成功 = true;
            $新状态 = 'approved';
            写("入群申请/{$群号}/{$申请ID}", "status", $新状态);
            if ($成功) 发送结果卡片($群号, $目标UID, 'approved', '', $点击用户);
            return;
        }
        if ($操作 === 'reject') {
            $操作人昵称 = 获取昵称缓存($点击用户, $群号);
            $reason = $操作人昵称 ? "被管理员{$操作人昵称}拒绝！" : "管理员拒绝";
            $结果 = 审批入群($目标UID, 'decline', $申请ID, $reason, false, $群号);
            $成功 = false;
            if (is_array($结果) && (!isset($结果['code']) || $结果['code'] == 0)) $成功 = true;
            elseif ($结果 === null) $成功 = true;
            $新状态 = 'rejected';
            写("入群申请/{$群号}/{$申请ID}", "status", $新状态);
            if ($成功) 发送结果卡片($群号, $目标UID, 'rejected', $reason, $点击用户);
            return;
        }
    }
}

// 3. 群聊消息处理（包括群管命令和入群审核命令）
if (消息来源 === "群聊") {
    // 提取纯命令（去掉艾特）
    $纯命令 = preg_replace('/<@!?[A-F0-9]+>/i', '', $原始消息内容);
    $纯命令 = trim($纯命令, " /\t\n\r");
    $所有艾特用户 = 提取被艾特用户($原始消息内容);
    $content = trim(消息);
    $rawContent = $原始消息内容;

    // ----- 入群审核快捷命令（不受系统开关影响） -----
    if ($content === '入群审批' || $content === '入群审核') {
        if (!校验管理权限(用户, $群号, $发言身份)) {
            文字('⚠️ 只有群主或管理员可以操作。');
            return;
        }
        发送审批菜单($群号, $发言身份);
        return;
    }

    // ----- 入群审核匹配词/黑名单命令（同样不受系统开关影响） -----
    if (preg_match('/^添加匹配词\s+(.+)/u', $content, $matches)) {
        if (!校验管理权限(用户, $群号, $发言身份)) { 主动文字($群号, '⚠️ 只有群主或管理员可以操作。'); return; }
        $keyword = trim($matches[1]);
        if (empty($keyword)) { 主动文字($群号, '请指定要添加的关键词。'); return; }
        $cfg = 获取群配置($群号);
        if (in_array($keyword, $cfg['match_keywords'])) { 主动文字($群号, "匹配词「{$keyword}」已存在。"); return; }
        $cfg['match_keywords'][] = $keyword;
        保存群配置($群号, $cfg);
        主动文字($群号, "✅ 已添加匹配词：{$keyword}");
        return;
    }
    if (preg_match('/^删除匹配词\s+(.+)/u', $content, $matches)) {
        if (!校验管理权限(用户, $群号, $发言身份)) { 主动文字($群号, '⚠️ 只有群主或管理员可以操作。'); return; }
        $keyword = trim($matches[1]);
        if (empty($keyword)) { 主动文字($群号, '请指定要删除的关键词。'); return; }
        $cfg = 获取群配置($群号);
        if (!in_array($keyword, $cfg['match_keywords'])) { 主动文字($群号, "匹配词「{$keyword}」不存在。"); return; }
        $cfg['match_keywords'] = array_values(array_diff($cfg['match_keywords'], [$keyword]));
        保存群配置($群号, $cfg);
        主动文字($群号, "✅ 已删除匹配词：{$keyword}");
        return;
    }
    if (mb_strpos($rawContent, '添加黑名单') !== false) {
        if (!校验管理权限(用户, $群号, $发言身份)) { 主动文字($群号, '⚠️ 只有群主或管理员可以操作。'); return; }
        $pos = mb_strpos($rawContent, '添加黑名单') + mb_strlen('添加黑名单');
        $rest = trim(mb_substr($rawContent, $pos));
        if (empty($rest)) { 主动文字($群号, '请指定要添加的用户（可 @ 或直接输入 OpenID）。'); return; }
        if (preg_match('/<@!?([A-F0-9]+)>/', $rest, $atMatch)) $openid = $atMatch[1];
        else { $openid = trim($rest); if (!preg_match('/^[A-F0-9]+$/i', $openid)) { 主动文字($群号, '未能识别用户，请 @ 或输入 OpenID。'); return; } }
        if (empty($openid)) { 主动文字($群号, '未能识别用户，请 @ 或输入 OpenID。'); return; }
        $cfg = 获取群配置($群号);
        if (in_array($openid, $cfg['blacklist'])) { 主动文字($群号, "该用户已在黑名单中。"); return; }
        $cfg['blacklist'][] = $openid;
        保存群配置($群号, $cfg);
        $昵称 = 获取昵称缓存($openid, $群号);
        主动文字($群号, "✅ 已添加黑名单：" . ($昵称 ? $昵称 : $openid));
        return;
    }
    if (mb_strpos($rawContent, '删除黑名单') !== false) {
        if (!校验管理权限(用户, $群号, $发言身份)) { 主动文字($群号, '⚠️ 只有群主或管理员可以操作。'); return; }
        $pos = mb_strpos($rawContent, '删除黑名单') + mb_strlen('删除黑名单');
        $rest = trim(mb_substr($rawContent, $pos));
        if (empty($rest)) { 主动文字($群号, '请指定要移除的用户（可 @ 或直接输入 OpenID）。'); return; }
        if (preg_match('/<@!?([A-F0-9]+)>/', $rest, $atMatch)) $openid = $atMatch[1];
        else { $openid = trim($rest); if (!preg_match('/^[A-F0-9]+$/i', $openid)) { 主动文字($群号, '未能识别用户，请 @ 或输入 OpenID。'); return; } }
        if (empty($openid)) { 主动文字($群号, '未能识别用户，请 @ 或输入 OpenID。'); return; }
        $cfg = 获取群配置($群号);
        if (!in_array($openid, $cfg['blacklist'])) { 主动文字($群号, "该用户不在黑名单中。"); return; }
        $cfg['blacklist'] = array_values(array_diff($cfg['blacklist'], [$openid]));
        保存群配置($群号, $cfg);
        $昵称 = 获取昵称缓存($openid, $群号);
        主动文字($群号, "✅ 已移除黑名单：" . ($昵称 ? $昵称 : $openid));
        return;
    }

    // ==================== 群管命令（不受系统开关限制，所有命令均可执行） ====================

    // ---------- 管理员命令前缀集合（用于豁免自动撤回） ----------
    $管理员命令前缀 = [
        "撤回", "撤回全部", "针对", "取消针对",
        "添加违禁词", "删除违禁词",
        "链接撤回切换", "合并撤回切换",
        "禁言开关", "设置禁言时长",
        "开启群管系统", "关闭群管系统",
        "添加管理员", "删除管理员"
    ];
    $是否管理命令 = false;
    foreach ($管理员命令前缀 as $前缀词) {
        if (前缀($纯命令, $前缀词)) {
            $是否管理命令 = true;
            break;
        }
    }
    // 子菜单入口也算管理命令
    if (in_array($纯命令, ["用户管理", "内容管控", "系统设置", "管理员管理"])) {
        $是否管理命令 = true;
    }

    // ---------- 消息缓存（仅群聊消息，且非管理命令） ----------
    if (消息来源 === "群聊" && !$是否管理命令) {
        $缓存键 = "群管/消息缓存/".$群号;
        $缓存文件路径 = __DIR__ . '/../database/' . $缓存键;
        if (file_exists($缓存文件路径)) {
            if (time() - filemtime($缓存文件路径) > 缓存有效期) {
                file_put_contents($缓存文件路径, '{}');
            }
        }
        $用户消息列表 = 读($缓存键, $发言用户, []);
        array_unshift($用户消息列表, $当前消息ID);
        $用户消息列表 = array_slice($用户消息列表, 0, 30);
        写($缓存键, $发言用户, $用户消息列表);
    }

    // ---------- 自动撤回模块（受系统开关控制） ----------
    if (消息来源 === "群聊" && !$是否管理命令 && $系统开关 == "开启") {
        // 针对名单
        $针对列表 = 读("群管/针对名单/".$群号, "list", []);
        if (in_array($发言用户, $针对列表)) {
            撤回($当前消息ID);
            return;
        }
        // 链接撤回
        $链接撤回状态 = 读("群管/链接撤回/".$群号, "status", "关闭");
        if ($链接撤回状态 == "开启" && preg_match('/https?:\/\/[^\s]+/', $原始消息内容)) {
            撤回($当前消息ID);
            return;
        }
        // 合并消息撤回
        $合并撤回状态 = 读("群管/合并撤回/".$群号, "status", "关闭");
        if ($合并撤回状态 == "开启" && strpos($原始消息内容, "合并转发消息") !== false) {
            撤回($当前消息ID);
            return;
        }
        // 违禁词撤回 + 自动禁言
        $违禁词列表 = 读("群管/违禁词列表/".$群号, "list", []);
        if (!empty($违禁词列表)) {
            foreach ($违禁词列表 as $词) {
                if (strpos($原始消息内容, $词) !== false) {
                    撤回($当前消息ID);
                    // 自动禁言
                    $禁言开关 = 读("群管/禁言开关/".$群号, "status", "关闭");
                    if ($禁言开关 == "开启") {
                        $禁言秒数 = (int)读("群管/禁言时长/".$群号, "value", 默认禁言秒数);
                        if ($禁言秒数 > 0) {
                            群禁言($发言用户, $禁言秒数, $群号);
                        }
                    }
                    return;
                }
            }
        }
    }

    // ==================== 群管主菜单 ====================
    if ($纯命令 == "群管菜单") {
        $md = 生成菜单Markdown("🛡️ 群管系统", "点击下方按钮快速使用群管功能。\n\n当前状态：" . ($系统开关 == "开启" ? "🟢 运行中" : "🔴 已关闭"));
        $rows = [
            [
                "buttons" => [
                    ["id" => "btn_user", "render_data" => ["label" => "👤 用户管理", "visited_label" => "👤 用户管理", "style" => 1],
                     "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "用户管理", "unsupport_tips" => "当前QQ版本不支持"]],
                    ["id" => "btn_content", "render_data" => ["label" => "⚠️ 内容管控", "visited_label" => "⚠️ 内容管控", "style" => 1],
                     "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "内容管控", "unsupport_tips" => "当前QQ版本不支持"]]
                ]
            ],
            [
                "buttons" => [
                    ["id" => "btn_audit", "render_data" => ["label" => "📋 入群审核", "visited_label" => "📋 入群审核", "style" => 1],
                     "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "入群审批", "unsupport_tips" => "当前QQ版本不支持"]]
                ]
            ]
        ];
        if (校验管理权限($发言用户, $群号, $发言身份)) {
            $rows[] = [
                "buttons" => [
                    ["id" => "btn_sys", "render_data" => ["label" => "⚙️ 系统设置", "visited_label" => "⚙️ 系统设置", "style" => 1],
                     "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "系统设置", "unsupport_tips" => "当前QQ版本不支持"]],
                    ["id" => "btn_admin", "render_data" => ["label" => "🛡️ 管理员管理", "visited_label" => "🛡️ 管理员管理", "style" => 1],
                     "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "管理员管理", "unsupport_tips" => "当前QQ版本不支持"]]
                ]
            ];
        }
        原生按钮($md, $rows);
        return;
    }

    // ==================== 用户管理子菜单 ====================
    if ($纯命令 == "用户管理") {
        $md = 生成菜单Markdown("👤 用户管理");
        $rows = [];
        if (校验管理权限($发言用户, $群号, $发言身份)) {
            $rows = [
                [
                    "buttons" => [
                        ["id" => "btn_recall", "render_data" => ["label" => "🔴 撤回@用户", "visited_label" => "🔴 撤回@用户", "style" => 1],
                         "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "撤回 ", "unsupport_tips" => "当前QQ版本不支持"]],
                        ["id" => "btn_recall_all", "render_data" => ["label" => "🔴 撤回全部", "visited_label" => "🔴 撤回全部", "style" => 1],
                         "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "撤回全部 ", "unsupport_tips" => "当前QQ版本不支持"]]
                    ]
                ],
                [
                    "buttons" => [
                        ["id" => "btn_ban", "render_data" => ["label" => "🟢 针对@用户", "visited_label" => "🟢 针对@用户", "style" => 1],
                         "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "针对 ", "unsupport_tips" => "当前QQ版本不支持"]],
                        ["id" => "btn_unban", "render_data" => ["label" => "🟢 取消针对", "visited_label" => "🟢 取消针对", "style" => 1],
                         "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "取消针对 ", "unsupport_tips" => "当前QQ版本不支持"]]
                    ]
                ]
            ];
        }
        $rows[] = [
            "buttons" => [
                ["id" => "btn_ban_list", "render_data" => ["label" => "📋 针对列表", "visited_label" => "📋 针对列表", "style" => 1],
                 "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "针对列表", "unsupport_tips" => "当前QQ版本不支持"]],
                ["id" => "btn_back", "render_data" => ["label" => "⬅️ 返回主菜单", "visited_label" => "⬅️ 返回主菜单", "style" => 0],
                 "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "群管菜单", "unsupport_tips" => "当前QQ版本不支持"]]
            ]
        ];
        原生按钮($md, $rows);
        return;
    }

    // ==================== 内容管控子菜单 ====================
    if ($纯命令 == "内容管控") {
        $禁言开关状态 = 读("群管/禁言开关/".$群号, "status", "关闭");
        $禁言时长 = (int)读("群管/禁言时长/".$群号, "value", 默认禁言秒数);
        $禁言时长分钟 = floor($禁言时长 / 60);
        $额外 = "禁言开关：" . ($禁言开关状态 == "开启" ? "🟢 开启" : "🔴 关闭") . "\n";
        $额外 .= "禁言时长：{$禁言时长分钟} 分钟\n";
        $md = 生成菜单Markdown("⚠️ 内容管控", $额外);
        $rows = [];
        if (校验管理权限($发言用户, $群号, $发言身份)) {
            $rows = [
                [
                    "buttons" => [
                        ["id" => "btn_add_word", "render_data" => ["label" => "➕ 添加违禁词", "visited_label" => "➕ 添加违禁词", "style" => 1],
                         "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "添加违禁词 ", "unsupport_tips" => "当前QQ版本不支持"]],
                        ["id" => "btn_del_word", "render_data" => ["label" => "➖ 删除违禁词", "visited_label" => "➖ 删除违禁词", "style" => 1],
                         "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "删除违禁词 ", "unsupport_tips" => "当前QQ版本不支持"]]
                    ]
                ],
                [
                    "buttons" => [
                        ["id" => "btn_link", "render_data" => ["label" => "🔗 链接撤回开关", "visited_label" => "🔗 链接撤回开关", "style" => 1],
                         "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "链接撤回切换", "unsupport_tips" => "当前QQ版本不支持"]],
                        ["id" => "btn_merge", "render_data" => ["label" => "📨 合并撤回开关", "visited_label" => "📨 合并撤回开关", "style" => 1],
                         "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "合并撤回切换", "unsupport_tips" => "当前QQ版本不支持"]]
                    ]
                ],
                [
                    "buttons" => [
                        ["id" => "btn_mute_switch", "render_data" => ["label" => "🔇 违禁词禁言开关", "visited_label" => "🔇 违禁词禁言开关", "style" => 1],
                         "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "禁言开关", "unsupport_tips" => "当前QQ版本不支持"]],
                        ["id" => "btn_mute_time", "render_data" => ["label" => "⏱️ 设置禁言时长", "visited_label" => "⏱️ 设置禁言时长", "style" => 1],
                         "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "设置禁言时长 ", "unsupport_tips" => "当前QQ版本不支持"]]
                    ]
                ]
            ];
        }
        $rows[] = [
            "buttons" => [
                ["id" => "btn_word_list", "render_data" => ["label" => "📃 违禁词列表", "visited_label" => "📃 违禁词列表", "style" => 1],
                 "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "违禁词列表", "unsupport_tips" => "当前QQ版本不支持"]],
                ["id" => "btn_back", "render_data" => ["label" => "⬅️ 返回主菜单", "visited_label" => "⬅️ 返回主菜单", "style" => 0],
                 "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "群管菜单", "unsupport_tips" => "当前QQ版本不支持"]]
            ]
        ];
        原生按钮($md, $rows);
        return;
    }

    // ==================== 系统设置子菜单 ====================
    if ($纯命令 == "系统设置") {
        if (!校验管理权限($发言用户, $群号, $发言身份)) {
            文字("抱歉，您没有权限访问系统设置。");
            return;
        }
        $状态行 = "当前系统：" . ($系统开关 == "开启" ? "🟢 运行中" : "🔴 已关闭") . "\n";
        $状态行 .= "链接撤回：" . (读("群管/链接撤回/".$群号, "status", "关闭") == "开启" ? "🟢 开启" : "🔴 关闭") . "\n";
        $状态行 .= "合并撤回：" . (读("群管/合并撤回/".$群号, "status", "关闭") == "开启" ? "🟢 开启" : "🔴 关闭");
        $md = 生成菜单Markdown("⚙️ 系统设置", $状态行);
        $rows = [
            [
                "buttons" => [
                    ["id" => "btn_on", "render_data" => ["label" => "🟢 开启系统", "visited_label" => "🟢 开启系统", "style" => 1],
                     "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "开启群管系统", "unsupport_tips" => "当前QQ版本不支持"]],
                    ["id" => "btn_off", "render_data" => ["label" => "🔴 关闭系统", "visited_label" => "🔴 关闭系统", "style" => 1],
                     "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "关闭群管系统", "unsupport_tips" => "当前QQ版本不支持"]]
                ]
            ],
            [
                "buttons" => [
                    ["id" => "btn_back", "render_data" => ["label" => "⬅️ 返回主菜单", "visited_label" => "⬅️ 返回主菜单", "style" => 0],
                     "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "群管菜单", "unsupport_tips" => "当前QQ版本不支持"]]
                ]
            ]
        ];
        原生按钮($md, $rows);
        return;
    }

    // ==================== 管理员管理子菜单 ====================
    if ($纯命令 == "管理员管理") {
        if (!校验全权限($发言用户, $发言身份)) {
            文字("抱歉，仅群主、群管理员或主人可管理机器人管理员。");
            return;
        }
        $md = 生成菜单Markdown("🛡️ 管理员管理");
        $rows = [
            [
                "buttons" => [
                    ["id" => "btn_add_admin", "render_data" => ["label" => "➕ 添加管理员", "visited_label" => "➕ 添加管理员", "style" => 1],
                     "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "添加管理员 ", "unsupport_tips" => "当前QQ版本不支持"]],
                    ["id" => "btn_del_admin", "render_data" => ["label" => "➖ 删除管理员", "visited_label" => "➖ 删除管理员", "style" => 1],
                     "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "删除管理员 ", "unsupport_tips" => "当前QQ版本不支持"]]
                ]
            ],
            [
                "buttons" => [
                    ["id" => "btn_admin_list", "render_data" => ["label" => "📋 管理员列表", "visited_label" => "📋 管理员列表", "style" => 1],
                     "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "管理员列表", "unsupport_tips" => "当前QQ版本不支持"]],
                    ["id" => "btn_back", "render_data" => ["label" => "⬅️ 返回主菜单", "visited_label" => "⬅️ 返回主菜单", "style" => 0],
                     "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "群管菜单", "unsupport_tips" => "当前QQ版本不支持"]]
                ]
            ]
        ];
        原生按钮($md, $rows);
        return;
    }

    // ==================== 全员可用的列表查询 ====================
    if ($纯命令 == "针对列表") {
        $名单 = 读("群管/针对名单/".$群号, "list", []);
        if (empty($名单)) {
            文字("当前针对列表为空。");
            return;
        }
        $md = "📋 当前针对列表（共".count($名单)."人）\n\n";
        foreach ($名单 as $i => $uid) {
            $md .= ($i+1)."、<@{$uid}>\n";
        }
        原生MD($md);
        return;
    }

    if ($纯命令 == "违禁词列表") {
        $违禁词列表 = 读("群管/违禁词列表/".$群号, "list", []);
        if (empty($违禁词列表)) {
            文字("当前违禁词列表为空。");
            return;
        }
        $md = "📃 本群违禁词列表（共".count($违禁词列表)."个）\n\n";
        foreach ($违禁词列表 as $i => $词) {
            $md .= ($i+1)."、{$词}\n";
        }
        文字("抱歉暂时不提供!");
        return;
    }

    if ($纯命令 == "管理员列表") {
        $管理员列表 = 读("群管/管理员列表/".$群号, "list", []);
        $md = "⚙️ 群管理员配置\n\n";
        $md .= "👑 超级管理员（主人）：\n";
        $主人 = 主人ID;
        if ($主人) {
            $md .= "<@{$主人}>\n";
        } else {
            $md .= "未设置\n";
        }
        $md .= "\n🛡️ 本群机器人管理员：\n";
        $md .= empty($管理员列表) ? "暂无\n" : "";
        foreach ($管理员列表 as $i => $uid) {
            $md .= ($i+1)."、<@{$uid}>\n";
        }
        $md .= "\n💡 提示：QQ群主、群管理员自动拥有全部权限。\n";
        原生MD($md);
        return;
    }

    // ========== 权限检查（需要管理员权限的命令统一拦截） ==========
    if ($是否管理命令 && !校验管理权限($发言用户, $群号, $发言身份)) {
        文字("抱歉，您没有管理员权限执行此操作。");
        return;
    }

    // ========== 1. 撤回单条消息 ==========
    if (前缀($纯命令, "撤回") && !前缀($纯命令, "撤回全部")) {
        if (empty($所有艾特用户)) {
            文字("请@要撤回消息的用户，例如：撤回 @用户");
            return;
        }
        if (消息来源 !== "群聊") {
            文字("撤回功能仅支持群聊消息。");
            return;
        }
        $目标 = $所有艾特用户[0];
        if ($目标 !== 机器人ID) {
            $目标角色 = 获取群成员角色($群号, $目标);
            $机器人管理员列表 = 读("群管/管理员列表/".$群号, "list", []);
            if (in_array($目标角色, ["owner", "admin"]) || in_array($目标, $机器人管理员列表)) {
                文字("无法撤回该用户的消息，因为对方是群主、管理员或机器人管理员。");
                return;
            }
        }
        $消息池 = 读("群管/消息缓存/".$群号, $目标, []);
        if (empty($消息池)) {
            文字("未找到该用户的可撤回消息，只能撤回两分钟内的消息哦。");
            return;
        }
        $返回结果 = 撤回($消息池[0]);
        $返回数据 = json_decode($返回结果, true);
        if (isset($返回数据['code']) && $返回数据['code'] != 0) {
            文字("撤回失败，请确认：\n1. 机器人已被设为群管理员。\n2. 消息发送未超过2分钟。\n3. 对方身份不高于机器人。");
            return;
        }
        array_shift($消息池);
        写("群管/消息缓存/".$群号, $目标, $消息池);
        文字("已成功撤回该用户最新一条消息。");
        return;
    }

    // ========== 2. 撤回全部消息 ==========
    if (前缀($纯命令, "撤回全部")) {
        if (empty($所有艾特用户)) {
            文字("请@要撤回消息的用户，例如：撤回全部 @用户");
            return;
        }
        if (消息来源 !== "群聊") {
            文字("撤回功能仅支持群聊消息。");
            return;
        }
        $目标 = $所有艾特用户[0];
        if ($目标 !== 机器人ID) {
            $目标角色 = 获取群成员角色($群号, $目标);
            $机器人管理员列表 = 读("群管/管理员列表/".$群号, "list", []);
            if (in_array($目标角色, ["owner", "admin"]) || in_array($目标, $机器人管理员列表)) {
                文字("无法撤回该用户的消息，因为对方是群主、管理员或机器人管理员。");
                return;
            }
        }
        $消息池 = 读("群管/消息缓存/".$群号, $目标, []);
        if (empty($消息池)) {
            文字("未找到该用户的可撤回消息。");
            return;
        }
        $总数 = count($消息池);
        $成功数 = 0; $失败数 = 0;
        foreach ($消息池 as $msgid) {
            $返回结果 = 撤回($msgid);
            $返回数据 = json_decode($返回结果, true);
            if (!isset($返回数据['code']) || $返回数据['code'] == 0) {
                $成功数++;
            } else {
                $失败数++;
            }
            usleep(100000);
        }
        if ($失败数 == 0) {
            写("群管/消息缓存/".$群号, $目标, []);
            文字("已成功撤回该用户全部 {$成功数} 条消息。");
        } else {
            $提示 = "本次共尝试撤回 {$总数} 条，成功 {$成功数} 条，失败 {$失败数} 条。\n\n";
            $提示 .= "撤回失败请确认：\n1. 机器人已被设为群管理员。\n2. 消息发送未超过2分钟。\n3. 对方身份不高于机器人。\n\n消息缓存已保留。";
            文字($提示);
        }
        return;
    }

    // ========== 3. 针对名单操作 ==========
    if (前缀($纯命令, "针对") && !前缀($纯命令, "取消针对")) {
        $操作目标列表 = array_values(array_diff($所有艾特用户, [机器人ID]));
        if (empty($操作目标列表)) {
            文字("请@要加入针对名单的用户，不能@机器人自身。");
            return;
        }
        $目标 = $操作目标列表[0];
        $名单 = 读("群管/针对名单/".$群号, "list", []);
        if (in_array($目标, $名单)) {
            文字("该用户已经在针对名单中，无需重复添加。");
            return;
        }
        $名单[] = $目标;
        写("群管/针对名单/".$群号, "list", $名单);
        文字("已将该用户加入针对列表，其发言将被自动撤回。");
        return;
    }

    if (前缀($纯命令, "取消针对")) {
        $操作目标列表 = array_values(array_diff($所有艾特用户, [机器人ID]));
        if (empty($操作目标列表)) {
            文字("请@要取消针对的用户，不能@机器人自身。");
            return;
        }
        $目标 = $操作目标列表[0];
        $名单 = 读("群管/针对名单/".$群号, "list", []);
        if (!in_array($目标, $名单)) {
            文字("该用户不在针对名单中，无需取消。");
            return;
        }
        $名单 = array_values(array_diff($名单, [$目标]));
        写("群管/针对名单/".$群号, "list", $名单);
        文字("已取消针对该用户。");
        return;
    }

    // ========== 4. 违禁词操作 ==========
    if (前缀($纯命令, "添加违禁词")) {
        $新词 = trim(substr($纯命令, strlen("添加违禁词")));
        if (empty($新词)) {
            文字("请指定要添加的违禁词，例如：添加违禁词 广告");
            return;
        }
        $违禁词列表 = 读("群管/违禁词列表/".$群号, "list", []);
        if (in_array($新词, $违禁词列表)) {
            文字("违禁词「{$新词}」已存在，无需重复添加。");
            return;
        }
        $违禁词列表[] = $新词;
        写("群管/违禁词列表/".$群号, "list", $违禁词列表);
        文字("添加成功！");
        return;
    }

    if (前缀($纯命令, "删除违禁词")) {
        $删词 = trim(substr($纯命令, strlen("删除违禁词")));
        if (empty($删词)) {
            文字("请指定要删除的违禁词，例如：删除违禁词 广告");
            return;
        }
        $违禁词列表 = 读("群管/违禁词列表/".$群号, "list", []);
        if (!in_array($删词, $违禁词列表)) {
            文字("违禁词「{$删词}」不存在，无需删除。");
            return;
        }
        $违禁词列表 = array_values(array_diff($违禁词列表, [$删词]));
        写("群管/违禁词列表/".$群号, "list", $违禁词列表);
        文字("已删除违禁词：{$删词}");
        return;
    }

    // ========== 5. 开关类 ==========
    if ($纯命令 == "链接撤回切换") {
        $当前状态 = 读("群管/链接撤回/".$群号, "status", "关闭");
        $新状态 = ($当前状态 == "开启") ? "关闭" : "开启";
        写("群管/链接撤回/".$群号, "status", $新状态);
        文字($新状态 == "开启" ? "链接撤回已开启。" : "链接撤回已关闭。");
        return;
    }

    if ($纯命令 == "合并撤回切换") {
        $当前状态 = 读("群管/合并撤回/".$群号, "status", "关闭");
        $新状态 = ($当前状态 == "开启") ? "关闭" : "开启";
        写("群管/合并撤回/".$群号, "status", $新状态);
        文字($新状态 == "开启" ? "合并撤回已开启。" : "合并撤回已关闭。");
        return;
    }

    // ========== 6. 禁言开关与时长 ==========
    if ($纯命令 == "禁言开关") {
        $当前状态 = 读("群管/禁言开关/".$群号, "status", "关闭");
        $新状态 = ($当前状态 == "开启") ? "关闭" : "开启";
        写("群管/禁言开关/".$群号, "status", $新状态);
        文字($新状态 == "开启" ? "违禁词自动禁言已开启。" : "违禁词自动禁言已关闭。");
        return;
    }

    if (前缀($纯命令, "设置禁言时长")) {
        $时长分钟 = trim(substr($纯命令, strlen("设置禁言时长")));
        if (!is_numeric($时长分钟) || (int)$时长分钟 <= 0) {
            文字("请指定有效的分钟数，例如：设置禁言时长 5（单位：分钟）");
            return;
        }
        $秒数 = (int)$时长分钟 * 60;
        写("群管/禁言时长/".$群号, "value", $秒数);
        文字("禁言时长已设置为 {$时长分钟} 分钟。");
        return;
    }

    // ========== 7. 系统总开关 ==========
    if ($纯命令 == "开启群管系统") {
        if (读("群管/系统开关/".$群号, "status", "关闭") == "开启") {
            文字("群管系统已经是开启状态，无需重复操作。");
            return;
        }
        写("群管/系统开关/".$群号, "status", "开启");
        文字("群管系统已开启。");
        return;
    }

    if ($纯命令 == "关闭群管系统") {
        if (读("群管/系统开关/".$群号, "status", "关闭") == "关闭") {
            文字("群管系统已经是关闭状态，无需重复操作。");
            return;
        }
        写("群管/系统开关/".$群号, "status", "关闭");
        文字("群管系统已关闭，所有自动撤回和管理功能暂停，发送「开启群管系统」即可恢复。");
        return;
    }

    // ========== 8. 管理员管理 ==========
    if (前缀($纯命令, "添加管理员")) {
        if (!校验全权限($发言用户, $发言身份)) {
            文字("抱歉，仅群主、群管理员或主人可添加机器人管理员。");
            return;
        }
        $操作目标列表 = array_values(array_diff($所有艾特用户, [机器人ID]));
        if (empty($操作目标列表)) {
            文字("请@要添加的管理员，不能@机器人自身。");
            return;
        }
        $目标 = $操作目标列表[0];
        $管理员列表 = 读("群管/管理员列表/".$群号, "list", []);
        if (in_array($目标, $管理员列表)) {
            文字("该用户已经是机器人管理员，无需重复添加。");
            return;
        }
        $管理员列表[] = $目标;
        写("群管/管理员列表/".$群号, "list", $管理员列表);
        文字("已成功添加该用户为机器人管理员。");
        return;
    }

    if (前缀($纯命令, "删除管理员")) {
        if (!校验全权限($发言用户, $发言身份)) {
            文字("抱歉，仅群主、群管理员或主人可删除机器人管理员。");
            return;
        }
        $操作目标列表 = array_values(array_diff($所有艾特用户, [机器人ID]));
        if (empty($操作目标列表)) {
            文字("请@要删除的管理员，不能@机器人自身。");
            return;
        }
        $目标 = $操作目标列表[0];
        $管理员列表 = 读("群管/管理员列表/".$群号, "list", []);
        if (!in_array($目标, $管理员列表)) {
            文字("该用户不在机器人管理员列表中，无需删除。");
            return;
        }
        $管理员列表 = array_values(array_diff($管理员列表, [$目标]));
        写("群管/管理员列表/".$群号, "list", $管理员列表);
        文字("已移除该用户的机器人管理员权限。");
        return;
    }
} // end 群聊消息处理