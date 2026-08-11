<?php
// 目标代理接口地址
$targetUrl = 'http://38.76.206.101:5200/?appid=1904139651';

// 初始化 cURL
$ch = curl_init($targetUrl);

// cURL 基础配置
curl_setopt_array($ch, [
    CURLOPT_FOLLOWLOCATION => true,  // 跟随重定向
    CURLOPT_TIMEOUT        => 30,    // 超时时间 30 秒
    CURLOPT_CONNECTTIMEOUT => 10,    // 连接超时 10 秒
    CURLOPT_RETURNTRANSFER => true,   // 不直接输出，返回内容
    CURLOPT_SSL_VERIFYPEER => false, // 非 HTTPS 可忽略，兼容配置
    CURLOPT_SSL_VERIFYHOST => false,
]);

// 执行请求，获取接口返回内容
$response = curl_exec($ch);
// 获取请求状态信息
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$errorMsg = curl_error($ch);

// 关闭 cURL
curl_close($ch);

// 处理请求异常
if ($errorMsg) {
    http_response_code(502);
    exit("代理请求失败：" . $errorMsg);
}

// 转发原接口的 HTTP 状态码
http_response_code($httpCode);

// 原样输出接口返回内容
echo $response;
exit;
?>
