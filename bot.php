<?php

/**
 * ============================================================
 *  云雀 (Yunque) · QQ 官方机器人框架 · 消息发送 API
 * ============================================================
 *  提供完整的中文 + 英文发送 API：
 *   - 被动回复（文字/富媒体/卡片/原生MD/按钮/流式/撤回）
 *   - 主动推送（群聊主动消息 / C2C 互动召回等）
 *   - 群成员 / 身份判定（群主/管理员/群员/机器人）
 *   - 富媒体上传
 *
 *  兼容旧版中文函数：文字() 图片() 视频() 语音() 文件()
 *  按钮() 文卡() 大图() 跳转卡() 流式() 撤回()
 *  原生MD() 原生按钮() 发MD() 富媒体() 头像()
 * ============================================================
 */

/* ------------------------------------------------------------
 * 底层请求
 * --------------------------------------------------------- */
function BOTAPI($Address, $me, $json) {
    $urls = [
        "正式" => "https://api.bot.qq.com",
        "沙箱" => "https://sandbox.api.bot.qq.com",
    ];
    $fallback = [
        "正式" => "https://api.sgroup.qq.com",
        "沙箱" => "https://sandbox.api.sgroup.qq.com",
    ];
    $type = 常量('type', '正式');
    $host = isset($urls[$type]) ? $urls[$type] : $urls["正式"];
    $url = $host . $Address;
    $header = ["Authorization: QQBot " . BOT凭证(), 'Content-Type: application/json'];
    $result = curl($url, $me, $header, $json);
    // 新域名不可达（网络失败 / 404）时回退旧域名，兼容域名切换过渡期
    $decoded = json_decode($result, true);
    if (!is_array($decoded) || (isset($decoded['code']) && $decoded['code'] == -1)) {
        $oldHost = isset($fallback[$type]) ? $fallback[$type] : $fallback["正式"];
        $oldResult = curl($oldHost . $Address, $me, $header, $json);
        $oldDecoded = json_decode($oldResult, true);
        if (is_array($oldDecoded) && (!isset($oldDecoded['code']) || $oldDecoded['code'] != -1)) {
            return $oldResult;
        }
    }
    return $result;
}

/** 安全读取常量 */
function 常量($name, $默认 = null) {
    return defined($name) ? constant($name) : $默认;
}

/** 被动回复锚点：优先 event_id，其次 msg_id */
function 被动锚点() {
    $eventId = 常量('事件ID', '');
    if ($eventId !== '') return ['event_id' => $eventId];
    $msgId = 常量('消息ID', '');
    if ($msgId !== '') return ['msg_id' => $msgId];
    return [];
}

/** 解析当前事件对应的发送场景/目标 */
function 当前场景(&$scene, &$target, &$anchor) {
    $source = 常量('消息来源', '未知');
    switch ($source) {
        case "群聊":
            $scene = "群聊"; $target = 常量('来源', ''); $anchor = ['msg_id' => 常量('消息ID', '')];
            break;
        case "私聊":
            $scene = "私聊"; $target = 常量('来源', '');
            $anchor = 常量('事件ID', '') !== '' ? ['event_id' => 常量('事件ID')] : ['msg_id' => 常量('消息ID', '')];
            break;
        case "加群":
        case "退群":
            $scene = "群聊"; $target = 常量('来源', ''); $anchor = ['event_id' => 常量('事件ID', '')];
            break;
        case "互动":
            if (互动私聊()) {
                $scene = "私聊"; $target = 互动目标用户();
            } else {
                $scene = "群聊"; $target = 常量('来源', '');
            }
            $anchor = ['event_id' => 常量('事件ID', '')];
            break;
        default:
            $scene = "未知"; $target = ''; $anchor = [];
    }
}

