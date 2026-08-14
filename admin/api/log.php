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
        // ---- 消息类 ----
        case "GROUP_AT_MESSAGE_CREATE":
            return "群聊 @机器人消息：" . (trim($json["d"]["content"] ?? "", "/ ") ?: "(无内容)");
        case "GROUP_MESSAGE_CREATE":
            return "群聊消息：" . (trim($json["d"]["content"] ?? "", "/ ") ?: "(无内容)");
        case "C2C_MESSAGE_CREATE":
            return "单聊消息：" . (trim($json["d"]["content"] ?? "", "/ ") ?: "(无内容)");
        case "DIRECT_MESSAGE_CREATE":
            return "频道私信：" . (trim($json["d"]["content"] ?? "", "/ ") ?: "(无内容)");
        case "AT_MESSAGE_CREATE":
            return "频道 @机器人消息：" . (trim($json["d"]["content"] ?? "", "/ ") ?: "(无内容)");
        case "MESSAGE_CREATE":
            return "子频道消息：" . (trim($json["d"]["content"] ?? "", "/ ") ?: "(无内容)");
        // ---- 消息撤回 ----
        case "MESSAGE_DELETE":
        case "PUBLIC_MESSAGE_DELETE":
            return "频道消息撤回";
        case "GROUP_AT_MESSAGE_DELETE":
            return "群聊 @消息撤回";
        case "GROUP_MESSAGE_DELETE":
            return "群聊消息撤回";
        case "C2C_MESSAGE_DELETE":
            return "单聊消息撤回";
        case "DIRECT_MESSAGE_DELETE":
            return "频道私信撤回";
        // ---- 群聊管理 ----
        case "GROUP_ADD_ROBOT":
            return "被拉入群聊";
        case "GROUP_DEL_ROBOT":
            return "被移出群聊";
        case "GROUP_MEMBER_ADD":
            return "成员入群";
        case "GROUP_MEMBER_REMOVE":
            return "成员退群";
        case "GROUP_JOIN_REQUEST":
            return "入群申请";
        case "GROUP_MSG_REJECT":
            return "群拒绝接收机器人消息（被拉黑）";
        case "GROUP_MSG_RECEIVE":
            return "群恢复接收机器人消息";
        case "GROUP_MSG_EMOJI_UPDATE":
            return "群消息表情表态";
        case "GROUP_MSG_EMOJI_REACTION":
            return "群消息表情回应";
        case "GROUP_AUDIT":
            return "群聊消息审核结果";
        case "GROUP_AUDIT_RETRY":
            return "群聊消息审核不通过重试";
        // ---- 单聊管理 ----
        case "FRIEND_ADD":
            return "添加好友";
        case "FRIEND_DEL":
            return "删除好友";
        case "C2C_MSG_REJECT":
            return "用户拒绝接收机器人消息（被拉黑）";
        case "C2C_MSG_RECEIVE":
            return "用户恢复接收机器人消息";
        // ---- 互动/按钮 ----
        case "INTERACTION_CREATE":
            $itype = $json["d"]["type"] ?? "";
            $map = [
                11 => "消息按钮回调",
                12 => "单聊快捷菜单回调",
                13 => "消息反馈（点赞/点踩）",
                14 => "清空会话",
                15 => "进出故事集",
                16 => "切换模型",
                18 => "用户授权",
                19 => "群授权",
                20 => "群授权状态变更",
            ];
            return "互动事件：" . ($map[$itype] ?? "类型{$itype}");
        // ---- 频道管理 ----
        case "GUILD_CREATE":
            return "进入频道";
        case "GUILD_UPDATE":
            return "频道信息更新";
        case "GUILD_DELETE":
            return "退出频道";
        case "GUILD_MEMBER_ADD":
            return "频道成员加入";
        case "GUILD_MEMBER_UPDATE":
            return "频道成员更新";
        case "GUILD_MEMBER_REMOVE":
            return "频道成员退出";
        case "CHANNEL_CREATE":
            return "子频道创建";
        case "CHANNEL_UPDATE":
            return "子频道更新";
        case "CHANNEL_DELETE":
            return "子频道删除";
        case "MESSAGE_REACTION_ADD":
            return "频道消息表情表态添加";
        case "MESSAGE_REACTION_REMOVE":
            return "频道消息表情表态取消";
        case "AUDIT":
            return "频道消息审核";
        case "FORUM_THREAD_CREATE":
            return "帖子创建";
        case "FORUM_THREAD_UPDATE":
            return "帖子更新";
        case "FORUM_THREAD_DELETE":
            return "帖子删除";
        case "FORUM_POST_CREATE":
            return "帖子评论创建";
        case "FORUM_POST_DELETE":
            return "帖子评论删除";
        case "FORUM_REPLY_CREATE":
            return "回复创建";
        case "FORUM_REPLY_DELETE":
            return "回复删除";
        case "FORUM_PUBLISH_EVENT":
            return "帖子发布事件";
        case "AUDIO_OR_LIVE_CHANNEL_MEMBER_ENTER":
            return "进入音频/直播频道";
        case "AUDIO_OR_LIVE_CHANNEL_MEMBER_EXIT":
            return "退出音频/直播频道";
        case "LIVE_CHANNEL_MEMBER_ENTER":
            return "进入直播频道";
        case "LIVE_CHANNEL_MEMBER_EXIT":
            return "退出直播频道";
        case "READY":
            return "WebSocket 连接就绪";
        case "RESUMED":
            return "WebSocket 连接恢复";
        default:
            return "未知事件";
    }
}