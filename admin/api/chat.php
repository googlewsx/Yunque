<?php
// 聊天记录API接口
header('Content-Type: application/json');

// mbstring 缺失时的兼容垫片
if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null, $encoding = 'UTF-8') {
        if (function_exists('iconv_substr')) {
            if ($length === null) $length = function_exists('iconv_strlen') ? iconv_strlen($str, $encoding) : strlen($str);
            return iconv_substr($str, $start, $length, $encoding);
        }
        $chars = preg_split('//u', (string)$str, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($chars)) return '';
        $chars = array_slice($chars, $start, $length === null ? null : $length);
        return implode('', $chars);
    }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($str, $encoding = 'UTF-8') {
        return strtolower((string)$str);
    }
}

$type = $_REQUEST["type"] ?? "";
$appid = $_REQUEST["appid"] ?? "";
$name = $_REQUEST["name"] ?? date("Y-m-d").".log";
$path = dirname(__DIR__, 2)."/Log/{$appid}/".$name;

/*
①获取聊天记录列表（按群聊/私聊分组）
type = list
appid
name = 日志文件名

②获取指定群聊/私聊的聊天记录
type = messages
appid
name = 日志文件名
chat_type = group/private
chat_id = 群聊ID或用户ID
*/

/* ------------------------------------------------------------
 * 工具函数
 * --------------------------------------------------------- */
/** 构建卡片消息体（type: 文卡 / 跳转卡 / 大图） */
function 云雀_卡片JSON($card) {
    $card = is_array($card) ? $card : [];
    $type = (string)($card['type'] ?? '文卡');
    $title = (string)($card['title'] ?? '');
    $desc = (string)($card['desc'] ?? '');
    $image = (string)($card['image'] ?? '');
    $url = (string)($card['url'] ?? '');
    $prompt = (string)($card['prompt'] ?? '云雀 Yunque');
    $sub = (string)($card['subtitle'] ?? $prompt);

    if ($type === '跳转卡') {
        return [
            "msg_type" => 3,
            "ark" => [
                "template_id" => 24,
                "kv" => [
                    ["key" => "#DESC#", "value" => $sub],
                    ["key" => "#PROMPT#", "value" => $prompt],
                    ["key" => "#TITLE#", "value" => $title],
                    ["key" => "#METADESC#", "value" => $desc],
                    ["key" => "#IMG#", "value" => $image],
                    ["key" => "#LINK#", "value" => $url],
                    ["key" => "#SUBTITLE#", "value" => $sub],
                ],
            ],
        ];
    }
    if ($type === '大图') {
        return [
            "msg_type" => 3,
            "ark" => [
                "template_id" => 37,
                "kv" => [
                    ["key" => "#METATITLE#", "value" => $title],
                    ["key" => "#METASUBTITLE#", "value" => $desc],
                    ["key" => "#PROMPT#", "value" => $prompt],
                    ["key" => "#METACOVER#", "value" => $image],
                ],
            ],
        ];
    }
    // 文卡（template 23）：lines = [{text,url}]，可多行
    $lines = $card['lines'] ?? [];
    if (!is_array($lines) || empty($lines)) {
        $lines = [['text' => $title, 'url' => $url]];
    }
    $list_items = [];
    foreach ($lines as $item) {
        if (isset($item['url']) && $item['url'] !== '') {
            $list_items[] = ["obj_kv" => [
                ["key" => "desc", "value" => (string)($item['text'] ?? '')],
                ["key" => "link", "value" => (string)$item['url']],
            ]];
        } else {
            $list_items[] = ["obj_kv" => [["key" => "desc", "value" => (string)($item['text'] ?? '')]]];
        }
    }
    return [
        "msg_type" => 3,
        "ark" => [
            "template_id" => 23,
            "kv" => [
                ["key" => "#DESC#", "value" => $prompt],
                ["key" => "#PROMPT#", "value" => $prompt],
                ["key" => "#LIST#", "obj" => $list_items],
            ],
        ],
    ];
}

/** 发送卡片消息（兼容主动/被动） */
function 云雀_发送卡片($scene, $target, $card, $active) {
    $json = 云雀_卡片JSON($card);
    return 云雀API($scene, $target, $json, $active);
}

/** 将一条日志记录规整为会话列表的预览文本 */
function 消息预览($data) {
    if (!is_array($data)) return '';
    // 机器人发送记录
    if (($data['direction'] ?? '') === '发送') {
        $ct = (string)($data['content_type'] ?? '文字');
        if (strpos($ct, '图片') !== false) return '[图片]';
        if (strpos($ct, '视频') !== false) return '[视频]';
        if (strpos($ct, '语音') !== false || strpos($ct, '音频') !== false) return '[语音]';
        if (strpos($ct, '文件') !== false) return '[文件]';
        if (strpos($ct, '卡') !== false) return '[卡片]';
        if (strpos($ct, '按钮') !== false) return '[按钮]';
        $c = $data['content'] ?? '';
        if (is_array($c)) $c = json_encode($c, JSON_UNESCAPED_UNICODE);
        return mb_substr(trim((string)$c), 0, 80, 'UTF-8');
    }
    // 收到的消息
    $d = $data['d'] ?? [];
    $content = trim((string)($d['content'] ?? ''), "/ ");
    $att = $d['attachments'] ?? [];
    if (is_array($att) && count($att) > 0) {
        $hasImg = false;
        foreach ($att as $a) {
            if (strpos((string)($a['content_type'] ?? ''), 'image/') === 0) { $hasImg = true; break; }
        }
        if ($hasImg) return '[图片]';
    }
    return mb_substr($content, 0, 80, 'UTF-8');
}

/** 宽容提取消息里的图片链接：attachments 各字段 + 文本中的图片链接 */
function 云雀_提取图片($attachments, $content = '') {
    $urls = [];
    if (is_array($attachments)) {
        foreach ($attachments as $a) {
            if (!is_array($a)) continue;
            $url = (string)($a['url'] ?? '');
            if ($url === '') continue;
            $ct = (string)($a['content_type'] ?? '');
            $type = (string)($a['type'] ?? '');
            $isImg = $ct !== '' ? strpos($ct, 'image/') === 0 : (in_array(strtolower($type), ['image', 'img'], true));
            if (!$isImg && $ct === '' && preg_match('/\.(png|jpe?g|gif|webp)(\?|$)/i', $url)) $isImg = true;
            if ($isImg && !in_array($url, $urls, true)) $urls[] = $url;
        }
    }
    if ($content !== '' && preg_match('/https?:\/\/[^\s"\'\)]+\.(png|jpe?g|gif|webp)([?"\'\s]|$)/i', $content, $m2)) {
        $u = rtrim(trim($m2[0]), '"\'');
        if (!in_array($u, $urls, true)) $urls[] = $u;
    }
    return $urls;
}
/** 从 Markdown 文本中提取图片链接 ![alt](url)，兼容带尺寸标注的写法 */
function 云雀_提取Markdown图片($content) {
    $urls = [];
    if ($content === '' || $content === null) return $urls;
    // 匹配 ![alt text](url) 以及 ![alt #width #height](url)
    if (preg_match_all('/!\[[^\]]*\]\((https?:\/\/[^\s\)]+)\)/i', (string)$content, $matches)) {
        foreach ($matches[1] as $u) {
            $u = trim($u);
            if ($u !== '' && !in_array($u, $urls, true)) $urls[] = $u;
        }
    }
    // 同时提取纯文本中的图片直链（非 markdown 语法）
    if (preg_match_all('/https?:\/\/[^\s"\'\)<>]+\.(png|jpe?g|gif|webp|bmp)([?"\'\s<>)#]|$)/i', (string)$content, $m2)) {
        foreach ($m2[0] as $u) {
            $u = rtrim(trim($u), '"\')#,');
            if ($u !== '' && !in_array($u, $urls, true)) $urls[] = $u;
        }
    }
    return $urls;
}

/**
 * 后台群管理操作：载入框架运行环境并定义必要常量。
 * 返回调用前的原始工作目录（调用方负责 chdir 恢复）。
 */
function 云雀_后台框架($appid) {
    $frameworkRoot = dirname(dirname(__DIR__));
    $originalDir = getcwd();
    chdir($frameworkRoot);
    require_once $frameworkRoot . "/function.php";
    require_once $frameworkRoot . "/bot.php";
    if (!defined('appid')) define('appid', $appid);
    $mainJsonPath = $frameworkRoot . '/main.json';
    if (is_file($mainJsonPath)) {
        $mainData = json_decode(@file_get_contents($mainJsonPath), true);
        if (is_array($mainData) && isset($mainData[$appid])) {
            if (!defined('secret') && isset($mainData[$appid]['secret'])) define('secret', $mainData[$appid]['secret']);
            if (!defined('type') && isset($mainData[$appid]['type'])) define('type', $mainData[$appid]['type']);
        }
    }
    return $originalDir;
}

/** 解析群管理接口返回中的错误信息 */
function 云雀_操作错误($result) {
    $decoded = json_decode($result, true);
    if (is_array($decoded) && isset($decoded['code']) && $decoded['code'] != 0) {
        return $decoded['message'] ?? ($decoded['msg'] ?? '操作失败');
    }
    return '';
}

/** 群名缓存文件路径（按机器人分开缓存） */
function 云雀_群名缓存文件($appid) {
    $dir = dirname(__DIR__) . "/data";
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir . "/group_names_{$appid}.json";
}

/** 读取群名缓存 map */
function 云雀_读群名缓存($appid) {
    $file = 云雀_群名缓存文件($appid);
    $map = json_decode(@file_get_contents($file), true);
    return is_array($map) ? $map : [];
}

/** 写入群名缓存 map */
function 云雀_写群名缓存($appid, $map) {
    @file_put_contents(云雀_群名缓存文件($appid), json_encode($map, JSON_UNESCAPED_UNICODE));
}