/** 通用消息发送（scene: 群聊/私聊；active 为 true 时走主动消息） */
function 云雀API($scene, $target, $body, $active = false) {
    if ($target === '' || $scene === '未知') return json_encode(['code' => -1, 'message' => '无效发送目标'], JSON_UNESCAPED_UNICODE);
    if (!$active) {
        $anchor = 被动锚点();
        foreach ($anchor as $k => $v) {
            if (!isset($body[$k]) || $body[$k] === '') $body[$k] = $v;
        }
    }
    if (!isset($body['msg_seq'])) $body['msg_seq'] = mt_rand(1, 99999);

    // 群聊被动回复内容加换行，避免被当作“斜杠命令”
    if (!$active && $scene === '群聊' && isset($body['content']) && is_string($body['content']) && $body['content'] !== ''
        && in_array($body['msg_type'] ?? -1, [0, 7], true) && $body['content'][0] !== "\n") {
        $body['content'] = "\n" . $body['content'];
    }

    $endpoint = $scene === "私聊" ? "/v2/users/{$target}/messages" : "/v2/groups/{$target}/messages";
    return BOTAPI($endpoint, "POST", json_encode($body, JSON_UNESCAPED_UNICODE));
}

/** 当前上下文发送 */
function 云雀发送($body, $active = false) {
    if (常量('消息来源') === '文字子频道') {
        $json = array_merge($body, ['msg_id' => 常量('消息ID', '')]);
        return BOTAPI("/channels/" . 常量('来源') . "/messages", "POST", json_encode($json, JSON_UNESCAPED_UNICODE));
    }
    当前场景($scene, $target, $anchor);
    return 云雀API($scene, $target, $body, $active);
}

/* ------------------------------------------------------------
 * 富媒体上传
 * --------------------------------------------------------- */
function 上传富媒体($scene, $target, $type, $data, $name = null) {
    $types = ["图片" => 1, "视频" => 2, "语音" => 3, "文件" => 4];
    $t = $types[$type] ?? 1;
    if (preg_match('/^https?:\/\//i', $data)) {
        $jsonData = ['file_type' => $t, 'url' => $data, 'file_name' => $name, 'srv_send_msg' => false];
    } else {
        $jsonData = ['file_type' => $t, 'file_data' => base64_encode($data), 'file_name' => $name, 'srv_send_msg' => false];
    }
    $endpoint = $scene === "私聊" ? "/v2/users/{$target}/files" : "/v2/groups/{$target}/files";
    $r = BOTAPI($endpoint, "POST", json_encode($jsonData));
    return json_decode($r, true) ?: [];
}

/** 兼容旧调用：按当前上下文上传 */
function 富媒体($type, $image, $name = null) {
    当前场景($scene, $target, $anchor);
    return 上传富媒体($scene, $target, $type, $image, $name);
}

/** 发送富媒体消息（msg_type=7） */
function 云雀媒体($fileInfo, $scene, $target, $content = '', $active = false) {
    $body = ['msg_type' => 7, 'media' => ['file_info' => $fileInfo]];
    if ($content !== '' && $content !== null) {
        $body['content'] = $scene === '群聊' ? $content : $content;
    } else {
        $body['content'] = '';
    }
    return 云雀API($scene, $target, $body, $active);
}

/** 富媒体上传失败时返回的错误信息 */
function 富媒体错误($info) {
    return $info['message'] ?? $info['msg'] ?? '';
}

/* ------------------------------------------------------------
 * 中文发送 API（兼容旧插件）
 * --------------------------------------------------------- */
function 文字($content, $引用id = null) {
    记录发送("发送文字", 常量('来源'), $content, "文字");
    $body = ["content" => (string)$content, "msg_type" => 0];
    if ($引用id) $body["message_reference"] = ["message_id" => (string)$引用id];
    return 云雀发送($body);
}

function 图片($image, $content = null) {
    记录发送("发送图片", 常量('来源'), $content ?? "[图片]", "图片", ['image_url' => $image]);
    if (常量('消息来源') === '文字子频道') {
        return BOTAPI("/channels/" . 常量('来源') . "/messages", "POST", json_encode([
            "content" => $content, "file_image" => $image, "msg_id" => 常量('消息ID', ''),
        ], JSON_UNESCAPED_UNICODE));
    }
    当前场景($scene, $target, $anchor);
    $info = 上传富媒体($scene, $target, "图片", $image);
    if (富媒体错误($info) !== '') return 文字(富媒体错误($info));
    return 云雀媒体($info['file_info'] ?? '', $scene, $target, $content);
}

