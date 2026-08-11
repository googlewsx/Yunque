<?php
header('Content-Type: application/json; charset=utf-8');

// mbstring 缺失时的兼容垫片
if (!function_exists('mb_strpos')) {
    function mb_strpos($haystack, $needle, $offset = 0, $encoding = 'UTF-8') {
        return strpos((string)$haystack, (string)$needle, $offset);
    }
}

$type = $_REQUEST['type'] ?? 'list';
$root = dirname(__DIR__, 2);
$pluginDir = $root . '/plugin';
$mainFile = $root . '/main.json';

function apiGenSafeName($name){
    $name = trim($name);
    $name = preg_replace('/[\\\\\/\:\*\?"\<\>\|]/u', '', $name);
    $name = preg_replace('/\.php$/iu', '', $name);
    return $name;
}
function apiGenJson($data){ echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); exit; }
function apiGenPluginCode($cfg){
    $name = $cfg['name'];
    $trigger = $cfg['trigger'];
    $match = $cfg['match'];
    $url = $cfg['url'];
    $method = strtoupper($cfg['method']);
    $headers = $cfg['headers'];
    $body = $cfg['body'];
    $path = $cfg['path'];
    $reply = $cfg['reply'];
    $send = $cfg['send'];
    $timeout = intval($cfg['timeout']);
    $export = static function($v){ return var_export($v, true); };
    return "<?php\n".
"// 自定义API插件：".$name."\n".
"// 由后台自定义API插件生成器生成，可在插件管理中开关。\n\n".
"if (!function_exists('custom_api_plugin_value')) {\n".
"    function custom_api_plugin_value(\$raw, \$path) {\n".
"        if (\$path === '' || \$path === null) return \$raw;\n".
"        \$obj = json_decode((string)\$raw, true);\n".
"        if (!is_array(\$obj)) return \$raw;\n".
"        \$cur = \$obj;\n".
"        foreach (explode('.', \$path) as \$key) {\n".
"            if (\$key === '') continue;\n".
"            if (is_array(\$cur) && array_key_exists(\$key, \$cur)) \$cur = \$cur[\$key];\n".
"            else return \$raw;\n".
"        }\n".
"        return (is_array(\$cur) || is_object(\$cur)) ? json_encode(\$cur, JSON_UNESCAPED_UNICODE) : (string)\$cur;\n".
"    }\n".
"}\n".
"\$__name = ".$export($name).";\n".
"\$__trigger = ".$export($trigger).";\n".
"\$__match = ".$export($match).";\n".
"\$__url = ".$export($url).";\n".
"\$__method = ".$export($method).";\n".
"\$__headersRaw = ".$export($headers).";\n".
"\$__body = ".$export($body).";\n".
"\$__path = ".$export($path).";\n".
"\$__replyTpl = ".$export($reply).";\n".
"\$__send = ".$export($send).";\n".
"\$__msg = defined('消息') ? 消息 : '';\n".
"\$__hit = false;\n".
"if (\$__match === 'equals') \$__hit = (\$__msg === \$__trigger);\n".
"elseif (\$__match === 'regex') \$__hit = (@preg_match('/'.\$__trigger.'/u', \$__msg) === 1);\n".
"else \$__hit = (\$__trigger !== '' && mb_strpos(\$__msg, \$__trigger) !== false);\n".
"if (\$__hit) {\n".
"    \$__vars = [\n".
"        'msg' => \$__msg,\n".
"        'user_id' => (defined('用户') ? 用户 : ''),\n".
"        'group_id' => (defined('来源') ? 来源 : ''),\n".
"        'appid' => (defined('appid') ? appid : ''),\n".
"    ];\n".
"    foreach (\$__vars as \$k => \$v) {\n".
"        \$__url = str_replace('{'.\$k.'}', (string)\$v, \$__url);\n".
"        \$__body = str_replace('{'.\$k.'}', (string)\$v, \$__body);\n".
"        \$__headersRaw = str_replace('{'.\$k.'}', (string)\$v, \$__headersRaw);\n".
"    }\n".
"    \$__headersMap = json_decode(\$__headersRaw, true);\n".
"    \$__headers = [];\n".
"    if (is_array(\$__headersMap)) foreach (\$__headersMap as \$k => \$v) \$__headers[] = \$k . ': ' . \$v;\n".
"    \$__resp = curl(\$__url, \$__method, \$__headers, \$__body);\n".
"    if (is_array(\$__resp) && isset(\$__resp['Error'])) { wlog('[自定义API插件] '.\$__name.' 请求失败：'.\$__resp['Error']); return; }\n".
"    \$__val = custom_api_plugin_value((string)\$__resp, \$__path);\n".
"    \$__text = str_replace(['{response}','{msg}'], [(string)\$__val, \$__msg], \$__replyTpl);\n".
"    if (trim(\$__text) !== '') {\n".
"        if (\$__send === 'image') 图片(\$__text);\n".
"        elseif (\$__send === 'video') 视频(\$__text);\n".
"        elseif (\$__send === 'native_md') 原生MD(\$__text);\n".
"        elseif (\$__send === 'card') 文卡(['text'=>\$__text]);\n".
"        else 文字(\$__text);\n".
"    }\n".
"}\n";
}

