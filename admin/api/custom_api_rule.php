<?php
header('Content-Type: application/json; charset=utf-8');

$type = $_REQUEST['type'] ?? 'list';
$dataFile = dirname(__DIR__) . '/database/custom_api_config.json';

function ensureRuleFile($file){
    $dir = dirname($file);
    if(!is_dir($dir)) mkdir($dir,0777,true);
    if(!is_file($file)) file_put_contents($file, json_encode(['apis'=>[], 'rules'=>[]], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
}
function readRules($file){
    ensureRuleFile($file);
    $d=json_decode(file_get_contents($file),true);
    if(is_array($d) && isset($d['rules']) && is_array($d['rules'])) return $d['rules'];
    return [];
}
function writeRules($file,$arr){
    ensureRuleFile($file);
    $d=json_decode(file_get_contents($file),true);
    if(!is_array($d)) $d=['apis'=>[], 'rules'=>[]];
    if(!isset($d['apis']) || !is_array($d['apis'])) $d['apis']=[];
    $d['rules']=array_values($arr);
    file_put_contents($file,json_encode($d,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
}

switch($type){
    case 'list':
        echo json_encode(['code'=>200,'data'=>readRules($dataFile)], JSON_UNESCAPED_UNICODE); break;
    case 'save':
        $id = $_POST['id'] ?? $_REQUEST['id'] ?? '';
        $item = [
            'id' => $id ?: ('rule_'.time().rand(100,999)),
            'name' => trim($_POST['name'] ?? ''),
            'enabled' => intval($_POST['enabled'] ?? 1),
            'scope' => trim($_POST['scope'] ?? 'all'), // all/group/private
            'match_type' => trim($_POST['match_type'] ?? 'keyword'), // keyword/equals/regex
            'pattern' => trim($_POST['pattern'] ?? ''),
            'api_id' => trim($_POST['api_id'] ?? ''),
            'reply_template' => trim($_POST['reply_template'] ?? '{response}'),
            'send_type' => trim($_POST['send_type'] ?? 'text'),
            'response_path' => trim($_POST['response_path'] ?? ''),
            'continue_on_match' => intval($_POST['continue_on_match'] ?? 0),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        if($item['name']===''||$item['pattern']===''||$item['api_id']===''){ echo json_encode(['code'=>400,'msg'=>'名称/规则/API不能为空'], JSON_UNESCAPED_UNICODE); exit; }
        $list = readRules($dataFile);
        $found=false;
        foreach($list as &$v){ if(($v['id']??'')===$item['id']){ $v=$item; $found=true; break; }}
        if(!$found) $list[]=$item;
        writeRules($dataFile,$list);
        echo json_encode(['code'=>200,'msg'=>'保存成功','id'=>$item['id']], JSON_UNESCAPED_UNICODE); break;
    case 'delete':
        $id = $_POST['id'] ?? $_REQUEST['id'] ?? '';
        $list = array_values(array_filter(readRules($dataFile), fn($x)=>($x['id']??'')!==$id));
        writeRules($dataFile,$list);
        echo json_encode(['code'=>200,'msg'=>'删除成功'], JSON_UNESCAPED_UNICODE); break;
    default:
        echo json_encode(['code'=>400,'msg'=>'unknown type'], JSON_UNESCAPED_UNICODE);
}
