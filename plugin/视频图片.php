<?php
if (!in_array(消息来源, ["群聊", "私聊", "互动"])) {
    return;
}



// ================== 图片数据 ==================
$图片数据 = [
    "腹肌" => ["http://api.yujn.cn/api/fujiimg.php?"],
    "黑丝" => ["http://api.yujn.cn/api/heisi.php"],
    "夕阳" => ["http://api.yujn.cn/api/xiyang.php"],
    "布布" => ["http://api.yujn.cn/api/bubu.php?"],
    "萌宠" => ["http://api.yujn.cn/api/mc.php?"],
    "JK图" => ["https://api.suyanw.cn/api/jk.php"],
    "COS图" => ["http://api.yujn.cn/api/cos.php"],
    "猫羽雫" => ["https://api.suyanw.cn/api/mao.php"],
    "动漫壁纸" => ["http://api.yujn.cn/api/cos.php", "http://api.yujn.cn/api/pcbizi.php", "http://api.yujn.cn/api/ACG.php", "https://api.suyanw.cn/api/ys.php"],
    "朋友圈壁纸" => ["https://api.suyanw.cn/api/pyqbj.php"],
    "小姐姐图片" => ["http://api.yujn.cn/api/ksxjj.php", "http://api.yujn.cn/api/xjjtp.php?", "http://api.yujn.cn/api/jk.php?"]
];

// ================== 视频数据 ==================
$视频数据 = [
    "小姐姐" => ["https://api.yujn.cn/api/zzxjj.php?type=video", "https://api.yujn.cn/api/xjj.php?type=video", "http://api.yujn.cn/api/juhexjj.php?type=video", "http://api.yujn.cn/api/ksxjjsp.php", "https://api-v2.cenguigui.cn/api/mp4/MP4_xiaojiejie.php"],
    "鞠婧祎" => ["http://api.yujn.cn/api/jjy.php?type=video"],
    "章若楠" => ["http://api.yujn.cn/api/zrn.php?type=video"],
    "女大学生" => ["https://api.yujn.cn/api/nvda.php?type=video"],
    "双倍快乐" => ["http://api.yujn.cn/api/sbkl.php?type=video"],
    "你的欲梦" => ["http://api.yujn.cn/api/ndym.php?type=video"],
    "完美身材" => ["http://api.yujn.cn/api/wmsc.php?type=video"],
    "极品狱卒" => ["http://api.yujn.cn/api/jpmt.php?type=video", "http://api.yujn.cn/api/yuzu.php?type=video"],
    "纯情女高" => ["http://api.yujn.cn/api/nvgao.php?type=video"],
    "帅哥视频" => ["http://api.yujn.cn/api/xgg.php?type=video"],
    "黑丝视频" => ["http://api.yujn.cn/api/heisis.php?type=video"],
    "白丝视频" => ["http://api.yujn.cn/api/baisis.php?type=video"],
    "漫展视频" => ["https://api.yujn.cn/api/manzhan.php?type=video"],
    "风景视频" => ["http://api.yujn.cn/api/bianzhuang.php?"],
    "穿搭系列" => ["http://api.yujn.cn/api/chuanda.php?type=video"],
    "舞蹈系列" => ["http://api.yujn.cn/api/shwd.php?type=video", "http://api.yujn.cn/api/rewu.php?type=video"],
    "古风系列" => ["http://api.yujn.cn/api/hanfu.php?type=video"],
    "萌娃系列" => ["http://api.yujn.cn/api/mengwa.php?type=video"],
    "慢摇系列" => ["http://api.yujn.cn/api/manyao.php?type=video"],
    "吊带系列" => ["http://api.yujn.cn/api/diaodai.php?type=video"],
    "清纯系列" => ["http://api.yujn.cn/api/qingchun.php?type=video"],
    "COS系列" => ["http://api.yujn.cn/api/COS.php?type=video"],
    "变装系列" => ["http://api.yujn.cn/api/ksbianzhuang.php?type=video", "http://api.yujn.cn/api/bianzhuang.php?"]
];

// ========== 监听 看xxx 指令（核心触发） ==========
try {
    if (preg_match('/看(.+)$/u', 消息, $m)) {
        $tag = trim($m[1]);
        // 视频
        if (isset($视频数据[$tag])) {
            $list = $视频数据[$tag];
            $url = $list[rand(0, count($list)-1)];
            视频($url);
            exit;
        }
        // 图片
        if (isset($图片数据[$tag])) {
            $list = $图片数据[$tag];
            $url = $list[rand(0, count($list)-1)];
            图片($url);
            exit;
        }
    }
}catch(Exception $e){
    wlog("图文错误：".$e->getMessage());
    文字("加载失败");
}


