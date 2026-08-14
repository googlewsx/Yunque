<?php
// 王者荣耀自定义房间插件（基于 wzfj 项目链接生成逻辑移植，仅正式服）
//
// 指令：
//   开房 / 开房菜单 / 王者开房   →  显示开房菜单
//   开房 5v5 / 开房3v3 ...      →  生成对应模式房间（自动套用你的自定义配置）
//   开房模式列表                 →  查看全部可开模式
//   房间配置                     →  自定义配置面板（按钮循环调节）
//   调节等级/法术/物理/冷却/金币/移速 [档位] → 无参数循环切档，带数字直接设档（如 调节等级 6）
//   重置配置                     →  恢复默认配置
//   返回房间                     →  退回房间已创建界面（按上次模式重开）
//   房间二维码                   →  获取上一个房间的二维码
//
// 进房方式：全部走腾讯官方 H5 启动接口（h5.nes.smoba.qq.com），
//          加入蓝方 / 加入红方 / 退出房间 均为跳转按钮，无需自建网页

if (!in_array(消息来源, ["群聊", "私聊", "互动"])) {
    return;
}

// ================== 配置区 ==================

// 正式服 scheme（同 wzfj js/config/constants.js URL_SCHEMES.zsf）
$wzkf_scheme = "tencentmsdk1104466820://";

// 腾讯官方 H5 启动接口（同 wzfj linkBuilder.js QQ 内打开的替换地址）
$wzkf_launch = "https://h5.nes.smoba.qq.com/pvpesport.web.user/#/launch-game-mp-qq?gamedata=SmobaLaunch_";

// 可开房地图数据（同 wzfj js/data/mapData.js webCreateableMapData）
// 格式: 模式名 => [地图ID, 模式类型, 队伍人数, 是否仅开房]
$wzkf_maps = [
    "多人训练营"      => [20047, 1, 4,  true],
    "5v5"           => [20011, 1, 10, false],
    "5v5征召0ban位"  => [20910, 1, 10, false],
    "5v5征召1ban位"  => [20911, 1, 10, false],
    "5v5征召2ban位"  => [20912, 1, 10, false],
    "5v5征召3ban位"  => [20913, 1, 10, false],
    "5v5征召4ban位"  => [20111, 1, 10, false],
    "5v5随机征召"    => [20414, 1, 10, false],
    "3v3"           => [20002, 1, 6,  false],
    "2v2"           => [20014, 1, 4,  false],
    "1v1"           => [20001, 1, 2,  false],
];

// 常用别名 => 标准模式名
$wzkf_alias = [
    "训练营"   => "多人训练营",
    "征召"     => "5v5征召4ban位",
    "4ban"    => "5v5征召4ban位",
    "随机征召" => "5v5随机征召",
    "solo"    => "1v1",
    "单挑"     => "1v1",
];

// ================== 工具函数 ==================