if (!is_dir($pluginDir)) mkdir($pluginDir, 0777, true);

function apiGenMainData($mainFile){
    if (!is_file($mainFile)) return [];
    $data = json_decode(file_get_contents($mainFile), true);
    return is_array($data) ? $data : [];
}
function apiGenFirstAppid($data){
    foreach ($data as $appid => $cfg) return (string)$appid;
    return '';
}
function apiGenSetPlugin($mainFile, $name, $open){
    $data = apiGenMainData($mainFile);
    $appid = $_REQUEST['appid'] ?? apiGenFirstAppid($data);
    if ($appid === '') apiGenJson(['code'=>400,'msg'=>'未找到appid']);
    if (!isset($data[$appid]) || !is_array($data[$appid])) $data[$appid] = [];
    if (!isset($data[$appid]['plugin']) || !is_array($data[$appid]['plugin'])) $data[$appid]['plugin'] = [];
    if ($open) $data[$appid]['plugin'][$name] = true;
    else unset($data[$appid]['plugin'][$name]);
    file_put_contents($mainFile, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    apiGenJson(['code'=>200,'msg'=>$open?'已启用':'已关闭','appid'=>$appid,'plugin'=>$name]);
}


function apiGenReplaceVars($text){
    $vars = [
        'msg' => $_REQUEST['msg'] ?? 'hello',
        'user_id' => $_REQUEST['user_id'] ?? 'demo_user',
        'group_id' => $_REQUEST['group_id'] ?? 'demo_group',
        'appid' => $_REQUEST['appid'] ?? '',
    ];
    foreach ($vars as $k=>$v) $text = str_replace('{'.$k.'}', (string)$v, (string)$text);
    return $text;
}
function apiGenValueByPath($raw, $path){
    if ($path === '' || $path === null) return $raw;
    $obj = json_decode((string)$raw, true);
    if (!is_array($obj)) return $raw;
    $cur = $obj;
    foreach (explode('.', $path) as $key) {
        if ($key === '') continue;
        if (is_array($cur) && array_key_exists($key, $cur)) $cur = $cur[$key];
        else return $raw;
    }
    return (is_array($cur) || is_object($cur)) ? json_encode($cur, JSON_UNESCAPED_UNICODE) : (string)$cur;
}

try {
    if ($type === 'list') {
        $files = glob($pluginDir . '/自定义API_*.php') ?: [];
        $mainData = apiGenMainData($mainFile);
        $appid = $_REQUEST['appid'] ?? apiGenFirstAppid($mainData);
        $enabled = ($appid !== '' && isset($mainData[$appid]['plugin']) && is_array($mainData[$appid]['plugin'])) ? $mainData[$appid]['plugin'] : [];
        $list = [];
        foreach ($files as $f) {
            $n = basename($f, '.php');
            $list[] = ['name'=>$n, 'enabled'=>isset($enabled[$n]), 'path'=>$f];
        }
        apiGenJson(['code'=>200, 'appid'=>$appid, 'list'=>$list]);
    }
    if ($type === 'test') {
        $url = trim($_POST['url'] ?? $_REQUEST['url'] ?? '');
        $method = strtoupper(trim($_POST['method'] ?? $_REQUEST['method'] ?? 'GET'));
        $headersRaw = trim($_POST['headers'] ?? $_REQUEST['headers'] ?? '{}');
        $body = trim($_POST['body'] ?? $_REQUEST['body'] ?? '');
        $path = trim($_POST['path'] ?? $_REQUEST['path'] ?? '');
        $timeout = max(1, intval($_POST['timeout'] ?? $_REQUEST['timeout'] ?? 10));
        if ($url === '') apiGenJson(['code'=>400,'msg'=>'接口URL不能为空']);
        if (!in_array($method, ['GET','POST'], true)) $method = 'GET';
        $url = apiGenReplaceVars($url);
        $body = apiGenReplaceVars($body);
        $headersRaw = apiGenReplaceVars($headersRaw);
        $headersMap = json_decode($headersRaw, true);
        if (!is_array($headersMap)) $headersMap = [];
        $headers = [];
        foreach ($headersMap as $k=>$v) $headers[] = $k . ': ' . $v;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        if ($method !== 'GET' && $body !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $start = microtime(true);
        $resp = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $status = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);
        $cost = round((microtime(true)-$start)*1000);
        if ($errno) apiGenJson(['code'=>500,'msg'=>'请求失败：'.$err,'cost_ms'=>$cost]);
        apiGenJson(['code'=>200,'status'=>$status,'cost_ms'=>$cost,'response'=>$resp,'value'=>apiGenValueByPath($resp, $path),'path'=>$path]);
    }
    if ($type === 'generate') {
        $name = apiGenSafeName($_POST['name'] ?? $_REQUEST['name'] ?? '');
        if ($name === '') apiGenJson(['code'=>400,'msg'=>'插件名不能为空']);
        if (mb_strpos($name, '自定义API_') !== 0) $name = '自定义API_' . $name;
        $cfg = [
            'name'=>$name,
            'trigger'=>trim($_POST['trigger'] ?? ''),
            'match'=>trim($_POST['match'] ?? 'keyword'),
            'url'=>trim($_POST['url'] ?? ''),
            'method'=>strtoupper(trim($_POST['method'] ?? 'GET')),
            'headers'=>trim($_POST['headers'] ?? '{}'),
            'body'=>trim($_POST['body'] ?? ''),
            'path'=>trim($_POST['path'] ?? ''),
            'reply'=>trim($_POST['reply'] ?? '{response}'),
            'send'=>trim($_POST['send'] ?? 'text'),
            'timeout'=>intval($_POST['timeout'] ?? 10),
        ];
        if ($cfg['trigger'] === '' || $cfg['url'] === '') apiGenJson(['code'=>400,'msg'=>'触发词和接口URL不能为空']);
        if (!in_array($cfg['match'], ['keyword','equals','regex'], true)) $cfg['match'] = 'keyword';
        if (!in_array($cfg['method'], ['GET','POST'], true)) $cfg['method'] = 'GET';
        if (!in_array($cfg['send'], ['text','image','video','native_md','card'], true)) $cfg['send'] = 'text';
        if (json_decode($cfg['headers'], true) === null && trim($cfg['headers']) !== '{}' && trim($cfg['headers']) !== '') apiGenJson(['code'=>400,'msg'=>'请求头必须是JSON对象']);
        $path = $pluginDir . '/' . $name . '.php';
        file_put_contents($path, apiGenPluginCode($cfg));
        apiGenJson(['code'=>200,'msg'=>'插件生成成功','plugin'=>$name,'path'=>$path]);
    }
    if ($type === 'open' || $type === 'close') {
        $name = apiGenSafeName($_REQUEST['name'] ?? '');
        $path = $pluginDir . '/' . $name . '.php';
        if (!is_file($path) || mb_strpos($name, '自定义API_') !== 0) apiGenJson(['code'=>400,'msg'=>'插件不存在或不允许操作']);
        apiGenSetPlugin($mainFile, $name, $type === 'open');
    }
    if ($type === 'delete') {
        $name = apiGenSafeName($_REQUEST['name'] ?? '');
        $path = $pluginDir . '/' . $name . '.php';
        if (!is_file($path) || mb_strpos($name, '自定义API_') !== 0) apiGenJson(['code'=>400,'msg'=>'插件不存在或不允许删除']);
        $data = apiGenMainData($mainFile);
        foreach ($data as &$app) if (is_array($app) && isset($app['plugin']) && is_array($app['plugin'])) unset($app['plugin'][$name]);
        file_put_contents($mainFile, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        unlink($path);
        apiGenJson(['code'=>200,'msg'=>'删除成功']);
    }
    apiGenJson(['code'=>400,'msg'=>'unknown type']);
} catch (Throwable $e) { apiGenJson(['code'=>500,'msg'=>$e->getMessage()]); }
