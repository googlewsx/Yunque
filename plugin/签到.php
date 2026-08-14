<?php
if (!in_array(消息来源, ["群聊", "私聊", "互动"])) {
    return;
}

$todayDate = date("Y-m-d");
$yesterdayDate = date("Y-m-d", strtotime("-1 day"));

// 统一封装签到核心逻辑（消除重复代码）
function checkSignIn($todayDate, $yesterdayDate){
    $lastSign = 读("sign", 用户, "");
    $continuous = 读("continuous", 用户, 0);
    $points = 读("points", 用户, 0);

    // 今日已签到
    if ($lastSign === $todayDate) {
        return [
            "status" => "signed",
            "continuous" => $continuous,
            "points" => $points
        ];
    }

    // 修复断签BUG：昨天签到→续签，否则断签重置
    if ($lastSign === $yesterdayDate) {
        $continuous++;
    } else {
        $continuous = 1;
    }

    // 积分规则
    $basePoints = 10;
    $bonus = $continuous === 15 ? 50 : ($continuous === 7 ? 20 : ($continuous >= 3 ? 1 : 0));
    $totalPoints = $basePoints + $bonus;
    $newPoints = $points + $totalPoints;

    // 写入数据
    写("sign", 用户, $todayDate);
    写("continuous", 用户, $continuous);
    写("points", 用户, $newPoints);

    return [
        "status" => "success",
        "continuous" => $continuous,
        "points" => $newPoints,
        "bonus" => $bonus,
        "total" => $totalPoints
    ];
}

// 统一生成按钮卡片
function buildSignCard($data, $todayDate){
    // 已签到直接不发送任何提示
    if($data["status"] === "signed"){
        return;
    }

    $md = "";
    $md .= "![头像 #640px #640px](" . 头像(用户) . ")\n\n";
    $md .= "<@" . 用户 . ">\n\n";

    $md .= "✅ 签到成功\n\n";
    $md .= "📅 签到日期：" . $todayDate . "\n";
    $md .= "🔥 连续签到：" . $data["continuous"] . " 天\n";
    $md .= "🎁 本次积分：+" . $data["total"] . "\n";
    if ($data["bonus"] > 0) {
        $md .= "🎁 连续奖励：+" . $data["bonus"] . "\n";
    }
    $md .= "💰 总积分：" . $data["points"] . " 积分\n\n";
    $btnLabel = "去签到";

    $rows = [[
        "buttons" => [[
            "id" => "sign_btn_001",
            "render_data" => [
                "label" => $btnLabel,
                "visited_label" => "已签到",
                "style" => 1
            ],
            "action" => [
                "type" => 1,
                "permission" => ["type" => 2],
                "data" => "cd:click",
                "at_bot_show_channel_list" => true,
                "unsupport_tips" => "当前客户端不支持"
            ]
        ]]
    ]];

    原生按钮($md, $rows);
}

// 文字指令：签到（正常可用）
if (消息来源 != "互动" && preg_match('/签到/u', 消息)) {
    $res = checkSignIn($todayDate, $yesterdayDate);
    buildSignCard($res, $todayDate);
    return;
}


// ========== 【新增】积分抽奖功能（所有人可用） ==========
if (preg_match('/抽奖\s*(\d+)$/u', 消息, $match)) {
    $cost = (int)$match[1];
    if ($cost < 2) {
        原生MD("# ❌ 抽奖积分必须大于1");
        return;
    }

    $user_points = 读("points", 用户, 0);
    if ($user_points < $cost) {
        原生MD("# ❌ 积分不足\n当前积分：{$user_points}\n本次抽奖消耗：{$cost}");
        return;
    }

    // 扣除抽奖积分
    $user_points -= $cost;
    写("points", 用户, $user_points);

    // ========== 抽奖档位配置（可自行修改） ==========
    // 格式：[概率百分比, 倍率, 描述]
    $prizes = [
        [1,   10, "超级大奖（10倍）"],
        [10,  3,  "一等奖（3倍）"],
        [20,  2,  "二等奖（2倍）"],
        [25,  1,  "三等奖（1倍）"],
        [30,  0.5,"安慰奖（0.5倍）"],
        [14,   0,  "谢谢参与（0倍）"]
    ];

    // 生成随机数（1-100）
    $rand = mt_rand(1, 100);
    $current = 0;
    $win_multi = 0;
    $win_desc = "";

    foreach ($prizes as $prize) {
        list($percent, $multi, $desc) = $prize;
        $current += $percent;
        if ($rand <= $current) {
            $win_multi = $multi;
            $win_desc = $desc;
            break;
        }
    }

    // 计算中奖积分
    $win_points = $cost * $win_multi;
    $final_points = $user_points + $win_points;
    写("points", 用户, $final_points);

    // 构建消息
    $md = "";
    $md .= "#![头像 #640px #640px](" . 头像(用户) . ")<@" . 用户 . ">\n";
    $md .= "消耗积分：**{$cost}**\n";
    $md .= "中奖档位：**{$win_desc}**\n";
    $md .= "获得积分：**{$win_points}**\n";
    $md .= "当前剩余积分：**{$final_points}**\n";
    $rows = [[
        "buttons" => [[
            "id" => "btn1",
            "render_data" => ["label" => "再来一次", "visited_label" => "再来一次", "style" => 1],
            "action" => [
                "type" => 2,
                "permission" => ["type" => 2],
                "data" => "/抽奖2",
                "unsupport_tips" => "当前QQ版本不支持"
            ]
        ]]
    ]];
    原生按钮($md, $rows);
    return;
}


// 文字指令：积分查询
if (消息来源 != "互动" && (消息 == "积分" || 消息 == "我的积分")) {
    $points = 读("points", 用户, 0);
    $continuous = 读("continuous", 用户, 0);

    $md = "";
    $md .= "![头像 #640px #640px](" . 头像(用户) . ")\n\n";
    $md .= "<@" . 用户 . ">\n\n";
    $md .= "📊 个人积分资料\n\n";
    $md .= "🔥 连续签到：" . $continuous . " 天\n";
    $md .= "💰 当前积分：" . $points . " 积分\n\n";

    $rows = [[
        "buttons" => [[
            "id" => "sign_btn_001",
            "render_data" => [
                "label" => "去签到",
                "visited_label" => "已签到",
                "style" => 1
            ],
            "action" => [
                "type" => 1,
                "permission" => ["type" => 2],
                "data" => "cd:click",
                "at_bot_show_channel_list" => true,
                "unsupport_tips" => "当前客户端不支持"
            ]
        ]]
    ]];
    原生按钮($md, $rows);
    return;
}
if (消息来源 === "互动") {
    $btnData = raw["d"]["data"]["resolved"]["button_data"] ?? (raw["d"]["data"]["data"] ?? "");
     if ($btnData === "cd:click") {
         $res = checkSignIn($todayDate, $yesterdayDate);
         buildSignCard($res, $todayDate);
         $interId = raw["d"]["id"] ?? "";
         if ($interId !== "") BOTAPI("/v2/interactions/".$interId,"PUT",json_encode(["code"=>0],JSON_UNESCAPED_UNICODE));
         exit; 
     }
 }
?>
