<?php
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
}