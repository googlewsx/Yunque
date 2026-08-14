<?php

/**
 * ============================================================
 *  云雀 (Yunque) · QQ 官方机器人框架 · 公共函数库
 * ============================================================
 *  本文件为框架公共底层，包含：
 *   - 文件级 JSON KV 存储（读/写，带文件锁）
 *   - 结构化日志 wlog
 *   - HTTP 请求 curl
 *   - 官方事件 URL 验证 sign
 *   - BOT AccessToken 缓存
 *   - 常用字符串/编码/工具函数
 * ============================================================
 */

include __DIR__ . "/function/qrcode.php";
include __DIR__ . "/function/GD.php";
include __DIR__ . "/function/Parsedown.php";
include __DIR__ . "/function/Mail/class.smtp.php";
include __DIR__ . "/function/tuwen.php";

define('云雀', '云雀 Yunque');
define('云雀版本', '3.0.0');

/* ------------------------------------------------------------
 * 0. mbstring 缺失时的兼容垫片（保证低依赖环境可用）
 * --------------------------------------------------------- */
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
if (!function_exists('mb_strpos')) {
    function mb_strpos($haystack, $needle, $offset = 0, $encoding = 'UTF-8') {
        return strpos((string)$haystack, (string)$needle, $offset);
    }
}

/* ------------------------------------------------------------
 * 1. 文件级 JSON KV 存储
 * --------------------------------------------------------- */
