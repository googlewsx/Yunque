<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
$file = dirname(__DIR__,2)."/main.json";
$type = $_REQUEST["type"] ?? "";

    if (empty($type)) {
        $json = [
            "code" => 400,
            "msg" => "未传入数据"
        ];
        echo json_encode($json,480);
        exit;
    }
    
    switch ($type) {
        case "list":
            $content = file_get_contents($file);
            $json = json_decode($content,true);
            $list = [];
            foreach ($json as $key => $value) {
                $appid = $key;
                $secret = $value["secret"];
                $environment = $value["type"];
                $fh = [
                    "appid" => $appid,
                    "secret" => $secret,
                    "type" => $environment
                ];
                $msg = BOT信息($appid,$secret,$environment);
                $fh["name"] = $msg["username"];
                $fh["avatar"] = $msg["avatar"];
                $fh["data"] = 数据统计($appid);
                $list[] =$fh;
            }
            echo json_encode($list,480);
            break;
    }
    
    
    
function 数据统计($appid) {
$file = dirname(__DIR__,2)."/database/事件判断/{$appid}/".date("Y-m-d");
$群聊 = 0;
$私聊 = 0;
$加群 = 0;
$退群 = 0;
$被删 = 0;
$添加 = 0;
    if (is_file($file)) {
        $content = file_get_contents($file);
        $json = json_decode($content,true);
        foreach ($json as $key => $value) {
            if (preg_match('/^([^:]+):/',$key,$matches)) {
                $type = $matches[1];
                switch ($type) {
                    case "GROUP_MESSAGE_CREATE":
                    case "GROUP_AT_MESSAGE_CREATE":
                        $群聊++;
                        break;
                    case "C2C_MESSAGE_CREATE":
                    case "DIRECT_MESSAGE_CREATE":
                        $私聊++;
                        break;
                    case "GROUP_ADD_ROBOT":
                    case "GROUP_MEMBER_ADD":
                        $加群++;
                        break;
                    case "GROUP_DEL_ROBOT":
                    case "GROUP_MEMBER_REMOVE":
                        $退群++;
                        break;
                    case "FRIEND_ADD":
                        $添加++;
                        break;
                    case "FRIEND_DEL":
                        $被删++;
                        break;
                }
            } else {
                return false;
            }
        }
        return [
        "群聊" => $群聊,
        "私聊" => $私聊,
        "加群" => $加群,
        "退群" => $退群,
        "添加" => $添加,
        "被删" => $被删
        ];
    } else {
        return [
        "群聊" => $群聊,
        "私聊" => $私聊,
        "加群" => $加群,
        "退群" => $退群,
        "添加" => $添加,
        "被删" => $被删
        ];
    }
}

function BOT信息($appid,$secret,$type = "正式") {
    $url = "https://bots.qq.com/app/getAppAccessToken";
    $json = json_encode(["appId" => (string)$appid, "clientSecret" => $secret]);
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $json,
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    $fw = json_decode($response, true);
    $Access = $fw["access_token"];
    $hosts = ["正式" => "https://api.bot.qq.com", "沙箱" => "https://sandbox.api.bot.qq.com"];
    $fallbacks = ["正式" => "https://api.sgroup.qq.com", "沙箱" => "https://sandbox.api.sgroup.qq.com"];
    $host = isset($hosts[$type]) ? $hosts[$type] : $hosts["正式"];
    $oldHost = isset($fallbacks[$type]) ? $fallbacks[$type] : $fallbacks["正式"];
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: QQBot ".$Access."\r\n" .
                        "Content-Type: application/json\r\n",
            'ignore_errors' => true,
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    $response = @file_get_contents($host . "/users/@me", false, $context);
    $decoded = json_decode($response, true);
    if (!is_array($decoded) || isset($decoded['code']) && $decoded['code'] == -1) {
        // 新域名不可达时回退旧域名
        $response2 = @file_get_contents($oldHost . "/users/@me", false, $context);
        $decoded2 = json_decode($response2, true);
        if (is_array($decoded2)) return $decoded2;
    }
    return is_array($decoded) ? $decoded : [];
}