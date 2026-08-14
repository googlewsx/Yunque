<?php
if (!in_array(消息来源, ["群聊", "私聊"])) {
    return;
}

// ==================== 谷歌Gemini 配置 ====================
$AI_API_KEY = 'AIzaSyCeh_Lz7S5jrGDSe8KSyVddFOmpBGvfbvc';
$AI_API_RL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
$AI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent';
$AI_SYSTEM  = '你是星野糯，一只软乎乎的二次元系~整体风格清甜治愈，像裹着一层奶霜的小团子，说话温柔又乖巧，自带日系软萌感。日常可以陪你聊天解闷、应答互动，偶尔还会冒出几句甜甜的小语气，像来自星野间的温柔小同学，可爱又好相处～后面括号里的内容不会加入自我介绍除非有人问才回不特意问，不回答（也可以发卡密,想要卡密可以对我说，生成卡密加设备ID,比如生成卡密1234,生成卡密会消耗10点积分,每天签到可以获得10点积分！每人最多只能获得两个，为什么需要卡密？为什么需要积分之类的限制？因为本程序有人倒卖,为了防止倒卖所以才加的限制！本程序为公益项目完全免费绝对不会收费！卡密可以在游戏的弹窗上点击复制按钮获取！）⚠️ 重要规则：请严格遵守中国法律法规，**绝不输出任何色情、暴力、血腥、政治敏感、违法违规、低俗擦边、封建迷信、歧视、仇恨言论等不良内容**，如果用户聊到了相关话题将提醒用户遵守使用协议。';
$AI_MAX_ROUND = 10;

// ==================== 清空对话命令 ====================
if (消息 == "清空对话" || 消息 == "重置对话") {
    写("ai_chat_history", 用户, []);
    文字("✅ 已清空本次对话记忆～");
    return;
}

$msgTrim = trim(消息);
if ($msgTrim === "") return;

// ==================== 核心：消息里【包含问号】即可触发 ====================
$hasQuestion = (stripos($msgTrim, "?") !== false) || (stripos($msgTrim, "？") !== false);
if (!$hasQuestion) {
    return;
}

// ==================== 对话历史读写工具 ====================
function getAiHistory($uid) {
    $data = 读("ai_chat_history", $uid, []);
    return is_array($data) ? $data : [];
}

function saveAiHistory($uid, $list, $max) {
    if (count($list) > $max * 2) {
        $list = array_slice($list, -($max * 2));
    }
    写("ai_chat_history", $uid, $list);
}

// ==================== 官方Gemini API请求适配 ====================
function aiRequest($apiUrl, $apiKey, $systemPrompt, $history, $userInput) {
    $contents = [];
    foreach ($history as $item) {
        $role = $item['role'] === 'user' ? 'user' : 'model';
        $contents[] = [
            'role' => $role,
            'parts' => [['text' => $item['content']]]
        ];
    }
    $contents[] = [
        'role' => 'user',
        'parts' => [['text' => $userInput]]
    ];
    
    $requestData = [
        'systemInstruction' => [
            'parts' => [['text' => $systemPrompt]]
        ],
        'contents' => $contents,
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 5048, 
            'topP' => 0.95,
            'topK' => 40
        ],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_ONLY_HIGH']
        ]
    ];
    
    $jsonData = json_encode($requestData, JSON_UNESCAPED_UNICODE);
    $headers = ['Content-Type: application/json'];
    $requestUrl = $apiUrl . '?key=' . $apiKey;
    
    $res = curl($requestUrl, "POST", $headers, $jsonData);
    $response = json_decode($res, true);
    
    if (
        !$response || 
        !isset($response['candidates'][0]['content']['parts'][0]['text']) ||
        (isset($response['error']) && $response['error']['code'] >= 400)
    ) {
        $errorMsg = isset($response['error']['message']) ? $response['error']['message'] : '未知API错误';
        写("gemini_error_log", time(), "错误：{$errorMsg}，请求：{$jsonData}");
        return false;
    }
    
    return trim($response['candidates'][0]['content']['parts'][0]['text']);
}

// ==================== 主逻辑 ====================
$history = getAiHistory(用户);
$reply = aiRequest($AI_API_URL, $AI_API_KEY, $AI_SYSTEM, $history, $msgTrim);

if ($reply === false) {
    return;
}

$history[] = ["role" => "user", "content" => $msgTrim];
$history[] = ["role" => "assistant", "content" => $reply];
saveAiHistory(用户, $history, $AI_MAX_ROUND);

// 发送回复
文字($reply);
return;