function 写($文件, $键, $值) {
    $文件路径 = __DIR__ . "/database/" . $文件;
    $目录 = dirname($文件路径);
    if (!is_dir($目录)) {
        if (!@mkdir($目录, 0777, true) && !is_dir($目录)) {
            error_log("云雀: 无法创建目录 {$目录}");
            return false;
        }
    }
    $fp = fopen($文件路径, "c+");
    if (!$fp) return false;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return false; }
    try {
        $内容 = filesize($文件路径) > 0 ? fread($fp, filesize($文件路径)) : '{}';
        $数据 = json_decode($内容, true);
        if (!is_array($数据)) $数据 = [];
        $数据[$键] = $值;
        $json = json_encode($数据, JSON_UNESCAPED_UNICODE);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $json);
        return true;
    } catch (Throwable $e) {
        error_log("云雀: 写入文件出错 {$文件路径} - " . $e->getMessage());
        return false;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function 读($文件, $键, $默认值 = null) {
    $文件路径 = __DIR__ . "/database/" . $文件;
    if (!is_file($文件路径)) return $默认值;
    $fp = fopen($文件路径, "r");
    if (!$fp) return $默认值;
    if (!flock($fp, LOCK_SH)) { fclose($fp); return $默认值; }
    try {
        $内容 = fread($fp, filesize($文件路径));
        $数据 = json_decode($内容, true);
        return is_array($数据) ? ($数据[$键] ?? $默认值) : $默认值;
    } catch (Throwable $e) {
        return $默认值;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

/** 读取整份 JSON KV 文件 */
function 读全部($文件) {
    $文件路径 = __DIR__ . "/database/" . $文件;
    if (!is_file($文件路径)) return [];
    $fp = fopen($文件路径, "r");
    if (!$fp) return [];
    flock($fp, LOCK_SH);
    $内容 = fread($fp, filesize($文件路径));
    flock($fp, LOCK_UN);
    fclose($fp);
    $数据 = json_decode($内容, true);
    return is_array($数据) ? $数据 : [];
}

/* ------------------------------------------------------------
 * 2. 结构化日志 wlog
 * ------------------------------------------------------------
 * 兼容旧调用：wlog('字符串') / wlog(json字符串) / wlog(数组)
 * 新行格式为单行 JSON：{"time":"...","appid":"...","kind":"...","data":{...}}
 * 也兼容旧格式 [2026-01-01 00:00:00] {json} 的读取。
 */
function wlog($payload, $kind = 'event') {
    $date = date('Y-m-d H:i:s');
    $appid = defined('appid') ? appid : 'system';

    $rec = [
        "time" => $date,
        "appid" => $appid,
        "kind" => $kind,
    ];

    if (is_array($payload)) {
        $rec = array_merge($rec, $payload);
    } elseif (is_string($payload)) {
        $dec = json_decode($payload, true);
        if (is_array($dec)) {
            $rec = array_merge($rec, $dec);
        } else {
            $rec["msg"] = $payload;
        }
    }

    $logDir = __DIR__ . "/Log/" . $appid;
    if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
    $logFile = $logDir . '/' . date('Y-m-d') . '.log';

    // 混合格式：保留旧版 [时间] 前缀，便于旧版日志解析器兼容；结构字段仍完整。
    $line = "[" . $date . "] " . json_encode($rec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

    // 系统级日志（错误/调试）
    if ($kind === 'error' || (defined('云雀调试') && 云雀调试)) {
        $sysDir = __DIR__ . "/Log/system";
        if (!is_dir($sysDir)) @mkdir($sysDir, 0777, true);
        @file_put_contents($sysDir . '/' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
    return true;
}

/** 记录一次“发送”日志（供聊天界面渲染机器人消息） */
function 记录发送($action, $target, $content, $type = "文字", $extra = []) {
    $rec = array_merge([
        "kind" => "send",
        "direction" => "发送",
        "action" => $action,
        "source_type" => defined('消息来源') ? 消息来源 : '',
        "target_id" => $target,
        "target_openid" => $target,
        "content_type" => $type,
        "content" => $content,
        "time" => date("Y-m-d H:i:s"),
    ], $extra);
    wlog($rec, 'send');
}

/** 发送成功后，把接口返回的 message_id 补记到今天最后一条 send 日志（供撤回使用） */
function 记录发送ID($messageId) {
    $messageId = trim((string)$messageId);
    if ($messageId === '') return;
    $appid = defined('appid') ? appid : 'system';
    $logFile = __DIR__ . "/Log/" . $appid . '/' . date('Y-m-d') . '.log';
    if (!is_file($logFile)) return;
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) return;
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        $line = trim($lines[$i]);
        if (!preg_match('/^\[([^\]]+)\]\s*(.*)$/', $line, $m)) continue;
        $d = json_decode($m[2], true);
        if (!is_array($d) || ($d['kind'] ?? '') !== 'send') continue;
        if (!empty($d['message_id'])) continue;
        $d['message_id'] = $messageId;
        $lines[$i] = '[' . $m[1] . '] ' . json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        @file_put_contents($logFile, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX);
        return;
    }
}

/** 记录一次“收到”事件日志 */
function 记录收到($raw, $extra = []) {
    $rec = array_merge([
        "kind" => "receive",
        "t" => $raw["t"] ?? "",
        "d" => $raw["d"] ?? [],
        "raw" => $raw,
    ], $extra);
    wlog($rec, 'receive');
}

/**
 * 解析日志文件，兼容旧格式（[时间] {json}）与混合格式。
 * 返回数组，每个元素为一行日志解析出的结构，并附带 _line_time 字段。
 */
function 读日志($文件路径) {
    if (!is_file($文件路径)) return [];
    $内容 = file($文件路径, FILE_IGNORE_NEW_LINES);
    if (!is_array($内容)) return [];
    $out = [];
    foreach ($内容 as $line) {
        $line = trim($line);
        if ($line === '' || $line === '重复数据') continue;
        $time = '';
        $json = $line;
        if (preg_match('/^\[([^\]]+)\]\s*(.*)$/', $line, $m)) {
            $time = $m[1];
            $json = $m[2];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) continue;
        if ($time === '' && isset($data["time"])) $time = $data["time"];
        $data["_line_time"] = $time;
        $out[] = $data;
    }
    return $out;
}

/* ------------------------------------------------------------
 * 3. HTTP 请求
 * --------------------------------------------------------- */
function curl($url, $method, $headers, $params) {
    $url = str_replace(" ", "%20", $url);
    if (is_array($params)) {
        $requestString = http_build_query($params);
    } else {
        $requestString = (string)$params;
    }
    if (empty($headers)) {
        $headers = ['Content-type: text/json'];
    } elseif (!is_array($headers)) {
        parse_str($headers, $headers);
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
    switch (strtoupper($method)) {
        case "GET":
            curl_setopt($ch, CURLOPT_HTTPGET, 1);
            break;
        case "POST":
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $requestString);
            break;
        case "PUT":
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $requestString);
            break;
        case "DELETE":
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $requestString);
            break;
        case "PATCH":
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $requestString);
            break;
    }
    $response = curl_exec($ch);
    curl_close($ch);
    if (stristr((string)$response, 'HTTP 404') || $response === '' || $response === false) {
        return json_encode(['code' => -1, 'message' => '请求错误', 'ok' => false], JSON_UNESCAPED_UNICODE);
    }
    return $response;
}

/* ------------------------------------------------------------
 * 4. 官方事件 URL 验证（op=13 明文验证）
 * --------------------------------------------------------- */
function sign($payload, $seed) {
    while (strlen($seed) < SODIUM_CRYPTO_SIGN_SEEDBYTES) {
        $seed .= $seed;
    }
    $privateKey = sodium_crypto_sign_secretkey(
        sodium_crypto_sign_seed_keypair(substr($seed, 0, SODIUM_CRYPTO_SIGN_SEEDBYTES))
    );
    $signature = bin2hex(
        sodium_crypto_sign_detached(
            $payload['d']['event_ts'] . $payload['d']['plain_token'],
            $privateKey
        )
    );
    echo json_encode([
        'plain_token' => $payload['d']['plain_token'],
        'signature' => $signature,
    ]);
    return true;
}

/* ------------------------------------------------------------
 * 5. BOT AccessToken 缓存
 * --------------------------------------------------------- */
function BOT凭证($appidArg = null, $secretArg = null) {
    $appid = $appidArg ?? (defined('appid') ? appid : '');
    $secret = $secretArg ?? (defined('secret') ? secret : '');
    if ($appid === '') return '';

    $time = 读("function/" . $appid, "time", 0);
    if (time() < $time) {
        $Access = 读("function/" . $appid, "Access", "");
        if ($Access !== '') return $Access;
    }

    $url = "https://bots.qq.com/app/getAppAccessToken";
    $json = json_encode([
        "appId" => (string)$appid,
        "clientSecret" => $secret,
    ]);
    $header = ['Content-Type: application/json'];
    $fw = curl($url, "POST", $header, $json);
    $fw = json_decode($fw, true);
    if (!isset($fw["access_token"])) {
        wlog(['level' => 'error', 'msg' => '获取AccessToken失败', 'resp' => $fw], 'error');
        return '';
    }
    $Access = $fw["access_token"];
    $time = (int)($fw["expires_in"] ?? 7200);
    写("function/" . $appid, "time", time() + max($time - 120, 60));
    写("function/" . $appid, "Access", $Access);
    return $Access;
}

/** 获取机器人信息（用于后台头像/昵称展示） */
function BOT信息($appidArg = null, $secretArg = null) {
    $appid = $appidArg ?? (defined('appid') ? appid : '');
    $secret = $secretArg ?? (defined('secret') ? secret : '');
    if ($appid === '' || $secret === '') return [];
    $Access = BOT凭证($appid, $secret);
    if ($Access === '') return [];
    $urls = ["正式" => "https://api.bot.qq.com", "沙箱" => "https://sandbox.api.bot.qq.com"];
    $fallback = ["正式" => "https://api.sgroup.qq.com", "沙箱" => "https://sandbox.api.sgroup.qq.com"];
    $isSandbox = (defined('type') ? type : '正式') === '沙箱';
    $host = $urls[$isSandbox ? "沙箱" : "正式"];
    $r = curl($host . "/users/@me", "GET", ["Authorization: QQBot " . $Access], '');
    $decoded = json_decode($r, true);
    if (!is_array($decoded)) {
        // 新域名不可达时回退旧域名
        $oldHost = $fallback[$isSandbox ? "沙箱" : "正式"];
        $r2 = curl($oldHost . "/users/@me", "GET", ["Authorization: QQBot " . $Access], '');
        $decoded2 = json_decode($r2, true);
        if (is_array($decoded2)) return $decoded2;
    }
    return $decoded ?: [];
}

/* ------------------------------------------------------------
 * 6. 常用工具
 * --------------------------------------------------------- */
function 二维码($content) {
    ob_start();
    Toplib_Lib_QRcode::png($content, false, QR_ECLEVEL_L, 7, 1, false, [255, 255, 255], [0, 0, 0]);
    return ob_get_clean();
}

function 前缀后($str, $prefix) {
    return strpos($str, $prefix) !== false ? substr($str, strlen($prefix)) : $str;
}

function 前缀($str, $prefix) {
    return strpos($str, $prefix) === 0;
}

function 域名大写($msg) {
    $suffixes = [
        'com', 'net', 'org', 'edu', 'gov', 'mil', 'biz', 'info', 'top',
        'xyz', 'vip', 'pro', 'name', 'tech', 'site', 'club', 'online',
        'store', 'shop', 'blog', 'app', 'cn', 'cc', 'tv', 'io', 'ai',
    ];
    foreach ($suffixes as $suffix) {
        $pattern = '/([\.\/])(' . $suffix . ')\b/i';
        $msg = preg_replace_callback($pattern, function ($matches) {
            return $matches[1] . ucfirst(strtolower($matches[2]));
        }, $msg);
    }
    return $msg;
}

function markdown转html($markdown) {
    $parsedown = new Parsedown();
    return $parsedown->text($markdown);
}

function 邮箱($mailTitle, $content, $Adress, $user, $password) {
    $mail = new PHPMailer();
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->SMTPAuth = true;
    $mail->Host = 'smtp.qq.com';
    $mail->SMTPSecure = 'ssl';
    $mail->Port = 465;
    $mail->CharSet = 'UTF-8';
    $mail->Username = $user;
    $mail->Password = $password;
    $mail->From = $user;
    $mail->FromName = defined('云雀') ? 云雀 : '云雀';
    $mail->isHTML(true);
    $mail->addAddress($Adress);
    $mail->Subject = $mailTitle;
    $mail->Body = $content;
    return $mail->send();
}

function HTML转图($html, $selector = ".container", $noCache = false, $quality = 100, $scale = 1.5, $format = "png") {
    $url = "http://nw.qzqi.com:45000/screenshot";
    $params = [
        'text' => $html,
        'selector' => $selector,
        'noCache' => $noCache ? 'true' : 'false',
        'quality' => $quality,
        'scale' => $scale,
        'format' => $format,
    ];
    $header = ['Accept: image/' . $format, 'Content-Type: application/json'];
    return curl($url, "POST", $header, json_encode($params, JSON_UNESCAPED_UNICODE));
}

/** 机器人默认设置（后台新增开关时在此统一维护） */
function 云雀默认设置() {
    return [
        "群非艾特" => true,        // 开启后接收群内非艾特消息
        "排除机器人" => true,      // 忽略所有机器人账号消息
        "自动去艾特" => true,      // 去掉消息中的所有艾特标记
        "处理自己消息" => false,   // 是否处理机器人自己发出的消息
        "仅群主可用" => false,     // 仅群主可触发插件
        "屏蔽其他机器人" => false, // 忽略其他机器人消息（不影响自己）
        "自动去开头艾特" => true,  // 自动去掉开头艾特机器人（仅开头，不影响艾特其他人）
    ];
}

/** 读取某个机器人配置里的主人列表（无则返回空数组） */
function 云雀主人($appid = null) {
    $appid = $appid ?? (defined('appid') ? appid : '');
    if ($appid === '') return [];
    $raw = @file_get_contents(__DIR__ . "/main.json");
    $main = json_decode($raw, true);
    if (!is_array($main) || !isset($main[$appid]["主人"])) return [];
    $owner = $main[$appid]["主人"];
    if (!is_array($owner)) return [];
    if (isset($owner['id']) || isset($owner['name'])) {
        return [$owner];
    }
    return array_values(array_filter($owner, 'is_array'));
}

/** 当前机器人主人的 openid（插件可直接调用，取第一个主人；空串表示未设置） */
function 主人ID($appid = null) {
    $owners = 云雀主人($appid);
    foreach ($owners as $one) {
        if (!empty($one['id'])) return (string)$one['id'];
    }
    return '';
}

/** 判断某个 openid 是否是机器人主人（支持多主人） */
function 是否主人($openid, $appid = null) {
    if ($openid === '' || $openid === null) return false;
    foreach (云雀主人($appid) as $one) {
        if (!empty($one['id']) && (string)$one['id'] === (string)$openid) return true;
        if (!empty($one['qq_number']) && (string)$one['qq_number'] === (string)$openid) return true;
    }
    return false;
}

/** 当前机器人自身的 openid（插件可直接调用，需要已配置 secret） */
function 机器人ID($appidArg = null, $secretArg = null) {
    return 自身ID($appidArg, $secretArg);
}

/** 读取后台全局配置 */
function 云雀配置($key = null, $默认 = null) {
    static $cfg = null;
    if ($cfg === null) {
        $raw = @file_get_contents(__DIR__ . "/config.json");
        $cfg = json_decode($raw, true);
        if (!is_array($cfg)) $cfg = [];
    }
    if ($key === null) return $cfg;
    $settings = $cfg["settings"] ?? [];
    return $settings[$key] ?? $默认;
}

/** 读取某个机器人的设置项（含默认值） */
function 机器人设置($appid, $key, $默认 = null) {
    static $cache = [];
    if (!isset($cache[$appid])) {
        $raw = @file_get_contents(__DIR__ . "/main.json");
        $main = json_decode($raw, true);
        $cache[$appid] = (isset($main[$appid]["settings"]) && is_array($main[$appid]["settings"]))
            ? $main[$appid]["settings"] : [];
    }
    return $cache[$appid][$key] ?? $默认;
}

/** 判断机器人插件是否启用，以及当前事件是否命中插件作用域 */
function 插件作用域($appid, $pluginName) {
    static $main = null;
    if ($main === null) {
        $raw = @file_get_contents(__DIR__ . "/main.json");
        $main = json_decode($raw, true);
        if (!is_array($main)) $main = [];
    }
    $cfg = $main[$appid]["plugin"][$pluginName] ?? null;

    // 旧格式：true / false
    if (is_bool($cfg)) return $cfg;
    if (is_int($cfg)) return (bool)$cfg;

    // 新格式：{enable, scope, groups}
    if (is_array($cfg)) {
        if (isset($cfg["enable"]) && $cfg["enable"] === false) return false;
        if (isset($cfg["enable"]) && $cfg["enable"] === true) {
            $scope = $cfg["scope"] ?? "all";
            $groups = $cfg["groups"] ?? [];
            if (!is_array($groups)) $groups = [];
            // 作用域：all 放行；specified 仅在指定群放行
            if ($scope === "specified") {
                $source = defined('消息来源') ? 消息来源 : '';
                if (!in_array($source, ["群聊", "加群", "退群", "互动"], true)) return false;
                $target = defined('来源') ? 来源 : '';
                return in_array($target, $groups, true);
            }
            return true;
        }
    }
    return false;
}

/**
 * 语音转 silk 占位函数。
 * QQ 官方机器人富媒体接口支持直传 mp3/wav/amr 等常见音频，
 * 一般无需额外转码；此函数保留旧插件调用兼容，原样透传。
 */
function silk($audio) {
    return $audio;
}

/**
 * 机器人自身 openid（缓存 1 小时）。
 * 用于“处理自己消息”开关：判断群消息作者是否为机器人自己。
 */
function 自身ID($appidArg = null, $secretArg = null) {
    $appid = $appidArg ?? (defined('appid') ? appid : '');
    $secret = $secretArg ?? (defined('secret') ? secret : '');
    if ($appid === '' || $secret === '') return '';

    $cache = 读("机器人/" . $appid, "self", null);
    if (is_array($cache) && isset($cache['time']) && time() - $cache['time'] < 3600) {
        return $cache['id'] ?? '';
    }

    $me = BOT信息($appid, $secret);
    $id = $me['id'] ?? '';
    写("机器人/" . $appid, "self", ['time' => time(), 'id' => $id]);
    return $id;
}

/**
 * 判断字符串是否为 member_openid 格式（大写十六进制，通常32位）
 */
function 是成员openid格式($s) {
    // member_openid 是大写十六进制（含字母A-F），AppID 是纯数字，以此区分
    return is_string($s) && preg_match('/^[A-F0-9]{16,64}$/i', $s) && preg_match('/[A-F]/i', $s);
}

/**
 * 缓存机器人在群内的 member_openid（框架层，收到@消息或发送响应时自动学习）
 */
function 缓存机器人成员ID($群号, $成员ID) {
    $群号 = trim((string)$群号);
    $成员ID = trim((string)$成员ID);
    if ($群号 === '' || !是成员openid格式($成员ID)) return false;
    $成员ID = strtoupper($成员ID);
    写("群管/机器人身份/" . $群号, "member_openid", $成员ID);
    写("群管/机器人身份/_global", "member_openid", $成员ID);
    return true;
}

/**
 * 获取机器人在指定群的 member_openid
 * 优先群级缓存 → 全局缓存 → 回退 AppID
 */
function 获取机器人成员ID($群号 = '') {
    if ($群号 !== '') {
        $id = 读("群管/机器人身份/" . $群号, "member_openid", '');
        if (是成员openid格式($id)) return strtoupper($id);
    }
    $id = 读("群管/机器人身份/_global", "member_openid", '');
    if (是成员openid格式($id)) return strtoupper($id);
    return defined('机器人ID') ? (string)机器人ID : (string)自身ID();
}

/** 解析时间（兼容 RFC3339 与 Y-m-d H:i:s） */
function 解析时间($t) {
    if (empty($t)) return time();
    if (is_int($t)) return $t;
    $ts = strtotime($t);
    return $ts ? $ts : time();
}

// 添加至 function.php
function 解析入群申请($verify_info) {
    $result = ['问题' => '', '答案' => ''];
    if (!is_array($verify_info)) return $result;

    // 新格式：admin_review_qa
    if (($verify_info['method'] ?? '') === 'admin_review_qa' && isset($verify_info['review_qa_list'][0])) {
        $qa = $verify_info['review_qa_list'][0];
        $result['问题'] = $qa['question'] ?? '';
        $result['答案'] = $qa['answer'] ?? '';
        return $result;
    }

    // 旧格式：verify_message
    $raw = $verify_info['verify_message'] ?? '';
    if (preg_match('/问题[：:](.+?)(?:\s*答案[：:](.+))?$/su', $raw, $m)) {
        $result['问题'] = trim($m[1]);
        $result['答案'] = isset($m[2]) ? trim($m[2]) : '';
    } else {
        $result['问题'] = $raw;
    }
    return $result;
}