function 语音($yy) {
    记录发送("发送语音", 常量('来源'), "[语音文件]", "语音", ['voice_url' => $yy]);
    当前场景($scene, $target, $anchor);
    $silk = silk($yy);
    $info = 上传富媒体($scene, $target, "语音", $silk);
    if (富媒体错误($info) !== '') return 文字(富媒体错误($info));
    return 云雀媒体($info['file_info'] ?? '', $scene, $target, '', false);
}

function 文件($yy, $nm) {
    记录发送("发送文件", 常量('来源'), "[文件: {$nm}]", "文件", ['file_url' => $yy, 'file_name' => $nm]);
    当前场景($scene, $target, $anchor);
    $info = 上传富媒体($scene, $target, "文件", $yy, $nm);
    if (富媒体错误($info) !== '') return 文字(富媒体错误($info));
    return 云雀媒体($info['file_info'] ?? '', $scene, $target, '', false);
}

function 视频($video) {
    记录发送("发送视频", 常量('来源'), "[视频文件]", "视频", ['video_url' => $video]);
    当前场景($scene, $target, $anchor);
    $info = 上传富媒体($scene, $target, "视频", $video);
    if (富媒体错误($info) !== '') return 文字(富媒体错误($info));
    return 云雀媒体($info['file_info'] ?? '', $scene, $target, '', false);
}

function 按钮($key) {
    记录发送("发送按钮", 常量('来源'), "[按钮ID: {$key}]", "按钮");
    return 云雀发送(["msg_type" => 2, "keyboard" => ["id" => $key]]);
}

function 文卡(...$items) {
    $itemTexts = [];
    foreach ($items as $item) $itemTexts[] = $item['text'] ?? '[文本]';
    记录发送("发送文卡", 常量('来源'), implode(" | ", $itemTexts), "文卡");
    $list_items = [];
    foreach ($items as $item) {
        if (isset($item['url'])) {
            $list_items[] = ["obj_kv" => [
                ["key" => "desc", "value" => $item['text'] ?? ''],
                ["key" => "link", "value" => $item['url']],
            ]];
        } else {
            $list_items[] = ["obj_kv" => [["key" => "desc", "value" => $item['text'] ?? '']]];
        }
    }
    $json = [
        "msg_type" => 3,
        "ark" => [
            "template_id" => 23,
            "kv" => [
                ["key" => "#DESC#", "value" => "愿为西南风,长逝入君怀"],
                ["key" => "#PROMPT#", "value" => "愿为西南风,长逝入君怀"],
                ["key" => "#LIST#", "obj" => $list_items],
            ],
        ],
    ];
    return 云雀发送($json);
}

function 大图($title, $xtitle, $iurl) {
    记录发送("发送大图卡片", 常量('来源'), "标题: {$title}", "大图卡片", ['card_url' => $iurl]);
    $json = [
        "msg_type" => 3,
        "ark" => [
            "template_id" => 37,
            "kv" => [
                ["key" => "#METATITLE#", "value" => $title],
                ["key" => "#METASUBTITLE#", "value" => $xtitle],
                ["key" => "#PROMPT#", "value" => "愿为西南风,长逝入君怀"],
                ["key" => "#METACOVER#", "value" => $iurl],
            ],
        ],
    ];
    return 云雀发送($json);
}

function 跳转卡($title, $desc, $image, $tz) {
    记录发送("发送跳转卡片", 常量('来源'), "标题: {$title}, 链接: {$tz}", "跳转卡片", ['card_url' => $image]);
    $json = [
        "msg_type" => 3,
        "ark" => [
            "template_id" => 24,
            "kv" => [
                ["key" => "#DESC#", "value" => "愿为西南风,长逝入君怀"],
                ["key" => "#PROMPT#", "value" => "愿为西南风,长逝入君怀"],
                ["key" => "#TITLE#", "value" => $title],
                ["key" => "#METADESC#", "value" => $desc],
                ["key" => "#IMG#", "value" => $image],
                ["key" => "#LINK#", "value" => $tz],
                ["key" => "#SUBTITLE#", "value" => "愿为西南风,长逝入君怀"],
            ],
        ],
    ];
    return 云雀发送($json);
}

