<?php
// 全局加群授权链接生成插件
if (!in_array(消息来源, ["群聊", "私聊"])) {
    return;
}

// 匹配指令：全局加群号+群号（支持有无空格）
$rule = '/^全局\s*(\d+)$/u';
if (preg_match($rule, 消息, $res)) {
    $qun = $res[1];
    
    // 固定官方链接模板（botUin / botUid 需替换为你的机器人实际值）
    $botUin = "你的botUin";
    $botUid = "你的botUid";
    $link = 'https://club.vip.qq.com/transfer?open_kuikly_info=%7B%22page_name%22%3A%20%22ai_group_service_agreement_pop_page%22%2C%22groupCode%22%3A'
    .$qun.
    '%2C%22botUin%22%3A'.$botUin.'%2C%22botUid%22%3A%22'.$botUid.'%22%2C%22screen%22%3A1%7D';



    $imageUrl = "https://download.nature.qq.com/SnsShare/Imhfgcfhjag513.jpg";

    // ========== 2. 获取随机语录 + 容错兜底 ==========
      $randomText = curl("https://api.oddfar.com/yl/q.php?c=2004&encode=text", "GET", [], "");
    $randomText = trim($randomText);
    if (empty($randomText)) {
        $randomText = "远赴人间惊鸿宴，一睹人间盛世颜";
    }

    // ========== 3. 移除HTML标签，纯Markdown格式（保证解析正常） ==========
    $md = "##全局免@授权";
    $md .= "![封面 #1080px #888px]($imageUrl)\n\n> ";
    $md .= $randomText . "\n\n";
   $rows = [
    [
        "buttons" => [
            [
                "id" => "btn_1778769149420_k6hcj1",
                "render_data" => [
                    "label" => "请群主点击授权",
                    "visited_label" => "已点击",
                    "style" => 1
                ],
                "action" => [
                    "type" => 0,
                    "permission" => [
                        "type" => 2
                    ],
                    "data" => $link,
                    "unsupport_tips" => "请升级QQ版本"
                ]
            ]
        ]
    ]
];

   原生按钮($md, $rows);



    return;
}
