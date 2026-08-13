<?php
/**
 * ============================================================
 *  云雀 (Yunque) · QQ 官方机器人框架 · 事件入口
 * ============================================================
 *  统一处理：
 *   - 腾讯官方事件回调（WebSocket 推送上来的 HTTP 透传）
 *   - op=13 事件 URL 验证 / op=0 事件分发与去重
 *   - 群艾特、群非艾特、私聊、频道、加群、退群、按钮互动
 *   - 按机器人设置过滤：排除机器人 / 处理自己消息 / 自动去艾特 / 仅群主可用
 *   - 兼容旧版“代发”入口
 *
 *  事件分发矩阵（对照 QQ 开放平台官方文档）：
 *   群聊  GROUP_AT_MESSAGE_CREATE 群内 @机器人消息（被动 msg_id 锚点）
 *   群聊  GROUP_MESSAGE_CREATE    群内全量消息（需申请群消息接收权限）
 *   单聊  C2C_MESSAGE_CREATE      单聊消息（被动 msg_id 锚点）
 *   频道  AT_MESSAGE_CREATE       频道内 @机器人消息
 *   频道  MESSAGE_CREATE          频道内全量消息
 *   频道  DIRECT_MESSAGE_CREATE   频道私信（回复走 /channels/{id}/messages）
 *   群聊  GROUP_ADD_ROBOT         机器人被拉入群（事件类，以 event_id 为锚点回复）
 *   群聊  GROUP_DEL_ROBOT         机器人被移出群
 *   群聊  GROUP_JOIN_REQUEST      入群申请（需开启对应权限，以 event_id 为锚点回复）
 *   互动  INTERACTION_CREATE      按钮/菜单回调（需快速 ACK + PUT 互动回应）
 *   管理  *_DELETE / *_REJECT / *_AUDIT / *_REACTION / *_EMOJI 等：记录不处理
 *
 *  被动回复规则（官方限制，发送函数内不重复处理）：
 *   - 群聊每条消息 5 分钟内最多回复 5 次；单聊 60 分钟内最多 4 次
 *   - 消息类事件用 msg_id 锚点，事件类（加群/退群/互动）用 event_id 锚点
 *   - 主动消息需用户开启“允许主动发送”，群聊主动消息需机器人具备权限
 * ============================================================
 */
ob_start();

require __DIR__ . "/function.php";
require_once __DIR__ . "/bot.php";

$rawText = file_get_contents("php://input");
if (empty($rawText)) {
    wlog('{"plat_error":"收到未知请求,元数据为空已阻拦"}', 'error');
    ob_end_clean();
    die("Request error");
}

$mainFile = __DIR__ . "/main.json";
$main = file_get_contents($mainFile);
$main_json = json_decode($main, true);
if (!is_array($main_json) || empty($main_json)) {
    ob_end_clean();
    die("Main config error");
}

$raw = json_decode($rawText, true);
if (!is_array($raw)) {
    ob_end_clean();
    die("JSON error");
}

// 兼容旧版“代发”入口
if (($raw['type'] ?? '') === '代发') {
    handleRelay($raw, $main_json);
    exit;
}

// 腾讯官方事件入口
$appid = $_SERVER["HTTP_X_BOT_APPID"] ?? "";
if (!isset($main_json[$appid])) {
    wlog('{"plat_error":"收到非官方请求,已阻拦"}', 'error');
    ob_end_clean();
    die("Appid error");
}

initAppContext($appid, $main_json[$appid]);

if (!function_exists('sodium_crypto_sign_seed_keypair') || !extension_loaded('sodium')) {
    wlog('{"plat_error":"未安装或未加载sodium拓展"}', 'error');
    ob_end_clean();
    die("sodium error");
}

define("raw", $raw);
$op = $raw["op"] ?? null;

if ($op == 13) {
    sign($raw, secret);
    exit;
}

if ($op == 0) {
    $event_id = $raw["id"] ?? '';
    if ($event_id === '') {
        die("event error");
    }

    $t = $raw["t"] ?? '';
    $dedupKey = $t !== '' ? "{$t}:{$event_id}" : $event_id;

    $event = 读("事件判断/" . appid . "/" . date("Y-m-d"), $dedupKey, false);
    if ($event) {
        wlog('{"plat_error":"元数据重复上传"}');
        die("error");
    }

    写("事件判断/" . appid . "/" . date("Y-m-d"), $dedupKey, true);
    wlog(json_encode($raw, JSON_UNESCAPED_UNICODE));
    Main($raw);
}

