<?php
/**
 * ============================================================
 *  云雀 (Yunque) · QQ 官方机器人框架 · 事件入口
 * ============================================================
 *  统一处理：
 *   - 腾讯官方事件回调（WebSocket 推送上来的 HTTP 透传）
 *   - op=13 事件 URL 验证 / op=0 事件分发与去重
 *   - 群艾特、群非艾特、私聊、加群、退群、按钮互动
 *   - 按机器人设置过滤：排除机器人 / 处理自己消息 / 自动去艾特 / 仅群主可用
 *   - 兼容旧版“代发”入口
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

/**
 * 事件分发主入口：定义插件常量 → 按设置过滤 → 加载插件。
 */
function Main($raw)
{
    $event = $raw["t"] ?? '';
    $d = $raw["d"] ?? [];

    // 不同事件的消息字段兼容官方两种事件命名
    switch ($event) {
        case "GROUP_AT_MESSAGE_CREATE":
        case "GROUP_MESSAGE_CREATE":
            define("消息来源", "群聊");
            define("消息ID", $d["id"] ?? '');
            define("来源", $d["group_openid"] ?? $d["group_id"] ?? '');
            define("用户", $d["author"]["id"] ?? $d["author"]["member_openid"] ?? '');
            break;

        case "C2C_MESSAGE_CREATE":
            define("消息来源", "私聊");
            define("消息ID", $d["id"] ?? '');
            define("来源", $d["author"]["id"] ?? $d["author"]["user_openid"] ?? '');
            define("用户", $d["author"]["id"] ?? $d["author"]["user_openid"] ?? '');
            break;

        case "GROUP_ADD_ROBOT":
            define("消息来源", "加群");
            define("事件ID", $raw["id"] ?? '');
            define("消息ID", $d["id"] ?? '');
            define("来源", $d["group_openid"] ?? '');
            define("用户", $d["op_member_openid"] ?? '');
            break;

        case "GROUP_DEL_ROBOT":
            define("消息来源", "退群");
            define("事件ID", $raw["id"] ?? '');
            define("消息ID", $d["id"] ?? '');
            define("来源", $d["group_openid"] ?? '');
            define("用户", $d["op_member_openid"] ?? '');
            break;

        case "INTERACTION_CREATE":
            define("消息来源", "互动");
            define("事件ID", $raw["id"] ?? '');
            define("消息ID", $d["id"] ?? '');
            define("来源", $d["group_openid"] ?? ($d["user_openid"] ?? ''));
            define("用户", $d["user_openid"] ?? ($d["group_member_openid"] ?? ''));
            break;

        default:
            // 未知/频道类事件：记录但不处理
            return;
    }

    // 群非艾特开关：关闭时忽略非艾特群消息
    if ($event === "GROUP_MESSAGE_CREATE" && !机器人设置(appid, "群非艾特", true)) {
        return;
    }

    // 机器人自身 ID（缓存 1 小时，首次会请求一次官方接口）
    $me = 自身ID();

    // 排除机器人：开启时忽略机器人账号（含机器人与他人对话）
    if (机器人设置(appid, "排除机器人", true) && !empty($d["author"]["bot"])) {
        return;
    }

    // 屏蔽其他机器人：开启时忽略其他机器人的消息，不影响机器人自己
    if (机器人设置(appid, "屏蔽其他机器人", false) && !empty($d["author"]["bot"])) {
        $authorId = $d["author"]["id"] ?? $d["author"]["member_openid"] ?? '';
        if ($me === '' || $authorId !== $me) {
            return;
        }
    }

    // 处理自己消息：关闭时不处理机器人自己发出的消息
    if (!机器人设置(appid, "处理自己消息", false)) {
        if ($me !== '' && $me === 常量('用户')) {
            return;
        }
    }

    // 消息文本
    $content = (string)($d["content"] ?? "");

    // 非艾特消息：自动排除开头的“艾特机器人+空格”，不影响艾特其他非机器人的人
    if ($event === "GROUP_MESSAGE_CREATE" && 机器人设置(appid, "群非艾特", true) && 机器人设置(appid, "自动去开头艾特", true)) {
        if ($me !== '' && preg_match('/^<@' . preg_quote($me, '/') . '>\s*/u', $content, $m)) {
            $content = substr($content, strlen($m[0]));
        }
    }

    // 自动去艾特：去掉所有艾特标记（原有行为）
    if (机器人设置(appid, "自动去艾特", true)) {
        $content = (string)preg_replace('/<@[^>]*>/u', '', $content);
    }
    define("消息", trim($content, " /"));

    // 向插件提供主人 ID 与机器人自身 ID
    if (!defined('主人ID')) {
        define("主人ID", 主人ID());
    }
    if (!defined('机器人ID')) {
        define("机器人ID", $me);
    }

    // 身份判定：仅群聊场景、且开启“仅群主可用”时才实时查询，避免无谓的接口调用
    if (常量('消息来源') === "群聊" && 机器人设置(appid, "仅群主可用", false)) {
        define("身份", 群身份());
    } else {
        define("身份", "");
    }
    define("是否机器人", !empty($d["author"]["bot"]));

    // 按钮互动需要快速 ACK，否则 QQ 客户端可能提示“请求失败”。
    // fastcgi_finish_request 会先结束 HTTP 响应，后续 PHP 仍继续执行插件逻辑发送消息。
    if ($event === "INTERACTION_CREATE" && function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    }

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
