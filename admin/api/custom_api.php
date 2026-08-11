<?php
header('Content-Type: application/json; charset=utf-8');

$type = $_REQUEST['type'] ?? 'list';
$dataFile = dirname(__DIR__) . '/database/custom_api_config.json';

function ensureDataFile($file) {
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    if (!is_file($file)) file_put_contents($file, json_encode([], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
}

function readData($file) {
    ensureDataFile($file);
    $raw = file_get_contents($file);
    $obj = json_decode($raw, true);
    if (is_array($obj) && isset($obj['apis']) && is_array($obj['apis'])) return $obj['apis'];
    return is_array($obj) ? $obj : [];
}

function writeData($file, $arr) {
    ensureDataFile($file);
    $raw = file_get_contents($file);
    $obj = json_decode($raw, true);
    if (!is_array($obj) || !isset($obj['apis'])) $obj = ['apis'=>[], 'rules'=>[]];
    if (!isset($obj['rules']) || !is_array($obj['rules'])) $obj['rules'] = [];
    $obj['apis'] = array_values($arr);
    file_put_contents($file, json_encode($obj, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
}

function replaceVars($text, $vars) {
    foreach ($vars as $k => $v) {
        $text = str_replace('{'.$k.'}', (string)$v, $text);
    }
    return $text;
}

try {
    switch ($type) {
        case 'list':
            echo json_encode(['code'=>200,'data'=>readData($dataFile)], JSON_UNESCAPED_UNICODE);
            break;

        case 'save':
            $id = $_POST['id'] ?? $_REQUEST['id'] ?? '';
            $item = [
                'id' => $id ?: ('api_' . time() . '_' . rand(100,999)),
                'name' => trim($_POST['name'] ?? $_REQUEST['name'] ?? ''),
                'url' => trim($_POST['url'] ?? $_REQUEST['url'] ?? ''),
                'method' => strtoupper(trim($_POST['method'] ?? $_REQUEST['method'] ?? 'GET')),
                'headers' => trim($_POST['headers'] ?? $_REQUEST['headers'] ?? '{}'),
                'body' => trim($_POST['body'] ?? $_REQUEST['body'] ?? ''),
                'timeout' => intval($_POST['timeout'] ?? $_REQUEST['timeout'] ?? 10),
                'enabled' => intval($_POST['enabled'] ?? $_REQUEST['enabled'] ?? 1),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if ($item['name'] === '' || $item['url'] === '') {
                echo json_encode(['code'=>400,'msg'=>'名称和URL不能为空'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $list = readData($dataFile);
            $found = false;
            foreach ($list as &$v) {
                if (($v['id'] ?? '') === $item['id']) {
                    $v = $item;
                    $found = true;
                    break;
                }
            }
            if (!$found) $list[] = $item;
            writeData($dataFile, $list);
            echo json_encode(['code'=>200,'msg'=>'保存成功','id'=>$item['id']], JSON_UNESCAPED_UNICODE);
            break;

        case 'delete':
            $id = $_POST['id'] ?? $_REQUEST['id'] ?? '';
            if (!$id) { echo json_encode(['code'=>400,'msg'=>'缺少id'], JSON_UNESCAPED_UNICODE); exit; }
            $list = array_values(array_filter(readData($dataFile), function($x) use ($id) { return ($x['id'] ?? '') !== $id; }));
            writeData($dataFile, $list);
            echo json_encode(['code'=>200,'msg'=>'删除成功'], JSON_UNESCAPED_UNICODE);
            break;


        case 'rule_list':
            ensureDataFile($dataFile);
            $raw = file_get_contents($dataFile);
            $obj = json_decode($raw, true);
            $rules = (is_array($obj) && isset($obj['rules']) && is_array($obj['rules'])) ? $obj['rules'] : [];
            echo json_encode(['code'=>200,'data'=>$rules], JSON_UNESCAPED_UNICODE);
            break;

        case 'rule_save':
            ensureDataFile($dataFile);
            $raw = file_get_contents($dataFile);
            $obj = json_decode($raw, true);
            if (!is_array($obj)) $obj = ['apis'=>[], 'rules'=>[]];
            if (!isset($obj['apis']) || !is_array($obj['apis'])) $obj['apis'] = [];
            if (!isset($obj['rules']) || !is_array($obj['rules'])) $obj['rules'] = [];

            $id = $_POST['id'] ?? $_REQUEST['id'] ?? '';
            $item = [
                'id' => $id ?: ('rule_'.time().rand(100,999)),
                'name' => trim($_POST['name'] ?? ''),
                'enabled' => intval($_POST['enabled'] ?? 1),
                'scope' => trim($_POST['scope'] ?? 'all'),
                'match_type' => trim($_POST['match_type'] ?? 'keyword'),
                'pattern' => trim($_POST['pattern'] ?? ''),
                'api_id' => trim($_POST['api_id'] ?? ''),
                'reply_template' => trim($_POST['reply_template'] ?? '{response}'),
                'send_type' => trim($_POST['send_type'] ?? 'text'),
                'response_path' => trim($_POST['response_path'] ?? ''),
                'continue_on_match' => intval($_POST['continue_on_match'] ?? 0),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            if($item['name']===''||$item['pattern']===''||$item['api_id']===''){
                echo json_encode(['code'=>400,'msg'=>'名称/规则/API不能为空'], JSON_UNESCAPED_UNICODE); exit;
            }
            $apiExists = false;
            foreach($obj['apis'] as $apiItem){
                if(($apiItem['id'] ?? '') === $item['api_id']) { $apiExists = true; break; }
            }
            if(!$apiExists){
                echo json_encode(['code'=>400,'msg'=>'绑定的API不存在，请重新选择'], JSON_UNESCAPED_UNICODE); exit;
            }
            if(!in_array($item['scope'], ['all','group','private'], true)) $item['scope'] = 'all';
            if(!in_array($item['match_type'], ['keyword','equals','regex'], true)) $item['match_type'] = 'keyword';
            if(!in_array($item['send_type'], ['text','image','video','native_md','card'], true)) $item['send_type'] = 'text';
            $found=false;
            foreach($obj['rules'] as &$v){ if(($v['id']??'')===$item['id']){ $v=$item; $found=true; break; }}
            if(!$found) $obj['rules'][] = $item;
            file_put_contents($dataFile, json_encode($obj, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
            echo json_encode(['code'=>200,'msg'=>'保存成功','id'=>$item['id']], JSON_UNESCAPED_UNICODE);
            break;

        case 'rule_delete':
            ensureDataFile($dataFile);
            $raw = file_get_contents($dataFile);
            $obj = json_decode($raw, true);
            if (!is_array($obj)) $obj = ['apis'=>[], 'rules'=>[]];
            if (!isset($obj['apis']) || !is_array($obj['apis'])) $obj['apis'] = [];
            if (!isset($obj['rules']) || !is_array($obj['rules'])) $obj['rules'] = [];
            $id = $_POST['id'] ?? $_REQUEST['id'] ?? '';
            $obj['rules'] = array_values(array_filter($obj['rules'], function($x) use ($id) { return ($x['id'] ?? '') !== $id; }));
            file_put_contents($dataFile, json_encode($obj, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
            echo json_encode(['code'=>200,'msg'=>'删除成功'], JSON_UNESCAPED_UNICODE);
            break;

        case 'test':
            $url = trim($_POST['url'] ?? $_REQUEST['url'] ?? '');
            $method = strtoupper(trim($_POST['method'] ?? $_REQUEST['method'] ?? 'GET'));
            $headersRaw = trim($_POST['headers'] ?? $_REQUEST['headers'] ?? '{}');
            $bodyRaw = trim($_POST['body'] ?? $_REQUEST['body'] ?? '');
            $timeout = intval($_POST['timeout'] ?? $_REQUEST['timeout'] ?? 10);
            $vars = [
                'msg' => $_POST['msg'] ?? $_REQUEST['msg'] ?? 'hello',
                'user_id' => $_POST['user_id'] ?? $_REQUEST['user_id'] ?? 'demo_user',
                'group_id' => $_POST['group_id'] ?? $_REQUEST['group_id'] ?? 'demo_group',
                'appid' => $_POST['appid'] ?? $_REQUEST['appid'] ?? '102753159',
            ];

            if (!$url) { echo json_encode(['code'=>400,'msg'=>'URL不能为空'], JSON_UNESCAPED_UNICODE); exit; }

            $url = replaceVars($url, $vars);
            $bodyRaw = replaceVars($bodyRaw, $vars);

            $headersMap = json_decode($headersRaw, true);
            if (!is_array($headersMap)) $headersMap = [];
            $headerLines = [];
            foreach ($headersMap as $k=>$v) $headerLines[] = $k.': '.$v;

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => max(1,$timeout),
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headerLines,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            if ($method !== 'GET' && $bodyRaw !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyRaw);

            $start = microtime(true);
            $resp = curl_exec($ch);
            $errno = curl_errno($ch);
            $err = curl_error($ch);
            $status = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
            curl_close($ch);
            $cost = round((microtime(true)-$start)*1000);

            if ($errno) {
                echo json_encode(['code'=>500,'msg'=>'请求失败: '.$err,'cost_ms'=>$cost], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['code'=>200,'status'=>$status,'cost_ms'=>$cost,'response'=>$resp], JSON_UNESCAPED_UNICODE);
            }
            break;

        default:
            echo json_encode(['code'=>400,'msg'=>'不支持的type'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    echo json_encode(['code'=>500,'msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
