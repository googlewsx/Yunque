<?php
if (!in_array(消息来源, ["群聊","私聊"])) return;

// ====================== 工具函数 ======================
function 获取随机装饰() {
    $randParam = "?nocache=" . time() . mt_rand(1000, 9999);
    $imageUrl = "https://api.elaina.cat/random/pc" . $randParam;
    $randomText = trim(curl("https://api.oddfar.com/yl/q.php?c=2004&encode=text", "GET", [], ""));
    if (empty($randomText)) {
        $randomText = "远赴人间惊鸿宴，一睹人间盛世颜";
    }
    return [$imageUrl, $randomText];
}

function 按钮行($id, $label, $data, $style = 1) {
    return [
        "buttons" => [
            [
                "id" => $id,
                "render_data" => ["label" => $label, "visited_label" => $label, "style" => $style],
                "action" => [
                    "type" => 2,
                    "permission" => ["type" => 2],
                    "data" => $data,
                    "unsupport_tips" => "当前QQ版本不支持"
                ]
            ]
        ]
    ];
}

// ====================== 🔧 修改1：搜索歌曲函数 ======================
function 搜索歌曲($关键词) {
    // 使用酷我API，每页返回9条数据
    $api = "https://oiapi.net/api/Kuwo?msg=" . urlencode($关键词) . "&limit=9";
    $res = curl($api, "GET", [], "");
    $result = json_decode($res, true);
    // 返回data数组，若无数据则返回空数组
    return isset($result['data']) && is_array($result['data']) ? $result['data'] : [];
}

// ====================== 🔧 修改2：获取歌曲详情函数 ======================
function 获取歌曲详情($关键词, $序号) {
    // 使用酷我API，通过n参数指定选择第几首
    $api = "https://oiapi.net/api/Kuwo?msg=" . urlencode($关键词) . "&n=" . intval($序号);
    $res = curl($api, "GET", [], "");
    $result = json_decode($res, true);
    // 接口返回的data可能是对象（单曲）或数组，统一转为数组处理
    $data = $result['data'] ?? [];
    if (is_object($data)) {
        $data = get_object_vars($data);
    }
    return $data;
}

// ====================== 🔧 修改3：生成播放MD函数 ======================
function 生成播放MD($歌曲数据) {
    // 适配酷我API的字段名：song, singer, picture, rid
    $歌曲名 = $歌曲数据['song'] ?? "";
    $歌手 = $歌曲数据['singer'] ?? "";
    $封面 = $歌曲数据['picture'] ?? "";
    $rid = $歌曲数据['rid'] ?? "";

    $md = "# 🎵 正在播放\n**歌曲**：{$歌曲名}\n**歌手**：{$歌手}\n";
    // 酷我音乐详情页链接使用rid
    $md .= "![图片 scheme=\"https://www.kuwo.cn/play_detail/{$rid}\" #200px #200px]({$封面})\n";
    return [$md, $rid];
}

// ====================== 音乐菜单 ======================
if (preg_match('/音乐系统/u', 消息)) {
    list($imageUrl, $randomText) = 获取随机装饰();
    $md = "##音乐系统";
    $md .= "![封面 #800px #400px]($imageUrl)\n\n> ";
    $md .= $randomText . "\n\n";

    $rows = [
        按钮行("btn1", "点歌 离开我的依赖", "点歌 离开我的依赖"),
        按钮行("btn4", "点歌 孤城", "点歌 孤城"),
        按钮行("btn2", "个人歌单", "个人歌单"),
        按钮行("btn3", "清空收藏", "清空个人收藏歌曲"),
    ];

    原生按钮($md, $rows);
    return;
}

