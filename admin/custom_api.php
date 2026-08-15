<?php
if (!isset($_COOKIE['admin_token'])) {
    header("Location: index.php");
    exit();
}
$active_page = 'custom_api';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API插件生成器</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin-common.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg: #eef1f8; --card: #ffffff; --border: #e4e9f4;
            --primary: #5b6cff; --primary-hover: #4a5ae8; --primary-light: #eef0ff;
            --brand2: #8f9aff;
            --text-main: #1f2437; --text-sub: #6b7396; --text-muted: #9aa3c0;
            --danger: #ff6b6b; --success: #2ecc71;
            --sidebar-width: 240px; --header-height: 56px;
        }
        body {
            background: var(--bg);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'PingFang SC', 'Microsoft YaHei', sans-serif;
            color: var(--text-main); line-height: 1.5;
        }
        .desktop-layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: var(--sidebar-width); background: var(--card);
            border-right: 1px solid var(--border);
            position: fixed; top: 0; bottom: 0; left: 0;
            display: flex; flex-direction: column; z-index: 50;
        }
        .sidebar-header { padding: 22px 24px 18px; border-bottom: 1px solid var(--border); }
        .sidebar-header h1 { font-size: 17px; font-weight: 700; color: var(--text-main); }
        .sidebar-header p { font-size: 11.5px; color: var(--text-muted); margin-top: 4px; }
        .sidebar-nav { flex: 1; padding: 14px 12px; overflow-y: auto; }
        .nav-item {
            display: flex; align-items: center; gap: 11px;
            padding: 10px 14px; color: var(--text-sub);
            text-decoration: none; font-size: 13.5px; font-weight: 500;
            border-radius: 9px; margin-bottom: 2px; transition: all .15s;
        }
        .nav-item:hover { background: #f1f3fb; color: var(--primary); }
        .nav-item.active { background: var(--primary-light); color: var(--primary); font-weight: 600; }
        .nav-item i { width: 18px; font-size: 14px; text-align: center; }
        .sidebar-footer { padding: 14px 20px; border-top: 1px solid var(--border); font-size: 11px; color: var(--text-muted); }
        .main-content { flex: 1; margin-left: var(--sidebar-width); min-height: 100vh; }
        .top-bar {
            background: var(--card); border-bottom: 1px solid var(--border);
            padding: 0 28px; height: var(--header-height);
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 10;
        }
        .page-title { font-size: 15px; font-weight: 650; color: var(--text-main); }
        .container { padding: 24px 28px; max-width: 1000px; }
        .card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 12px; padding: 20px 22px; margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(31,36,55,.04);
        }
        .card h3 { font-size: 15px; font-weight: 650; color: var(--text-main); margin-bottom: 6px; }
        .hint { font-size: 12.5px; color: var(--text-muted); }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; }
        .row3 { display: grid; grid-template-columns: 1fr 1fr 140px; gap: 10px; margin-top: 10px; }
        input, select, textarea {
            width: 100%; padding: 9px 13px; border-radius: 10px;
            border: 1.5px solid var(--border); background: #f7f8fd;
            box-sizing: border-box; font-size: 13.5px; font-family: inherit;
            color: var(--text-main); transition: all .15s;
        }
        input:focus, select:focus, textarea:focus {
            outline: none; border-color: var(--primary); background: #fff;
            box-shadow: 0 0 0 3px rgba(91,108,255,.1);
        }
        textarea { min-height: 86px; resize: vertical; }
        .btn {
            padding: 9px 18px; border-radius: 10px; border: none;
            cursor: pointer; font-size: 13px; font-weight: 600;
            font-family: inherit; transition: all .15s;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--brand2));
            color: #fff; box-shadow: 0 2px 8px rgba(91,108,255,.25);
        }
        .btn-primary:hover { filter: brightness(.95); }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-danger:hover { filter: brightness(.95); }
        .btn-default { background: #f1f3fb; color: var(--text-sub); border: 1px solid var(--border); }
        .btn-default:hover { background: #e8ebf8; }
        .actions { display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap; margin-top: 14px; }
        .list { display: grid; gap: 8px; margin-top: 10px; }
        .item {
            display: grid; grid-template-columns: 1fr auto; gap: 10px; align-items: center;
            padding: 12px 14px; border: 1px solid var(--border);
            border-radius: 10px; background: #f7f8fd;
        }
        .name { font-weight: 600; font-size: 13.5px; }
        .meta { font-size: 12px; color: var(--text-muted); word-break: break-all; margin-top: 2px; }
        details { margin-top: 12px; }
        summary { cursor: pointer; color: var(--primary); font-size: 13px; font-weight: 500; }
        #result {
            background: #1f2437; color: #c8cef0; border-radius: 10px;
            padding: 14px; font-size: 12px; font-family: 'SF Mono', Monaco, Consolas, monospace;
            white-space: pre-wrap; word-break: break-word; margin-top: 10px;
            max-height: 300px; overflow-y: auto;
        }
        .mobile-header {
            display: none; padding: 12px 16px; background: #fff;
            border-bottom: 1px solid var(--border);
            align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 60;
        }
        .menu-toggle { background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text-main); }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform .2s; z-index: 200; box-shadow: 2px 0 14px rgba(0,0,0,.12); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-header { display: flex; }
            .top-bar { display: none; }
            .container { padding: 18px 14px; }
            .row, .row3, .item { grid-template-columns: 1fr !important; }
            input, select, textarea { font-size: 16px; }
            .actions { justify-content: flex-start; }
        }
    </style>