/**
 * 兼容旧版“代发”入口：其它系统通过本入口代为请求官方 API。
 */
function handleRelay(array $raw, array $main_json)
{
    $relayOp = $raw['op'] ?? '';
    $data = $raw['data'] ?? [];

    switch ($relayOp) {
        case 'get':
            $group = $data['group'] ?? '';
            if ($group === '') {
                echo json_encode(['code' => -2], JSON_UNESCAPED_UNICODE);
                return;
            }

            $resolved = resolveRelayTarget($group, $main_json);
            if (!$resolved) {
                echo json_encode(['code' => -2], JSON_UNESCAPED_UNICODE);
                return;
            }

            $eventJsonFile = __DIR__ . "/官机事件ID.json";
            $eventMap = file_exists($eventJsonFile) ? json_decode(file_get_contents($eventJsonFile), true) : [];
            if (!is_array($eventMap)) {
                $eventMap = [];
            }

            $boundGroup = $resolved['boundGroup'];
            if (!isset($eventMap[$boundGroup]['time'], $eventMap[$boundGroup]['msgid'])) {
                echo json_encode(['code' => -1], JSON_UNESCAPED_UNICODE);
                return;
            }

            if (time() - (int)$eventMap[$boundGroup]['time'] > 290) {
                echo json_encode(['code' => -1], JSON_UNESCAPED_UNICODE);
                return;
            }

            echo json_encode([
                'code' => 1,
                'msgid' => $eventMap[$boundGroup]['msgid'],
                'bind' => $boundGroup
            ], JSON_UNESCAPED_UNICODE);
            return;

        case 'send':
            $targetAppid = (string)($data['appid'] ?? '');
            if ($targetAppid === '' || !isset($main_json[$targetAppid])) {
                $targetAppid = array_key_first($main_json);
            }
            if ($targetAppid === null || !isset($main_json[$targetAppid])) {
                echo json_encode(['code' => -3, 'msg' => 'appid not found'], JSON_UNESCAPED_UNICODE);
                return;
            }

            initAppContext($targetAppid, $main_json[$targetAppid]);

            $address = $data['address'] ?? '';
            $method = $data['method'] ?? 'POST';
            $body = $data['body'] ?? [];

            if ($address === '') {
                echo json_encode(['code' => -4, 'msg' => 'address empty'], JSON_UNESCAPED_UNICODE);
                return;
            }

            echo BOTAPI($address, $method, json_encode($body, JSON_UNESCAPED_UNICODE));
            return;

        default:
            echo json_encode(['code' => -9, 'msg' => 'unknown relay op'], JSON_UNESCAPED_UNICODE);
            return;
    }
}

function resolveRelayTarget(string $group, array $main_json)
{
    foreach ($main_json as $appidKey => $cfg) {
        $boundGroup = 读($appidKey . "2bind.json", $group, '');
        if ($boundGroup !== '') {
            return [
                'appid' => (string)$appidKey,
                'boundGroup' => $boundGroup
            ];
        }
    }
    return null;
}

function initAppContext(string $appidVal, array $cfg)
{
    if (!defined('appid')) {
        define("appid", $appidVal);
    }
    if (!defined('secret')) {
        define("secret", $cfg["secret"] ?? '');
    }
    if (!defined('type')) {
        define("type", $cfg["type"] ?? '正式');
    }
    if (!defined('plugin')) {
        define("plugin", $cfg["plugin"] ?? []);
    }
}