// ====================== 点歌 ======================
if (preg_match('/^点歌\s*(.+)/u', 消息, $m)) {
    $歌曲名 = trim($m[1] ?? '');

    if (empty($歌曲名)) {
        原生MD("# ⚠️ 提示\n请输入要搜索的歌曲名\n[点歌 孤勇者](mqqapi://aio/inlinecmd?enter=true&command=点歌 孤勇者)");
        return;
    }

    $音源 = "酷我";  // 修改音源标识
    写("音乐/用户音源", 用户, $音源);

    $歌曲列表 = 搜索歌曲($歌曲名);
    $总数 = count($歌曲列表);

    if ($总数 < 1) {
        原生MD("# ⚠️ 提示\n未找到歌曲或接口异常");
        return;
    }

    写("音乐/点歌记录", 用户, $歌曲名);
    写("音乐/最大序号", 用户, $总数);

    $md = "![酷我音乐 #60px #60px](https://qqbot.ugcimg.cn/102813815/bacf39730a54015f251867fc194c6a75fdc8e33b/ebbf62bbda28d2ced925033001ffab84)                            ![会员 #25px #25px](https://qqbot.ugcimg.cn/102813815/405c0ca5abc3cfc8db1d74ee53bb3bbf7ab3c27a/0d570be5fbee1056e66b936ef39c12ca)\n***\n";
    $临时歌单 = [];
    foreach ($歌曲列表 as $索引 => $歌曲) {
        $序号 = $索引 + 1;
        $歌名 = $歌曲['song'] ?? "";
        $歌手 = $歌曲['singer'] ?? "";
        // 适配酷我API的封面字段 picture
        $songCover = $歌曲['picture'] ?? "https://qqbot.ugcimg.cn/102813815/018de3429a900fb17ad859748101def469ad042d/830615e0ad61ca48c7940932808761b6";
        $md .= "![专辑封面 #30px #30px]({$songCover}) | [{$歌名}-{$歌手}](mqqapi://aio/inlinecmd?command=选歌{$序号}&enter=false&reply=false)\n";
        $临时歌单[] = ["歌名" => $歌名, "歌手" => $歌手];
    }
    $md .= "***\n💡 提示：点击歌名可直接播放";

    写("音乐/临时歌单/".用户, "data", $临时歌单);
    写("音乐/临时歌单/".用户, "音源", $音源);
    原生MD($md);
    return;
}

// ====================== 选歌 ======================
if (preg_match('/选歌([0-9]+)$/u', 消息, $m)) {
    $序号 = intval($m[1] ?? 0);
    $搜索关键词 = 读("音乐/点歌记录", 用户, "");
    $最大序号 = 读("音乐/最大序号", 用户, 0);

    if ($序号 < 1 || $序号 > $最大序号) {
        原生MD("# ⚠️ 提示\n请先点歌，序号范围 `1~{$最大序号}`");
        return;
    }

    $歌曲数据 = 获取歌曲详情($搜索关键词, $序号);
    // 适配酷我API：音乐链接字段为 url
    $音乐链接 = $歌曲数据['url'] ?? "";

    if (empty($音乐链接)) {
        原生MD("# ⚠️ 提示\n歌曲链接获取失败");
        return;
    }

    list($md, $rid) = 生成播放MD($歌曲数据);
    // 酷我音乐详情页链接
    $yapi = "https://www.kuwo.cn/play_detail/{$rid}";

    $rows = [
        按钮行("bt3", "收藏歌曲", "收藏歌曲{$序号}"),
        [
            "buttons" => [
                [
                    "id" => "btn_float",
                    "render_data" => ["label" => "悬浮窗播放", "visited_label" => "已点击", "style" => 1],
                    "action" => ["type" => 0, "permission" => ["type" => 2], "data" => $yapi, "unsupport_tips" => "请升级QQ版本"]
                ]
            ]
        ]
    ];

    原生按钮($md, $rows);
    语音($音乐链接);
    return;
}

// ====================== 个人歌单 ======================
if (preg_match('/个人歌单/u', 消息)) {
    $收藏列表 = 读("音乐/收藏", 用户, []);
    $总数 = count($收藏列表);
    if ($总数 < 1) {
        原生MD("# 📂 个人歌单\n你还没有收藏任何歌曲");
        return;
    }
    $md = "# 📂 个人收藏歌单\n共收藏 **{$总数}** 首歌曲\n---\n";
    foreach ($收藏列表 as $索引 => $歌曲) {
        $序号 = $索引 + 1;
        $歌名 = $歌曲['歌名'] ?? "";
        $歌手 = $歌曲['歌手'] ?? "";
        $md .= "{$序号}. **{$歌名}** - {$歌手}\n[播放收藏{$序号}](mqqapi://aio/inlinecmd?enter=true&command=播放收藏{$序号}) | [取消收藏{$序号}](mqqapi://aio/inlinecmd?enter=true&command=取消收藏{$序号})\n\n";
    }
    原生MD($md);
    return;
}

