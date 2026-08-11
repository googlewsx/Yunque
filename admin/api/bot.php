<?php
/**
 * 云雀 · 机器人管理 API
 * 支持：add / del / update / list
 */
header('Content-Type: application/json');

$file = dirname(__DIR__, 2) . "/main.json";
$main = json_decode(@file_get_contents($file), true);
if (!is_array($main)) $main = [];

$type = $_REQUEST["type"] ?? "";

function 云雀_默认设置() {
    return [
        "群非艾特" => true,
        "排除机器人" => true,
        "自动去艾特" => true,
        "处理自己消息" => false,
        "仅群主可用" => false,
        "屏蔽其他机器人" => false,
        "自动去开头艾特" => true
    ];
}

switch ($type) {
    case "add":
        $appid = trim($_REQUEST["appid"] ?? "");
        $secret = trim($_REQUEST["secret"] ?? "");
        $environment = trim($_REQUEST["environment"] ?? $_REQUEST["type"] ?? "正式");
        $qq_number = trim($_REQUEST["qq_number"] ?? "");
        $remark = trim($_REQUEST["remark"] ?? "");

        if ($appid === "" || $secret === "") {
            echo json_encode(["code" => 400, "msg" => "缺少 appid 或 secret"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (isset($main[$appid])) {
            echo json_encode(["code" => 400, "msg" => "该 AppID 已存在"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $main[$appid] = [
            "secret" => $secret,
            "type" => $environment,
            "qq_number" => $qq_number,
            "remark" => $remark,
            "settings" => 云雀_默认设置(),
            "plugin" => []
        ];
        file_put_contents($file, json_encode($main, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(["code" => 200, "msg" => "添加成功"], JSON_UNESCAPED_UNICODE);
        break;

    case "del":
        $appid = trim($_REQUEST["appid"] ?? "");
        if ($appid === "" || !isset($main[$appid])) {
            echo json_encode(["code" => 400, "msg" => "机器人不存在"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        unset($main[$appid]);
        file_put_contents($file, json_encode($main, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(["code" => 200, "msg" => "删除成功"], JSON_UNESCAPED_UNICODE);
        break;

    case "update":
        $appid = trim($_REQUEST["appid"] ?? "");
        if ($appid === "" || !isset($main[$appid])) {
            echo json_encode(["code" => 400, "msg" => "机器人不存在"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        // 允许部分更新
        foreach (["secret", "type", "qq_number", "remark"] as $field) {
            if (isset($_REQUEST[$field])) {
                $main[$appid][$field] = trim((string)$_REQUEST[$field]);
            }
        }
        // 设置项：JSON 字符串，如 {"群非艾特":true}
        if (isset($_REQUEST["settings"])) {
            $settings = json_decode($_REQUEST["settings"], true);
            if (is_array($settings)) {
                $main[$appid]["settings"] = array_merge(云雀_默认设置(), $settings);
            }
        }
        // 主人：JSON 字符串，如 {"name":"XX","id":"openid","qq_number":"","remark":""}
        if (isset($_REQUEST["主人"])) {
            $owner = json_decode($_REQUEST["主人"], true);
            if (is_array($owner)) {
                $main[$appid]["主人"] = array_merge([
                    "name" => "", "id" => "", "qq_number" => "", "remark" => ""
                ], $owner);
            }
        }
        file_put_contents($file, json_encode($main, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(["code" => 200, "msg" => "修改成功"], JSON_UNESCAPED_UNICODE);
        break;

    case "list":
        $list = [];
        foreach ($main as $appid => $cfg) {
            $list[] = [
                "appid" => $appid,
                "secret" => $cfg["secret"] ?? "",
                "type" => $cfg["type"] ?? "正式",
                "qq_number" => $cfg["qq_number"] ?? "",
                "remark" => $cfg["remark"] ?? "",
                "settings" => $cfg["settings"] ?? 云雀_默认设置(),
                "主人" => $cfg["主人"] ?? ["name" => "", "id" => "", "qq_number" => "", "remark" => ""],
                "plugin_count" => count($cfg["plugin"] ?? [])
            ];
        }
        echo json_encode(["code" => 200, "list" => $list], JSON_UNESCAPED_UNICODE);
        break;

    case "users":
        // 遍历该机器人全部日志，返回去重用户列表（用于「从日志选主人」）
        $appid = trim($_REQUEST["appid"] ?? "");
        $users = [];
        if ($appid !== "") {
            $files = glob(dirname(__DIR__, 2) . "/Log/{$appid}/*.log");
            foreach ($files as $file) {
                $lines = @file($file, FILE_IGNORE_NEW_LINES);
                if (!is_array($lines)) continue;
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
                            $users[$uid] = ['id' => $uid, 'username' => '', 'last_time' => '', 'scenes' => []];
                        }
                        if ($uname !== '' && $users[$uid]['username'] === '') $users[$uid]['username'] = $uname;
                        if (strcmp($m[1], $users[$uid]['last_time']) > 0) $users[$uid]['last_time'] = $m[1];
                    } catch (Throwable $e) {
                        continue;
                    }
                }
            }
        }
        $usersList = array_values($users);
        usort($usersList, function ($a, $b) {
            return strcmp($b['last_time'], $a['last_time']);
        });
        echo json_encode(["code" => 200, "users" => $usersList], JSON_UNESCAPED_UNICODE);
        break;

    case "groups":
        // 遍历该机器人全部日志，返回去重群列表（用于「插件作用域从日志选择」）
        $appid = trim($_REQUEST["appid"] ?? "");
        $groups = [];
        if ($appid !== "") {
            $files = glob(dirname(__DIR__, 2) . "/Log/{$appid}/*.log");
            foreach ($files as $file) {
                $lines = @file($file, FILE_IGNORE_NEW_LINES);
                if (!is_array($lines)) continue;
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || $line === '重复数据') continue;
                    if (!preg_match('/^\[([^\]]+)\]\s*(.*)$/', $line, $m)) continue;
                    try {
                        $d = json_decode($m[2], true);
                        if (!is_array($d)) continue;
                        $ev = $d['t'] ?? '';
                        $gid = $d['d']['group_openid'] ?? $d['d']['group_id'] ?? '';
                        if ($gid === '') continue;
                        if (!in_array($ev, ['GROUP_AT_MESSAGE_CREATE', 'GROUP_MESSAGE_CREATE', 'GROUP_ADD_ROBOT', 'GROUP_DEL_ROBOT'], true)) continue;
                        if (!isset($groups[$gid])) {
                            $groups[$gid] = ['id' => $gid, 'last_time' => ''];
                        }
                        if (strcmp($m[1], $groups[$gid]['last_time']) > 0) $groups[$gid]['last_time'] = $m[1];
                    } catch (Throwable $e) {
                        continue;
                    }
                }
            }
        }
        $groupsList = array_values($groups);
        usort($groupsList, function ($a, $b) {
            return strcmp($b['last_time'], $a['last_time']);
        });
        echo json_encode(["code" => 200, "groups" => $groupsList], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(["code" => 400, "msg" => "无效的请求类型"], JSON_UNESCAPED_UNICODE);
}