function 流式(...$msgs) {
    $content_preview = implode(" ", array_slice($msgs, 0, 2));
    记录发送("流式回复", 常量('来源'), $content_preview . (count($msgs) > 2 ? " ..." : ""), "流式");
    $id = null;
    $index = 0;
    $total = count($msgs);
    $target = 常量('来源');
    $curl = null;
    foreach ($msgs as $msg) {
        $isLast = ($index === $total - 1);
        $json = [
            "content" => (string)$msg,
            "msg_id" => 常量('消息ID'),
            "msg_seq" => rand(1, 99999),
            "stream" => ["state" => $isLast ? 10 : 1, "id" => $id, "index" => $index, "reset" => false],
        ];
        $curl = BOTAPI("/v2/users/{$target}/messages", "POST", json_encode($json));
        $decoded = json_decode($curl, true);
        $id = $decoded["id"] ?? null;
        $index++;
    }
    return $curl;
}

function 撤回($id) {
    记录发送("撤回消息", 常量('来源'), "消息ID: {$id}", "撤回");
    $type = ["群聊" => "groups", "私聊" => "users"];
    $type = $type[常量('消息来源')] ?? 'groups';
    return BOTAPI("/v2/{$type}/" . 常量('来源') . "/messages/" . $id, "DELETE", "");
}

function 互动私聊() {
    $raw = 常量('raw', []);
    return 常量('消息来源') == "互动" && (
        ($raw["d"]["scene"] ?? "") == "c2c" ||
        (string)($raw["d"]["chat_type"] ?? "") == "2" ||
        (!isset($raw["d"]["group_openid"]) && isset($raw["d"]["user_openid"]))
    );
}

function 互动目标用户() {
    return 常量('raw', [])["d"]["user_openid"] ?? 常量('来源');
}

function 原生MD($md, $keyboard = null) {
    记录发送("发送原生MD", 常量('来源'), $md, "原生MD");
    $json = ["msg_type" => 2, "markdown" => ["content" => $md]];
    if ($keyboard !== null) $json["keyboard"] = ["id" => $keyboard];
    return 云雀发送($json);
}

function 原生按钮($md, $rows) {
    记录发送("发送原生自定义按钮", 常量('来源'), $md, "原生按钮");
    $json = [
        "msg_type" => 2,
        "markdown" => ["content" => $md],
        "keyboard" => ["content" => ["rows" => $rows]],
    ];
    return 云雀发送($json);
}

function 发MD($template_id, $params, $keyboard_id = null) {
    $logParams = [];
    if (isset($params['key']) && isset($params['values'])) {
        $logParams[] = $params['key'] . ":" . implode(",", $params['values']);
    } elseif (is_array($params)) {
        foreach ($params as $p) {
            if (isset($p['key'])) $logParams[] = $p['key'];
        }
    }
    记录发送("发送自定义MD", 常量('来源'), "模板: {$template_id} " . implode(" ", $logParams), "自定义MD");

    if (isset($params['key']) && isset($params['values'])) $params = [$params];
    $json_data = [
        "content" => "",
        "msg_type" => 2,
        "markdown" => ["custom_template_id" => $template_id, "params" => $params],
    ];
    if (!empty($keyboard_id)) $json_data["keyboard"] = ["id" => $keyboard_id];

    $source = 常量('消息来源');
    if (in_array($source, ["群聊", "加群", "退群", "互动"])) {
        $scene = "群聊";
    } elseif ($source === "私聊") {
        $scene = "私聊";
    } elseif ($source === "文字子频道") {
        return BOTAPI("/channels/" . 常量('来源') . "/messages", "POST", json_encode(array_merge($json_data, ['msg_id' => 常量('消息ID', '')]), JSON_UNESCAPED_UNICODE));
    } else {
        return "错误：消息来源不支持";
    }
    当前场景($scene2, $target, $anchor);
    return 云雀API($scene, $target, $json_data);
}

/* ------------------------------------------------------------
 * 主动消息（需要群主开启【机器人主动在群聊内发言】或相应权限）
 * --------------------------------------------------------- */
function 主动文字($target, $content, $scene = '群聊') {
    记录发送("主动文字", $target, $content, "文字", ['source_type' => $scene, 'active' => true]);
    return 云雀API($scene, $target, ["content" => (string)$content, "msg_type" => 0], true);
}

