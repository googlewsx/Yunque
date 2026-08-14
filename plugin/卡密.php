<?php
/**
 * 卡密系统（积分+签到+抽奖）
 * - 启用/禁用由 main.json 中的 plugin.卡密 作用域控制
 * - 主人权限通过框架 是否主人() 判断
 * - 所有用户均可查看菜单、生成卡密、签到、积分查询、抽奖
 * - 仅主人可加减积分、开启/关闭（已由作用域替代，故删除开关指令）
 */
if (!in_array(消息来源, ["群聊", "互动"])) {
    return;
}

$group_id = 来源;
$user = 用户;

// ---------- 配置 ----------
$cost_per = 100; // 1次 = 100积分（减次数消耗）

// ========== 主人专用：积分加减 ==========
if (是否主人($user)) {
    // 框架"自动去艾特"会剥离消息中的@标记，需从原始内容解析被@的人
    $rawContent = (string)(raw['d']['content'] ?? '');
    $botId = defined('机器人ID') ? (string)机器人ID : '';
    if ($botId !== '') {
        $rawContent = preg_replace('/^<@!?' . preg_quote($botId, '/') . '>\s*/u', '', $rawContent);
    }
    // @某人 加分
    if (preg_match('/<@!?([0-9a-zA-Z]+)>\s*加分\s*(\d+)/u', $rawContent, $m)) {
        $target = strtoupper($m[1]);
        if ($target !== strtoupper($botId)) {
            $add = (int)$m[2];
            $now = 读("points", $target, 0);
            $new = $now + $add;
            写("points", $target, $new);
            原生MD("#![头像 #60px #60px](" . 头像($target) . ")<@{$target}>\n增加积分：{$add}\n当前积分：{$new}");
            return;
        }
    }
    // @某人 减分
    if (preg_match('/<@!?([0-9a-zA-Z]+)>\s*减分\s*(\d+)/u', $rawContent, $m)) {
        $target = strtoupper($m[1]);
        if ($target !== strtoupper($botId)) {
            $sub = (int)$m[2];
            $now = 读("points", $target, 0);
            $new = $now - $sub;
            写("points", $target, $new);
            原生MD("#![头像 #60px #60px](" . 头像($target) . ")<@{$target}>\n扣除积分：{$sub}\n当前积分：{$new}");
            return;
        }
    }
    // 无艾特默认给自己加分
    if (preg_match('/加分\s*(\d+)$/u', 消息, $m)) {
        $add = (int)$m[1];
        $now = 读("points", $user, 0);
        $new = $now + $add;
        写("points", $user, $new);
        原生MD("#![头像 #60px #60px](" . 头像($user) . ")<@" . $user . ">\n增加积分：{$add}\n当前积分：{$new}");
        return;
    }
    // 无艾特默认给自己减分
    if (preg_match('/减分\s*(\d+)$/u', 消息, $m)) {
        $sub = (int)$m[1];
        $now = 读("points", $user, 0);
        $new = $now - $sub;
        写("points", $user, $new);
        原生MD("#![头像 #60px #60px](" . 头像($user) . ")<@" . $user . ">\n扣除积分：{$sub}\n当前积分：{$new}");
        return;
    }
}