/**
 * 获取群名称：优先读缓存，未命中则调用官方群信息接口并写入缓存。
 * 接口无权（如错误码 11253 仅白名单机器人可用）或失败时返回空串。
 */
function 云雀_获取群名($appid, $group) {
    if ($group === '') return '';
    $map = 云雀_读群名缓存($appid);
    if (isset($map[$group]) && $map[$group] !== '') return $map[$group];
    static $loaded = false;
    if (!$loaded) {
        云雀_后台框架($appid);
        $loaded = true;
    }
    $name = '';
    try {
        $g = 群信息($group);
        $name = is_array($g) ? (string)($g['group_name'] ?? '') : '';
    } catch (Throwable $e) {
        $name = '';
    }
    if ($name !== '') {
        $map[$group] = $name;
        云雀_写群名缓存($appid, $map);
    }
    return $name;
}

switch ($type) {
    case "list":
        // 获取所有聊天会话列表（群聊和私聊）
        if (!is_file($path)) {
            echo json_encode([
                "code" => 404,
                "msg" => "日志文件不存在"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $content = file_get_contents($path);
        if (empty($content)) {
            echo json_encode([
                "code" => 200,
                "groups" => [],
                "privates" => []
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $content = explode("\n", $content);
        $groups = []; // 群聊列表
        $privates = []; // 私聊列表
        
        foreach ($content as $value) {
            if (preg_match('/^\[([^\]]+)\]\s*(.*)$/', $value, $matches)) {
                $time = $matches[1];
                $json = $matches[2];
                
                if ($json == "重复数据") {
                    continue;
                }
                
                try {
                    $data = json_decode($json, true);
                    if (!is_array($data)) continue;
                    
                    $eventType = $data["t"] ?? "";
                    
                    // 处理用户消息
                    if ($eventType == "GROUP_AT_MESSAGE_CREATE" || $eventType == "GROUP_MESSAGE_CREATE") {
                        // 群聊ID可能在group_id或group_openid字段
                        $groupId = $data["d"]["group_id"] ?? $data["d"]["group_openid"] ?? "";
                        if ($groupId && !isset($groups[$groupId])) {
                            $groups[$groupId] = [
                                "id" => $groupId,
                                "type" => "group",
                                "last_message_time" => $time,
                                "last_message" => 消息预览($data),
                                "message_count" => 0
                            ];
                        }
                        if ($groupId) {
                            $groups[$groupId]["message_count"]++;
                            if (strtotime($time) > strtotime($groups[$groupId]["last_message_time"])) {
                                $groups[$groupId]["last_message_time"] = $time;
                                $groups[$groupId]["last_message"] = 消息预览($data);
                            }
                        }
                    } elseif ($eventType == "C2C_MESSAGE_CREATE") {
                        // 机器人自己发出的私聊消息回传时 author.id 是机器人自己，无法据此确定会话方，跳过
                        if (!empty($data["d"]["author"]["bot"])) {
                            continue;
                        }
                        $userId = $data["d"]["author"]["id"] ?? "";
                        if ($userId && !isset($privates[$userId])) {
                            $privates[$userId] = [
                                "id" => $userId,
                                "type" => "private",
                                "last_message_time" => $time,
                                "last_message" => 消息预览($data),
                                "message_count" => 0
                            ];
                        }
                        if ($userId) {
                            $privates[$userId]["message_count"]++;
                            if (strtotime($time) > strtotime($privates[$userId]["last_message_time"])) {
                                $privates[$userId]["last_message_time"] = $time;
                                $privates[$userId]["last_message"] = 消息预览($data);
                            }
                        }
                    } elseif ($eventType == "GROUP_ADD_ROBOT" || $eventType == "GROUP_DEL_ROBOT") {
                        $groupId = $data["d"]["group_openid"] ?? "";
                        if ($groupId && !isset($groups[$groupId])) {
                            $groups[$groupId] = [
                                "id" => $groupId,
                                "type" => "group",
                                "last_message_time" => $time,
                                "last_message" => $eventType == "GROUP_ADD_ROBOT" ? "[加群事件]" : "[退群事件]",
                                "message_count" => 0
                            ];
                        }
                    } elseif ($eventType == "FRIEND_ADD") {
                        // 好友添加事件
                        $userId = $data["d"]["openid"] ?? $data["d"]["author"]["id"] ?? "";
                        if ($userId && !isset($privates[$userId])) {
                            $privates[$userId] = [
                                "id" => $userId,
                                "type" => "private",
                                "last_message_time" => $time,
                                "last_message" => "[加好友事件]",
                                "message_count" => 0
                            ];
                        }
                    } elseif ($eventType == "FRIEND_DEL") {
                        // 好友删除事件
                        $userId = $data["d"]["openid"] ?? $data["d"]["author"]["id"] ?? "";
                        if ($userId && !isset($privates[$userId])) {
                            $privates[$userId] = [
                                "id" => $userId,
                                "type" => "private",
                                "last_message_time" => $time,
                                "last_message" => "[被删除好友]",
                                "message_count" => 0
                            ];
                        }
                    } elseif (isset($data["direction"]) && $data["direction"] === "发送") {
                        // 机器人发送记录（新日志结构）
                        $sourceType = $data["source_type"] ?? "";
                        $targetId = $data["target_id"] ?? "";
                        $sendContent = is_array($data["content"] ?? null) ? json_encode($data["content"], JSON_UNESCAPED_UNICODE) : ($data["content"] ?? "");
                        // 群聊相关 source_type：群聊、互动、成员入群等（排除私聊）
                        if ($sourceType != "私聊" && $targetId) {
                            if (!isset($groups[$targetId])) {
                                $groups[$targetId] = [
                                    "id" => $targetId,
                                    "type" => "group",
                                    "last_message_time" => $time,
                                    "last_message" => 消息预览($data),
                                    "message_count" => 0
                                ];
                            }
                            $groups[$targetId]["message_count"]++;
                            if (strtotime($time) > strtotime($groups[$targetId]["last_message_time"])) {
                                $groups[$targetId]["last_message_time"] = $time;
                                $groups[$targetId]["last_message"] = 消息预览($data);
                            }
                        } elseif ($sourceType == "私聊" && $targetId) {
                            if (!isset($privates[$targetId])) {
                                $privates[$targetId] = [
                                    "id" => $targetId,
                                    "type" => "private",
                                    "last_message_time" => $time,
                                    "last_message" => 消息预览($data),
                                    "message_count" => 0
                                ];
                            }
                            $privates[$targetId]["message_count"]++;
                            if (strtotime($time) > strtotime($privates[$targetId]["last_message_time"])) {
                                $privates[$targetId]["last_message_time"] = $time;
                                $privates[$targetId]["last_message"] = 消息预览($data);
                            }
                        }
                    } elseif ($eventType == "BOT_MESSAGE") {
                        // 机器人发送的消息
                        $botData = $data["d"] ?? [];
                        $source = $botData["source"] ?? "";
                        $target = $botData["target"] ?? "";
                        
                        if ($source == "群聊" && $target) {
                            if (!isset($groups[$target])) {
                                $groups[$target] = [
                                    "id" => $target,
                                    "type" => "group",
                                    "last_message_time" => $time,
                                    "last_message" => 消息预览($data),
                                    "message_count" => 0
                                ];
                            }
                            $groups[$target]["message_count"]++;
                            if (strtotime($time) > strtotime($groups[$target]["last_message_time"])) {
                                $groups[$target]["last_message_time"] = $time;
                                $groups[$target]["last_message"] = 消息预览($data);
                            }
                        } elseif ($source == "私聊" && $target) {
                            if (!isset($privates[$target])) {
                                $privates[$target] = [
                                    "id" => $target,
                                    "type" => "private",
                                    "last_message_time" => $time,
                                    "last_message" => 消息预览($data),
                                    "message_count" => 0
                                ];
                            }
                            $privates[$target]["message_count"]++;
                            if (strtotime($time) > strtotime($privates[$target]["last_message_time"])) {
                                $privates[$target]["last_message_time"] = $time;
                                $privates[$target]["last_message"] = 消息预览($data);
                            }
                        }
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }
        
        // 转换为数组并按时间排序
        $groupsList = array_values($groups);
        $privatesList = array_values($privates);
        
        // 按最后消息时间倒序排序
        usort($groupsList, function($a, $b) {
            return strtotime($b["last_message_time"]) - strtotime($a["last_message_time"]);
        });
        
        usort($privatesList, function($a, $b) {
            return strtotime($b["last_message_time"]) - strtotime($a["last_message_time"]);
        });
        
        // 填充群名称（缓存命中则直接显示，未命中返回空串由前端异步补齐）
        $groupNameMap = 云雀_读群名缓存($appid);
        foreach ($groupsList as $i => $g) {
            $gid = $g["id"];
            $groupsList[$i]["name"] = isset($groupNameMap[$gid]) ? $groupNameMap[$gid] : "";
        }
        
        echo json_encode([
            "code" => 200,
            "groups" => $groupsList,
            "privates" => $privatesList
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case "messages":
        // 获取指定聊天会话的消息记录
        $chatType = $_REQUEST["chat_type"] ?? "";
        $chatId = $_REQUEST["chat_id"] ?? "";
        
        if (empty($chatType) || empty($chatId)) {
            echo json_encode([
                "code" => 400,
                "msg" => "缺少必要参数"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if (!is_file($path)) {
            echo json_encode([
                "code" => 404,
                "msg" => "日志文件不存在"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $content = file_get_contents($path);
        if (empty($content)) {
            echo json_encode([
                "code" => 200,
                "messages" => []
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $content = explode("\n", $content);
        $messages = [];
        $seenMsgIds = []; // 按 message_id 去重，避免同一机器人消息被多种日志格式重复记录
        $previousCardMessages = []; // 存储之前的卡片消息，用于查找视频/语音链接
        
        // 先遍历一遍，收集所有卡片消息的链接
        foreach ($content as $value) {
            if (preg_match('/^\[([^\]]+)\]\s*(.*)$/', $value, $matches)) {
                $time = $matches[1];
                $json = $matches[2];
                
                if ($json == "重复数据") {
                    continue;
                }
                
                try {
                    $data = json_decode($json, true);
                    if (!is_array($data)) continue;
                    
                    $eventType = $data["t"] ?? "";
                    
                    // 收集卡片消息的链接
                    if ($eventType == "BOT_MESSAGE") {
                        $botData = $data["d"] ?? [];
                        $source = $botData["source"] ?? "";
                        $target = $botData["target"] ?? "";
                        
                        if (($source == "群聊" && $chatType == "group" && $target == $chatId) ||
                            ($source == "私聊" && $chatType == "private" && $target == $chatId)) {
                            if (($botData["type"] == "card" || $botData["type"] == "ark") && 
                                isset($botData["card_data"]) && is_array($botData["card_data"])) {
                                foreach ($botData["card_data"] as $cardItem) {
                                    if (isset($cardItem["url"])) {
                                        $previousCardMessages[] = [
                                            "time" => $time,
                                            "url" => $cardItem["url"],
                                            "text" => $cardItem["text"] ?? ""
                                        ];
                                    }
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }
        
        // 重新遍历，处理所有消息
        foreach ($content as $value) {
            if (preg_match('/^\[([^\]]+)\]\s*(.*)$/', $value, $matches)) {
                $time = $matches[1];
                $json = $matches[2];
                
                if ($json == "重复数据") {
                    continue;
                }
                
                try {
                    $data = json_decode($json, true);
                    if (!is_array($data)) continue;
                    
                    $eventType = $data["t"] ?? "";
                    $message = null;
                    
                    if ($chatType == "group") {
                        // 群聊消息
                        if ($eventType == "GROUP_AT_MESSAGE_CREATE" || $eventType == "GROUP_MESSAGE_CREATE") {
                            // 群聊ID可能在group_id或group_openid字段
                            $groupId = $data["d"]["group_id"] ?? $data["d"]["group_openid"] ?? "";
                            if ($groupId == $chatId) {
                                $userId = $data["d"]["author"]["id"] ?? "";
                                $content = trim($data["d"]["content"] ?? "", "/ ");
                                $messageId = $data["d"]["id"] ?? "";
                                $isBot = !empty($data["d"]["author"]["bot"]);
                                // 提取图片
                                $imageUrls = 云雀_提取图片($data["d"]["attachments"] ?? [], $content);

                                if ($isBot) {
                                    // 机器人自己发出的消息（平台回传，author.bot=true）
                                    $message = [
                                        "time" => $time,
                                        "type" => "bot",
                                        "content" => $content,
                                        "message_type" => "text",
                                        "message_id" => $messageId,
                                        "image_url" => null,
                                        "voice_url" => null,
                                        "voice_url_silk" => null,
                                        "video_url" => null,
                                        "card_data" => null,
                                        "image_urls" => $imageUrls,
                                        "emojis" => $data["d"]["emojis"] ?? [],
                                        "attachments" => $data["d"]["attachments"] ?? []
                                    ];
                                } else {
                                    $rawUsername = $data["d"]["author"]["username"] ?? "";
                                    $memberNick = $data["d"]["member"]["nick"] ?? "";
                                    $username = $rawUsername ?: ($memberNick ?: ("用户" . substr($userId, -6)));
                                    $message = [
                                        "time" => $time,
                                        "type" => "user",
                                        "user_id" => $userId,
                                        "username" => $username,
                                        "raw_username" => $rawUsername,
                                        "content" => $content,
                                        "message_id" => $messageId,
                                        "image_urls" => $imageUrls,
                                        "emojis" => $data["d"]["emojis"] ?? [],
                                        "attachments" => $data["d"]["attachments"] ?? []
                                    ];
                                }
                            }
                        } elseif ($eventType == "GROUP_ADD_ROBOT") {
                            $groupId = $data["d"]["group_openid"] ?? "";
                            if ($groupId == $chatId) {
                                $operatorId = $data["d"]["op_member_openid"] ?? "";
                                $message = [
                                    "time" => $time,
                                    "type" => "event",
                                    "event_type" => "group_join",
                                    "operator_id" => $operatorId,
                                    "content" => "加入群聊"
                                ];
                            }
                        } elseif ($eventType == "GROUP_DEL_ROBOT") {
                            $groupId = $data["d"]["group_openid"] ?? "";
                            if ($groupId == $chatId) {
                                $operatorId = $data["d"]["op_member_openid"] ?? "";
                                $message = [
                                    "time" => $time,
                                    "type" => "event",
                                    "event_type" => "group_leave",
                                    "operator_id" => $operatorId,
                                    "content" => "退出群聊"
                                ];
                            }
                        } elseif (isset($data["direction"]) && $data["direction"] === "发送") {
                            // 兼容新日志结构：发送记录（无 BOT_MESSAGE 事件）
                            $sourceType = $data["source_type"] ?? "";
                            $targetId = $data["target_id"] ?? "";
                            // 群聊相关 source_type：群聊、互动、成员入群等（排除私聊）
                            $isGroupSource = ($sourceType !== "私聊");
                            if ($isGroupSource && $targetId == $chatId) {
                                $rawType = $data["content_type"] ?? "text";
                                $rawTypeNorm = strtolower(trim((string)$rawType));
                                $mappedType = (strpos($rawTypeNorm, 'md') !== false || strpos((string)($data['action'] ?? ''), '原生MD') !== false) ? 'native_md' : ((strpos($rawTypeNorm, '卡') !== false) ? 'card' : 'text');
                                $sendContent = $data["content"] ?? "";
                                // 从内容中提取 markdown 图片和直链图片
                                $imgUrls = 云雀_提取Markdown图片($sendContent);
                                $message = [
                                    "time" => $time,
                                    "type" => "bot",
                                    "content" => $sendContent,
                                    "message_type" => $mappedType,
                                    "message_id" => $data["message_id"] ?? "",
                                    "image_url" => null,
                                    "voice_url" => null,
                                    "voice_url_silk" => null,
                                    "video_url" => null,
                                    "card_data" => null,
                                    "image_urls" => $imgUrls
                                ];
                            }
                        } elseif ($eventType == "BOT_MESSAGE") {
                            $botData = $data["d"] ?? [];
                            $source = $botData["source"] ?? "";
                            $target = $botData["target"] ?? "";
                            
                            if ($source == "群聊" && $target == $chatId) {
                                $message = [
                                    "time" => $time,
                                    "type" => "bot",
                                    "content" => $botData["content"] ?? "",
                                    "message_type" => $botData["type"] ?? "text",
                                    "message_id" => $botData["message_id"] ?? "",
                                    "image_url" => $botData["image_url"] ?? null,
                                    "voice_url" => $botData["voice_url"] ?? null,
                                    "voice_url_silk" => $botData["voice_url_silk"] ?? null,
                                    "video_url" => $botData["video_url"] ?? null,
                                    "card_data" => $botData["card_data"] ?? null
                                ];
                                
                                // 如果是卡片消息，保存起来供后续视频/语音消息使用
                                if (($botData["type"] == "card" || $botData["type"] == "ark") && 
                                    isset($botData["card_data"]) && is_array($botData["card_data"])) {
                                    foreach ($botData["card_data"] as $cardItem) {
                                        if (isset($cardItem["url"])) {
                                            $previousCardMessages[] = [
                                                "time" => $time,
                                                "url" => $cardItem["url"],
                                                "text" => $cardItem["text"] ?? ""
                                            ];
                                        }
                                    }
                                }
                                
                                // 如果视频/语音没有URL，尝试从content中提取链接
                                if (($botData["type"] == "video" || $botData["type"] == "voice") && 
                                    !$message["video_url"] && !$message["voice_url"]) {
                                    // 首先从content中提取链接
                                    if (!empty($botData["content"]) && 
                                        preg_match('/\(?(https?:\/\/[^\s\)]+)\)?/', $botData["content"], $matches)) {
                                        if ($botData["type"] == "video") {
                                            $message["video_url"] = $matches[1];
                                        } elseif ($botData["type"] == "voice") {
                                            $message["voice_url"] = $matches[1];
                                        }
                                    } else {
                                        // 如果content中没有，尝试从最近的卡片消息中获取链接
                                        // 查找时间相近（5秒内）的卡片消息
                                        foreach ($previousCardMessages as $cardMsg) {
                                            $timeDiff = abs(strtotime($time) - strtotime($cardMsg["time"]));
                                            if ($timeDiff <= 5) {
                                                // 检查卡片文本是否包含"视频"或"语音"关键词
                                                $cardText = strtolower($cardMsg["text"]);
                                                if (($botData["type"] == "video" && (strpos($cardText, "视频") !== false || strpos($cardText, "shipin") !== false)) ||
                                                    ($botData["type"] == "voice" && (strpos($cardText, "语音") !== false || strpos($cardText, "音乐") !== false))) {
                                                    if ($botData["type"] == "video") {
                                                        $message["video_url"] = $cardMsg["url"];
                                                    } elseif ($botData["type"] == "voice") {
                                                        $message["voice_url"] = $cardMsg["url"];
                                                    }
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } elseif ($chatType == "private") {
                        // 私聊消息
                        if ($eventType == "C2C_MESSAGE_CREATE") {
                            $userId = $data["d"]["author"]["id"] ?? "";
                            if ($userId == $chatId) {
                                $content = trim($data["d"]["content"] ?? "", "/ ");
                                $messageId = $data["d"]["id"] ?? "";
                                $rawUsername = $data["d"]["author"]["username"] ?? "";
                                $memberNick = $data["d"]["member"]["nick"] ?? "";
                                $username = $rawUsername ?: ($memberNick ?: ("用户" . substr($userId, -6)));
                                
                                // 提取图片
                                $imageUrls = 云雀_提取图片($data["d"]["attachments"] ?? [], $content);
                                
                                $message = [
                                    "time" => $time,
                                    "type" => "user",
                                    "user_id" => $userId,
                                    "username" => $username,
                                    "raw_username" => $rawUsername,
                                    "content" => $content,
                                    "message_id" => $messageId,
                                    "image_urls" => $imageUrls,
                                    "emojis" => $data["d"]["emojis"] ?? [],
                                    "attachments" => $data["d"]["attachments"] ?? []
                                ];
                            }
                        } elseif ($eventType == "FRIEND_ADD") {
                            // 好友添加事件
                            $userId = $data["d"]["openid"] ?? $data["d"]["author"]["id"] ?? "";
                            if ($userId == $chatId) {
                                $message = [
                                    "time" => $time,
                                    "type" => "event",
                                    "event_type" => "friend_add",
                                    "user_id" => $userId,
                                    "content" => "添加了好友"
                                ];
                            }
                        } elseif ($eventType == "FRIEND_DEL") {
                            // 好友删除事件
                            $userId = $data["d"]["openid"] ?? $data["d"]["author"]["id"] ?? "";
                            if ($userId == $chatId) {
                                $message = [
                                    "time" => $time,
                                    "type" => "event",
                                    "event_type" => "friend_delete",
                                    "user_id" => $userId,
                                    "content" => "被删除好友"
                                ];
                            }
                        } elseif (isset($data["direction"]) && $data["direction"] === "发送") {
                            // 兼容新日志结构：发送记录（无 BOT_MESSAGE 事件）
                            $sourceType = $data["source_type"] ?? "";
                            $targetId = $data["target_id"] ?? "";
                            if ($sourceType === "私聊" && $targetId == $chatId) {
                                $rawType = $data["content_type"] ?? "text";
                                $rawTypeNorm = strtolower(trim((string)$rawType));
                                $mappedType = (strpos($rawTypeNorm, 'md') !== false || strpos((string)($data['action'] ?? ''), '原生MD') !== false) ? 'native_md' : ((strpos($rawTypeNorm, '卡') !== false) ? 'card' : 'text');
                                $sendContent = $data["content"] ?? "";
                                // 从内容中提取 markdown 图片和直链图片
                                $imgUrls = 云雀_提取Markdown图片($sendContent);
                                $message = [
                                    "time" => $time,
                                    "type" => "bot",
                                    "content" => $sendContent,
                                    "message_type" => $mappedType,
                                    "message_id" => $data["message_id"] ?? "",
                                    "image_url" => null,
                                    "voice_url" => null,
                                    "voice_url_silk" => null,
                                    "video_url" => null,
                                    "card_data" => null,
                                    "image_urls" => $imgUrls
                                ];
                            }
                        } elseif ($eventType == "BOT_MESSAGE") {
                            $botData = $data["d"] ?? [];
                            $source = $botData["source"] ?? "";
                            $target = $botData["target"] ?? "";
                            
                            if ($source == "私聊" && $target == $chatId) {
                                $message = [
                                    "time" => $time,
                                    "type" => "bot",
                                    "content" => $botData["content"] ?? "",
                                    "message_type" => $botData["type"] ?? "text",
                                    "image_url" => $botData["image_url"] ?? null,
                                    "voice_url" => $botData["voice_url"] ?? null,
                                    "voice_url_silk" => $botData["voice_url_silk"] ?? null,
                                    "video_url" => $botData["video_url"] ?? null,
                                    "card_data" => $botData["card_data"] ?? null
                                ];
                                
                                // 如果是卡片消息，保存起来供后续视频/语音消息使用
                                if (($botData["type"] == "card" || $botData["type"] == "ark") && 
                                    isset($botData["card_data"]) && is_array($botData["card_data"])) {
                                    foreach ($botData["card_data"] as $cardItem) {
                                        if (isset($cardItem["url"])) {
                                            $previousCardMessages[] = [
                                                "time" => $time,
                                                "url" => $cardItem["url"],
                                                "text" => $cardItem["text"] ?? ""
                                            ];
                                        }
                                    }
                                }
                                
                                // 如果视频/语音没有URL，尝试从content中提取链接
                                if (($botData["type"] == "video" || $botData["type"] == "voice") && 
                                    !$message["video_url"] && !$message["voice_url"]) {
                                    // 首先从content中提取链接
                                    if (!empty($botData["content"]) && 
                                        preg_match('/\(?(https?:\/\/[^\s\)]+)\)?/', $botData["content"], $matches)) {
                                        if ($botData["type"] == "video") {
                                            $message["video_url"] = $matches[1];
                                        } elseif ($botData["type"] == "voice") {
                                            $message["voice_url"] = $matches[1];
                                        }
                                    } else {
                                        // 如果content中没有，尝试从最近的卡片消息中获取链接
                                        // 查找时间相近（5秒内）的卡片消息
                                        foreach ($previousCardMessages as $cardMsg) {
                                            $timeDiff = abs(strtotime($time) - strtotime($cardMsg["time"]));
                                            if ($timeDiff <= 5) {
                                                // 检查卡片文本是否包含"视频"或"语音"关键词
                                                $cardText = strtolower($cardMsg["text"]);
                                                if (($botData["type"] == "video" && (strpos($cardText, "视频") !== false || strpos($cardText, "shipin") !== false)) ||
                                                    ($botData["type"] == "voice" && (strpos($cardText, "语音") !== false || strpos($cardText, "音乐") !== false))) {
                                                    if ($botData["type"] == "video") {
                                                        $message["video_url"] = $cardMsg["url"];
                                                    } elseif ($botData["type"] == "voice") {
                                                        $message["voice_url"] = $cardMsg["url"];
                                                    }
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    if ($message) {
                        // 按 message_id 去重（同一机器人消息可能同时被「发送记录」和「平台回传事件」记录）
                        $mid = $message["message_id"] ?? "";
                        if ($mid !== "") {
                            if (isset($seenMsgIds[$mid])) {
                                $message = null;
                            } else {
                                $seenMsgIds[$mid] = true;
                            }
                        }
                        if ($message) $messages[] = $message;
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }
        
        // 按时间正序排序（最早的在前面）
        usort($messages, function($a, $b) {
            return strtotime($a["time"]) - strtotime($b["time"]);
        });
        
        // 增量拉取：传 since=时间 时仅返回该时间之后的新消息（配合前端轮询实时同步）
        $since = trim((string)($_REQUEST["since"] ?? ""));
        if ($since !== '') {
            $messages = array_values(array_filter($messages, function($m) use ($since) {
                return strcmp((string)($m["time"] ?? ""), $since) > 0;
            }));
        }
        
        echo json_encode([
            "code" => 200,
            "messages" => $messages
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case "search":
        // 搜索消息
        $keyword = $_REQUEST["keyword"] ?? "";
        $chatType = $_REQUEST["chat_type"] ?? ""; // 可选：group/private，为空则搜索所有
        
        if (empty($keyword)) {
            echo json_encode([
                "code" => 400,
                "msg" => "搜索关键词不能为空"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if (!is_file($path)) {
            echo json_encode([
                "code" => 404,
                "msg" => "日志文件不存在"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $content = file_get_contents($path);
        if (empty($content)) {
            echo json_encode([
                "code" => 200,
                "results" => []
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $content = explode("\n", $content);
        $results = [];
        $keywordLower = mb_strtolower($keyword, 'UTF-8');
        
        foreach ($content as $value) {
            if (preg_match('/^\[([^\]]+)\]\s*(.*)$/', $value, $matches)) {
                $time = $matches[1];
                $json = $matches[2];
                
                if ($json == "重复数据") {
                    continue;
                }
                
                try {
                    $data = json_decode($json, true);
                    if (!is_array($data)) continue;
                    
                    $eventType = $data["t"] ?? "";
                    $matched = false;
                    $chatId = "";
                    $chatTypeFound = "";
                    $contentText = "";
                    $userId = "";
                    
                    // 检查用户消息
                    if ($eventType == "GROUP_AT_MESSAGE_CREATE" || $eventType == "GROUP_MESSAGE_CREATE") {
                        $chatId = $data["d"]["group_id"] ?? $data["d"]["group_openid"] ?? "";
                        $chatTypeFound = "group";
                        $userId = $data["d"]["author"]["id"] ?? "";
                        $contentText = trim($data["d"]["content"] ?? "", "/ ");
                        
                        // 匹配ID或内容
                        if (stripos($chatId, $keyword) !== false || 
                            stripos($userId, $keyword) !== false ||
                            stripos($contentText, $keyword) !== false) {
                            $matched = true;
                        }
                    } elseif ($eventType == "C2C_MESSAGE_CREATE") {
                        $chatId = $data["d"]["author"]["id"] ?? "";
                        $chatTypeFound = "private";
                        $userId = $chatId;
                        $contentText = trim($data["d"]["content"] ?? "", "/ ");
                        
                        // 匹配ID或内容
                        if (stripos($chatId, $keyword) !== false ||
                            stripos($contentText, $keyword) !== false) {
                            $matched = true;
                        }
                    } elseif (isset($data["direction"]) && $data["direction"] === "发送") {
                        // 机器人发送记录（新日志结构）
                        $source = $data["source_type"] ?? "";
                        $target = $data["target_id"] ?? "";
                        $contentText = is_array($data["content"] ?? null) ? json_encode($data["content"], JSON_UNESCAPED_UNICODE) : ($data["content"] ?? "");

                        if ($source == "群聊") {
                            $chatId = $target;
                            $chatTypeFound = "group";
                        } elseif ($source == "私聊") {
                            $chatId = $target;
                            $chatTypeFound = "private";
                        }

                        if ($chatId && (stripos($chatId, $keyword) !== false ||
                            stripos($contentText, $keyword) !== false)) {
                            $matched = true;
                        }
                    } elseif ($eventType == "BOT_MESSAGE") {
                        $botData = $data["d"] ?? [];
                        $source = $botData["source"] ?? "";
                        $target = $botData["target"] ?? "";
                        $contentText = $botData["content"] ?? "";
                        
                        if ($source == "群聊") {
                            $chatId = $target;
                            $chatTypeFound = "group";
                        } elseif ($source == "私聊") {
                            $chatId = $target;
                            $chatTypeFound = "private";
                        }
                        
                        // 匹配ID或内容
                        if ($chatId && (stripos($chatId, $keyword) !== false ||
                            stripos($contentText, $keyword) !== false)) {
                            $matched = true;
                        }
                    }
                    
                    // 如果指定了chat_type，需要匹配
                    if ($matched && !empty($chatType) && $chatTypeFound != $chatType) {
                        $matched = false;
                    }
                    
                    if ($matched && $chatId) {
                        // 检查是否已存在该聊天
                        $key = $chatTypeFound . "_" . $chatId;
                        if (!isset($results[$key])) {
                            $results[$key] = [
                                "id" => $chatId,
                                "type" => $chatTypeFound,
                                "last_message_time" => $time,
                                "last_message" => mb_substr($contentText, 0, 50, 'UTF-8'),
                                "match_count" => 0
                            ];
                        }
                        $results[$key]["match_count"]++;
                        if (strtotime($time) > strtotime($results[$key]["last_message_time"])) {
                            $results[$key]["last_message_time"] = $time;
                            $results[$key]["last_message"] = mb_substr($contentText, 0, 50, 'UTF-8');
                        }
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }
        
        // 转换为数组并按时间排序
        $resultsList = array_values($results);
        usort($resultsList, function($a, $b) {
            return strtotime($b["last_message_time"]) - strtotime($a["last_message_time"]);
        });
        
        echo json_encode([
            "code" => 200,
            "results" => $resultsList
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case "get_nicknames":
        // 批量获取用户昵称
        $userIds = $_POST["user_ids"] ?? $_REQUEST["user_ids"] ?? "";
        if (empty($userIds)) {
            echo json_encode([
                "code" => 400,
                "msg" => "缺少用户ID列表"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 处理JSON字符串或数组
        if (is_string($userIds)) {
            $userIdsArray = json_decode($userIds, true);
        } else {
            $userIdsArray = $userIds;
        }
        
        if (!is_array($userIdsArray)) {
            echo json_encode([
                "code" => 400,
                "msg" => "用户ID列表格式错误"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $nicknames = [];
        
        // 从日志文件中提取用户昵称
        if (is_file($path)) {
            $content = file_get_contents($path);
            $lines = explode("\n", $content);
            
            foreach ($lines as $line) {
                if (preg_match('/^\[([^\]]+)\]\s*(.*)$/', $line, $matches)) {
                    $json = $matches[2];
                    if ($json == "重复数据") continue;
                    
                    try {
                        $data = json_decode($json, true);
                        if (!is_array($data)) continue;
                        
                        $eventType = $data["t"] ?? "";
                        $userId = "";
                        
                        // 提取用户ID和昵称
                        if ($eventType == "GROUP_AT_MESSAGE_CREATE" || $eventType == "GROUP_MESSAGE_CREATE" || $eventType == "C2C_MESSAGE_CREATE") {
                            $userId = $data["d"]["author"]["id"] ?? "";
                            $rawUsername = $data["d"]["author"]["username"] ?? "";
                            $memberNick = $data["d"]["member"]["nick"] ?? "";
                            $username = $rawUsername ?: $memberNick;
                            
                            if ($userId && in_array($userId, $userIdsArray) && $username) {
                                if (!isset($nicknames[$userId]) || empty($nicknames[$userId])) {
                                    $nicknames[$userId] = $username;
                                }
                            }
                        }
                    } catch (Exception $e) {
                        continue;
                    }
                }
            }
        }
        
        // 对于没有找到昵称的用户，使用默认值
        foreach ($userIdsArray as $userId) {
            if (!isset($nicknames[$userId])) {
                $nicknames[$userId] = "用户" . substr($userId, -6);
            }
        }
        
        echo json_encode([
            "code" => 200,
            "nicknames" => $nicknames
        ], JSON_UNESCAPED_UNICODE);
        break;

    case "send":
        // 从后台聊天页面发送文字消息到指定会话
        $chatType = $_POST["chat_type"] ?? $_REQUEST["chat_type"] ?? "";
        $chatId = $_POST["chat_id"] ?? $_REQUEST["chat_id"] ?? "";
        $sendMethod = $_POST["send_method"] ?? $_REQUEST["send_method"] ?? "text";
        $content = $_POST["content"] ?? $_REQUEST["content"] ?? "";
        $active = in_array((string)($_POST["active"] ?? $_REQUEST["active"] ?? ""), ["1", "true", "主动"], true);
        $mediaFileInfo = (string)($_POST["media_file_info"] ?? $_REQUEST["media_file_info"] ?? "");
        $fileName = (string)($_POST["file_name"] ?? $_REQUEST["file_name"] ?? "");
        $cardJson = (string)($_POST["card"] ?? $_REQUEST["card"] ?? "");

        if (empty($appid) || empty($chatType) || empty($chatId)) {
            echo json_encode([
                "code" => 400,
                "msg" => "缺少必要参数"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!in_array($sendMethod, ['text', 'card', 'native_md', 'image', 'video', 'audio', 'file'])) {
            echo json_encode([
                "code" => 400,
                "msg" => "不支持的消息类型"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 媒体类消息必须已有上传成功的 file_info，或提供 URL / base64
        if (in_array($sendMethod, ['image', 'video', 'audio', 'file']) && $mediaFileInfo === '' && trim($content) === '') {
            echo json_encode([
                "code" => 400,
                "msg" => "请先上传媒体文件"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 文本类内容不能为空
        if (in_array($sendMethod, ['text', 'native_md']) && trim($content) === "") {
            echo json_encode([
                "code" => 400,
                "msg" => "消息内容不能为空"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 卡片消息：优先使用 JSON 模板（title/desc/image/url/type/lines），否则按文本行构建
        $cardData = null;
        if ($sendMethod === 'card') {
            if ($cardJson !== '') {
                $cardData = json_decode($cardJson, true);
                if (!is_array($cardData)) {
                    echo json_encode([
                        "code" => 400,
                        "msg" => "卡片数据格式错误"
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            } else {
                $lines = [];
                foreach (explode("\n", $content) as $ln) {
                    $ln = trim($ln);
                    if ($ln === '') continue;
                    $item = ['text' => $ln];
                    if (preg_match('/^(.+?)\|(https?:\/\/.+)$/', $ln, $m)) {
                        $item['text'] = trim($m[1]);
                        $item['url'] = trim($m[2]);
                    }
                    $lines[] = $item;
                }
                $cardData = ['type' => '文卡', 'title' => trim($content), 'lines' => $lines];
            }
        }
        $content = trim($content);

        // 定义必要的常量，供 bot.php / function.php 使用
        if (!defined('appid')) {
            define('appid', $appid);
        }
        
        // 从 main.json 读取 secret 和 type
        $mainJsonPath = dirname(dirname(__DIR__)) . '/main.json';
        if (is_file($mainJsonPath)) {
            $mainContent = file_get_contents($mainJsonPath);
            $mainData = json_decode($mainContent, true);
            if (isset($mainData[$appid])) {
                $botConfig = $mainData[$appid];
                if (!defined('secret') && isset($botConfig['secret'])) {
                    define('secret', $botConfig['secret']);
                }
                if (!defined('type') && isset($botConfig['type'])) {
                    define('type', $botConfig['type']);
                }
            }
        }

        // 引入机器人发送函数
        // admin/api/chat.php -> admin -> 我的框架
        $frameworkRoot = dirname(dirname(__DIR__));
        $botFile = $frameworkRoot . '/bot.php';
        $funcFile = $frameworkRoot . '/function.php';
        
        // 切换工作目录到框架根目录，确保 wlog() 等函数使用正确的相对路径
        $originalDir = getcwd();
        chdir($frameworkRoot);
        
        if (is_file($funcFile)) {
            require_once $funcFile;
        }
        if (!is_file($botFile)) {
            chdir($originalDir); // 恢复原目录
            echo json_encode([
                "code" => 500,
                "msg" => "机器人核心文件不存在：" . $botFile
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        require_once $botFile;

        // 聊天记录发送锚点：优先按钮回调 event_id（可跨时段），其次最近 msg_id（299秒）
        // 主动消息无需锚点，直接跳过锚点查找
        $recentEventId = null;
        $recentMsgId = null;
        $recentMsgTime = null;

        if (!$active && is_file($path)) {
            $logContent = file_get_contents($path);
            $lines = explode("\n", $logContent);

            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $line = trim($lines[$i]);
                if (empty($line) || $line === "重复数据") continue;

                if (!preg_match('/^\[([^\]]+)\]\s*(.*)$/', $line, $matches)) continue;

                $timestamp = $matches[1];
                $jsonStr = $matches[2];

                try {
                    $data = json_decode($jsonStr, true);
                    if (!is_array($data)) continue;

                    $eventType = $data["t"] ?? "";

                    if ($chatType === 'group') {
                        $groupId = $data["d"]["group_openid"] ?? $data["d"]["group_id"] ?? "";
                        if ($groupId !== $chatId) continue;

                        if ($eventType === "INTERACTION_CREATE" && empty($recentEventId)) {
                            $eid = $data["id"] ?? "";
                            if (!empty($eid)) {
                                $recentEventId = $eid;
                                // 找到最新 event_id 后继续向前找一条 msg_id 作为兜底
                                continue;
                            }
                        }

                        if (($eventType === "GROUP_AT_MESSAGE_CREATE" || $eventType === "GROUP_MESSAGE_CREATE") && empty($recentMsgId)) {
                            $mid = $data["d"]["id"] ?? "";
                            if (!empty($mid)) {
                                $recentMsgId = $mid;
                                $recentMsgTime = $timestamp;
                            }
                        }
                    } else {
                        $userId = $data["d"]["openid"] ?? $data["d"]["author"]["id"] ?? "";
                        if ($userId !== $chatId) continue;

                        if ($eventType === "INTERACTION_CREATE" && empty($recentEventId)) {
                            $eid = $data["id"] ?? "";
                            if (!empty($eid)) {
                                $recentEventId = $eid;
                                continue;
                            }
                        }

                        if ($eventType === "C2C_MESSAGE_CREATE" && empty($recentMsgId)) {
                            $mid = $data["d"]["id"] ?? "";
                            if (!empty($mid)) {
                                $recentMsgId = $mid;
                                $recentMsgTime = $timestamp;
                            }
                        }
                    }

                    // 两个锚点都拿到就停
                    if (!empty($recentEventId) && !empty($recentMsgId)) {
                        break;
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }

        // 无 event_id 时，msg_id 需在299秒内（仅被动回复需要锚点，主动消息跳过校验）
        if (!$active) {
            if (empty($recentEventId) && empty($recentMsgId)) {
                chdir($originalDir);
                echo json_encode([
                    "code" => 400,
                    "msg" => "未找到可用锚点（event_id/msg_id），请先触发按钮交互或先发一条消息"
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if (empty($recentEventId) && !empty($recentMsgId)) {
                $msgTimestamp = strtotime($recentMsgTime);
                $timeDiff = time() - $msgTimestamp;
                if ($timeDiff > 299) {
                    chdir($originalDir);
                    echo json_encode([
                        "code" => 400,
                        "msg" => "仅找到过期msg_id（超过299秒），请先触发按钮交互或发送新消息"
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
        }

        try {
            // 定义必要常量：有event_id优先走event，其次使用有效msg_id
            if (!defined('消息ID')) {
                define('消息ID', !empty($recentMsgId) ? $recentMsgId : ($active ? ('ACTIVE_FALLBACK_' . time()) : ('ROBOT1.0_EVENT_FALLBACK_' . time())));
            }
            if (!defined('事件ID') && !empty($recentEventId)) {
                define('事件ID', $recentEventId);
            }
            if (!defined('消息来源')) {
                define('消息来源', $chatType === 'group' ? '群聊' : '私聊');
            }
            if (!defined('来源')) {
                define('来源', $chatId);
            }
            if (!defined('用户')) {
                define('用户', $chatId);
            }
            
            // 根据 send_method + 主动/被动开关 调用不同的发送函数
            $sceneName = $chatType === 'group' ? '群聊' : '私聊';
            $result = null;

            if (!$active) {
                // ===== 被动回复（需要有 event_id / msg_id 锚点）=====
                switch ($sendMethod) {
                    case 'image':
                        记录发送("发送图片", $chatId, $content !== '' ? $content : '[图片]', "图片", ['source_type' => $sceneName, 'active' => false, 'media_file_info' => $mediaFileInfo]);
                        $result = $mediaFileInfo !== '' ? 云雀媒体($mediaFileInfo, $sceneName, $chatId, $content, false) : 图片($content, '');
                        break;
                    case 'video':
                        记录发送("发送视频", $chatId, '[视频]', "视频", ['source_type' => $sceneName, 'active' => false, 'media_file_info' => $mediaFileInfo]);
                        $result = $mediaFileInfo !== '' ? 云雀媒体($mediaFileInfo, $sceneName, $chatId, '', false) : 视频($content);
                        break;
                    case 'audio':
                        记录发送("发送语音", $chatId, '[语音]', "语音", ['source_type' => $sceneName, 'active' => false, 'media_file_info' => $mediaFileInfo]);
                        $result = $mediaFileInfo !== '' ? 云雀媒体($mediaFileInfo, $sceneName, $chatId, '', false) : 语音($content);
                        break;
                    case 'file':
                        记录发送("发送文件", $chatId, $fileName !== '' ? "[文件: {$fileName}]" : '[文件]', "文件", ['source_type' => $sceneName, 'active' => false, 'media_file_info' => $mediaFileInfo]);
                        $result = $mediaFileInfo !== '' ? 云雀媒体($mediaFileInfo, $sceneName, $chatId, '', false) : 文件($content, $fileName);
                        break;
                    case 'card':
                        记录发送("发送卡片", $chatId, '[卡片]', "卡片", ['source_type' => $sceneName, 'active' => false]);
                        $result = 云雀_发送卡片($sceneName, $chatId, $cardData, false);
                        break;
                    case 'native_md':
                        $result = 原生MD($content);
                        break;
                    default:
                        $result = 文字($content);
                }
            } else {
                // ===== 主动消息（无需锚点）=====
                switch ($sendMethod) {
                    case 'text':
                        $result = $sceneName === '私聊' ? 主动私聊($chatId, $content) : 主动文字($chatId, $content, '群聊');
                        break;
                    case 'native_md':
                        $result = 主动MD($chatId, $content, $sceneName);
                        break;
                    case 'image':
                        记录发送("主动图片", $chatId, $content !== '' ? $content : '[图片]', "图片", ['source_type' => $sceneName, 'active' => true, 'media_file_info' => $mediaFileInfo]);
                        $result = $mediaFileInfo !== '' ? 云雀媒体($mediaFileInfo, $sceneName, $chatId, $content, true) : 主动图片($chatId, $content, '', $sceneName);
                        break;
                    case 'video':
                        记录发送("主动视频", $chatId, '[视频]', "视频", ['source_type' => $sceneName, 'active' => true, 'media_file_info' => $mediaFileInfo]);
                        $result = $mediaFileInfo !== '' ? 云雀媒体($mediaFileInfo, $sceneName, $chatId, '', true) : 主动视频($chatId, $content, $sceneName);
                        break;
                    case 'audio':
                        记录发送("主动语音", $chatId, '[语音]', "语音", ['source_type' => $sceneName, 'active' => true, 'media_file_info' => $mediaFileInfo]);
                        $result = $mediaFileInfo !== '' ? 云雀媒体($mediaFileInfo, $sceneName, $chatId, '', true) : 主动语音($chatId, $content, $sceneName);
                        break;
                    case 'file':
                        记录发送("主动文件", $chatId, $fileName !== '' ? "[文件: {$fileName}]" : '[文件]', "文件", ['source_type' => $sceneName, 'active' => true, 'media_file_info' => $mediaFileInfo]);
                        $result = $mediaFileInfo !== '' ? 云雀媒体($mediaFileInfo, $sceneName, $chatId, '', true) : 主动文件($chatId, $content, $fileName, $sceneName);
                        break;
                    case 'card':
                        记录发送("主动卡片", $chatId, '[卡片]', "卡片", ['source_type' => $sceneName, 'active' => true]);
                        $result = 云雀_发送卡片($sceneName, $chatId, $cardData, true);
                        break;
                    default:
                        $result = $sceneName === '私聊' ? 主动私聊($chatId, $content) : 主动文字($chatId, $content, '群聊');
                }
            }
            $decoded = @json_decode($result, true);

            // 检查返回结果
            if (is_array($decoded)) {
                if (isset($decoded['code']) && $decoded['code'] != 0) {
                    $msg = $decoded['message'] ?? ($decoded['msg'] ?? '发送失败');
                    chdir($originalDir); // 恢复原目录
                    echo json_encode([
                        "code" => 500,
                        "msg" => "发送失败: " . $msg
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            chdir($originalDir); // 恢复原目录
            echo json_encode([
                "code" => 200,
                "msg" => $active ? "发送成功（主动消息）" : (!empty($recentEventId) ? "发送成功（event_id锚点）" : "发送成功（msg_id锚点）")
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            chdir($originalDir); // 恢复原目录
            echo json_encode([
                "code" => 500,
                "msg" => "发送异常: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
    
    case "all_users":
        // 遍历当天聊天记录，返回去重后的用户列表（用于「从消息里选主人」）
        if (!is_file($path)) {
            echo json_encode(["code" => 200, "users" => []], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $users = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line === '重复数据') continue;
            if (!preg_match('/^\[([^\]]+)\]\s*(.*)$/', $line, $m)) continue;
            try {
                $d = json_decode($m[2], true);
                if (!is_array($d)) continue;
                $ev = $d['t'] ?? '';
                $uid = '';
                $uname = '';
                if ($ev === 'GROUP_AT_MESSAGE_CREATE' || $ev === 'GROUP_MESSAGE_CREATE') {
                    $uid = $d['d']['author']['id'] ?? $d['d']['author']['member_openid'] ?? '';
                    $uname = $d['d']['author']['username'] ?? $d['d']['member']['nick'] ?? '';
                } elseif ($ev === 'C2C_MESSAGE_CREATE') {
                    $uid = $d['d']['author']['id'] ?? $d['d']['author']['user_openid'] ?? '';
                    $uname = $d['d']['author']['username'] ?? $d['d']['member']['nick'] ?? '';
                } elseif ($ev === 'GROUP_ADD_ROBOT' || $ev === 'GROUP_DEL_ROBOT') {
                    $uid = $d['d']['op_member_openid'] ?? '';
                    $uname = $d['d']['op_member']['nick'] ?? '';
                }
                if ($uid === '') continue;
                if (!isset($users[$uid])) {
                    $users[$uid] = ['id' => $uid, 'username' => '', 'last_time' => ''];
                }
                if ($uname !== '' && $users[$uid]['username'] === '') $users[$uid]['username'] = $uname;
                if (strcmp($m[1], $users[$uid]['last_time']) > 0) $users[$uid]['last_time'] = $m[1];
            } catch (Throwable $e) {
                continue;
            }
        }
        $usersList = array_values($users);
        usort($usersList, function($a, $b) {
            return strcmp($b['last_time'], $a['last_time']);
        });
        echo json_encode(["code" => 200, "users" => $usersList], JSON_UNESCAPED_UNICODE);
        break;

    case "save_format":
        // 保存发送格式设置
        $format = $_POST['format'] ?? $_REQUEST['format'] ?? 'text';
        
        if (!in_array($format, ['text', 'card', 'native_md'])) {
            echo json_encode([
                "code" => 400,
                "msg" => "无效的格式类型"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 确保 admin/data 目录存在
        $dataDir = __DIR__ . '/../data';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        // 保存到文件
        $configFile = $dataDir . '/send_format_' . $appid . '.txt';
        file_put_contents($configFile, $format);
        
        echo json_encode([
            "code" => 200,
            "msg" => "设置已保存"
        ], JSON_UNESCAPED_UNICODE);
        break;
    
    case "get_format":
        // 读取发送格式设置
        $configFile = __DIR__ . '/../data/send_format_' . $appid . '.txt';
        $format = 'text'; // 默认值
        
        if (is_file($configFile)) {
            $format = trim(file_get_contents($configFile));
            if (!in_array($format, ['text', 'card', 'native_md'])) {
                $format = 'text';
            }
        }
        
        echo json_encode([
            "code" => 200,
            "format" => $format
        ], JSON_UNESCAPED_UNICODE);
        break;
    
    case "save_templates":
        // 保存文卡模板
        $jsonInput = file_get_contents('php://input');
        $data = json_decode($jsonInput, true);
        
        if (!isset($data['templates']) || !is_array($data['templates'])) {
            echo json_encode([
                "code" => 400,
                "msg" => "无效的模板数据"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 确保 admin/data 目录存在
        $dataDir = __DIR__ . '/../data';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        // 保存到文件
        $templateFile = $dataDir . '/card_templates_' . $appid . '.json';
        file_put_contents($templateFile, json_encode($data['templates'], JSON_UNESCAPED_UNICODE));
        
        echo json_encode([
            "code" => 200,
            "msg" => "模板已保存"
        ], JSON_UNESCAPED_UNICODE);
        break;
    
    case "get_templates":
        // 读取文卡模板
        $templateFile = __DIR__ . '/../data/card_templates_' . $appid . '.json';
        $templates = [];
        
        if (is_file($templateFile)) {
            $content = file_get_contents($templateFile);
            $templates = json_decode($content, true);
            if (!is_array($templates)) {
                $templates = [];
            }
        }
        
        echo json_encode([
            "code" => 200,
            "templates" => $templates
        ], JSON_UNESCAPED_UNICODE);
        break;
    
    case "save_reply_mode":
        // 保存回复模式
        $mode = $_POST['mode'] ?? $_REQUEST['mode'] ?? 'instant';
        
        if (!in_array($mode, ['instant', 'delayed'])) {
            echo json_encode([
                "code" => 400,
                "msg" => "无效的回复模式"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $dataDir = __DIR__ . '/../data';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        $configFile = $dataDir . '/reply_mode_' . $appid . '.txt';
        file_put_contents($configFile, $mode);
        
        echo json_encode([
            "code" => 200,
            "msg" => "回复模式已保存"
        ], JSON_UNESCAPED_UNICODE);
        break;
    
    case "get_reply_mode":
        // 读取回复模式
        $configFile = __DIR__ . '/../data/reply_mode_' . $appid . '.txt';
        $mode = 'instant';
        
        if (is_file($configFile)) {
            $mode = trim(file_get_contents($configFile));
            if (!in_array($mode, ['instant', 'delayed'])) {
                $mode = 'instant';
            }
        }
        
        echo json_encode([
            "code" => 200,
            "mode" => $mode
        ], JSON_UNESCAPED_UNICODE);
        break;
    
    case "save_delayed_message":
        // 保存延迟消息
        $chatType = $_POST['chat_type'] ?? $_REQUEST['chat_type'] ?? '';
        $chatId = $_POST['chat_id'] ?? $_REQUEST['chat_id'] ?? '';
        $sendMethod = $_POST['send_method'] ?? $_REQUEST['send_method'] ?? 'text';
        $content = $_POST['content'] ?? $_REQUEST['content'] ?? '';
        $userId = $_POST['user_id'] ?? $_REQUEST['user_id'] ?? ''; // 群聊模式下的目标用户ID
        
        if (empty($chatType) || empty($chatId) || empty($content)) {
            echo json_encode([
                "code" => 400,
                "msg" => "缺少必要参数"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $dataDir = __DIR__ . '/../data';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        $delayedFile = $dataDir . '/delayed_messages_' . $appid . '.json';
        $messages = [];
        
        if (is_file($delayedFile)) {
            $content_file = file_get_contents($delayedFile);
            $messages = json_decode($content_file, true);
            if (!is_array($messages)) {
                $messages = [];
            }
        }
        
        // 创建新的延迟消息
        $messageId = uniqid('dm_');
        $now = time();
        $expiresAt = $now + (24 * 60 * 60); // 24小时后过期
        
        // 确定user_id：
        // 群聊时：如果指定了userId则使用，否则为空（任何用户可触发）
        // 私聊时：使用chatId
        if ($chatType === 'group') {
            $finalUserId = !empty($userId) ? $userId : '';
        } else {
            $finalUserId = $chatId;
        }
        
        $newMessage = [
            'id' => $messageId,
            'appid' => $appid,
            'chat_type' => $chatType,
            'chat_id' => $chatId,
            'user_id' => $finalUserId, // 群聊：目标用户ID；私聊：chat_id
            'send_method' => $sendMethod,
            'content' => $content,
            'created_at' => $now,
            'expires_at' => $expiresAt
        ];
        
        $messages[] = $newMessage;
        file_put_contents($delayedFile, json_encode($messages, JSON_UNESCAPED_UNICODE));
        
        echo json_encode([
            "code" => 200,
            "msg" => "延迟消息已保存",
            "message_id" => $messageId
        ], JSON_UNESCAPED_UNICODE);
        break;
    
    case "get_delayed_messages":
        // 获取延迟消息列表
        $chatType = $_GET['chat_type'] ?? $_REQUEST['chat_type'] ?? '';
        $chatId = $_GET['chat_id'] ?? $_REQUEST['chat_id'] ?? '';
        $userId = $_GET['user_id'] ?? $_REQUEST['user_id'] ?? ''; // 可选：筛选特定用户
        
        $delayedFile = __DIR__ . '/../data/delayed_messages_' . $appid . '.json';
        $allMessages = [];
        
        if (is_file($delayedFile)) {
            $content = file_get_contents($delayedFile);
            $allMessages = json_decode($content, true);
            if (!is_array($allMessages)) {
                $allMessages = [];
            }
        }
        
        // 清理过期消息
        $now = time();
        $validMessages = [];
        foreach ($allMessages as $msg) {
            if ($msg['expires_at'] > $now) {
                $validMessages[] = $msg;
            }
        }
        
        // 如果有清理，更新文件
        if (count($validMessages) != count($allMessages)) {
            file_put_contents($delayedFile, json_encode($validMessages, JSON_UNESCAPED_UNICODE));
        }
        
        // 筛选当前聊天的消息
        $filteredMessages = [];
        if (!empty($chatType) && !empty($chatId)) {
            foreach ($validMessages as $msg) {
                // 基本匹配：chat_type 和 chat_id
                $matchBasic = ($msg['chat_type'] === $chatType && $msg['chat_id'] === $chatId);
                
                // 如果指定了 user_id，还需要匹配 user_id
                if ($matchBasic && !empty($userId)) {
                    if ($msg['user_id'] === $userId) {
                        $filteredMessages[] = $msg;
                    }
                } elseif ($matchBasic && empty($userId)) {
                    // 未指定 user_id，返回所有匹配的消息
                    $filteredMessages[] = $msg;
                }
            }
        } else {
            $filteredMessages = $validMessages;
        }
        
        echo json_encode([
            "code" => 200,
            "messages" => $filteredMessages
        ], JSON_UNESCAPED_UNICODE);
        break;
    
    case "send_delayed_message":
        // 立即发送延迟消息
        $messageId = $_POST['message_id'] ?? $_REQUEST['message_id'] ?? '';
        
        if (empty($messageId)) {
            echo json_encode([
                "code" => 400,
                "msg" => "缺少消息ID"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $delayedFile = __DIR__ . '/../data/delayed_messages_' . $appid . '.json';
        $messages = [];
        
        if (is_file($delayedFile)) {
            $content = file_get_contents($delayedFile);
            $messages = json_decode($content, true);
            if (!is_array($messages)) {
                $messages = [];
            }
        }
        
        // 查找并发送消息
        $targetMessage = null;
        $remainingMessages = [];
        
        foreach ($messages as $msg) {
            if ($msg['id'] === $messageId) {
                $targetMessage = $msg;
            } else {
                $remainingMessages[] = $msg;
            }
        }
        
        if (!$targetMessage) {
            echo json_encode([
                "code" => 404,
                "msg" => "消息不存在"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 更新文件（移除该消息）
        file_put_contents($delayedFile, json_encode($remainingMessages, JSON_UNESCAPED_UNICODE));
        
        // 发送消息（直接复用send逻辑，不添加@标记）
        $_POST['chat_type'] = $targetMessage['chat_type'];
        $_POST['chat_id'] = $targetMessage['chat_id'];
        $_POST['send_method'] = $targetMessage['send_method'];
        $_POST['content'] = $targetMessage['content'];
        $_REQUEST['type'] = 'send';
        
        // 递归调用send逻辑
        include __FILE__;
        exit;
        break;
    
    case "delete_delayed_message":
        // 删除延迟消息
        $messageId = $_POST['message_id'] ?? $_REQUEST['message_id'] ?? '';
        
        if (empty($messageId)) {
            echo json_encode([
                "code" => 400,
                "msg" => "缺少消息ID"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $delayedFile = __DIR__ . '/../data/delayed_messages_' . $appid . '.json';
        $messages = [];
        
        if (is_file($delayedFile)) {
            $content = file_get_contents($delayedFile);
            $messages = json_decode($content, true);
            if (!is_array($messages)) {
                $messages = [];
            }
        }
        
        // 过滤掉要删除的消息
        $remainingMessages = [];
        foreach ($messages as $msg) {
            if ($msg['id'] !== $messageId) {
                $remainingMessages[] = $msg;
            }
        }
        
        file_put_contents($delayedFile, json_encode($remainingMessages, JSON_UNESCAPED_UNICODE));
        
        echo json_encode([
            "code" => 200,
            "msg" => "消息已删除"
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case "upload":
        // 上传本地媒体到 QQ 官方（返回 file_info 供发送接口复用）
        $chatType = $_POST["chat_type"] ?? $_REQUEST["chat_type"] ?? "";
        $chatId = $_POST["chat_id"] ?? $_REQUEST["chat_id"] ?? "";
        $type = $_POST["type"] ?? $_REQUEST["type"] ?? "image";   // image/video/audio/file
        $data = $_POST["data"] ?? $_REQUEST["data"] ?? "";        // base64 或 URL
        $name = $_POST["name"] ?? $_REQUEST["name"] ?? "";

        if (empty($appid) || empty($chatType) || empty($chatId) || empty($data)) {
            echo json_encode([
                "code" => 400,
                "msg" => "缺少必要参数"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $typeMap = ['image' => '图片', 'video' => '视频', 'audio' => '语音', 'file' => '文件'];
        if (!in_array($chatType, ['group', 'private'], true) || !isset($typeMap[$type])) {
            echo json_encode([
                "code" => 400,
                "msg" => "无效的参数"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 加载框架
        if (!defined('appid')) define('appid', $appid);
        $mainJsonPath = dirname(dirname(__DIR__)) . '/main.json';
        if (is_file($mainJsonPath)) {
            $mainData = json_decode(file_get_contents($mainJsonPath), true);
            if (isset($mainData[$appid])) {
                if (!defined('secret') && isset($mainData[$appid]['secret'])) define('secret', $mainData[$appid]['secret']);
                if (!defined('type') && isset($mainData[$appid]['type'])) define('type', $mainData[$appid]['type']);
            }
        }
        $frameworkRoot = dirname(dirname(__DIR__));
        $originalDir = getcwd();
        chdir($frameworkRoot);
        if (is_file($frameworkRoot . '/function.php')) require_once $frameworkRoot . '/function.php';
        if (!is_file($frameworkRoot . '/bot.php')) {
            chdir($originalDir);
            echo json_encode(["code" => 500, "msg" => "机器人核心文件不存在"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        require_once $frameworkRoot . '/bot.php';

        $sceneName = $chatType === 'group' ? '群聊' : '私聊';
        // 本地文件：base64 解码后以原始字节上传（上传富媒体内部会再 base64 编码）
        $uploadData = $data;
        if (strpos($data, 'data:') === 0) {
            $comma = strpos($data, ',');
            $b64 = $comma !== false ? substr($data, $comma + 1) : $data;
            $raw = base64_decode($b64);
            $uploadData = $raw === false ? $b64 : $raw;
        } elseif (preg_match('/^[A-Za-z0-9+/=]+$/', $data) && strlen($data) > 200) {
            $raw = base64_decode($data);
            if ($raw !== false && base64_encode($raw) === $data) $uploadData = $raw;
        }

        try {
            $info = 上传富媒体($sceneName, $chatId, $typeMap[$type], $uploadData, $name !== '' ? $name : null);
            $fileInfo = $info['file_info'] ?? '';
            if ($fileInfo === '' || isset($info['code']) && (int)$info['code'] !== 0) {
                chdir($originalDir);
                echo json_encode([
                    "code" => 500,
                    "msg" => "上传失败: " . (富媒体错误($info) ?: json_encode($info, JSON_UNESCAPED_UNICODE))
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            chdir($originalDir);
            echo json_encode([
                "code" => 200,
                "file_info" => $fileInfo,
                "type" => $type,
                "name" => $name
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            chdir($originalDir);
            echo json_encode(["code" => 500, "msg" => "上传异常: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        break;

    case "card_templates":
        // 卡片模板 CRUD：action = list / save / delete
        $action = $_REQUEST["action"] ?? "list";
        $tplFile = __DIR__ . '/../database/card_templates.json';
        $templates = [];
        if (is_file($tplFile)) {
            $tmp = json_decode(@file_get_contents($tplFile), true);
            if (is_array($tmp)) $templates = $tmp;
        }

        if ($action === 'save') {
            $jsonInput = file_get_contents('php://input');
            $body = json_decode($jsonInput, true);
            $tpl = is_array($body) ? ($body['template'] ?? $body) : null;
            if (!is_array($tpl) || empty($tpl['name'])) {
                echo json_encode(["code" => 400, "msg" => "模板数据无效"], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (empty($tpl['id'])) $tpl['id'] = 'tpl_' . uniqid();
            $found = false;
            foreach ($templates as &$t) {
                if (($t['id'] ?? '') === $tpl['id']) { $t = array_merge($t, $tpl); $found = true; break; }
            }
            unset($t);
            if (!$found) $templates[] = $tpl;
            if (!is_dir(__DIR__ . '/../database')) @mkdir(__DIR__ . '/../database', 0777, true);
            file_put_contents($tplFile, json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            echo json_encode(["code" => 200, "msg" => "模板已保存", "id" => $tpl['id']], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'delete') {
            $id = $_REQUEST["id"] ?? "";
            $templates = array_values(array_filter($templates, function($t) use ($id) { return ($t['id'] ?? '') !== $id; }));
            file_put_contents($tplFile, json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            echo json_encode(["code" => 200, "msg" => "模板已删除"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(["code" => 200, "templates" => array_values($templates)], JSON_UNESCAPED_UNICODE);
        break;

    /* ---------------- 群管理：撤回 / 禁言 ---------------- */

    case "group_name":
        // 获取单个群的名称（调用官方群信息接口并缓存）
        $groupId = $_REQUEST["group_id"] ?? "";
        if (empty($appid) || empty($groupId)) {
            echo json_encode(["code" => 400, "msg" => "缺少必要参数"], JSON_UNESCAPED_UNICODE);
            break;
        }
        $name = 云雀_获取群名($appid, $groupId);
        if ($name === '') {
            echo json_encode(["code" => 404, "name" => "", "msg" => "获取群名失败，该接口可能仅白名单机器人可用"], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(["code" => 200, "name" => $name, "msg" => "获取成功"], JSON_UNESCAPED_UNICODE);
        }
        break;

    case "revoke":
        // 撤回机器人主动发出的消息
        $chatType = $_REQUEST["chat_type"] ?? "";
        $chatId   = $_REQUEST["chat_id"] ?? "";
        $msgId    = $_REQUEST["msg_id"] ?? "";
        if (empty($appid) || empty($chatType) || empty($chatId) || empty($msgId)) {
            echo json_encode(["code" => 400, "msg" => "缺少必要参数"], JSON_UNESCAPED_UNICODE);
            break;
        }
        $originalDir = 云雀_后台框架($appid);
        if (!defined('消息来源')) define('消息来源', $chatType === 'group' ? '群聊' : '私聊');
        if (!defined('来源')) define('来源', $chatId);
        if (!defined('消息ID')) define('消息ID', $msgId);
        try {
            $err = 云雀_操作错误(撤回($msgId));
            if ($err) {
                chdir($originalDir);
                echo json_encode(["code" => 500, "msg" => "撤回失败：" . $err], JSON_UNESCAPED_UNICODE);
            } else {
                chdir($originalDir);
                echo json_encode(["code" => 200, "msg" => "撤回成功"], JSON_UNESCAPED_UNICODE);
            }
        } catch (Throwable $e) {
            chdir($originalDir);
            echo json_encode(["code" => 500, "msg" => "撤回异常：" . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        break;

    case "mute":
        // 群禁言指定成员
        $groupId = $_REQUEST["group_id"] ?? "";
        $userId  = $_REQUEST["user_id"] ?? "";
        $seconds = max(0, (int)($_REQUEST["seconds"] ?? 600));
        if (empty($appid) || empty($groupId) || empty($userId)) {
            echo json_encode(["code" => 400, "msg" => "缺少必要参数"], JSON_UNESCAPED_UNICODE);
            break;
        }
        $originalDir = 云雀_后台框架($appid);
        try {
            $err = 云雀_操作错误($seconds > 0 ? 群禁言($userId, $seconds, $groupId) : 解除群禁言($userId, $groupId));
            if ($err) {
                chdir($originalDir);
                echo json_encode(["code" => 500, "msg" => "操作失败：" . $err], JSON_UNESCAPED_UNICODE);
            } else {
                chdir($originalDir);
                echo json_encode(["code" => 200, "msg" => $seconds > 0 ? "禁言成功" : "已解除禁言"], JSON_UNESCAPED_UNICODE);
            }
        } catch (Throwable $e) {
            chdir($originalDir);
            echo json_encode(["code" => 500, "msg" => "操作异常：" . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        break;

    default:
        echo json_encode([
            "code" => 400,
            "msg" => "无效的请求类型"
        ], JSON_UNESCAPED_UNICODE);
}