function 主动图片($target, $image, $content = '', $scene = '群聊') {
    记录发送("主动图片", $target, $content ?: "[图片]", "图片", ['source_type' => $scene, 'active' => true, 'image_url' => $image]);
    $info = 上传富媒体($scene, $target, "图片", $image);
    if (富媒体错误($info) !== '') return json_encode(['code' => -1, 'message' => 富媒体错误($info)], JSON_UNESCAPED_UNICODE);
    return 云雀媒体($info['file_info'] ?? '', $scene, $target, $content, true);
}

function 主动文件($target, $data, $name, $scene = '群聊') {
    记录发送("主动文件", $target, "[文件: {$name}]", "文件", ['source_type' => $scene, 'active' => true]);
    $info = 上传富媒体($scene, $target, "文件", $data, $name);
    if (富媒体错误($info) !== '') return json_encode(['code' => -1, 'message' => 富媒体错误($info)], JSON_UNESCAPED_UNICODE);
    return 云雀媒体($info['file_info'] ?? '', $scene, $target, '', true);
}

function 主动视频($target, $video, $scene = '群聊') {
    记录发送("主动视频", $target, "[视频文件]", "视频", ['source_type' => $scene, 'active' => true]);
    $info = 上传富媒体($scene, $target, "视频", $video);
    if (富媒体错误($info) !== '') return json_encode(['code' => -1, 'message' => 富媒体错误($info)], JSON_UNESCAPED_UNICODE);
    return 云雀媒体($info['file_info'] ?? '', $scene, $target, '', true);
}

function 主动语音($target, $audio, $scene = '群聊') {
    记录发送("主动语音", $target, "[语音文件]", "语音", ['source_type' => $scene, 'active' => true]);
    $silk = silk($audio);
    $info = 上传富媒体($scene, $target, "语音", $silk);
    if (富媒体错误($info) !== '') return json_encode(['code' => -1, 'message' => 富媒体错误($info)], JSON_UNESCAPED_UNICODE);
    return 云雀媒体($info['file_info'] ?? '', $scene, $target, '', true);
}

function 主动MD($target, $md, $scene = '群聊') {
    记录发送("主动原生MD", $target, $md, "原生MD", ['source_type' => $scene, 'active' => true]);
    return 云雀API($scene, $target, ["content" => "", "msg_type" => 2, "markdown" => ["content" => $md]], true);
}

/** C2C 主动消息：用户近期对话过才可成功 */
function 主动私聊($openid, $content) {
    记录发送("主动私聊", $openid, $content, "文字", ['source_type' => '私聊', 'active' => true]);
    return 云雀API("私聊", $openid, ["content" => (string)$content, "msg_type" => 0], true);
}

/* ------------------------------------------------------------
 * 群成员 / 身份判定
 * --------------------------------------------------------- */
function 群成员($uid = null, $group = null) {
    $uid = $uid ?? 常量('用户', '');
    $group = $group ?? 常量('来源', '');
    if ($uid === '' || $group === '') return null;
    $cache = 读("成员/" . $group, $uid, null);
    if (is_array($cache) && isset($cache['time']) && time() - $cache['time'] < 120) return $cache['data'];
    $r = BOTAPI("/v2/groups/{$group}/members/{$uid}", "GET", '');
    $data = json_decode($r, true);
    if (!is_array($data)) return null;
    写("成员/" . $group, $uid, ['time' => time(), 'data' => $data]);
    return $data;
}

function 群成员列表($group, $limit = 100, $offset = 0) {
    if ($group === '') return [];
    $r = BOTAPI("/v2/groups/{$group}/members?limit={$limit}&offset={$offset}", "GET", '');
    $data = json_decode($r, true);
    return is_array($data) ? $data : [];
}

/** 身份：群主 / 管理员 / 群员 / 未知 */
function 群身份($uid = null) {
    if ($uid === null && 常量('身份', '') !== '') return 常量('身份');
    $m = 群成员($uid);
    if (!$m) return '未知';
    $role = $m['member_role'] ?? 'member';
    return ['owner' => '群主', 'admin' => '管理员', 'member' => '群员'][$role] ?? '未知';
}

