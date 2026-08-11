<?php
/**
 * 云雀 · 后台账号 API
 * 支持多管理员账号（config.json 的 admins 数组）。
 */
header('Content-Type: application/json');

$file = dirname(__DIR__, 2) . "/config.json";
$config = json_decode(@file_get_contents($file), true);
if (!is_array($config)) $config = [];
$admins = $config["admins"] ?? [];
if (!is_array($admins) || count($admins) === 0) {
    // 兼容旧版 config.json：顶层 admin / password 作为唯一管理员
    $oldName = $config["admin"] ?? "";
    $oldPassword = $config["password"] ?? "";
    $admins = ($oldName === "") ? [] : [["name" => $oldName, "password" => $oldPassword, "role" => "管理员", "note" => "旧版配置"]];
}

$type = $_POST["type"] ?? $_REQUEST["type"] ?? "";

function 云雀_查找管理员(array $admins, string $name) {
    foreach ($admins as $a) {
        if (($a["name"] ?? '') === $name) return $a;
    }
    return null;
}

function 云雀_保存配置($file, $config, $admins) {
    $config["admins"] = $admins;
    unset($config["admin"], $config["password"]);
    file_put_contents($file, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

switch ($type) {
    case "login":
        $admin = trim($_POST["admin"] ?? "");
        $password = trim($_POST["password"] ?? "");
        if ($admin === "" || $password === "") {
            echo json_encode(["code" => 400, "msg" => "缺少参数"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $a = 云雀_查找管理员($admins, $admin);
        if ($a && ($a["password"] ?? '') === $password) {
            echo json_encode([
                "code" => 200,
                "msg" => "登录成功",
                "name" => $admin,
                "role" => $a["role"] ?? "管理员"
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(["code" => 400, "msg" => "账号或密码错误"], JSON_UNESCAPED_UNICODE);
        }
        break;

    case "set":
        // 修改自己密码：需要当前账号、原密码、新密码
        $admin = trim($_POST["admin"] ?? "");
        $old_password = trim($_POST["old_password"] ?? "");
        $new_password = trim($_POST["password"] ?? "");
        if ($admin === "" || $old_password === "" || $new_password === "") {
            echo json_encode(["code" => 400, "msg" => "缺少参数"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $found = false;
        foreach ($admins as $i => $a) {
            if (($a["name"] ?? '') === $admin) {
                if (($a["password"] ?? '') !== $old_password) {
                    echo json_encode(["code" => 400, "msg" => "原密码错误"], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $admins[$i]["password"] = $new_password;
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo json_encode(["code" => 400, "msg" => "账号不存在"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        云雀_保存配置($file, $config, $admins);
        echo json_encode(["code" => 200, "msg" => "密码修改成功"], JSON_UNESCAPED_UNICODE);
        break;

    case "admins":
        // 管理员列表（不返回密码）
        $list = [];
        foreach ($admins as $a) {
            $list[] = [
                "name" => $a["name"] ?? "",
                "role" => $a["role"] ?? "管理员",
                "note" => $a["note"] ?? ""
            ];
        }
        echo json_encode(["code" => 200, "list" => $list], JSON_UNESCAPED_UNICODE);
        break;

    case "add_admin":
        $name = trim($_POST["name"] ?? "");
        $password = trim($_POST["password"] ?? "");
        $role = trim($_POST["role"] ?? "管理员");
        if ($name === "" || $password === "") {
            echo json_encode(["code" => 400, "msg" => "账号与密码不能为空"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (云雀_查找管理员($admins, $name)) {
            echo json_encode(["code" => 400, "msg" => "账号已存在"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $admins[] = ["name" => $name, "password" => $password, "role" => $role, "note" => ""];
        云雀_保存配置($file, $config, $admins);
        echo json_encode(["code" => 200, "msg" => "添加成功"], JSON_UNESCAPED_UNICODE);
        break;

    case "del_admin":
        $name = trim($_POST["name"] ?? "");
        if ($name === "") {
            echo json_encode(["code" => 400, "msg" => "缺少账号"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (count($admins) <= 1) {
            echo json_encode(["code" => 400, "msg" => "至少保留一个管理员"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $newAdmins = array_values(array_filter($admins, function ($a) use ($name) {
            return ($a["name"] ?? '') !== $name;
        }));
        if (count($newAdmins) === count($admins)) {
            echo json_encode(["code" => 400, "msg" => "账号不存在"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        云雀_保存配置($file, $config, $newAdmins);
        echo json_encode(["code" => 200, "msg" => "删除成功"], JSON_UNESCAPED_UNICODE);
        break;

    case "role":
        $name = trim($_POST["name"] ?? "");
        $role = trim($_POST["role"] ?? "");
        if ($name === "" || $role === "") {
            echo json_encode(["code" => 400, "msg" => "缺少参数"], JSON_UNESCAPED_UNICODE);
            exit;
        }
        foreach ($admins as $i => $a) {
            if (($a["name"] ?? '') === $name) {
                $admins[$i]["role"] = $role;
                云雀_保存配置($file, $config, $admins);
                echo json_encode(["code" => 200, "msg" => "修改成功"], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        echo json_encode(["code" => 400, "msg" => "账号不存在"], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(["code" => 400, "msg" => "无效的请求类型"], JSON_UNESCAPED_UNICODE);
}