// ==================== 图片菜单触发 ====================
if (preg_match('/图片菜单/u', 消息)) {
    // 防缓存随机封面图
    $randParam = "?nocache=" . time() . mt_rand(1000, 9999);
    $imageUrl = "https://api.elaina.cat/random/pc" . $randParam;

    // 获取随机语录 + 容错兜底
    $randomText = curl("https://api.oddfar.com/yl/q.php?c=2004&encode=text", "GET", [], "");
    $randomText = trim($randomText);
    if (empty($randomText)) {
        $randomText = "远赴人间惊鸿宴，一睹人间盛世颜";
    }

    // 头部图文
    $md = "图片菜单";
    $md .= "![封面 #800px #400px]($imageUrl)\n\n> ";
    $md .= $randomText . "\n\n";

    // 图片分类原生按钮 完全沿用你格式 type=2
    $rows = [
        // 第1行
        [
            "buttons" => [
                [
                    "id" => "img1",
                    "render_data" => ["label" => "腹肌", "visited_label" => "腹肌", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "看腹肌",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ],
                [
                    "id" => "img2",
                    "render_data" => ["label" => "黑丝", "visited_label" => "黑丝", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "看黑丝",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ]
            ]
        ],
        // 第2行
        [
            "buttons" => [
                [
                    "id" => "img3",
                    "render_data" => ["label" => "JK图", "visited_label" => "JK图", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "看JK图",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ],
                [
                    "id" => "img4",
                    "render_data" => ["label" => "COS图", "visited_label" => "COS图", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "看COS图",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ]
            ]
        ],
        // 第3行
        [
            "buttons" => [
                [
                    "id" => "img5",
                    "render_data" => ["label" => "动漫壁纸", "visited_label" => "动漫壁纸", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "看动漫壁纸",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ],
                [
                    "id" => "img6",
                    "render_data" => ["label" => "小姐姐图片", "visited_label" => "小姐姐图片", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "看小姐姐图片",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ]
            ]
        ],
        // 第4行
        [
            "buttons" => [
                [
                    "id" => "img7",
                    "render_data" => ["label" => "萌宠", "visited_label" => "萌宠", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "看萌宠",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ],
                [
                    "id" => "img8",
                    "render_data" => ["label" => "猫羽雫", "visited_label" => "猫羽雫", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "看猫羽雫",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ]
            ]
        ]
    ];

    原生按钮($md, $rows);
    return;
}

// ==================== 视频菜单触发 ====================
if (preg_match('/视频菜单/u', 消息)) {
    // 防缓存随机封面图
    $randParam = "?nocache=" . time() . mt_rand(1000, 9999);
    $imageUrl = "https://api.elaina.cat/random/pc" . $randParam;

    // 获取随机语录 + 容错兜底
    $randomText = curl("https://api.oddfar.com/yl/q.php?c=2004&encode=text", "GET", [], "");
    $randomText = trim($randomText);
    if (empty($randomText)) {
        $randomText = "远赴人间惊鸿宴，一睹人间盛世颜";
    }

    // 头部图文
    $md = "视频菜单";
    $md .= "![封面 #800px #400px]($imageUrl)\n\n> ";
    $md .= $randomText . "\n\n";

    // 视频分类原生按钮 完全沿用你格式 type=2
    $rows = [
        // 第1行
        [
            "buttons" => [
                [
                    "id" => "vid1",
                    "render_data" => ["label" => "小姐姐", "visited_label" => "小姐姐", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "看小姐姐",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ],
                [
                    "id" => "vid2",
                    "render_data" => ["label" => "纯情女高", "visited_label" => "纯情女高", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "看纯情女高",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ]
            ]
        ],
        // 第2行
        [
            "buttons" => [
                [
                    "id" => "vid3",
                    "render_data" => ["label" => "黑丝视频", "visited_label" => "黑丝视频", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "看黑丝视频",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ],
                [
                    "id" => "vid4",
                    "render_data" => ["label" => "白丝视频", "visited_label" => "白丝视频", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "看白丝视频",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ]
            ]
        ],
        // 第3行
        [
            "buttons" => [
                [
                    "id" => "vid5",
                    "render_data" => ["label" => "舞蹈系列", "visited_label" => "舞蹈系列", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "看舞蹈系列",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ],
                [
                    "id" => "vid6",
                    "render_data" => ["label" => "变装系列", "visited_label" => "变装系列", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "看变装系列",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ]
            ]
        ],
        // 第4行
        [
            "buttons" => [
                [
                    "id" => "vid7",
                    "render_data" => ["label" => "古风系列", "visited_label" => "古风系列", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "看古风系列",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ],
                [
                    "id" => "vid8",
                    "render_data" => ["label" => "漫展视频", "visited_label" => "漫展视频", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "看漫展视频",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ]
            ]
        ]
    ];

    原生按钮($md, $rows);
    return;
}