if (!function_exists('wzkf_属性表')) {
    /**
     * 英雄属性表（同 wzfj js/utils/config/constants.js HERO_ATTRIBUTES）
     * ids 为蓝/红方 5 个位置的游戏内配置ID，cd 特殊为每位置 2 个ID
     */
    function wzkf_属性表() {
        return [
            "exp" => [
                "label" => "初始等级", "icon" => "⭐", "cmd" => "调节等级",
                "options" => ["1级", "4级", "5级", "8级", "10级", "12级", "15级"],
                "blue" => [0, 51, 56, 61, 66], "red" => [28, 71, 76, 81, 86]
            ],
            "magic" => [
                "label" => "法术加成", "icon" => "🔮", "cmd" => "调节法术",
                "options" => ["无加成", "加10%", "加25%", "加50%", "加75%", "加100%"],
                "blue" => [1, 52, 57, 62, 67], "red" => [29, 72, 77, 82, 87]
            ],
            "physical" => [
                "label" => "物理加成", "icon" => "⚔️", "cmd" => "调节物理",
                "options" => ["无加成", "加10%", "加25%", "加50%", "加75%", "加100%"],
                "blue" => [2, 53, 58, 63, 68], "red" => [30, 73, 78, 83, 88]
            ],
            "cd" => [
                "label" => "冷却缩减", "icon" => "⏱️", "cmd" => "调节冷却",
                "options" => ["无加成", "减25%", "减40%", "减80%", "减99%"],
                "blue" => [3, 54, 59, 64, 69, 21, 91, 92, 93, 94],
                "red"  => [31, 74, 79, 84, 89, 47, 95, 96, 97, 98]
            ],
            "gold" => [
                "label" => "初始金币", "icon" => "💰", "cmd" => "调节金币",
                "options" => ["无加成", "1000", "2000", "5000", "12000"],
                "blue" => [4, 55, 60, 65, 70], "red" => [32, 75, 80, 85, 90]
            ],
            "speed" => [
                "label" => "移动速度", "icon" => "👟", "cmd" => "调节移速",
                "options" => ["无加成", "加10%", "加20%", "加30%"],
                "blue" => [106, 107, 108, 109, 110], "red" => [111, 112, 113, 114, 115]
            ]
        ];
    }

    function wzkf_读配置() {
    $config = 读("王者开房/config", 用户, null);
    // 如果从未写入过配置，或写入的是空数组，则返回默认拉满配置
    if ($config === null || !is_array($config) || empty($config)) {
        $default = [];
        foreach (wzkf_属性表() as $key => $attr) {
            // 取最大档位索引（options 数组最后一个元素的下标）
            $default[$key] = count($attr["options"]) - 1;
        }
        return $default;
    }
    return $config;
}

    /**
     * 配置是否有生效项
     */
    function wzkf_配置有效($config) {
        foreach ($config as $v) {
            if (intval($v) > 0) return true;
        }
        return false;
    }

    /**
     * 配置转 customDefineItems（同 wzfj converter.js configToCustomItems 全局模式）
     * 红蓝双方所有位置统一生效
     */
    function wzkf_构建自定义项($config, $playerCount) {
        $items = [];
        foreach (wzkf_属性表() as $key => $attr) {
            $val = intval($config[$key] ?? 0);
            if ($val <= 0) continue;
            for ($pos = 0; $pos < $playerCount; $pos++) {
                foreach (["blue", "red"] as $side) {
                    $ids = $attr[$side];
                    $items[] = $ids[$pos] . ":" . $val;
                    if ($key == "cd") {
                        $items[] = $ids[$pos + 5] . ":" . $val;
                    }
                }
            }
        }
        return $items;
    }

    /**
     * 生成房间数据（同 wzfj linkBuilder.js buildRoomOnlyLink / generateGameLink）
     */
    function wzkf_生成房间数据($mapInfo, $config) {
        // 18 位随机房间ID（对应 JS Math.round(Math.random()*1e18)）
        $roomId = (string)mt_rand(100000000, 999999999) . (string)mt_rand(100000000, 999999999);

        $roomData = [
            "createType"   => "2",
            "mapID"        => (string)$mapInfo[0],
            "mapType"      => (string)$mapInfo[1],
            "teamerNum"    => (string)$mapInfo[2],
            "ullRoomid"    => $roomId,
            "ullExternUid" => $roomId,
            "roomName"     => "1",
            "platType"     => "4",
            "campid"       => "1",
            "AddPos"       => "0",
            "AddType"      => "2"
        ];

        // 仅开房模式不套用自定义配置
        if (!$mapInfo[3] && wzkf_配置有效($config)) {
            $roomData["platType"] = "2";
            $roomData["banHerosCamp1"] = [];
            $roomData["banHerosCamp2"] = [];
            $roomData["customDefineItems"] = wzkf_构建自定义项($config, intval($mapInfo[2] / 2));
        }

        return $roomData;
    }

    /**
     * 房间数据转腾讯 H5 启动链接
     */
    function wzkf_启动链接($launch, $roomData) {
        $json = json_encode($roomData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $launch . base64_encode($json);
    }

    /**
     * 指定阵营加入链接（同 wzfj openGame.js setCampUrl）
     */
    function wzkf_阵营链接($launch, $roomData, $campId) {
        $camp = $roomData;
        unset($camp["AddPos"]);
        $camp["AddType"] = "0";
        $camp["campid"] = (string)$campId;
        return wzkf_启动链接($launch, $camp);
    }

    /**
     * 检查模式是否可用（同 wzfj mapModeChecker.js，仅正式服）
     */
    function wzkf_模式检查($mapName) {
        if (strpos($mapName, "觉醒") !== false) {
            return "当前地图模式暂时未开启，请重新选择";
        }
        if (strpos($mapName, "克隆") !== false || strpos($mapName, "契约") !== false) {
            if (!in_array(date("w"), ["5", "6", "0"])) {
                return "当前地图模式只在星期五到星期天开放，请重新选择";
            }
        }
        return "";
    }

    /**
     * type=2 直发指令按钮（同 菜单.php 风格）
     */
    function wzkf_按钮($id, $label, $cmd) {
        return [
            "id" => $id,
            "render_data" => ["label" => $label, "visited_label" => $label, "style" => 1],
            "action" => [
                "type" => 2,
                "permission" => ["type" => 2],
                "data" => $cmd,
                "unsupport_tips" => "当前QQ版本不支持"
            ]
        ];
    }

    /**
     * type=0 跳转链接按钮（用于加入阵营 / 退出房间）
     */
    function wzkf_链接按钮($id, $label, $url) {
        return [
            "id" => $id,
            "render_data" => ["label" => $label, "visited_label" => $label, "style" => 1],
            "action" => [
                "type" => 0,
                "permission" => ["type" => 2],
                "data" => $url,
                "unsupport_tips" => "当前QQ版本不支持"
            ]
        ];
    }

    /**
     * 发送配置面板
     */
    function wzkf_发送配置面板($config, $headTip = "") {
        $md = "##⚙️ 自定义房间配置\n\n";
        if ($headTip != "") {
            $md .= "> " . $headTip . "\n\n";
        }
        $md .= "> 点击按钮循环调节，对红蓝双方全体生效\n";
        $md .= "> 也可发送「指令 数字」直接设档位，如 调节等级 6\n\n";
        $md .= "***\n";

        foreach (wzkf_属性表() as $key => $attr) {
            $val = intval($config[$key] ?? 0);
            $current = $attr["options"][$val] ?? $attr["options"][0];
            $mark = ($val > 0) ? "✅" : "▫️";
            $md .= $mark . " " . $attr["icon"] . " **" . $attr["label"] . "**：`" . $current . "`\n";
        }

        $md .= "***\n";
        $md .= "配置完成后点击开房即可生效（训练营除外）";

        $attrs = wzkf_属性表();
        $rows = [];
        $rowBtns = [];
        $i = 1;
        // 6 个属性按钮，每行 2 个
        foreach ($attrs as $key => $attr) {
            $val = intval($config[$key] ?? 0);
            $current = $attr["options"][$val] ?? $attr["options"][0];
            $rowBtns[] = wzkf_按钮("cfg" . $i, $attr["icon"] . " " . $attr["label"] . "·" . $current, $attr["cmd"]);
            if (count($rowBtns) == 2) {
                $rows[] = ["buttons" => $rowBtns];
                $rowBtns = [];
            }
            $i++;
        }
        // 操作行
        $rows[] = ["buttons" => [
            wzkf_按钮("cfg7", "♻️ 重置配置", "重置配置"),
            wzkf_按钮("cfg8", "🏰 返回房间", "返回房间")
        ]];
        $rows[] = ["buttons" => [
            wzkf_按钮("cfg9", "📋 全部模式", "开房模式列表"),
            wzkf_按钮("cfg10", "📖 开房菜单", "开房菜单")
        ]];

        原生按钮($md, $rows);
    }

    /**
     * 发送房间已创建面板（开房 / 返回房间 共用）
     */
    function wzkf_发送房间面板($modeName, $mapInfo, $launch, $config) {
        $roomData = wzkf_生成房间数据($mapInfo, $config);
        $blueUrl  = wzkf_阵营链接($launch, $roomData, 1);
        $redUrl   = wzkf_阵营链接($launch, $roomData, 2);
        $exitUrl  = $launch . "AAAA";

        // 保存蓝方链接与模式，供二维码 / 返回房间使用
        写("王者开房/last", 用户, $blueUrl);
        写("王者开房/lastmode", 用户, $modeName);

        wlog("王者开房：" . 用户 . " " . $modeName);

        $useConfig = isset($roomData["customDefineItems"]);

        $md = "##🏰 房间已创建\n\n";
        $md .= "<@" . 用户 . "> 点击下方按钮加入阵营\n\n";
        $md .= "***\n";
        $md .= "🗺️ 模式：**" . $modeName . "**\n";
        $md .= "👥 人数：**" . $mapInfo[2] . "人**" . ($mapInfo[3] ? "（仅开房模式）" : "") . "\n";

        // 展示生效的自定义配置
        if ($useConfig) {
            $md .= "***\n⚙️ 自定义配置：\n";
            foreach (wzkf_属性表() as $key => $attr) {
                $val = intval($config[$key] ?? 0);
                if ($val > 0) {
                    $md .= "> " . $attr["icon"] . " " . $attr["label"] . "：**" . $attr["options"][$val] . "**\n";
                }
            }
        }
        $md .= "***\n";
        $md .= "按钮无反应可发送 房间二维码 扫码进入";

        $rows = [
            // 第1行：加入蓝方 + 加入红方（跳转按钮）
            ["buttons" => [
                wzkf_链接按钮("kfr1", "🔵 加入蓝方", $blueUrl),
                wzkf_链接按钮("kfr2", "🔴 加入红方", $redUrl)
            ]],
            // 第2行：退出房间（跳转按钮）+ 二维码
            ["buttons" => [
                wzkf_链接按钮("kfr3", "🚪 退出房间", $exitUrl),
                wzkf_按钮("kfr4", "📱 房间二维码", "房间二维码")
            ]],
            // 第3行：再开一间 + 房间配置
            ["buttons" => [
                wzkf_按钮("kfr5", "🔄 再开一间", "开房 " . $modeName),
                wzkf_按钮("kfr6", "⚙️ 房间配置", "房间配置")
            ]]
        ];

        原生按钮($md, $rows);
    }
}

// 框架已自动处理开头艾特机器人，这里只需去掉多余的 /
$wzkf_msg = trim(消息, "/ ");

// ================== 1. 开房菜单 ==================
if (preg_match('/^(开房|开房菜单|王者开房|开房帮助)$/u', $wzkf_msg)) {

    // 防缓存随机封面（同 菜单.php）
    $randParam = "?nocache=" . time() . mt_rand(1000, 9999);
    $imageUrl = "https://api.elaina.cat/random/pc" . $randParam;

    $config = wzkf_读配置();
    $cfgTip = wzkf_配置有效($config) ? "已启用自定义配置 ✅" : "未设置自定义配置";

    $md = "##🏰 王者自定义房间";
    $md .= "![封面 #800px #400px]($imageUrl)\n\n";
    $md .= "> " . $cfgTip . "\n\n";
   

    $rows = [
        // 第1行：5v5 + 3v3
        ["buttons" => [
            wzkf_按钮("kf1", "⚔️ 5v5 ", "开房 5v5"),
            wzkf_按钮("kf2", "🗡️ 3v3 ", "开房 3v3")
        ]],
        // 第2行：2v2 + 1v1
        ["buttons" => [
            wzkf_按钮("kf3", "🛡️ 2v2 ", "开房 2v2"),
            wzkf_按钮("kf4", "🥊 1v1 ", "开房 1v1")
        ]],
        // 第3行：征召 + 训练营
        ["buttons" => [
            wzkf_按钮("kf5", "🎯 征召4ban位", "开房 5v5征召4ban位"),
            wzkf_按钮("kf6", "🏕️ 多人训练营", "开房 多人训练营")
        ]],
        // 第4行：房间配置 + 全部模式
        ["buttons" => [
            wzkf_按钮("kf7", "⚙️ 房间配置", "房间配置"),
            wzkf_按钮("kf8", "📋 全部模式", "开房模式列表")
        ]]
    ];

    原生按钮($md, $rows);
    return;
}

// ================== 2. 模式列表 ==================
if ($wzkf_msg == "开房模式列表" || $wzkf_msg == "开房列表") {
    $md = "##📋 可开房模式\n\n> 点击模式名一键开房\n\n";
    foreach ($wzkf_maps as $name => $info) {
        $tip = $info[3] ? "（仅开房）" : "（" . ($info[2] / 2) . "人一队）";
        $md .= "[▸ " . $name . "](mqqapi://aio/inlinecmd?enter=true&command=" . rawurlencode("开房 " . $name) . ") " . $tip . "\n";
    }
    原生MD($md);
    return;
}

// ================== 3. 配置面板 ==================
if ($wzkf_msg == "房间配置" || $wzkf_msg == "开房配置") {
    wzkf_发送配置面板(wzkf_读配置());
    return;
}

// ================== 4. 调节配置项（支持数字参数直接设档位） ==================
foreach (wzkf_属性表() as $wzkf_key => $wzkf_attr) {
    if (preg_match('/^' . preg_quote($wzkf_attr["cmd"], '/') . '\s*(\d+)?$/u', $wzkf_msg, $wzkf_cm)) {
        $config = wzkf_读配置();
        $count = count($wzkf_attr["options"]);

        if (isset($wzkf_cm[1]) && $wzkf_cm[1] !== "") {
            // 带数字参数：直接设置档位
            $val = intval($wzkf_cm[1]);
            if ($val >= $count) {
                $tips = [];
                foreach ($wzkf_attr["options"] as $idx => $opt) {
                    $tips[] = $idx . "=" . $opt;
                }
                文字("❌ " . $wzkf_attr["label"] . " 档位范围：" . implode("，", $tips));
                return;
            }
        } else {
            // 无参数：循环切换到下一档
            $val = (intval($config[$wzkf_key] ?? 0) + 1) % $count;
        }

        $config[$wzkf_key] = $val;
        写("王者开房/config", 用户, $config);

        $current = $wzkf_attr["options"][$val];
        wzkf_发送配置面板($config, $wzkf_attr["icon"] . " " . $wzkf_attr["label"] . " 已调至 **" . $current . "**");
        return;
    }
}

// ================== 5. 重置配置 ==================
if ($wzkf_msg == "重置配置") {
    写("王者开房/config", 用户, []);
    wzkf_发送配置面板([], "♻️ 已恢复默认配置");
    return;
}

// ================== 6. 房间二维码 ==================
if ($wzkf_msg == "房间二维码") {
    $lastUrl = 读("王者开房/last", 用户, "");
    if ($lastUrl == "") {
        文字("你还没有开过房间，请先发送 开房 5v5 等指令开房");
        return;
    }
    $png = 二维码($lastUrl);
    图片($png, "扫码进入房间（蓝方）");
    return;
}

// ================== 7. 返回房间已创建界面 ==================
if ($wzkf_msg == "返回房间") {
    $lastMode = 读("王者开房/lastmode", 用户, "");
    if ($lastMode == "" || !isset($wzkf_maps[$lastMode])) {
        原生MD("你还没有开过房间\n\n[前往开房菜单](mqqapi://aio/inlinecmd?enter=true&command=开房菜单)");
        return;
    }
    wzkf_发送房间面板($lastMode, $wzkf_maps[$lastMode], $wzkf_launch, wzkf_读配置());
    return;
}

// ================== 8. 开房 <模式> ==================
if (preg_match('/^开房\s*(.+)$/u', $wzkf_msg, $wzkf_m)) {
    // 归一化：大写 V 转小写，去空格
    $modeName = str_replace(["V", " "], ["v", ""], trim($wzkf_m[1]));

    // 别名转换
    if (isset($wzkf_alias[$modeName])) {
        $modeName = $wzkf_alias[$modeName];
    }

    if (!isset($wzkf_maps[$modeName])) {
        原生MD("❌ 未找到模式「" . $modeName . "」\n\n[查看全部模式](mqqapi://aio/inlinecmd?enter=true&command=开房模式列表)");
        return;
    }

    // 模式开放检查
    $checkTip = wzkf_模式检查($modeName);
    if ($checkTip != "") {
        文字("❌ " . $checkTip);
        return;
    }

    $mapInfo = $wzkf_maps[$modeName];

    // 生成房间并发送房间已创建面板（全部走腾讯官方 H5 启动接口）
    wzkf_发送房间面板($modeName, $mapInfo, $wzkf_launch, wzkf_读配置());
    return;
}
