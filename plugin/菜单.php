<?php
if (!in_array(消息来源, ["群聊", "私聊", "互动"])) {
    return;
}

// 触发：菜单/帮助/功能（框架已自动处理开头艾特机器人）
if (preg_match('/^(?:\/\s*)?(菜单|帮助|功能)/u', 消息)) {
    // ========== 1. 防缓存随机图片 ==========
    $randParam = "?nocache=" . time() . mt_rand(1000, 9999);
    $imageUrl = "https://api.elaina.cat/random/pc" . $randParam;

    // ========== 2. 获取随机语录 + 容错兜底 ==========
    $randomText = curl("https://api.oddfar.com/yl/q.php?c=2004&encode=text", "GET", [], "");
    $randomText = trim($randomText);
    if (empty($randomText)) {
        $randomText = "远赴人间惊鸿宴，一睹人间盛世颜";
    }

    // ========== 3. 移除HTML标签，纯Markdown格式（保证解析正常） ==========
    $md = "##菜单";
    $md .= "![封面 #800px #400px]($imageUrl)\n\n> ";
    $md .= $randomText . "\n\n";

    // ========== 4. QQ原生按钮 保持原样 type=2 ==========
    $rows = [
        // 第1行：王者功能 + 视频菜单
        [
            "buttons" => [
                [
                    "id" => "btn1",
                    "render_data" => ["label" => "📄 全局模式", "visited_label" => "📄 全局模式", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "全局 把我换成群号",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ],
                [
                    "id" => "btn2",
                    "render_data" => ["label" => "🔒 群管菜单", "visited_label" => "🔒 群管菜单", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "群管菜单",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ]
            ]
        ],
        // 第2行：文案菜单 + 实用工具
        [
            "buttons" => [
                [
                    "id" => "btn3",
                    "render_data" => ["label" => "📚 随机语录", "visited_label" => "📚 随机语录", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "一言",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ],
                [
                    "id" => "btn4",
                    "render_data" => ["label" => "🥇 王者战力", "visited_label" => "🥇 王者战力", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "安卓/苹果 QQ/微信 英雄名称",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ]
            ]
        ],
        // 第3行：娱乐菜单 + 签到功能
        [
            "buttons" => [
                [
                    "id" => "btn5",
                    "render_data" => ["label" => "🎲 掷骰子", "visited_label" => "🎲 掷骰子", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "骰子",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ],
                [
                    "id" => "btn6",
                    "render_data" => ["label" => "⚔️ 王者自定义房间", "visited_label" => "⚔️ 王者自定义房间", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "开房菜单",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ]
            ]
        ],
        // 第4行：音乐系统 + 视频解析
        [
            "buttons" => [
                [
                    "id" => "btn7",
                    "render_data" => ["label" => "🎵 音乐系统", "visited_label" => "🎵 音乐系统", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "音乐系统",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ],
                [
                    "id" => "btn8",
                    "render_data" => ["label" => "🎬 视频解析", "visited_label" => "🎬 视频解析", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "请输入抖音或者B站的视频链接",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ]
            ]
        ]
    ];

    // 发送原生按钮消息
    原生按钮($md, $rows);
    return;
}