// ========== 生成菜单 ==========
if (preg_match('/生成菜单/u', 消息)) {
    $randParam = "?nocache=" . time() . mt_rand(1000, 9999);
    $imageUrl = "https://api.elaina.cat/random/pc" . $randParam;
    $randomText = trim(curl("https://api.oddfar.com/yl/q.php?c=2004&encode=text", "GET", [], ""));
    if (empty($randomText)) $randomText = "远赴人间惊鸿宴，一睹人间盛世颜";

    $md = "## 生成菜单";
    $md .= "![封面 #800px #400px]($imageUrl)\n\n";
    $md .= $randomText . "\n\n";

    $rows = [
        [
            "buttons" => [
                [
                    "id" => "btn_gen",
                    "render_data" => ["label" => "📚 生成卡", "visited_label" => "📚 生成", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "生成卡密 把我改成设备ID",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ],
                [
                    "id" => "btn_add",
                    "render_data" => ["label" => "➕ 增加次数", "visited_label" => "➕ 增加次数", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "次数 1",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ]
            ]
        ]
    ];
    原生按钮($md, $rows);
    return;
}

// ========== 减次数（消耗积分） ==========
if (preg_match('/次数\s*(\d+)$/', 消息, $match)) {
    $reduce = intval($match[1]);
    if ($reduce < 1) {
        原生MD("# ❌ 次数必须大于0");
        return;
    }
    $need = $reduce * $cost_per;
    $points = 读("points", $user, 0);

    if ($points < $need) {
        $md = "# ❌ 积分不足\n可用次数加：**{$reduce}** 次\n单次消耗：**{$cost_per}** 积分\n总共所需：**{$need}** 积分\n当前积分：**{$points}** 积分\n\n";
        $rows = [[
            "buttons" => [[
                "id" => "sign_btn_001",
                "render_data" => ["label" => "去签到", "visited_label" => "已签到", "style" => 1],
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

    // 扣除积分
    $new_points = $points - $need;
    写("points", $user, $new_points);

    $api_url = "https://wsx666.dpdns.org/apijc.php?qq=" . $user . "&num=-{$reduce}";
    $res = curl($api_url, "GET", [], "");
    $data = json_decode($res, true);

    if (isset($data['code']) && $data['code'] == 200) {
        $md = "![头像 #60px #60px](" . 头像($user) . ") <@" . $user . ">\n";
        $md .= "可用次数加：**{$reduce}** 次\n";
        $md .= "消耗积分：**{$need}** 积分\n";
        $md .= "总次数：**{$data['now_count']}/2**\n";
        $md .= "当前剩余积分：**{$new_points}**\n\n";
        $md .= "> 免费程序，请勿上当受骗！";
        原生MD($md);
    } else {
        $msg = $data['msg'] ?? "接口请求失败";
        // 退还积分
        写("points", $user, $points);
        原生MD("# ❌ 失败\n错误原因：{$msg}\n积分已原路返还");
    }
    return;
}

// ========== 生成卡密 ==========
if (preg_match('/生成卡密\s*(\d+)$/', 消息, $match)) {
    $device_id = $match[1];
    $points = 读("points", $user, 0);
    $need_points = 10;

    if ($points < $need_points) {
        $md = "# ❌ 积分不足\n当前积分：**{$points}**\n所需积分：**{$need_points}**\n";
        $md .= "> 复制指令无效请重新@\n或@发送全局加群号开起免@\n\n";
        $rows = [[
            "buttons" => [[
                "id" => "sign_btn_001",
                "render_data" => ["label" => "去签到", "visited_label" => "已签到", "style" => 1],
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

    $new_points = $points - $need_points;
    写("points", $user, $new_points);

    $api_url = "https://wsx666.dpdns.org/api.php?qq=" . $user . "&device_id=" . $device_id;
    $res = curl($api_url, "GET", [], "");
    $data = json_decode($res, true);

    if (isset($data['code']) && $data['code'] === 200) {
        $activate_code = $data['activate_code'] ?? '获取失败';
        $md = "![头像 #60px #60px](" . 头像($user) . ") <@" . $user . ">\n";
        $md .= "设备ID：{$data['device_id']}\n";
        $md .= "次数：{$data['current_count']}/{$data['max_count']}\n---\n";
        $md .= "**码：**\n```请点击复制按钮\n{$activate_code}\n```\n";
        $md .= "## 请自行删除末尾空格！\n---\n";
        $md .= "当前剩余积分：**{$new_points}**\n";
        $md .= "> 免费程序，请勿上当受骗！";
        原生MD($md);
    } else {
        $err_msg = $data['msg'] ?? '接口请求失败';
        $md = "# ❌ 卡密生成失败\n错误信息：**{$err_msg}**\n当前剩余积分：**{$new_points}**";
        原生MD($md);
    }
    return;
}

// ========== 签到核心 ==========
$todayDate = date("Y-m-d");
$yesterdayDate = date("Y-m-d", strtotime("-1 day"));

function checkSignIn($todayDate, $yesterdayDate, $user) {
    $lastSign = 读("sign", $user, "");
    $continuous = 读("continuous", $user, 0);
    $points = 读("points", $user, 0);

    if ($lastSign === $todayDate) {
        return ["status" => "signed", "continuous" => $continuous, "points" => $points];
    }

    if ($lastSign === $yesterdayDate) {
        $continuous++;
    } else {
        $continuous = 1;
    }

    $basePoints = 10;
    $bonus = 0;
    if ($continuous === 15) $bonus = 50;
    elseif ($continuous === 7) $bonus = 20;
    elseif ($continuous >= 3) $bonus = 1;
    $totalPoints = $basePoints + $bonus;
    $newPoints = $points + $totalPoints;

    写("sign", $user, $todayDate);
    写("continuous", $user, $continuous);
    写("points", $user, $newPoints);

    return [
        "status" => "success",
        "continuous" => $continuous,
        "points" => $newPoints,
        "bonus" => $bonus,
        "total" => $totalPoints
    ];
}

function buildSignCard($data, $user) {
    if ($data["status"] === "signed") return;
    $md = "![头像 #60px #60px](" . 头像($user) . ") <@" . $user . ">\n";
    $md .= "✅ 签到成功\n\n";
    $md .= "📅 签到日期：" . date("Y-m-d") . "\n";
    $md .= "🔥 连续签到：" . $data["continuous"] . " 天\n";
    $md .= "🎁 本次积分：+" . $data["total"] . "\n";
    if ($data["bonus"] > 0) {
        $md .= "🎁 连续奖励：+" . $data["bonus"] . "\n";
    }
    $md .= "💰 总积分：" . $data["points"] . " 积分\n\n";
    $rows = [[
        "buttons" => [[
            "id" => "sign_btn_001",
            "render_data" => ["label" => "去签到", "visited_label" => "已签到", "style" => 1],
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

if (消息来源 != "互动" && preg_match('/签到/u', 消息)) {
    $res = checkSignIn($todayDate, $yesterdayDate, $user);
    buildSignCard($res, $user);
    return;
}

// ========== 积分查询 ==========
if (消息来源 != "互动" && preg_match('/积分/u', 消息)) {
    $points = 读("points", $user, 0);
    $continuous = 读("continuous", $user, 0);
    $md = "![头像 #60px #60px](" . 头像($user) . ") <@" . $user . ">\n";
    $md .= "📊 个人积分资料\n\n";
    $md .= "🔥 连续签到：" . $continuous . " 天\n";
    $md .= "💰 当前积分：" . $points . " 积分\n\n";
    $rows = [[
        "buttons" => [[
            "id" => "sign_btn_001",
            "render_data" => ["label" => "去签到", "visited_label" => "已签到", "style" => 1],
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

// ========== 抽奖 ==========
if (preg_match('/抽奖\s*(\d+)$/u', 消息, $match)) {
    $cost = (int)$match[1];
    if ($cost < 2) {
        原生MD("# ❌ 抽奖积分必须大于1");
        return;
    }
    $user_points = 读("points", $user, 0);
    if ($user_points < $cost) {
        原生MD("# ❌ 积分不足\n当前积分：{$user_points}\n本次抽奖消耗：{$cost}");
        return;
    }

    $user_points -= $cost;
    写("points", $user, $user_points);

    $prizes = [
        [1, 10, "超级大奖（10倍）"],
        [10, 3, "一等奖（3倍）"],
        [20, 2, "二等奖（2倍）"],
        [25, 1, "三等奖（1倍）"],
        [30, 0.5, "安慰奖（0.5倍）"],
        [14, 0, "谢谢参与（0倍）"]
    ];

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

    $win_points = $cost * $win_multi;
    $final_points = $user_points + $win_points;
    写("points", $user, $final_points);

    $md = "#![头像 #60px #60px](" . 头像($user) . ")<@" . $user . ">\n";
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

// ========== 互动回调（签到按钮） ==========
if (消息来源 === "互动") {
    $btnData = raw["d"]["data"]["resolved"]["button_data"] ?? (raw["d"]["data"]["data"] ?? "");
    if ($btnData === "cd:click") {
        $res = checkSignIn($todayDate, $yesterdayDate, $user);
        buildSignCard($res, $user);
        // 回应互动（避免超时）
        $interId = raw["d"]["id"] ?? '';
        if ($interId !== '') {
            BOTAPI("/v2/interactions/" . $interId, "PUT", json_encode(["code" => 0], JSON_UNESCAPED_UNICODE));
        }
        return;
    }
}