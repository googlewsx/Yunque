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
        "仅群主可用" => false
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

    default:
        echo json_encode(["code" => 400, "msg" => "无效的请求类型"], JSON_UNESCAPED_UNICODE);
}
