<?php
if (消息来源 !== "成员入群") return;

$新人 = 用户;
$群号 = 来源;

// 获取新人昵称（调官方接口，失败则用@替代）
$昵称 = 群昵称($新人, $群号);
if (!$昵称) $昵称 = "新成员";

// 获取随机欢迎语（外部API，失败则用默认）
$randomText = curl("https://api.oddfar.com/yl/q.php?c=2004&encode=text", "GET", [], "");
$randomText = trim($randomText);
if (empty($randomText)) {
    $randomText = "远赴人间惊鸿宴，一睹人间盛世颜";
}

// 构建 Markdown 消息
$md = "";
$md .= "![头像 #60px #60px](" . 头像($新人) . ")<@" . $新人 . ">\n\n> ";
$md .= $randomText . "\n\n";
$md .= "欢迎 **" . $昵称 . "** 加入了我们！\n\n";

// 按钮布局（点击后触发"菜单"命令）
$rows = [[
    "buttons" => [[
        "id" => "welcome_btn_menu",
        "render_data" => [
            "label" => "菜单",
            "visited_label" => "菜单",
            "style" => 1
        ],
        "action" => [
            "type" => 2,
            "permission" => ["type" => 2],
            "data" => "菜单",
            "at_bot_show_channel_list" => true,
            "unsupport_tips" => "当前客户端不支持"
        ]
    ]]
]];

原生按钮($md, $rows);
return;
