<?php
if (!in_array(消息来源, ["群聊", "私聊", "互动"])) {
    return;
}
$trimMsg = trim(消息);



if (消息 == "查询教程") {
$activate_code =  "鸿蒙微信XXX\n鸿蒙QQ安琪拉\n苹果微信区XXX\n苹果QQ孙尚香\n安卓微信李白\n安卓QQ孙悟空\n\n将以上人物名处更换为要查询的人物人物名称必须下面有！\n\n1.廉颇\n2.小乔\n3.赵云\n4.墨子\n5.妲己\n6.嬴政\n7.孙尚香\n8.鲁班七号\n9.庄周\n10.刘禅\n11.高渐离\n12.阿轲\n13.钟无艳\n14.孙膑\n15.扁鹊\n16.白起\n17.芈月\n18.吕布\n19.周瑜\n20.元歌\n21.夏侯惇\n22.甄姬\n23.曹操\n24.典韦\n25.宫本武藏\n26.李白\n27.马可波罗\n28.狄仁杰\n29.达摩\n30.项羽\n31.武则天\n32.司马懿\n33.老夫子\n34.关羽\n35.貂蝉\n36.安琪拉\n37.程咬金\n38.露娜\n39.姜子牙\n40.刘邦\n41.韩信\n42.孙权\n43.王昭君\n44.兰陵王\n45.花木兰\n46.艾琳\n47.张良\n48.不知火舞\n49.朵莉亚\n50.娜可露露\n51.橘右京\n52.亚瑟\n53.孙悟空\n54.牛魔\n55.后羿\n56.刘备\n57.张飞\n58.蚩奼\n59.李元芳\n60.虞姬\n61.钟馗\n62.杨玉环\n63.苍\n64.杨戬\n65.女娲\n66.哪吒\n67.干将莫邪\n68.雅典娜\n69.蔡文姬\n70.太乙真人\n71.东皇太一\n72.大禹\n73.鬼谷子\n74.诸葛亮\n75.大乔\n76.黄忠\n77.铠\n78.苏烈\n79.百里玄策\n80.百里守约\n81.弈星\n82.梦奇\n83.公孙离\n84.沈梦溪\n85.明世隐\n86.裴擒虎\n87.狂铁\n88.米莱狄\n89.瑶\n90.云中君\n91.李信\n92.伽罗\n93.盾山\n94.孙策\n95.猪八戒\n96.上官婉儿\n97.亚连\n98.嫦娥\n99.大司命\n100.马超\n101.敖隐\n102.海月\n103.曜\n104.西施\n105.蒙犽\n106.鲁班大师\n107.蒙恬\n108.澜\n109.盘古\n110.镜\n111.阿古朵\n112.桑启\n113.夏洛特\n114.司空震\n115.云缨\n116.金蝉\n117.暃\n118.赵怀真\n119.莱西奥\n120.戈娅\n121.空空儿\n122.影\n123.海诺\n124.姬小满\n125.少司缘\n126.元流坦克 元坦 源流坦克 源坦\n127.元流法师 元法 源流法师 源法\n128.元流刺客 元刺 源流刺客 源刺\n129.元流射手 元射 源流射手 源射\n130.元流辅助 元辅 源流辅助 源辅\n";

  
    $md = "```查询教程
{$activate_code}
```\n";
    
    原生MD($md);
    return;
}




date_default_timezone_set('Asia/Shanghai');

// 兼容所有格式：安卓QQ/安卓QQ区/安卓 QQ/苹果微信/苹果微信区
$rule = '/(鸿蒙|安卓|苹果)\s*(qq|QQ|微信)\s*区?\s*(.+)$/u';
if (preg_match($rule, trim(消息), $resArr)) {
    $device = $resArr[1];
    $soft  = $resArr[2];
    $hero  = trim($resArr[3]);

    // 大区映射
    $typeMap = [
        "安卓QQ"   => "aqq",
        "安卓qq"   => "aqq",
        "鸿蒙qq"   => "aqq",
        "鸿蒙QQ"   => "aqq",
        "安卓微信" => "avx",
        "鸿蒙微信" => "avx",
        "苹果QQ"   => "iqq",
        "苹果qq"   => "iqq",
        "苹果微信" => "ivx"
    ];
    $typeKey = $device . $soft;
    if (!isset($typeMap[$typeKey])) {
        $md  = "## ❌ 大区错误\n";
        $md .= "支持格式示例：\n";
        $md .= "• 安卓QQ安琪拉\n";
        $md .= "• 安卓QQ区安琪拉\n";
        $md .= "• 苹果 微信 朵莉亚";
        原生按钮($md, []);
        return;
    }
    $type = $typeMap[$typeKey];

    // 调用新API
    $apiUrl = "https://wsx666.dpdns.org/wzz.php?platform={$type}&hero=" . urlencode($hero);
    $result = curl($apiUrl, "GET", [], "");
    $json = json_decode($result, true);

    if (!$json || $json["code"] != 0 || empty($json["data"])) {
        $md = "## ❌ 查询失败\n请稍后重试，或检查英雄名称是否正确。";
        原生MD($md);
        return;
    }

    $d = $json["data"];

    // 拼接职业名称
    $mainJob = $d['role_main_name'] ?? '未知';
    $subJob  = $d['role_sub_name'] ?? '';
    $heroJob = (!empty($subJob) && $subJob != '无') ? $mainJob . '/' . $subJob : $mainJob;

    // 解析省份数据
    $provinceStr = "暂无数据";
    if (isset($d['province_min']['score'], $d['province_min']['short_name'])) {
        $provinceStr = "{$d['province_min']['short_name']}({$d['province_min']['score']})";
    }

    // 解析市级数据 去除括号内容
    $cityStr = "暂无数据";
    if (isset($d['city_min']['score'], $d['city_min']['short_name'])) {
        $cityStr = "{$d['city_min']['short_name']}({$d['city_min']['score']})";
    }

    // 解析县级数据 去除括号内容
    $countyStr = "暂无数据";
    if (isset($d['county_min']['score'], $d['county_min']['short_name'])) {
        $countyStr = "{$d['county_min']['short_name']}({$d['county_min']['score']})";
    }

    // 时间戳格式化
    $updateTimeStr = "暂无数据";
    if (isset($d['update_time']) && is_numeric($d['update_time'])) {
        $updateTimeStr = date('m-d H:i:s', $d['update_time']);
    }

    // 紧凑排版卡片内容
    $md = "## 王者战力查询\n";
    $md .= "![封面 #60px #60px]({$d['avatar_url']}) **{$d['hero_name']}-{$d['hero_title']}**\n";
    $md .= "> 大区：{$device}{$soft} | 职业：{$heroJob}\n\n";
    
    $md .= "🏅 国标分数线\n";
    $md .= "大国标({$d['guofu_min_score']}) | 小国标({$d['quanguo_min_score']})\n\n";
    
    $md .= "🌍 地区战力门槛\n";
    $md .= "省级：{$provinceStr}\n";
    $md .= "市级：{$cityStr}\n";
    $md .= "县级：{$countyStr}\n\n";
    $md .= "数据更新：{$updateTimeStr}";

    $rows = [
        // 第1行：王者功能 + 视频菜单
        [
            "buttons" => [
                [
                    "id" => "btn1",
                    "render_data" => ["label" => "安卓QQ区", "visited_label" => "安卓QQ区", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "安卓QQ区",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ],
                [
                    "id" => "btn2",
                    "render_data" => ["label" => "安卓微信区", "visited_label" => "安卓微信区", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "安卓微信区",
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
                    "render_data" => ["label" => "苹果QQ区", "visited_label" => "苹果QQ区", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "苹果QQ区",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ],
                [
                    "id" => "btn4",
                    "render_data" => ["label" => "苹果微信区", "visited_label" => "苹果微信区", "style" => 1],
                    "action" => [
                        "type" => 2,
                        "permission" => ["type" => 2],
                        "data" => "苹果微信区",
                        "unsupport_tips" => "当前QQ版本不支持"
                    ]
                ]
            ]
        ]
    ];
    原生按钮($md, $rows);
    return;
}
?>