<?php
// ========== 多平台热榜插件 ==========
// 仅处理 群聊/私聊 消息，其他事件直接终止
if (!in_array(消息来源, ["群聊", "私聊"])) {
    return;
}

// 接口基础地址
$apiBase = "https://api.yuafeng.cn/API/ly/jinri_hot.php";
// 限制榜单展示条数，避免消息过长
$showNum = 20;

// 1. 热榜菜单指令
if (消息 == "菜单") {
    $md = "# 📊 全网热榜菜单\n\n";
    $md .= "发送以下指令即可查看对应榜单：\n";
    $md .= "1. 微博热榜\n";
    $md .= "2. 知乎热榜\n";
    $md .= "3. 微信热文榜\n";
    $md .= "4. 澎湃热榜\n";
    $md .= "5. 百度热点\n";
    $md .= "6. 知乎日报\n";
    $md .= "7. 今日头条热榜\n";
    $md .= "8. 梨视频总榜\n";
    原生MD($md);
    return;
}

// 2. 匹配所有热榜指令，映射接口参数
$action = "";
switch (消息) {
    case "微博热榜":
    case "微博热搜":
        $action = "微博热榜";
        break;
    case "知乎热榜":
       case "知乎热搜":
        $action = "知乎热榜";
        break;
    case "微信热文榜":
    case "微信热搜":
        $action = "微信热文榜";
        break;
    case "澎湃热榜":
    case "澎湃热搜":
        $action = "澎湃热榜";
        break;
    case "百度热点":
       case "百度热搜":
        $action = "百度热点";
        break;
    case "知乎日报":
    case "知乎热搜":
        $action = "知乎日报";
        break;
    case "今日头条热榜":
    case "今日热搜":
        $action = "今日头条热榜";
        break;
    case "梨视频总榜":
        $action = "梨视频总榜";
        break;
    default:
        // 非本插件指令，直接退出
        return;
}

// 拼接完整请求地址
$requestUrl = $apiBase . "?action=" . urlencode($action);
// 记录请求日志（调试用）

// 调用框架curl函数请求接口
$response = curl($requestUrl, "GET", [], "");
if (empty($response)) {
    文字("❌ 接口请求失败，请稍后再试！");
    return;
}

// 解析JSON数据
$jsonData = json_decode($response, true);
// JSON解析失败判断
if (json_last_error() !== JSON_ERROR_NONE) {
    文字("❌ 数据解析异常，接口返回格式错误！");
    return;
}

// 判断接口返回状态、数据是否为空
if (!isset($jsonData["data"]) || empty($jsonData["data"]) || !is_array($jsonData["data"])) {
    $tip = $jsonData["msg"] ?? "暂无榜单数据";
    文字("ℹ️ {$action}：{$tip}");
    return;
}

// 截取指定条数榜单
$hotList = array_slice($jsonData["data"], 0, $showNum);
// 拼接Markdown内容（美观排版）
$content = "# 🔥 {$action}\n\n";
$index = 1;

foreach ($hotList as $item) {
    // 兼容不同榜单的字段（标题/热度/链接）
    $title = $item["title"] ?? "未知标题";
    $hot = $item["hot"] ?? "";
    $link = $item["url"] ?? "";

    // 拼接单条内容，有链接则做成可点击跳转
    if (!empty($link)) {
        $line = "{$index}. [{$title}]({$link})";
    } else {
        $line = "{$index}. {$title}";
    }
    // 追加热度信息
    if (!empty($hot)) {
        $line .= " 【热度：{$hot}】";
    }
    $content .= $line . "\n";
    $index++;
}

// 发送Markdown消息
原生MD($content);
return;