/** 群成员昵称（缓存优先，失败返回 null） */
function 群昵称($uid = null, $group = null) {
    $m = 群成员($uid, $group);
    return $m['username'] ?? null;
}

function 群主($uid = null) { return 群身份($uid) === '群主'; }
function 管理员($uid = null) { $r = 群身份($uid); return $r === '群主' || $r === '管理员'; }
function 群管($uid = null) { return 管理员($uid); }
function 群员($uid = null) { return 群身份($uid) === '群员'; }

function 是机器人($uid = null) {
    if ($uid === null && defined('是否机器人')) return 是否机器人;
    $m = 群成员($uid);
    return $m ? !empty($m['bot']) : false;
}

/* ------------------------------------------------------------
 * 其他工具
 * --------------------------------------------------------- */
function 头像($id) {
    return "https://q.qlogo.cn/qqapp/" . 常量('appid') . "/{$id}/640";
}

function 艾特($uid) {
    return "<@{$uid}> ";
}

function 引用对象($msgId) {
    return ["message_id" => (string)$msgId];
}

/* ------------------------------------------------------------
 * 群聊管理（官方 v2 群管理能力）
 * --------------------------------------------------------- */
/** 获取群基本信息（群名/简介/人数等） */
function 群信息($group = null) {
    $group = $group ?? 常量('来源', '');
    if ($group === '') return null;
    $r = BOTAPI("/v2/groups/{$group}/info", "GET", '');
    $data = json_decode($r, true);
    return is_array($data) ? $data : null;
}

/** 群名称 */
function 群名($group = null) { $g = 群信息($group); return $g['group_name'] ?? null; }

/** 群成员人数 */
function 群人数($group = null) { $g = 群信息($group); return $g['group_member_num'] ?? null; }

/** 机器人在群内的状态（入群时间/是否接收主动推送/身份） */
function 机器人状态($group = null) {
    $group = $group ?? 常量('来源', '');
    if ($group === '') return null;
    $r = BOTAPI("/v2/groups/{$group}/bot_state", "GET", '');
    $data = json_decode($r, true);
    return is_array($data) ? $data : null;
}

/** 群禁言底层：op = add 增加 / update 更新 / del 解除 */
function 群禁言设置($uid, $op, $秒 = 0, $group = null, $到期时间 = null) {
    $group = $group ?? 常量('来源', '');
    if ($group === '' || $uid === '') return null;
    $member = ['op' => $op, 'member_openid' => $uid];
    if ($op === 'del') {
        $member['mute_expire_at'] = '';
    } else {
        $member['mute_expire_at'] = $到期时间 !== null
            ? (is_numeric($到期时间) ? date('c', (int)$到期时间) : $到期时间)
            : date('c', time() + (int)$秒);
    }
    $r = BOTAPI("/v2/groups/{$group}/restrict_chat_setting", "POST",
        json_encode(['members' => [$member]], JSON_UNESCAPED_UNICODE));
    return json_decode($r, true) ?: null;
}

/** 禁言群成员（秒） */
function 群禁言($uid, $秒, $group = null) {
    return 群禁言设置($uid, 'add', $秒, $group);
}

/** 禁言到指定时间（时间戳或 RFC3339 字符串） */
function 群禁言到($uid, $时间, $group = null) {
    return 群禁言设置($uid, 'update', 0, $group, $时间);
}

/** 解除群成员禁言 */
function 解除群禁言($uid, $group = null) {
    return 群禁言设置($uid, 'del', 0, $group);
}

/** 查询群禁言状态（全员禁言模式 + 成员禁言列表） */
function 查询群禁言($group = null) {
    $group = $group ?? 常量('来源', '');
    if ($group === '') return null;
    $r = BOTAPI("/v2/groups/{$group}/restrict_chat_setting", "GET", '');
    $data = json_decode($r, true);
    return is_array($data) ? $data : null;
}

/** 判断成员当前是否处于禁言中 */
function 是否禁言($uid, $group = null) {
    $info = 查询群禁言($group);
    if (!is_array($info) || empty($info['members'])) return false;
    foreach ($info['members'] as $m) {
        if (($m['member_openid'] ?? '') === $uid) {
            $exp = $m['mute_expire_at'] ?? '';
            if ($exp === '') return true;
            $t = strtotime($exp);
            return $t === false || $t > time();
        }
    }
    return false;
}

