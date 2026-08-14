<?php
if (!in_array(消息来源, ["群聊", "私聊", "互动"])) {
    return;
}

// ========== 获取当前操作用户ID ==========
$uid = 用户;
if (消息来源 === "互动") {
    $uid = raw["d"]["group_member_openid"] ?? "";
}

// 预先提取按钮回调数据（互动事件）
$btnData = "";
if (消息来源 === "互动") {
    $btnData = raw["d"]["data"]["resolved"]["button_data"] ?? (raw["d"]["data"]["data"] ?? "");
}

// ========== 频率限制（5分钟冷却） ==========
$isYiyan = false;
if (preg_match('/一言/u', 消息) && in_array(消息来源, ["群聊", "私聊"])) {
    $isYiyan = true;
}
if ($btnData === "demo:click") {
    $isYiyan = true;
}

if ($isYiyan && !empty($uid)) {
    $lastTime = 读("一言冷却", $uid, 0);
    if (time() - $lastTime < 300) {
        return; // 冷却中，静默返回（无任何提示）
    }
    写("一言冷却", $uid, time());
}

// ========== 发送“一言”命令（群聊/私聊） ==========
if (preg_match('/一言/u', 消息)) {
    $res = curl("https://api.oddfar.com/yl/q.php?c=1003&encode=text", "GET", [], "");
    $content = str_replace('<br>', "\r\n", trim($res));

    if (!empty($content)) {
        $randParam = "?nocache=" . time() . mt_rand(1000, 9999);
        $imageUrl = "https://api.elaina.cat/random/pc" . $randParam;
        $md = "##每日一言\n";
        $md .= "![封面 #800px #400px]($imageUrl)\n\n";
        $md .= $content;

        $rows = [[
            "buttons" => [[
                "id" => "demo_btn",
                "render_data" => [
                    "label" => "再来一句",
                    "visited_label" => "已点击",
                    "style" => 1
                ],
                "action" => [
                    "type" => 1,
                    "permission" => ["type" => 2],
                    "data" => "demo:click",
                    "at_bot_show_channel_list" => true,
                    "unsupport_tips" => "当前客户端不支持"
                ]
            ]]
        ]];
        原生按钮($md, $rows);
    } else {
        文字("❌ 一言接口暂时不可用，请稍后再试");
        wlog("一言接口异常：" . $res);
    }
    return;
}

// ========== 按钮回调“再来一句” ==========
if ($btnData === "demo:click") {
    $res = curl("https://api.oddfar.com/yl/q.php?c=1003&encode=text", "GET", [], "");
    $content = str_replace('<br>', "\r\n", trim($res));

    $randParam = "?nocache=" . time() . mt_rand(1000, 9999);
    $imageUrl = "https://api.elaina.cat/random/pc" . $randParam;
    $md = "##每日一言\n";
    $md .= "![封面 #800px #400px]($imageUrl)\n\n";
    $md .= $content;

    $rows = [[
        "buttons" => [[
            "id" => "demo_btn",
            "render_data" => [
                "label" => "再来一句",
                "visited_label" => "已点击",
                "style" => 1
            ],
            "action" => [
                "type" => 1,
                "permission" => ["type" => 2],
                "data" => "demo:click",
                "at_bot_show_channel_list" => true,
                "unsupport_tips" => "当前客户端不支持"
            ]
        ]]
    ]];
    原生按钮($md, $rows);
    return;
}