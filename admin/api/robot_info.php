<?php
$type = $_REQUEST["type"] ?? "";

if (empty($type)) {
    $json = [
        "code" => 400,
        "msg" => "未传入数据"
    ];
    echo json_encode($json, 480);
    exit;
}

switch ($type) {
    case "get_info":
        $appid = $_REQUEST["appid"] ?? "";
        $qq_number = $_REQUEST["qq_number"] ?? "";
        
        if (empty($appid) || empty($qq_number)) {
            $json = [
                "code" => 400,
                "msg" => "缺少参数"
            ];
            echo json_encode($json, 480);
            break;
        }
        
        $robotInfo = 获取机器人简介($qq_number, $appid);
        echo json_encode($robotInfo, 480);
        break;
        
    case "get_info_by_appid":
        $appid = $_REQUEST["appid"] ?? "";
        
        if (empty($appid)) {
            $json = [
                "code" => 400,
                "msg" => "缺少appid参数"
            ];
            echo json_encode($json, 480);
            break;
        }
        
        // 从main.json获取QQ号
        $file = dirname(__DIR__,2)."/main.json";
        $main = json_decode(file_get_contents($file), true);
        
        if (!isset($main[$appid])) {
            $json = [
                "code" => 404,
                "msg" => "机器人不存在"
            ];
            echo json_encode($json, 480);
            break;
        }
        
        $qq_number = $main[$appid]["qq_number"] ?? "";
        
        if (empty($qq_number)) {
            $json = [
                "code" => 400,
                "msg" => "该机器人未设置QQ号"
            ];
            echo json_encode($json, 480);
            break;
        }
        
        $robotInfo = 获取机器人简介($qq_number, $appid);
        echo json_encode($robotInfo, 480);
        break;
}

function 获取机器人简介($qq_number, $appid) {
    try {
        // 构建机器人分享URL
        $robot_share_url = "https://qun.qq.com/qunpro/robot/qunshare?robot_uin={$qq_number}";
        
        // 调用QQ群机器人管理平台API获取机器人信息
        $url = "https://qun.qq.com/qunpro/robot/proxy/domain/qun.qq.com/cgi-bin/group_pro/robot/manager/share_info?bkn=508459323&robot_appid={$appid}";
        
        $headers = [
            'User-Agent: Mozilla/5.0 (Linux; Android 15; PJX110 Build/UKQ1.231108.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/135.0.7049.111 Mobile Safari/537.36 V1_AND_SQ_9.1.75_10026_HDBM_T PA QQ/9.1.75.25965 NetType/WIFI WebP/0.4.1 AppId/537287845 Pixel/1080 StatusBarHeight/120 SimpleUISwitch/0 QQTheme/1000 StudyMode/0 CurrentMode/0 CurrentFontScale/0.87 GlobalDensityScale/0.9028571 AllowLandscape/false InMagicWin/0',
            'qname-service: 976321:131072',
            'qname-space: Production'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return [
                "code" => 500,
                "msg" => "API请求失败",
                "data" => [
                    "qq" => $qq_number,
                    "name" => "获取失败",
                    "description" => "无法获取机器人信息",
                    "avatar" => "",
                    "appid" => $appid,
                    "developer" => "未知",
                    "link" => $robot_share_url,
                    "status" => "离线",
                    "is_banned" => false,
                    "mute_status" => 0,
                    "is_sharable" => false,
                    "service_note" => ""
                ]
            ];
        }
        
        $api_response = json_decode($response, true);
        
        if ($api_response['retcode'] !== 0) {
            $error_msg = $api_response['msg'] ?? 'Unknown error';
            return [
                "code" => 500,
                "msg" => "API返回错误: {$error_msg}",
                "data" => [
                    "qq" => $qq_number,
                    "name" => "获取失败",
                    "description" => "无法获取机器人信息",
                    "avatar" => "",
                    "appid" => $appid,
                    "developer" => "未知",
                    "link" => $robot_share_url,
                    "status" => "离线",
                    "is_banned" => false,
                    "mute_status" => 0,
                    "is_sharable" => false,
                    "service_note" => ""
                ]
            ];
        }
        
        $robot_data = $api_response['data']['robot_data'] ?? [];
        $commands = $api_response['data']['commands'] ?? [];
        
        $avatar_url = $robot_data['robot_avatar'] ?? '';
        
        return [
            "code" => 200,
            "msg" => "获取成功",
            "data" => [
                "qq" => $robot_data['robot_uin'] ?? $qq_number,
                "name" => $robot_data['robot_name'] ?? '未知机器人',
                "description" => $robot_data['robot_desc'] ?? '暂无描述',
                "avatar" => $avatar_url,
                "appid" => $robot_data['appid'] ?? $appid,
                "developer" => $robot_data['create_name'] ?? '未知',
                "link" => $robot_share_url,
                "status" => ($robot_data['robot_offline'] ?? 1) == 0 ? '正常' : '离线',
                "is_banned" => $robot_data['robot_ban'] ?? false,
                "mute_status" => $robot_data['mute_status'] ?? 0,
                "commands_count" => count($commands),
                "is_sharable" => $robot_data['is_sharable'] ?? false,
                "service_note" => $robot_data['service_note'] ?? ''
            ]
        ];
        
    } catch (Exception $e) {
        return [
            "code" => 500,
            "msg" => "获取机器人信息时发生错误: " . $e->getMessage(),
            "data" => [
                "qq" => $qq_number,
                "name" => "获取失败",
                "description" => "无法获取机器人信息",
                "avatar" => "",
                "appid" => $appid,
                "developer" => "未知",
                "link" => $robot_share_url,
                "status" => "离线",
                "is_banned" => false,
                "mute_status" => 0,
                "is_sharable" => false,
                "service_note" => ""
            ]
        ];
    }
}
?>