</head>
<body>
<?php include '_nav.php'; ?>
    <main class="main-content">
            <div class="top-bar">
                <div class="page-title">API插件生成器</div>
                <a href="main.php" class="btn btn-default"><i class="fas fa-arrow-left"></i> 返回后台</a>
            </div>
            <div class="container">
                <div class="card">
                    <h3>API插件生成器</h3>
                    <div class="hint">填完后会直接生成一个 PHP 插件，后续去"插件管理"里开关，逻辑比 JSON 规则更直观。</div>
                    <div class="row3">
                        <input id="name" placeholder="插件名，例如：一言">
                        <input id="trigger" placeholder="触发词，例如：一言">
                        <select id="match"><option value="keyword">包含触发</option><option value="equals">完全匹配</option><option value="regex">正则匹配</option></select>
                    </div>
                    <div class="row">
                        <input id="url" placeholder="接口URL，支持 {msg} {user_id} {group_id} {appid}">
                        <input id="path" placeholder="返回字段，可空，如 hitokoto 或 data.text">
                    </div>
                    <div class="row">
                        <input id="reply" value="{response}" placeholder="回复模板，例如：结果：{response}">
                        <select id="send"><option value="text">文字</option><option value="native_md">原生MD</option><option value="image">图片</option><option value="video">视频</option><option value="card">卡片</option></select>
                    </div>
                    <details>
                        <summary>高级请求设置</summary>
                        <div class="row3" style="grid-template-columns:140px 140px 1fr;">
                            <select id="method"><option>GET</option><option>POST</option></select>
                            <input id="timeout" type="number" value="10" min="1" placeholder="超时秒">
                            <input id="headers" value="{}" placeholder='请求头JSON，如 {"Authorization":"Bearer xxx"}'>
                        </div>
                        <textarea id="body" style="margin-top:10px;" placeholder="POST请求体，支持变量"></textarea>
                    </details>
                    <div class="actions">
                        <button class="btn btn-default" id="btnOnlineTest">测试取值</button>
                        <button class="btn btn-primary" id="btnGenerate">生成/覆盖插件</button>
                    </div>
                </div>
                <div class="card">
                    <h3>已生成的API插件</h3>
                    <div id="list" class="list"></div>
                </div>
                <div class="card">
                    <h3>结果</h3>
                    <pre id="result"></pre>
                </div>
            </div>
        </main>
    </div>
<script>
const api='api/custom_api_generator.php';
const $=id=>document.getElementById(id);
async function req(data){
  const body=new URLSearchParams(data).toString();
  const r=await fetch(api,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
  return r.json();
}
async function load(){
  const r=await req({type:'list'}); const arr=r.list||[];
  $('list').innerHTML=arr.map(item=>{
    const name = typeof item === 'string' ? item : item.name;
    const enabled = typeof item === 'object' && item.enabled;
    return `<div class="item"><div><div class="name">${name} ${enabled?'<span style="color:#2ecc71;font-size:12px;">已启用</span>':'<span style="color:#9aa3c0;font-size:12px;">未启用</span>'}</div><div class="meta">/plugin/${name}.php</div></div><div class="actions" style="margin-top:0">${enabled?`<button class="btn btn-default" onclick="togglePlugin('${name}','close')">关闭</button>`:`<button class="btn btn-primary" onclick="togglePlugin('${name}','open')">启用</button>`}<button class="btn btn-danger" onclick="delPlugin('${name}')">删除</button></div></div>`;
  }).join('') || '<div class="hint">暂无生成插件</div>';
}
async function togglePlugin(name,type){ const r=await req({type,name}); $('result').textContent=JSON.stringify(r,null,2); load(); }
async function delPlugin(name){ if(!confirm('确认删除插件文件？')) return; const r=await req({type:'delete',name}); $('result').textContent=JSON.stringify(r,null,2); load(); }
async function onlineTest(){
  const r=await req({type:'test',url:$('url').value,path:$('path').value,method:$('method').value,timeout:$('timeout').value,headers:$('headers').value,body:$('body').value,msg:'hello'});
  $('result').textContent=JSON.stringify(r,null,2);
}
$('btnOnlineTest').onclick=onlineTest;
$('btnGenerate').onclick=async()=>{
  const r=await req({type:'generate',name:$('name').value,trigger:$('trigger').value,match:$('match').value,url:$('url').value,path:$('path').value,reply:$('reply').value,send:$('send').value,method:$('method').value,timeout:$('timeout').value,headers:$('headers').value,body:$('body').value});
  $('result').textContent=JSON.stringify(r,null,2); if(r.code===200) load();
};
load();
</script>
</body>
</html>
