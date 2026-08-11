<?php
if (!isset($_COOKIE['admin_token'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API插件生成器</title>
    <link rel="stylesheet" href="theme-align.css">
    <link rel="stylesheet" href="theme-pixel.css">
    <style>
        body{padding:16px}
        .wrap{max-width:1080px;margin:0 auto;display:grid;gap:12px}
        .cardx{background:linear-gradient(160deg,rgba(255,255,255,.78),rgba(236,242,255,.56));border:1px solid rgba(255,255,255,.86);border-radius:16px;box-shadow:0 10px 24px rgba(95,108,201,.16);padding:14px}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        .row3{display:grid;grid-template-columns:1fr 1fr 140px;gap:10px}
        input,select,textarea{width:100%;padding:10px;border-radius:12px;border:1px solid rgba(255,255,255,.9);background:rgba(255,255,255,.72);box-sizing:border-box}
        textarea{min-height:86px}
        .btn{padding:9px 12px;border-radius:10px;border:1px solid rgba(255,255,255,.7);cursor:pointer;background:rgba(255,255,255,.72)}
        .btn-primary{background:linear-gradient(130deg,#8f9aff,#aab5ff);color:#fff}
        .btn-danger{background:#ff6b6b;color:#fff}
        .actions{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;margin-top:10px}
        .hint{font-size:12px;color:#7a84b6;margin-top:6px}
        .list{display:grid;gap:8px}
        .item{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:center;padding:10px;border:1px solid rgba(255,255,255,.86);border-radius:12px;background:rgba(255,255,255,.65)}
        .name{font-weight:700}.meta{font-size:12px;color:#6f77a8;word-break:break-all}.mini{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
        details{margin-top:10px} summary{cursor:pointer;color:#6670b8;font-size:13px}
        @media(max-width:768px){body{padding:10px}.row,.row3,.item{grid-template-columns:1fr!important}input,select,textarea{font-size:16px}.actions{justify-content:flex-start}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="cardx">
        <h3>API插件生成器</h3>
        <div class="hint">填完后会直接生成一个 PHP 插件，后续去“插件管理”里开关，逻辑比 JSON 规则更直观。</div>
        <div class="row3" style="margin-top:10px;">
            <input id="name" placeholder="插件名，例如：一言">
            <input id="trigger" placeholder="触发词，例如：一言">
            <select id="match"><option value="keyword">包含触发</option><option value="equals">完全匹配</option><option value="regex">正则匹配</option></select>
        </div>
        <div class="row" style="margin-top:10px;">
            <input id="url" placeholder="接口URL，支持 {msg} {user_id} {group_id} {appid}">
            <input id="path" placeholder="返回字段，可空，如 hitokoto 或 data.text">
        </div>
        <div class="row" style="margin-top:10px;">
            <input id="reply" value="{response}" placeholder="回复模板，例如：结果：{response}">
            <select id="send"><option value="text">文字</option><option value="native_md">原生MD</option><option value="image">图片</option><option value="video">视频</option><option value="card">卡片</option></select>
        </div>
        <details>
            <summary>高级请求设置</summary>
            <div class="row3" style="margin-top:10px;grid-template-columns:140px 140px 1fr;">
                <select id="method"><option>GET</option><option>POST</option></select>
                <input id="timeout" type="number" value="10" min="1" placeholder="超时秒">
                <input id="headers" value="{}" placeholder='请求头JSON，如 {"Authorization":"Bearer xxx"}'>
            </div>
            <textarea id="body" style="margin-top:10px;" placeholder="POST请求体，支持变量"></textarea>
        </details>
        <div class="actions">
            <button class="btn" id="btnOnlineTest">测试取值</button>
            <button class="btn btn-primary" id="btnGenerate">生成/覆盖插件</button>
        </div>
    </div>


    <div class="cardx">
        <h3>已生成的API插件</h3>
        <div id="list" class="list"></div>
    </div>

    <div class="cardx">
        <h3>结果</h3>
        <pre id="result" style="white-space:pre-wrap;word-break:break-word;"></pre>
    </div>
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
    return `<div class="item"><div><div class="name">${name} ${enabled?'<span style="color:#36a269;font-size:12px;">已启用</span>':'<span style="color:#999;font-size:12px;">未启用</span>'}</div><div class="meta">/plugin/${name}.php</div></div><div class="actions" style="margin-top:0">${enabled?`<button class="btn" onclick="togglePlugin('${name}','close')">关闭</button>`:`<button class="btn btn-primary" onclick="togglePlugin('${name}','open')">启用</button>`}<button class="btn btn-danger" onclick="delPlugin('${name}')">删除文件</button></div></div>`;
  }).join('') || '<div class="meta">暂无生成插件</div>';
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