function Main($raw)
{
    $event = $raw["t"] ?? '';
    $d = $raw["d"] ?? [];

    // 定义事件类型常量（供插件识别）
    if (!defined('事件类型')) define('事件类型', $event);

    $isMessageEvent = false;          // 是否为可回复的消息类事件
    $msgSource = '';                  // 消息来源（群聊/私聊/频道等）
    $source = '';                     // 来源标识（群ID/频道ID/用户ID）
    $user = '';                       // 操作者ID
    $msgId = $d["id"] ?? '';          // 消息ID（事件数据中的消息id）
    $eventId = $raw["id"] ?? '';      // 事件ID（顶层id）
    $me = 自身ID();                   // 机器人自身ID（缓存1小时）

    // ========== 事件分发矩阵（覆盖全部53种） ==========
    switch ($event) {
        // ---- 消息类事件（可回复） ----
        case "GROUP_AT_MESSAGE_CREATE":
        case "GROUP_MESSAGE_CREATE":
            $isMessageEvent = true;
            $msgSource = "群聊";
            $source = $d["group_openid"] ?? $d["group_id"] ?? '';
            $user = $d["author"]["id"] ?? $d["author"]["member_openid"] ?? '';
            break;

        case "C2C_MESSAGE_CREATE":
            $isMessageEvent = true;
            $msgSource = "私聊";
            $source = $d["author"]["id"] ?? $d["author"]["user_openid"] ?? '';
            $user = $source;
            break;

        case "DIRECT_MESSAGE_CREATE":   // 频道私信
        case "AT_MESSAGE_CREATE":       // 频道@消息
        case "MESSAGE_CREATE":          // 频道全量消息
            $isMessageEvent = true;
            $msgSource = "文字子频道";
            $source = $d["channel_id"] ?? '';
            $user = $d["author"]["id"] ?? '';
            break;

        // ---- 群事件（非消息） ----
        case "GROUP_ADD_ROBOT":
        case "GROUP_DEL_ROBOT":
            $msgSource = "群事件";
            $source = $d["group_openid"] ?? '';
            $user = $d["op_member_openid"] ?? '';
            break;

        case "GROUP_JOIN_REQUEST":
            $msgSource = "入群申请";
            $source = $d["group_openid"] ?? '';
            $user = $d["member_openid"] ?? '';
            break;

        case "GROUP_MEMBER_ADD":
        case "GROUP_MEMBER_REMOVE":
        case "GROUP_MSG_RECEIVE":
        case "GROUP_MSG_REJECT":
        case "SUBSCRIBE_MESSAGE_STATUS":
            $msgSource = "群事件";
            $source = $d["group_openid"] ?? '';
            $user = $d["op_member_openid"] ?? ($d["member_openid"] ?? '');
            break;

        // ---- 单聊事件（非消息） ----
        case "FRIEND_ADD":
        case "FRIEND_DEL":
        case "C2C_MSG_REJECT":
        case "C2C_MSG_RECEIVE":
            $msgSource = "单聊事件";
            $source = $d["user_openid"] ?? '';
            $user = $source;
            break;

        // ---- 互动事件 ----
        case "INTERACTION_CREATE":
            $msgSource = "互动";
            $source = $d["group_openid"] ?? ($d["user_openid"] ?? '');
            $user = $d["user_openid"] ?? ($d["group_member_openid"] ?? '');
            break;

        // ---- 频道消息管理（撤回/表态/审核） ----
        case "MESSAGE_DELETE":
        case "PUBLIC_MESSAGE_DELETE":
        case "DIRECT_MESSAGE_DELETE":
        case "MESSAGE_REACTION_ADD":
        case "MESSAGE_REACTION_REMOVE":
        case "MESSAGE_AUDIT_PASS":
        case "MESSAGE_AUDIT_REJECT":
            $msgSource = "频道消息管理";
            $source = $d["channel_id"] ?? $d["guild_id"] ?? '';
            $user = $d["author"]["id"] ?? $d["user_id"] ?? '';
            break;

        // ---- 论坛事件（普通 + 公域） ----
        case "FORUM_THREAD_CREATE":
        case "FORUM_THREAD_DELETE":
        case "FORUM_THREAD_UPDATE":
        case "FORUM_POST_CREATE":
        case "FORUM_REPLY_CREATE":
        case "FORUM_POST_DELETE":
        case "FORUM_REPLY_DELETE":
        case "OPEN_FORUM_THREAD_CREATE":
        case "OPEN_FORUM_POST_CREATE":
        case "OPEN_FORUM_REPLY_CREATE":
        case "OPEN_FORUM_THREAD_UPDATE":
        case "OPEN_FORUM_POST_DELETE":
        case "OPEN_FORUM_REPLY_DELETE":
        case "OPEN_FORUM_THREAD_DELETE":
            $msgSource = "论坛";
            $source = $d["guild_id"] ?? $d["channel_id"] ?? '';
            $user = $d["author"]["id"] ?? $d["member_id"] ?? '';
            break;

        // ---- 频道管理（创建/更新/删除） ----
        case "GUILD_CREATE":
        case "GUILD_UPDATE":
        case "GUILD_DELETE":
            $msgSource = "频道管理";
            $source = $d["guild_id"] ?? '';
            $user = $d["operator_id"] ?? '';
            break;

        // ---- 子频道事件 ----
        case "CHANNEL_CREATE":
        case "CHANNEL_UPDATE":
        case "CHANNEL_DELETE":
            $msgSource = "子频道";
            $source = $d["guild_id"] ?? '';
            $user = $d["operator_id"] ?? '';
            break;

        // ---- 频道成员事件 ----
        case "GUILD_MEMBER_ADD":
        case "GUILD_MEMBER_REMOVE":
        case "GUILD_MEMBER_UPDATE":
            $msgSource = "成员";
            $source = $d["guild_id"] ?? '';
            $user = $d["user_id"] ?? $d["member_id"] ?? '';
            break;

        // ---- 音频事件 ----
        case "AUDIO_START":
        case "AUDIO_FINISH":
        case "AUDIO_ON_MIC":
        case "AUDIO_OFF_MIC":
            $msgSource = "音频";
            $source = $d["channel_id"] ?? '';
            $user = $d["user_id"] ?? '';
            break;

        default:
            // 未知事件（兜底，确保不中断）
            $msgSource = "未知";
            $source = $d["guild_id"] ?? $d["channel_id"] ?? $d["group_openid"] ?? '';
            $user = $d["author"]["id"] ?? $d["user_id"] ?? $d["op_member_openid"] ?? '';
            break;
    }

    // ---------- 定义常量（供插件使用） ----------
    if (!defined('消息来源')) define('消息来源', $msgSource);
    if (!defined('来源')) define('来源', $source);
    if (!defined('用户')) define('用户', $user);
    if (!defined('消息ID')) define('消息ID', $msgId);
    if (!defined('事件ID')) define('事件ID', $eventId);

    // ---------- 仅对消息类事件执行内容清洗与过滤 ----------
    if ($isMessageEvent) {
        // 群非艾特开关（仅针对 GROUP_MESSAGE_CREATE）
        if ($event === "GROUP_MESSAGE_CREATE" && !机器人设置(appid, "群非艾特", true)) {
            return;
        }

        // 排除机器人自己
        if (机器人设置(appid, "排除机器人", true) && !empty($d["author"]["bot"])) {
            return;
        }
        // 屏蔽其他机器人
        if (机器人设置(appid, "屏蔽其他机器人", false) && !empty($d["author"]["bot"])) {
            $authorId = $d["author"]["id"] ?? $d["author"]["member_openid"] ?? '';
            if ($me === '' || $authorId !== $me) {
                return;
            }
        }
        // 是否处理自己消息
        if (!机器人设置(appid, "处理自己消息", false)) {
            if ($me !== '' && $me === 常量('用户')) {
                return;
            }
        }

        // 提取并清洗消息文本
        $content = (string)($d["content"] ?? "");
        // 群非艾特自动去掉开头@机器人
        if ($event === "GROUP_MESSAGE_CREATE" && 机器人设置(appid, "群非艾特", true) && 机器人设置(appid, "自动去开头艾特", true)) {
            if ($me !== '' && preg_match('/^<@' . preg_quote($me, '/') . '>\s*/u', $content, $m)) {
                $content = substr($content, strlen($m[0]));
            }
        }
        // 去掉所有@标记（若启用）
        if (机器人设置(appid, "自动去艾特", true)) {
            $content = (string)preg_replace('/<@[^>]*>/u', '', $content);
        }
        define('消息', trim($content, " /"));
    } else {
        // 非消息事件无文本内容
        define('消息', '');
    }

    // ---------- 通用常量 ----------
    if (!defined('主人ID')) define("主人ID", 主人ID());
    if (!defined('机器人ID')) define("机器人ID", $me);

    // 身份判定（仅群聊消息场景且开启“仅群主可用”）
    if ($isMessageEvent && 常量('消息来源') === "群聊" && 机器人设置(appid, "仅群主可用", false)) {
        define("身份", 群身份());
    } else {
        define("身份", "");
    }
    define("是否机器人", !empty($d["author"]["bot"]));

    // ---------- 互动事件需提前 ACK ----------
    if ($event === "INTERACTION_CREATE" && function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    }

    // ---------- 加载插件 ----------
    load_plugin();
    exit;
}

function load_plugin()
{
    $All = glob(__DIR__ . "/plugin/*.php");
    foreach ($All as $name) {
        $plugin_name = basename($name, ".php");
        if (!插件作用域(appid, $plugin_name)) {
            continue;
        }
        try {
            require_once($name);
        } catch (Throwable $e) {
            $error = json_encode([
                "plat_error" => "[{$name}]运行出错: " . $e->getMessage() . " 行数:" . $e->getLine()
            ], JSON_UNESCAPED_UNICODE);
            wlog($error, 'error');
            continue;
        }
    }
}