/** 拉取入群申请列表（支持分页，limit 默认 20 最大 100） */
function 入群申请($limit = 20, $cursor = '', $group = null) {
    $group = $group ?? 常量('来源', '');
    if ($group === '') return null;
    $qs = http_build_query(['limit' => (int)$limit, 'cursor' => $cursor]);
    $r = BOTAPI("/v2/groups/{$group}/join_request_list?{$qs}", "GET", '');
    $data = json_decode($r, true);
    return is_array($data) ? $data : null;
}

/** 审批入群：op = approve 通过 / decline 拒绝 */
function 审批入群($uid, $op = 'approve', $申请id = null, $拒绝理由 = '', $拉黑 = false, $group = null) {
    $group = $group ?? 常量('来源', '');
    if ($group === '' || $uid === '') return null;
    $body = ['op' => $op];
    if ($申请id !== null) $body['join_request_id'] = (string)$申请id;
    if ($op === 'decline') {
        if ($拒绝理由 !== '') $body['reject_reason'] = $拒绝理由;
        if ($拉黑) $body['add_to_member_blacklist'] = true;
    }
    $r = BOTAPI("/v2/groups/{$group}/approval_join_request/{$uid}", "POST",
        json_encode($body, JSON_UNESCAPED_UNICODE));
    return json_decode($r, true) ?: null;
}

/** 同意入群 */
function 同意入群($uid, $申请id = null, $group = null) {
    return 审批入群($uid, 'approve', $申请id, '', false, $group);
}

/** 拒绝入群（可拉黑） */
function 拒绝入群($uid, $申请id = null, $理由 = '', $拉黑 = false, $group = null) {
    return 审批入群($uid, 'decline', $申请id, $理由, $拉黑, $group);
}

/* ------------------------------------------------------------
 * 频道管理（QQ 频道 / 子频道）
 * --------------------------------------------------------- */
/** 获取频道详情 */
function 频道信息($guildId) {
    if ($guildId === '') return null;
    $r = BOTAPI("/guilds/{$guildId}", "GET", '');
    $data = json_decode($r, true);
    return is_array($data) ? $data : null;
}

/** 机器人所在频道列表 */
function 我的频道() {
    $r = BOTAPI("/users/me/guilds", "GET", '');
    $data = json_decode($r, true);
    return is_array($data) ? $data : [];
}

/** 获取频道下的子频道列表 */
function 子频道列表($guildId) {
    if ($guildId === '') return [];
    $r = BOTAPI("/guilds/{$guildId}/channels", "GET", '');
    $data = json_decode($r, true);
    return is_array($data) ? $data : [];
}

/** 获取子频道详情 */
function 子频道信息($channelId) {
    if ($channelId === '') return null;
    $r = BOTAPI("/channels/{$channelId}", "GET", '');
    $data = json_decode($r, true);
    return is_array($data) ? $data : null;
}

/** 频道指定成员禁言（秒） */
function 频道禁言($guildId, $uid, $秒) {
    if ($guildId === '' || $uid === '') return null;
    $r = BOTAPI("/guilds/{$guildId}/members/{$uid}/mute", "PATCH",
        json_encode(['mute_seconds' => (string)$秒]));
    return json_decode($r, true) ?: null;
}

/** 解除频道指定成员禁言 */
function 频道解禁($guildId, $uid) {
    return 频道禁言($guildId, $uid, 0);
}

/** 频道全员禁言（秒，0 解除） */
function 频道全员禁言($guildId, $秒 = 0) {
    if ($guildId === '') return null;
    $r = BOTAPI("/guilds/{$guildId}/mute", "PATCH",
        json_encode(['mute_seconds' => (string)$秒]));
    return json_decode($r, true) ?: null;
}

/** 频道批量成员禁言（秒，0 解除） */
function 频道批量禁言($guildId, $uids, $秒) {
    if ($guildId === '' || empty($uids)) return null;
    $r = BOTAPI("/guilds/{$guildId}/mute", "PATCH",
        json_encode(['mute_seconds' => (string)$秒, 'user_ids' => array_values($uids)]));
    return json_decode($r, true) ?: null;
}

