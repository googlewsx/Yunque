<?php
if (消息来源 !== "成员退群") return;

$离开者 = 用户;
$群号 = 来源;

// 获取离开者昵称（调官方接口，失败则用通用称呼）
$昵称 = 群昵称($离开者, $群号);
if (!$昵称) $昵称 = "群友";

// 获取随机文案
$randomText = curl("https://api.oddfar.com/yl/q.php?c=2004&encode=text", "GET", [], "");
$randomText = trim($randomText);
if (empty($randomText)) {
    $randomText = "远赴人间惊鸿宴，一睹人间盛世颜";
}

// 构建 Markdown 内容
$md = "![头像 #60px #60px](" . 头像($离开者) . ") **" . $昵称 . "** 离开了我们！\n\n> ";
$md .= $randomText . "\n\n";
主动MD($群号, $md);
return;
