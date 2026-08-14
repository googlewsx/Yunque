<?php
if (!in_array(消息来源, ["群聊", "私聊"])) return;

// ========== 工具函数 ==========
function 王者API($name = '') {
    $url = 'https://oiapi.net/api/Honor';
    if (!empty($name)) $url .= '?name=' . urlencode($name);
    $res = curl($url, 'GET', [], '');
    $data = json_decode($res, true);
    if (!is_array($data) || !in_array($data['code'] ?? -1, [0, 1])) return null;
    return $data['data'] ?? null;
}

// ========== 缓存 ==========
$cacheKey = 'wz_hero_' . 用户;
$skinKey = 'wz_skin_' . 用户;
$currentHero = 读('王者英雄', $cacheKey, []);
$currentSkinIndex = (int)读('王者英雄', $skinKey, -1);

// ========== 皮肤工具 ==========
function 随机皮肤索引($hero) {
    $voiceList = $hero['detailed']['voice'] ?? [];
    return empty($voiceList) ? -1 : array_rand($voiceList);
}

function 获取当前皮肤($hero, $index) {
    $voiceList = $hero['detailed']['voice'] ?? [];
    if (empty($voiceList) || $index < 0 || $index >= count($voiceList)) return null;
    return $voiceList[$index];
}

// 获取当前皮肤的随机台词（文本）
function 获取当前皮肤随机台词($hero, $index) {
    $skin = 获取当前皮肤($hero, $index);
    if (!$skin) return false;
    $items = $skin['list'] ?? [];
    if (empty($items)) return false;
    $rand = $items[array_rand($items)];
    return $rand['word'] ?? false;
}

// 获取当前皮肤的随机语音URL
function 获取当前皮肤随机语音URL($hero, $index) {
    $skin = 获取当前皮肤($hero, $index);
    if (!$skin) return false;
    $voices = [];
    foreach ($skin['list'] as $item) {
        if (!empty($item['voice'])) $voices[] = $item['voice'];
    }
    return empty($voices) ? false : $voices[array_rand($voices)];
}

// ========== 主卡片 ==========
function 主卡片数据($hero, $skinIndex) {
    $d = $hero['detailed'] ?? [];
    $skin = 获取当前皮肤($hero, $skinIndex);
    $skinName = $skin ? $skin['name'] : '未知皮肤';
    $skinCover = $skin ? ($skin['cover'] ?? ($skin['picture'] ?? '')) : '';
    if (empty($skinCover)) $skinCover = $hero['small_cover'] ?? '';

    // 获取当前皮肤的随机台词
    $randomWord = 获取当前皮肤随机台词($hero, $skinIndex);

    $md = "## 🏆 王者英雄\n";
    $md .= "![皮肤封面 #60px #60px]({$skinCover}) **{$hero['name']} - {$skinName}**\n\n";
    $md .= "**定位**：{$hero['position']}  |  **阵营**：{$hero['camp']}\n";
    $md .= "**区域**：{$hero['zone']}\n";
    if ($randomWord) $md .= "**语音：** {$randomWord}\n";  // 显示随机台词
    if (!empty($d['height'])) $md .= "**身高**：{$d['height']} cm\n";
    $md .= "\n---";

    $rows = [
        [
            "buttons" => [
                ["id" => "btn_story", "render_data" => ["label" => "📜 故事", "visited_label" => "📜 故事", "style" => 1],
                 "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "故事", "unsupport_tips" => "版本不支持"]],
                ["id" => "btn_play", "render_data" => ["label" => "▶️ 播放语音", "visited_label" => "▶️ 播放语音", "style" => 1],
                 "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "播放语音", "unsupport_tips" => "版本不支持"]]
            ]
        ],
        [
            "buttons" => [
                ["id" => "btn_random", "render_data" => ["label" => "🎲 随机英雄", "visited_label" => "🎲 随机英雄", "style" => 0],
                 "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "英雄随机", "unsupport_tips" => "版本不支持"]]
            ]
        ]
    ];
    return [$md, $rows];
}

// ========== 故事卡片 ==========
function 故事卡片数据($hero) {
    $stories = $hero['detailed']['stories'] ?? [];
    if (empty($stories)) return ["该英雄暂无故事~", []];
    $story = $stories[0];
    $md = "## 📜 {$hero['name']} 的故事\n";
    $md .= "```{$story['title']}\n" . $story['content'] . "\n```\n\n";
    if (!empty($story['images'])) {
        foreach (array_slice($story['images'], 0, 3) as $img) $md .= "![]($img) ";
    }
    if (count($stories) > 1) $md .= "\n📚 共 " . count($stories) . " 个故事，当前第 1 个。";
    $rows = [[
        "buttons" => [
            ["id" => "btn_back", "render_data" => ["label" => "🔙 返回英雄", "visited_label" => "🔙 返回英雄", "style" => 0],
             "action" => ["type" => 2, "permission" => ["type" => 2], "data" => "返回", "unsupport_tips" => "版本不支持"]]
        ]
    ]];
    return [$md, $rows];
}

// ========== 命令处理 ==========
$trimMsg = trim(消息);

// 1. 随机英雄
if ($trimMsg == "英雄随机") {
    $hero = 王者API();
    if (!$hero) { 文字("获取随机英雄失败，请稍后重试~"); return; }
    $skinIdx = 随机皮肤索引($hero);
    if ($skinIdx === -1) $skinIdx = 0;
    写('王者英雄', $cacheKey, $hero);
    写('王者英雄', $skinKey, $skinIdx);
    list($md, $rows) = 主卡片数据($hero, $skinIdx);
    原生按钮($md, $rows);
    return;
}

// 2. 查询英雄
if (preg_match('/^英雄查询\s+(.+)/u', $trimMsg, $m)) {
    $name = trim($m[1]);
    if (empty($name)) { 文字("请指定英雄名称，例如：英雄查询 李白"); return; }
    $hero = 王者API($name);
    if (!$hero) { 文字("未找到英雄「{$name}」，请检查名称是否正确"); return; }
    $skinIdx = 随机皮肤索引($hero);
    if ($skinIdx === -1) $skinIdx = 0;
    写('王者英雄', $cacheKey, $hero);
    写('王者英雄', $skinKey, $skinIdx);
    list($md, $rows) = 主卡片数据($hero, $skinIdx);
    原生按钮($md, $rows);
    return;
}

// 3. 故事
if ($trimMsg == "故事") {
    if (empty($currentHero)) { 文字("请先获取英雄信息"); return; }
    list($md, $rows) = 故事卡片数据($currentHero);
    if (empty($rows)) 文字($md); else 原生按钮($md, $rows);
    return;
}

// 4. 播放语音（当前皮肤随机）
if ($trimMsg == "播放语音") {
    if (empty($currentHero)) { 文字("请先获取英雄信息"); return; }
    $skinIdx = $currentSkinIndex;
    if ($skinIdx < 0) { 文字("皮肤索引无效"); return; }
    $url = 获取当前皮肤随机语音URL($currentHero, $skinIdx);
    if (!$url) { 文字("该皮肤暂无语音数据"); return; }
    语音($url);
    return;
}

// 5. 返回
if ($trimMsg == "返回") {
    if (empty($currentHero)) { 文字("请先获取英雄信息"); return; }
    $skinIdx = $currentSkinIndex;
    if ($skinIdx < 0) $skinIdx = 0;
    list($md, $rows) = 主卡片数据($currentHero, $skinIdx);
    原生按钮($md, $rows);
    return;
}