// ====================== 收藏歌曲 ======================
if (preg_match('/收藏歌曲([0-9]+)$/u', 消息, $m)) {
    $序号 = intval($m[1] ?? 0);
    $临时歌单 = 读("音乐/临时歌单/".用户, "data", []);
    $搜索关键词 = 读("音乐/点歌记录", 用户, "");
    if (!isset($临时歌单[$序号 - 1])) {
        原生MD("# ⚠️ 提示\n请先点歌再收藏");
        return;
    }
    $目标歌曲 = $临时歌单[$序号 - 1];
    $收藏列表 = 读("音乐/收藏", 用户, []);
    $收藏列表[] = ["歌名"=>$目标歌曲['歌名'],"歌手"=>$目标歌曲['歌手'],"音源"=>"酷我","搜索词"=>$搜索关键词,"序号"=>$序号];
    写("音乐/收藏", 用户, $收藏列表);
    原生MD("# ✅ 收藏成功\n已收藏：**{$目标歌曲['歌名']}**\n[个人歌单](mqqapi://aio/inlinecmd?enter=true&command=个人歌单)");
    return;
}

// ====================== 取消收藏 ======================
if (preg_match('/取消收藏([0-9]+)$/u', 消息, $m)) {
    $序号 = intval($m[1] ?? 0) - 1;
    $收藏列表 = 读("音乐/收藏", 用户, []);
    if (!isset($收藏列表[$序号])) {
        原生MD("# ⚠️ 提示\n序号不存在");
        return;
    }
    $删除歌曲 = $收藏列表[$序号];
    array_splice($收藏列表, $序号, 1);
    写("音乐/收藏", 用户, $收藏列表);
    原生MD("# ✅ 取消收藏\n已取消收藏：**{$删除歌曲['歌名']}**\n[个人歌单](mqqapi://aio/inlinecmd?enter=true&command=个人歌单)");
    return;
}

// ====================== 播放收藏 ======================
if (preg_match('/播放收藏([0-9]+)$/u', 消息, $m)) {
    $序号 = intval($m[1] ?? 0);
    $收藏列表 = 读("音乐/收藏", 用户, []);
    if (!isset($收藏列表[$序号 - 1])) {
        原生MD("# ⚠️ 提示\n收藏不存在");
        return;
    }
    $目标歌曲 = $收藏列表[$序号 - 1];
    $搜索关键词 = $目标歌曲['搜索词'] ?? "";
    $歌曲序号 = $目标歌曲['序号'] ?? 1;

    $歌曲数据 = 获取歌曲详情($搜索关键词, $歌曲序号);
    // 适配酷我API：音乐链接字段为 url
    $音乐链接 = $歌曲数据['url'] ?? "";

    if (empty($音乐链接)) {
        原生MD("# ⚠️ 提示\n歌曲已失效");
        return;
    }

    list($md, $rid) = 生成播放MD($歌曲数据);
    // 酷我音乐详情页链接
    $yapi = "https://www.kuwo.cn/play_detail/{$rid}";

    $md .= "[取消收藏{$序号}](mqqapi://aio/inlinecmd?enter=true&command=取消收藏{$序号})\n[个人歌单](mqqapi://aio/inlinecmd?enter=true&command=个人歌单)\n";

    $rows = [
        [
            "buttons" => [
                [
                    "id" => "btn_float2",
                    "render_data" => ["label" => "悬浮窗播放", "visited_label" => "已点击", "style" => 1],
                    "action" => ["type" => 0, "permission" => ["type" => 2], "data" => $yapi, "unsupport_tips" => "请升级QQ版本"]
                ]
            ]
        ]
    ];

    原生按钮($md, $rows);
    语音($音乐链接);
    return;
}

// ====================== 清空收藏 ======================
if (preg_match('/清空个人收藏歌曲/u', 消息)) {
    写("音乐/收藏", 用户, []);
    原生MD("# ✅ 操作成功\n已清空收藏歌单\n[个人歌单](mqqapi://aio/inlinecmd?enter=true&command=个人歌单)");
    return;
}