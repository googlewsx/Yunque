<?php
if (!in_array(消息来源, ["群聊", "私聊"])) {
    return;
}

// 视频解析（B站 + 抖音 + 快手）
$解析链接 = "";
$解析方向 = "";

// 匹配 B 站
if (preg_match('/https?:\/\/b23\.tv\/[a-zA-Z0-9]+/i', 消息, $m)) {
    $解析链接 = trim($m[0]);
    $解析方向 = "哔哩哔哩";
}
if (preg_match('/https?:\/\/(?:www\.)?bilibili\.com\/video\/BV[a-zA-Z0-9]{10}/i', 消息, $m)) {
    $解析链接 = trim($m[0]);
    $解析方向 = "哔哩哔哩";
}

// 匹配抖音

if (preg_match('/https?:\/\/v\.douyin\.com\/[^\s]+/i', 消息, $m)) {
    $解析链接 = trim($m[0]);
    $解析方向 = "抖音";
}



// 匹配快手
if (preg_match('/https?:\/\/v\.kuaishou\.com\/[a-zA-Z0-9]+/i', 消息, $m)) {
    $解析链接 = trim($m[0]);
    $解析方向 = "快手";
}

if (empty($解析链接) || empty($解析方向)) {
    return;
}

// ======================================
// CURL公共请求函数（全平台统一稳定）
// ======================================
function 请求($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

// ======================================
// B站解析【BugPk官方API · 已替换完成】
// ======================================
if ($解析方向 == "哔哩哔哩") {
    $api = "https://api.bugpk.com/api/bilibili?url=" . urlencode($解析链接);
    $res = 请求($api);
    $json = json_decode($res, true);

    // 错误处理（兼容code/error）
    if (!isset($json['code']) || $json['code'] !== 200) {
        $err = $json['msg'] ?? ($json['error'] ?? '解析失败');
        原生MD("# ❌ B站失败\n$err");
        return;
    }

    $data = $json['data'] ?? [];
    $title = $data['title'] ?? '';
    $auther = $data['auther'] ?? '';
    $avatar = $data['avatar'] ?? '';
    $description = $data['description'] ?? '';
    $cover = $data['cover'] ?? '';
    $video_url = $data['url'] ?? '';
    $videos = $data['videos'] ?? [];
    $durationFormat = $videos[0]['durationFormat'] ?? '未知';

    // 构建卡片
    $md = "# 🎬 B站\n";
    if ($avatar) $md .= "![UP头像 scheme=\"{$video_url}\" #60px #60px]($avatar) **$auther**\n\n";
    if ($cover) $md .= "![封面 scheme=\"{$video_url}\" #380px #220px]($cover)\n\n";
    $md .= "> 标题：$title\n";
    $md .= "简介：$description\n";
    
    $md .= "时长：$durationFormat\n点击封面即可播放\n";

    原生MD($md);
    if ($video_url) 视频($video_url);
    return;
}

// ======================================
// 抖音解析（BugPk官方接口 · 保持不变）
// ======================================
if ($解析方向 == "抖音") {
    $api = "https://api.bugpk.com/api/douyin?url=" . urlencode($解析链接);
    $res = 请求($api);
    $json = json_decode($res, true);
    
    

    if (!isset($json['code']) || $json['code'] !== 200) {
        $err = $json['msg'] ?? '解析失败';
        原生MD("# ❌ 抖音失败\n$err");
        return;
    }

    $data = $json['data'] ?? [];
    $title = $data['title'] ?? '';
    $desc = $data['desc'] ?? '';
    $cover = $data['cover'] ?? '';
    $video_url = $data['url'] ?? '';
    $author_info = $data['author'] ?? [];
    $author_name = $author_info['name'] ?? '';
    $author_avatar = $author_info['avatar'] ?? '';
    $extra = $data['extra'] ?? [];
    $create_time = $extra['create_time'] ?? 0;
    $publish_time = $create_time ? date("Y-m-d H:i", $create_time) : '未知';
    $statistics = $extra['statistics'] ?? [];

    $md = "# 🎵 抖音\n";
    if ($author_avatar) $md .= "![UP头像 scheme=\"{$video_url}\" #30px #30px]($author_avatar) **$author_name**\n\n";
    if ($cover) $md .= "![封面 scheme=\"{$video_url}\" #380px #220px]($cover)\n";
   
    $md .= "> 标题：$title\n";
    $md .= "发布：$publish_time\n";
    $md .= "点赞：{$statistics['digg_count']}　评论：{$statistics['comment_count']}\n";
    $md .= "收藏：{$statistics['collect_count']}　分享：{$statistics['share_count']}\n点击封面即可播放\n";

    原生MD($md);
    if ($video_url) 视频($video_url);
    return;
}

// ======================================
// 快手解析（BugPk官方接口 · 保持不变）
// ======================================
if ($解析方向 == "快手") {
    $api = "https://api.bugpk.com/api/kuaishou?url=" . urlencode($解析链接);
    $res = 请求($api);
    $json = json_decode($res, true);

    if (!isset($json['code']) || $json['code'] !== 200) {
        $err = $json['msg'] ?? ($json['error'] ?? '系统内部异常');
        原生MD("# ❌ 快手失败\n$err");
        return;
    }

    $data = $json['data'] ?? [];
    $author = $data['author'] ?? '';
    $avatar = $data['avatar'] ?? '';
    $title = $data['title'] ?? '';
    $cover = $data['cover'] ?? '';
    $video_url = $data['url'] ?? '';
    $like = $data['like'] ?? 0;
    $time = $data['time'] ?? 0;
    $publish_time = $time ? date("Y-m-d H:i", $time / 1000) : '未知';
    $music = $data['music'] ?? [];
    $music_name = $music['name'] ?? '';
    $music_artist = $music['artist'] ?? '';

    $md = "# 🎬 快手\n";
    if ($avatar) $md .= "![头像 scheme=\"{$video_url}\" #60px #60px]($avatar) **$author**\n\n";
    if ($cover) $md .= "![封面 scheme=\"{$video_url}\" #380px #220px]($cover)\n\n";
    $md .= "> 标题：$title\n";
    
    $md .= "发布时间：$publish_time\n";
    $md .= "点赞：$like\n";
    if ($music_name) $md .= "🎵 背景音乐：$music_artist - $music_name\n点击封面即可播放\n";
  

    原生MD($md);
    if ($video_url) 视频($video_url);
    return;
}
