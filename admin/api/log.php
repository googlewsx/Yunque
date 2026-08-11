<?php
header('Content-Type: application/json');

$type = $_REQUEST["type"] ?? "";
$appid = $_REQUEST["appid"] ?? "";
$name = $_REQUEST["name"] ?? date("Y-m-d").".log";
$path = dirname(__DIR__, 2)."/Log/{$appid}/".$name;

/*
①日志列表
type = list
appid 

②删除日志
type = delete
name = 日志名
appid 

③读取日志
type = read
name = 日志名
appid 
*/

switch ($type) {
    case "list":
        $dir = glob(dirname(__DIR__, 2)."/Log/{$appid}/*.log");
        $logs = [];
        foreach($dir as $va) {
            $logs[] = basename($va);
        }
        // 按日期倒序排序
        rsort($logs);
        echo json_encode([
            "code" => 200,
            "list" => $logs
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    case "delete":
        if (!is_file($path)) {
            echo json_encode([
                "code" => 400,
                "msg" => "日志不存在"
            ], JSON_UNESCAPED_UNICODE);
        } else {
            if (unlink($path)) {
                echo json_encode([
                    "code" => 200,
                    "msg" => "删除成功"
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    "code" => 500,
                    "msg" => "删除失败"
                ], JSON_UNESCAPED_UNICODE);
            }
        }
        break;
        
    case "read":
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
                "list" => []
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $content = explode("\n", $content);
        $result = [];
        foreach ($content as $value) {
            if (preg_match('/^\[([^\]]+)\]\s*(.*)$/', $value, $matches)) {
                $time = $matches[1];
                $json = $matches[2];
                if ($json == "重复数据") {
                    continue;
                } else {
                    $res = [
                        "time" => $time,
                        "raw" => $json,
                        "summary" => event($json)
                    ];
                    array_unshift($result, $res);
                }
            }
        }
        echo json_encode([
            "code" => 200,
            "list" => $result
        ], JSON_UNESCAPED_UNICODE);
        break;
        
    default:
        echo json_encode([
            "code" => 400,
            "msg" => "无效的请求类型"
        ], JSON_UNESCAPED_UNICODE);
}

function event($json) {
    $json = json_decode($json, true);
    if (!is_array($json)) {
        return "无效";
    }
    
    $t = $json["t"] ?? "";
    switch ($t) {
        case "GROUP_MESSAGE_CREATE":
        case "GROUP_AT_MESSAGE_CREATE":
            return trim($json["d"]["content"] ?? "", "/ ");
            break;
        case "C2C_MESSAGE_CREATE":
        case "DIRECT_MESSAGE_CREATE":
            return trim($json["d"]["content"] ?? "", "/ ");
            break;
        case "GROUP_ADD_ROBOT":
            return "被邀进群";
            break;
        case "GROUP_DEL_ROBOT":
            return "被踢出群";
            break;
        case "FRIEND_ADD":
            return "添加好友";
            break;
        case "FRIEND_DEL":
            return "删除好友";
            break;
        case "MESSAGE_CREATE":
            return trim($json["d"]["content"] ?? "", "/ ");
            break;
        default:
            return "未知事件";
    }
}