/** 获取频道成员详情 */
function 频道成员($guildId, $uid) {
    if ($guildId === '' || $uid === '') return null;
    $r = BOTAPI("/guilds/{$guildId}/members/{$uid}", "GET", '');
    $data = json_decode($r, true);
    return is_array($data) ? $data : null;
}

/** 获取频道成员列表（分页游标 after） */
function 频道成员列表($guildId, $limit = 100, $after = '') {
    if ($guildId === '') return [];
    $qs = http_build_query(['limit' => (int)$limit, 'after' => $after]);
    $r = BOTAPI("/guilds/{$guildId}/members?{$qs}", "GET", '');
    $data = json_decode($r, true);
    return is_array($data) ? $data : [];
}

/** 踢出频道成员（可拉黑、可撤回历史消息，天数支持 3/7/15/30/-1） */
function 踢出频道($guildId, $uid, $拉黑 = false, $撤回天数 = 0) {
    if ($guildId === '' || $uid === '') return null;
    $body = [];
    if ($拉黑) $body['add_blacklist'] = true;
    if ($撤回天数 != 0) $body['delete_history_msg_days'] = (int)$撤回天数;
    $r = BOTAPI("/guilds/{$guildId}/members/{$uid}", "DELETE", json_encode($body));
    return json_decode($r, true) ?: null;
}

/* ------------------------------------------------------------
 * 英文 API（推荐新插件使用）
 * --------------------------------------------------------- */
function qq_send_text($scene, $target, $content, $opts = []) {
    $body = ['content' => (string)$content, 'msg_type' => 0];
    if (!empty($opts['reply_to'])) $body['message_reference'] = ['message_id' => $opts['reply_to']];
    return 云雀API($scene, $target, $body, !empty($opts['active']));
}

function qq_send_image($scene, $target, $image, $content = '', $opts = []) {
    $info = 上传富媒体($scene, $target, '图片', $image, $opts['name'] ?? null);
    if (富媒体错误($info) !== '') return json_encode(['code' => -1, 'message' => 富媒体错误($info)], JSON_UNESCAPED_UNICODE);
    return 云雀媒体($info['file_info'] ?? '', $scene, $target, $content, !empty($opts['active']));
}

function qq_send_media($scene, $target, $type, $data, $opts = []) {
    $info = 上传富媒体($scene, $target, $type, $data, $opts['name'] ?? null);
    if (富媒体错误($info) !== '') return json_encode(['code' => -1, 'message' => 富媒体错误($info)], JSON_UNESCAPED_UNICODE);
    return 云雀媒体($info['file_info'] ?? '', $scene, $target, $opts['content'] ?? '', !empty($opts['active']));
}

function qq_send_markdown($scene, $target, $content, $opts = []) {
    $body = ['content' => '', 'msg_type' => 2, 'markdown' => ['content' => $content]];
    if (!empty($opts['keyboard_id'])) $body['keyboard'] = ['id' => $opts['keyboard_id']];
    if (!empty($opts['keyboard'])) $body['keyboard'] = ['content' => ['rows' => $opts['keyboard']]];
    return 云雀API($scene, $target, $body, !empty($opts['active']));
}

function qq_send_ark($scene, $target, $templateId, $kv, $opts = []) {
    $body = ['msg_type' => 3, 'ark' => ['template_id' => $templateId, 'kv' => $kv]];
    return 云雀API($scene, $target, $body, !empty($opts['active']));
}

function qq_active_text($groupOpenid, $content) { return 主动文字($groupOpenid, $content, '群聊'); }
function qq_active_image($groupOpenid, $image, $content = '') { return 主动图片($groupOpenid, $image, $content, '群聊'); }
function qq_active_private($openid, $content) { return 主动私聊($openid, $content); }

function qq_role($uid = null) { return 群身份($uid); }
function qq_is_owner($uid = null) { return 群主($uid); }
function qq_is_admin($uid = null) { return 管理员($uid); }
function qq_is_member($uid = null) { return 群员($uid); }
function qq_is_bot($uid = null) { return 是机器人($uid); }
function qq_member_info($group, $uid) { return 群成员($uid, $group); }
