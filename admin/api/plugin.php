<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
$type = $_REQUEST["type"] ?? "";
$main = dirname(__DIR__,2)."/main.json";
/*
①全部插件列表(用来添加)
type = filelist

②添加插件
type = add
name = 插件名

③删除插件
type = delete
name = 插件名

④读取内容
type = read
name = 插件名

⑤写入内容
type = write
name = 插件名
POST:
{"content":"内容"}

⑥appid的插件列表
type = list
appid = appid

⑦开启插件
type = open
appid = appid
name = 插件名

⑦关闭插件
type = close
appid = appid
name = 插件名
*/
switch ($type) {
    case "list":
        $appid = $_REQUEST["appid"];
        $maincontent = file_get_contents($main);
        $json = json_decode($maincontent,true);
        echo json_encode($json[$appid]["plugin"]??"{}",480);
        break;
    case "open":
        $appid = $_REQUEST["appid"];
        $name = $_REQUEST["name"];
        $maincontent = file_get_contents($main);
        $json = json_decode($maincontent,true);
        $json[$appid]["plugin"][$name] = true;
        file_put_contents($main,json_encode($json,480));
        echo json_encode(["code"=>200],480);
        break;
    case "close":
        $appid = $_REQUEST["appid"];
        $name = $_REQUEST["name"];
        $maincontent = file_get_contents($main);
        $json = json_decode($maincontent,true);
        unset($json[$appid]["plugin"][$name]);
        file_put_contents($main,json_encode($json,480));
        echo json_encode(["code"=>200],480);
        break;
    case "filelist":
        $s=glob(dirname(__DIR__,2)."/plugin/*.php");
        $l=[];
        foreach($s as $va){
            $va = basename($va);
            $l[]=basename($va,".php");
        }
        $json = [
            "code" => 200,
            "list" => $l
        ];
        echo json_encode($json,480);
        break;
    case "add":
        $name = $_REQUEST["name"] ?? "";
        $path = dirname(__DIR__,2)."/plugin/".$name.".php";
        if (is_file($path)) {
            $json = [
            "code" => 400,
            "msg" => "插件已存在"
            ];
            echo json_encode($json,480);
            exit;
        }
        $add = file_put_contents($path,"<?php\n\n?>");
        if ($add) {
            $json = [
            "code" => 200
            ];
            echo json_encode($json,480);
        } else {
            $json = [
            "code" => 400,
            "msg" => "创建失败"
            ];
            echo json_encode($json,480);
        }
        break;
    case "delete":
        $name = $_REQUEST["name"] ?? "";
        $path = dirname(__DIR__,2)."/plugin/".$name.".php";
        if (is_file($path)) {
            if (unlink($path)) {
                $json = [
                "code" => 200
                ];
                echo json_encode($json,480);
            } else {
                $json = [
                "code" => 400,
                "msg" => "删除失败"
                ];
                echo json_encode($json,480);
            }
        } else {
            $json = [
            "code" => 400,
            "msg" => "插件不存在"
            ];
            echo json_encode($json,480);
        }
        break;
    case "read":
        $name = $_REQUEST["name"] ?? "";
        $path = dirname(__DIR__,2)."/plugin/".$name.".php";
        if (!is_file($path)) {
            $json = [
                "code" => 400,
                "msg" => "插件不存在"
            ];
            echo json_encode($json,480);
        } else {
            $content = file_get_contents($path);
            if($content) {
                $json = [
                   "code" => 200,
                   "msg" => $content
                ];
                echo json_encode($json,480);
            } else {
                $json = [
                   "code" => 400,
                   "msg" => "读取失败"
                ];
                echo json_encode($json,480);
            }
        }
        break;
    case "write":
        $name = $_REQUEST["name"] ?? "";
        $content = file_get_contents("php://input");
        $content = json_decode($content)->content;
        $path = dirname(__DIR__,2)."/plugin/".$name.".php";
        $put = file_put_contents($path,$content);
            if($put) {
                $json = [
                   "code" => 200,
                   "msg" => "写入成功"
                ];
                echo json_encode($json,480);
            } else {
                $json = [
                   "code" => 400,
                   "msg" => "写入失败"
                ];
                echo json_encode($json,480);
            }
        break;

    case "scopes":
        // 获取某个机器人的全部插件作用域配置
        $appid = $_REQUEST["appid"] ?? "";
        $maincontent = @file_get_contents($main);
        $json = json_decode($maincontent, true);
        $plugins = (isset($json[$appid]["plugin"]) && is_array($json[$appid]["plugin"])) ? $json[$appid]["plugin"] : [];
        $result = [];
        foreach ($plugins as $name => $cfg) {
            if (is_bool($cfg)) {
                $result[$name] = ["enable" => $cfg, "scope" => "all", "groups" => []];
            } elseif (is_array($cfg)) {
                $result[$name] = [
                    "enable" => (bool)($cfg["enable"] ?? false),
                    "scope" => $cfg["scope"] ?? "all",
                    "groups" => $cfg["groups"] ?? [],
                ];
            }
        }
        echo json_encode(["code" => 200, "scopes" => $result], 480);
        break;

    case "scope":
        // 设置插件作用域：scope = all / specified，specified 时指定 groups 群列表
        $appid = $_REQUEST["appid"] ?? "";
        $name = $_REQUEST["name"] ?? "";
        $scope = $_REQUEST["scope"] ?? "all";
        $groupsRaw = $_REQUEST["groups"] ?? "[]";
        $groups = json_decode($groupsRaw, true);
        if (!is_array($groups)) $groups = [];
        if ($appid === "" || $name === "") {
            echo json_encode(["code" => 400, "msg" => "缺少参数"], 480);
            exit;
        }
        $maincontent = @file_get_contents($main);
        $json = json_decode($maincontent, true);
        if (!is_array($json)) $json = [];
        if (!isset($json[$appid])) {
            echo json_encode(["code" => 400, "msg" => "机器人不存在"], 480);
            exit;
        }
        $cfg = [
            "enable" => true,
            "scope" => $scope === "specified" ? "specified" : "all",
            "groups" => array_values($groups),
        ];
        $json[$appid]["plugin"][$name] = $cfg;
        file_put_contents($main, json_encode($json, 480));
        echo json_encode(["code" => 200, "msg" => "作用域已保存"], 480);
        break;
}