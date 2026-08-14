<?php
/**
 * 云雀 · 机器人管理 API
 * 支持：add / del / update / list
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

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

/** 环境规范化：只允许「正式」「沙箱」，非法值回退「正式」 */
function 云雀_环境($v) {
    $v = trim((string)$v);
    return ($v === '沙箱') ? '沙箱' : '正式';
}

function 云雀_群名缓存文件($appid) {
    return dirname(__DIR__) . "/data/group_names_{$appid}.json";
}
function 云雀_读群名缓存($appid) {
    $map = json_decode(@file_get_contents(云雀_群名缓存文件($appid)), true);
    return is_array($map) ? $map : [];
}

switch ($type) {
    case "add":
        $appid = trim($_REQUEST["appid"] ?? "");
        $secret = trim($_REQUEST["secret"] ?? "");
        // 只接受显式 environment 参数，回退默认「正式」，不再取接口操作参数 type
        $environment = 云雀_环境($_REQUEST["environment"] ?? "正式");
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
        // 允许部分更新（注意：type 是接口操作参数，不是环境字段，此处排除）
        foreach (["secret", "qq_number", "remark"] as $field) {
            if (isset($_REQUEST[$field])) {
                $main[$appid][$field] = trim((string)$_REQUEST[$field]);
            }
        }
        // 环境只允许「正式」「沙箱」，通过 environment 参数更新，非法值回退「正式」
        if (isset($_REQUEST["environment"])) {
            $main[$appid]["type"] = 云雀_环境($_REQUEST["environment"]);
        } elseif (isset($main[$appid]["type"])) {
            $main[$appid]["type"] = 云雀_环境($main[$appid]["type"]);
        } else {
            $main[$appid]["type"] = "正式";
        }
        // 设置项：JSON 字符串，如 {"群非艾特":true}。
        // 前端会全量传回 7 个开关状态，直接覆盖存储，避免关闭项被漏写回默认勾选。
        if (isset($_REQUEST["settings"])) {
            $settings = json_decode($_REQUEST["settings"], true);
            if (is_array($settings)) {
                $main[$appid]["settings"] = $settings;
            }
        }
        // 主人：JSON，可为单对象 {"name":"XX","id":"openid","qq_number":"","remark":""}
        // 或多主人数组 [ {...}, {...} ]，统一规范化为数组存储
        if (isset($_REQUEST["主人"])) {
            $owner = json_decode($_REQUEST["主人"], true);
            $list = [];
            if (is_array($owner)) {
                $list = (isset($owner['id']) || isset($owner['name'])) ? [$owner] : array_values($owner);
            }
            $clean = [];
            foreach ($list as $one) {
                if (!is_array($one)) continue;
                $one['name'] = trim((string)($one['name'] ?? ''));
                $one['id'] = trim((string)($one['id'] ?? ''));
                $one['qq_number'] = trim((string)($one['qq_number'] ?? ''));
                $one['remark'] = trim((string)($one['remark'] ?? ''));
                if ($one['id'] !== '' || $one['qq_number'] !== '') {
                    $clean[] = $one;
                }
            }
            if (count($clean) === 1) {
                $main[$appid]["主人"] = $clean[0];
            } elseif (count($clean) > 1) {
                $main[$appid]["主人"] = $clean;
            } elseif (isset($main[$appid]["主人"])) {
                unset($main[$appid]["主人"]);
            }
        }
        file_put_contents($file, json_encode($main, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(["code" => 200, "msg" => "修改成功"], JSON_UNESCAPED_UNICODE);
        break;

    case "list":
        $list = [];
        $dirty = false;
        foreach ($main as $appid => $cfg) {
            $type = 云雀_环境($cfg["type"] ?? "正式");
            if (($cfg["type"] ?? "正式") !== $type) {
                $main[$appid]["type"] = $type;
                $dirty = true;
            }
            $owner = $cfg["主人"] ?? null;
            $ownerOut = null;
            if (is_array($owner)) {
                if (isset($owner['id']) || isset($owner['qq_number']) || isset($owner['name'])) {
                    $clean = array_merge(["name" => "", "id" => "", "qq_number" => "", "remark" => ""], $owner);
                    if ($clean['id'] !== '' || $clean['qq_number'] !== '') $ownerOut = $clean;
                } else {
                    $clean = [];
                    foreach (array_values($owner) as $one) {
                        if (!is_array($one)) continue;
                        $one = array_merge(["name" => "", "id" => "", "qq_number" => "", "remark" => ""], $one);
                        if ($one['id'] !== '' || $one['qq_number'] !== '') $clean[] = $one;
                    }
                    if ($clean) $ownerOut = count($clean) === 1 ? $clean[0] : $clean;
                }
            }
            $list[] = [
                "appid" => $appid,
                "secret" => $cfg["secret"] ?? "",
                "type" => $type,
                "qq_number" => $cfg["qq_number"] ?? "",
                "remark" => $cfg["remark"] ?? "",
                "settings" => array_merge(云雀_默认设置(), is_array($cfg["settings"] ?? null) ? $cfg["settings"] : []),
                "主人" => $ownerOut,
                "plugin_count" => count($cfg["plugin"] ?? [])
            ];
        }
        if ($dirty) {
            file_put_contents($file, json_encode($main, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
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
                        } elseif ($ev === 'GROUP_MEMBER_ADD' || $ev === 'GROUP_MEMBER_REMOVE') {
                            $uid = $d['d']['member_openid'] ?? '';
                            $uname = '';
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
                        if (!in_array($ev, ['GROUP_AT_MESSAGE_CREATE', 'GROUP_MESSAGE_CREATE', 'GROUP_ADD_ROBOT', 'GROUP_DEL_ROBOT', 'GROUP_MEMBER_ADD', 'GROUP_MEMBER_REMOVE'], true)) continue;
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
        $groupNameMap = 云雀_读群名缓存($appid);
        foreach ($groupsList as $i => $g) {
            $gid = $g['id'];
            $groupsList[$i]['name'] = isset($groupNameMap[$gid]) ? (string)$groupNameMap[$gid] : '';
        }
        usort($groupsList, function ($a, $b) {
            return strcmp($b['last_time'], $a['last_time']);
        });
        echo json_encode(["code" => 200, "groups" => $groupsList], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(["code" => 400, "msg" => "无效的请求类型"], JSON_UNESCAPED_UNICODE);
}
