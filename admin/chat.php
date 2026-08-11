<?php
if (!isset($_COOKIE['admin_token'])) {
    header("Location: index.php");
    exit();
}
$appid = $_GET['appid'] ?? '';

// ---- 从 main.json 读取 qq_number 并构建映射 ----
$mainFile = dirname(__DIR__) . "/main.json";
$mainData = [];
if (file_exists($mainFile)) {
    $mainContent = file_get_contents($mainFile);
    $mainData = json_decode($mainContent, true) ?? [];
}
$botInfo = $mainData[$appid] ?? [];
$botQQ = $botInfo['qq_number'] ?? $appid;

$appidMap = [];
foreach ($mainData as $aid => $info) {
    $appidMap[$aid] = $info['qq_number'] ?? $aid;
}
$appidMapJson = json_encode($appidMap);
$botQQJson = json_encode($botQQ);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>云雀 Yunque · 聊天记录</title>
<style>
:root{
    --bg:#eef1f8; --card:#ffffff; --line:#e4e9f4; --brand:#5b6cff; --brand2:#8f9aff;
    --text:#1f2437; --sub:#6b7396; --muted:#9aa3c0;
    --me:#e8ebff; --them:#ffffff; --green:#2ecc71; --danger:#ff6b6b; --warn:#f5b942;
}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{height:100%}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:var(--bg);color:var(--text);overflow:hidden}
button{font-family:inherit;cursor:pointer;border:none;background:none;color:inherit}
input,select,textarea{font-family:inherit;outline:none}
#app{display:flex;flex-direction:column;height:100vh;height:100dvh}

/* ---------- 顶栏 ---------- */
.topbar{display:flex;align-items:center;gap:12px;padding:10px 16px;background:var(--card);border-bottom:1px solid var(--line);flex-shrink:0}
.brand{display:flex;align-items:center;gap:8px;font-weight:800;font-size:16px;letter-spacing:.5px;cursor:pointer}
.brand i{font-style:normal;color:var(--brand)}
.brand span{font-size:12px;color:var(--muted);font-weight:500}
.top-actions{margin-left:auto;display:flex;gap:8px;align-items:center}
.top-actions select{padding:7px 12px;border:1px solid var(--line);border-radius:10px;background:#f7f8fd;color:var(--text);font-size:13px;max-width:200px}
.icon-btn{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:#f4f6ff;color:var(--brand);transition:.15s}
.icon-btn:hover{background:#e6eaff}

/* ---------- 主体 ---------- */
.body{flex:1;display:flex;min-height:0}
.sidebar{width:300px;min-width:300px;background:var(--card);border-right:1px solid var(--line);display:flex;flex-direction:column;min-height:0}
.sb-head{padding:12px 12px 8px;flex-shrink:0}
.tabs{display:flex;background:#f1f3fb;border-radius:12px;padding:3px;margin-bottom:10px}
.tabs button{flex:1;padding:8px 0;border-radius:9px;font-size:13px;color:var(--sub);transition:.15s}
.tabs button.active{background:#fff;color:var(--brand);font-weight:700;box-shadow:0 1px 4px rgba(91,108,255,.15)}
.sb-search{position:relative}
.sb-search input{width:100%;padding:9px 12px 9px 34px;border:1px solid var(--line);border-radius:10px;background:#f7f8fd;font-size:13px}
.sb-search::before{content:"🔍";position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:12px;opacity:.6}
.sb-list{flex:1;overflow-y:auto;padding:4px 8px 12px;-webkit-overflow-scrolling:touch}
.conv{display:flex;align-items:center;gap:10px;padding:10px;border-radius:12px;cursor:pointer;transition:.15s}
.conv:hover{background:#f5f7ff}
.conv.active{background:#eef1ff}
.conv .meta{flex:1;min-width:0}
.conv .nm{display:flex;justify-content:space-between;align-items:center;gap:6px}
.conv .nm b{font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:default} /* 移除指针样式，不可点击备注 */
.conv .nm time{font-size:11px;color:var(--muted);flex-shrink:0}
.conv .pv{display:flex;justify-content:space-between;align-items:center;gap:6px;margin-top:2px}
.conv .pv span{font-size:12px;color:var(--sub);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.conv .badge{background:var(--brand);color:#fff;font-size:12px;font-weight:700;border-radius:10px;padding:0 6px;line-height:18px;min-width:18px;text-align:center;flex-shrink:0;text-shadow:0 1px 2px rgba(0,0,0,0.2)}
.conv.empty{padding:30px 12px;text-align:center;color:var(--muted);font-size:13px}

/* 头像 */
.ava{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;color:#fff;flex-shrink:0;overflow:hidden;background:linear-gradient(135deg,var(--brand),var(--brand2))}
.ava img{width:100%;height:100%;object-fit:cover;cursor:pointer} /* 头像显示指针，提示可点击 */
.ava.sm{width:34px;height:34px;font-size:13px;border-radius:10px}

/* ---------- 聊天区 ---------- */
.chat{flex:1;display:flex;flex-direction:column;min-width:0;min-height:0;position:relative}
.chat-empty{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:10px;color:var(--muted);font-size:15px}
.chat-empty .em{font-size:44px;opacity:.5}
.chat-head{display:flex;align-items:center;gap:12px;padding:12px 16px;background:var(--card);border-bottom:1px solid var(--line);flex-shrink:0}
.chat-head .back{display:none;font-size:22px;color:var(--sub);padding:4px 8px}
.who{display:flex;align-items:center;gap:10px;min-width:0}
.who .nm b{font-size:15px;cursor:pointer;border-bottom:1px dashed transparent;transition:border-color .2s}
.who .nm b:hover{border-bottom-color:var(--brand)}
.who .nm div:last-child{font-size:12px;color:var(--muted);margin-top:2px;max-width:50vw;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.head-actions{margin-left:auto;display:flex;gap:8px}
.head-actions .icon-btn{display:flex !important;}

.msgs{flex:1;overflow-y:auto;padding:18px 16px;-webkit-overflow-scrolling:touch;background:linear-gradient(180deg,#eef1f8,#e7ecf9)}
.msg{display:flex;gap:8px;margin-bottom:14px;max-width:100%}
.msg .bubble{max-width:min(72%,620px)}
.msg .bname{font-size:11px;color:var(--muted);margin:0 4px 4px;display:flex;align-items:center;gap:5px;max-width:100%}
.msg.me{flex-direction:row-reverse}
.msg.me .bname{text-align:right}
.bubble .body{background:var(--them);border-radius:14px;padding:9px 13px;font-size:14px;line-height:1.6;word-break:break-word;box-shadow:0 1px 3px rgba(30,40,90,.06);display:inline-block;position:relative}
.msg.me .bubble .body{background:var(--me)}
.bubble .body.time{display:inline-block;font-size:12px;color:var(--muted)}
.msg.event{justify-content:center}
.msg.event .bubble .body{background:transparent;box-shadow:none;color:var(--muted);font-size:12px;padding:2px 10px}
.bubble img.rich{max-width:100%;max-height:340px;border-radius:10px;display:block;margin-top:6px;cursor:zoom-in}
.bubble video{max-width:100%;max-height:340px;border-radius:10px;display:block;margin-top:6px}
.bubble audio{max-width:260px;width:100%;margin-top:6px}
.mdbox{white-space:pre-wrap;font-family:ui-monospace,"Cascadia Code",Consolas,monospace;font-size:12.5px;background:rgba(0,0,0,.035);border-radius:8px;padding:8px 10px;margin-top:4px}
.cardx{margin-top:6px;border:1px solid var(--line);border-radius:12px;background:#fff;overflow:hidden;min-width:220px;max-width:320px}
.cardx .ci{padding:10px 12px;border-bottom:1px solid #f0f2f9;font-size:13.5px;line-height:1.5}
.cardx .ci:last-child{border-bottom:none}
.cardx .ci a{color:var(--brand);text-decoration:none;font-size:12.5px;word-break:break-all}
.emoji{width:24px;height:24px;vertical-align:-4px;margin:0 1px}
.emoji-tok{background:#eef1ff;color:var(--brand);border-radius:8px;padding:0 6px;font-size:12px}
.msg-attach{color:var(--brand);text-decoration:none;font-size:13px;display:block;margin-top:4px;word-break:break-all}
.msg-tools{display:none;gap:6px;margin-top:6px;flex-wrap:wrap}
.msg:hover .msg-tools{display:flex}
.mact{background:#fff;border:1px solid var(--line);color:var(--muted);border-radius:8px;padding:3px 9px;font-size:11.5px;cursor:pointer;transition:.15s}
.mact:hover{color:var(--brand);border-color:var(--brand)}

/* ---------- 输入区 ---------- */
.composer{background:var(--card);border-top:1px solid var(--line);padding:8px 14px 12px;flex-shrink:0}
.toolbar{display:flex;align-items:center;gap:4px;flex-wrap:wrap;margin-bottom:6px}
.tool-btn{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;background:#f4f6ff;color:var(--text);transition:.15s}
.tool-btn:hover{background:#e6eaff}
.tool-btn.on{background:var(--brand);color:#fff}
.tool-btn.md{font-size:12px;font-weight:800}
.tool-btn.card{font-size:12px;font-weight:800}
.send-row{display:flex;gap:10px;align-items:flex-end}
.send-row textarea{flex:1;resize:none;height:52px;max-height:140px;padding:12px 14px;border:1px solid var(--line);border-radius:14px;background:#f7f8fd;font-size:14px;line-height:1.5}
.send-row textarea:focus{border-color:var(--brand2);background:#fff}
.send-btn{padding:0 22px;height:52px;border-radius:14px;background:linear-gradient(130deg,var(--brand),var(--brand2));color:#fff;font-size:15px;font-weight:700;flex-shrink:0;box-shadow:0 4px 12px rgba(91,108,255,.3)}
.send-btn:active{transform:scale(.97)}
.opts{display:flex;align-items:center;gap:14px;margin:8px 2px 0;font-size:12.5px;color:var(--sub);flex-wrap:wrap}
.opts label{display:flex;align-items:center;gap:5px;cursor:pointer;user-select:none}
.opts label input{accent-color:var(--brand);width:15px;height:15px}
.opts .hint{color:var(--muted)}

/* ---------- 表情面板 ---------- */
.emoji-panel{position:absolute;bottom:70px;left:14px;z-index:50;display:none;width:320px;max-width:86vw;background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 10px 30px rgba(30,40,90,.18);padding:10px}
.emoji-panel.show{display:block}
.emoji-grid{display:grid;grid-template-columns:repeat(9,1fr);gap:4px;max-height:180px;overflow-y:auto}
.emoji-grid button{background:none;padding:4px;border-radius:8px;display:flex;align-items:center;justify-content:center}
.emoji-grid button:hover{background:#f0f2ff}
.emoji-grid img{width:26px;height:26px}
.emoji-panel .tip{font-size:11px;color:var(--muted);padding:6px 2px 0}

/* ---------- 卡片模板弹窗 ---------- */
.modal{position:fixed;inset:0;z-index:100;display:none;align-items:center;justify-content:center;background:rgba(20,25,50,.45);padding:16px}
.modal.show{display:flex}
.modal-box{background:#fff;border-radius:18px;width:620px;max-width:100%;max-height:88vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(30,40,90,.3)}
.modal-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--line);flex-shrink:0}
.modal-head h3{font-size:16px}
.modal-head button{font-size:20px;color:var(--muted);padding:4px}
.modal-body{flex:1;overflow-y:auto;padding:16px 20px}
.modal-foot{padding:14px 20px;border-top:1px solid var(--line);display:flex;gap:10px;justify-content:flex-end;flex-shrink:0}
.field{margin-bottom:12px}
.field label{display:block;font-size:12.5px;color:var(--sub);margin-bottom:5px}
.field input,.field select,.field textarea{width:100%;padding:9px 12px;border:1px solid var(--line);border-radius:10px;background:#f7f8fd;font-size:13.5px}
.field textarea{min-height:70px;resize:vertical}
.btn{padding:9px 16px;border-radius:10px;font-size:13.5px;font-weight:600;transition:.15s}
.btn-p{background:linear-gradient(130deg,var(--brand),var(--brand2));color:#fff}
.btn-g{background:#eef1f8;color:var(--text)}
.btn-d{background:#ffecec;color:var(--danger)}
.btn-s{background:#e6ffef;color:#18a058}
.btn:hover{filter:brightness(.97)}
.tpl-list{display:grid;gap:8px;margin-bottom:14px}
.tpl{display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--line);border-radius:12px;background:#fafbff;cursor:pointer}
.tpl:hover{border-color:var(--brand2)}
.tpl b{font-size:13.5px;flex:1}
.tpl .tp{font-size:11px;color:var(--muted)}
.tpl button{font-size:12px;color:var(--danger);padding:4px 8px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.form-row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}

/* 图片预览 */
.img-viewer{position:fixed;inset:0;z-index:200;display:none;align-items:center;justify-content:center;background:rgba(10,14,30,.88)}
.img-viewer.show{display:flex}
.img-viewer img{max-width:94vw;max-height:94vh;border-radius:10px}
.img-viewer button{position:absolute;top:16px;right:20px;color:#fff;font-size:28px}

/* 滚动条 */
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-thumb{background:#c4cbe4;border-radius:6px}
::-webkit-scrollbar-thumb:hover{background:#aab3d8}

/* ---------- 移动端 ---------- */
@media (max-width:820px){
    .sidebar{position:fixed;left:0;top:52px;bottom:0;width:100%;min-width:0;z-index:30;transition:transform .22s;transform:translateX(0)}
    .sidebar.hide{transform:translateX(-104%)}
    .chat{width:100%}
    .chat-head .back{display:block}
    .chat-empty{left:0}
    .brand span{display:none}
    .top-actions select{max-width:130px}
    .bubble{max-width:82%}
}
</style>
</head>
<body>
<div id="app">
    <header class="topbar">
        <div class="brand" onclick="location.href='main.php'" title="返回主页">
            <i>✦</i> 云雀 Yunque <span>聊天记录</span>
        </div>
        <div class="top-actions">
            <select id="botPicker"></select>
            <select id="logFileSelect" title="选择日志日期"><option value="">选择日期</option></select>
            <button class="icon-btn" id="refreshBtn" title="刷新列表">⟳</button>
        </div>
    </header>
    <div class="body">
        <aside class="sidebar" id="sidebar">
            <div class="sb-head">
                <div class="tabs">
                    <button data-tab="group" class="active">群聊</button>
                    <button data-tab="private">私聊</button>
                </div>
                <div class="sb-search"><input id="searchInput" placeholder="搜索会话 / ID / 消息"></div>
            </div>
            <div class="sb-list" id="convList"></div>
        </aside>

        <section class="chat" id="chatPanel">
            <div class="chat-empty" id="chatEmpty"><div class="em">💬</div><div>从左侧选择一个会话开始</div></div>
            <header class="chat-head" id="chatHead" style="display:none">
                <button class="back" id="backBtn">‹</button>
                <div class="who">
                    <div class="ava sm" id="headAva">?</div>
                    <div class="nm"><b id="headName"></b><div id="headSub"></div></div>
                </div>
                <div class="head-actions">
                    <button class="icon-btn" id="refreshChatBtn" title="刷新消息">↻</button>
                </div>
            </header>
            <div class="msgs" id="msgList"></div>

            <footer class="composer" id="composer" style="display:none;position:relative">
                <div class="toolbar">
                    <button class="tool-btn md" id="mdBtn" title="Markdown 模式">MD</button>
                    <button class="tool-btn card" id="cardBtn" title="卡片模板">卡片</button>
                </div>
                <div class="send-row">
                    <textarea id="msgInput" rows="1" placeholder="输入消息…"></textarea>
                    <button class="send-btn" id="sendBtn">发送</button>
                </div>
                <div class="opts">
                    <label title="主动消息无需用户最近发言，需机器人具备主动发言权限"><input type="checkbox" id="activeToggle"> 主动消息</label>
                    <span class="hint" id="composerHint">被动回复需会话内有 299 秒内的消息锚点</span>
                </div>
            </footer>
        </section>
    </div>
</div>

<!-- 卡片模板弹窗 -->
<div class="modal" id="cardModal">
    <div class="modal-box">
        <div class="modal-head"><h3>卡片消息 · 可编辑模板</h3><button id="closeCardBtn">&times;</button></div>
        <div class="modal-body">
            <div class="field">
                <label>模板列表（可保存 / 套用 / 删除）</label>
                <div class="tpl-list" id="tplList"></div>
            </div>
            <div class="form-row">
                <div class="field"><label>模板名称</label><input id="tplName" placeholder="例如：欢迎卡片"></div>
                <div class="field"><label>卡片类型</label><select id="tplType">
                    <option value="文卡">文卡（多行文字）</option>
                    <option value="跳转卡">跳转卡片</option>
                    <option value="大图">大图卡片</option>
                </select></div>
            </div>
            <div class="field"><label>标题</label><input id="tplTitle" placeholder="卡片标题"></div>
            <div class="form-row3">
                <div class="field"><label>副标题 / 描述</label><input id="tplDesc" placeholder="描述文字"></div>
                <div class="field"><label>图片 URL</label><input id="tplImage" placeholder="https://…"></div>
                <div class="field"><label>跳转链接</label><input id="tplUrl" placeholder="https://…"></div>
            </div>
            <div class="field"><label>文卡内容（每行一条，文本可拼接 <code>|</code> 链接）</label>
                <textarea id="tplLines" placeholder="第一行文字
第二行文字|https://example.com"></textarea>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-d" id="deleteTplBtn">删除当前</button>
            <button class="btn btn-g" id="saveTplBtn">保存模板</button>
            <button class="btn btn-p" id="sendCardBtn">套用并发送</button>
        </div>
    </div>
</div>

<div class="img-viewer" id="imgViewer"><button id="closeViewer">✕</button><img id="viewerImg" src="" alt=""></div>

<script>
// ---- 注入 PHP 变量 ----
var appidMap = <?php echo $appidMapJson; ?>;
var botQQ = <?php echo $botQQJson; ?>;
var appid = '<?php echo htmlspecialchars($appid, ENT_QUOTES); ?>';

// ---- 全局状态 ----
const API = 'api/chat.php';
const state = { 
    appid: appid, 
    tab:'group', 
    convs:{group:[],private:[]}, 
    current:null, 
    md:false, 
    emojiRe:null, 
    emojiMap:{}, 
    cards:[], 
    active:false, 
    lastMsgTime:'', 
    lastKey:'', 
    msgInit:false, 
    listSig:'',
    nicknameMap: {},
    logName: '',
    groupNamePending: {},
    groupNameFailed: {}
};

// ---- 机器人全局变量 ----
let botName = '机器人';
let botAvatar = '';

// ---- 工具函数 ----
function $(id){ return document.getElementById(id); }
function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function cleanName(s){
    s = String(s==null?'':s).replace(/[\u0000-\u001f\u007f\u200b-\u200d\ufeff]/g,'').trim();
    const base = String(s);
    return (s || ('用户' + base.slice(-6))).slice(0,14);
}
function timeFmt(t){
    if(!t) return '';
    const d = new Date(String(t).replace(' ','T'));
    if(isNaN(d)) return String(t).slice(5,16);
    const now = new Date();
    const sameDay = d.toDateString()===now.toDateString();
    if(sameDay) return d.toTimeString().slice(0,5);
    if(d.getFullYear()===now.getFullYear()) return (d.getMonth()+1)+'/'+d.getDate();
    return d.getFullYear()+'/'+(d.getMonth()+1)+'/'+d.getDate();
}
function toast(msg, ok){
    const t = document.createElement('div');
    t.style.cssText = 'position:fixed;top:60px;left:50%;transform:translateX(-50%);z-index:999;padding:10px 18px;border-radius:12px;color:#fff;font-size:13px;box-shadow:0 6px 20px rgba(0,0,0,.18);background:'+(ok? '#18a058':'#ff6b6b');
    t.textContent = msg; document.body.appendChild(t);
    setTimeout(()=>t.remove(), 2600);
}
async function json(url, body){
    const opt = body ? {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams(body)} : {};
    const r = await fetch(url, opt);
    return r.json();
}

/* ---------- 备注功能（localStorage） ---------- */
function getRemarksKey(){
    return 'chat_remarks_' + state.appid;
}
function loadRemarks(){
    try {
        const raw = localStorage.getItem(getRemarksKey());
        return raw ? JSON.parse(raw) : {};
    } catch(e){ return {}; }
}
function saveRemarks(remarks){
    localStorage.setItem(getRemarksKey(), JSON.stringify(remarks));
}
function getRemark(type, id){
    const remarks = loadRemarks();
    const key = type + '_' + id;
    return remarks[key] || null;
}
function setRemark(type, id, name){
    const remarks = loadRemarks();
    const key = type + '_' + id;
    if(name && name.trim()){
        remarks[key] = name.trim();
    } else {
        delete remarks[key];
    }
    saveRemarks(remarks);
}
function getDisplayName(type, id, defaultName){
    const remark = getRemark(type, id);
    return remark || defaultName;
}

/* ---------- 字母头像 ---------- */
const palette = ['#5b6cff','#a26bff','#ff7a9d','#ff9f43','#2ec4b6','#3fb0ff','#8bc34a','#ff6b81'];
function letterAva(id, name, cls){
    const letter = (name || id || '?').charAt(0).toUpperCase();
    let h = 0; for(const c of String(id||'')) h = (h*31 + c.charCodeAt(0))>>>0;
    const color = palette[h % palette.length];
    return `<div class="ava ${cls||''}" style="background:${color}">${esc(letter)}</div>`;
}

// ---- 机器人头像 ----
function botAva(cls){
    if (!botAvatar) {
        return `<div class="ava ${cls||''} bot-ava"><span>雀</span></div>`;
    }
    return `<div class="ava ${cls||''}"><img src="${esc(botAvatar)}" loading="lazy" referrerpolicy="no-referrer" onerror="this.outerHTML='<span>雀</span>'"></div>`;
}

// ---- 机器人信息加载 ----
function loadBotInfo(qq_number) {
    if (!qq_number) return;
    fetch(`api/robot_info.php?type=get_info&appid=${encodeURIComponent(state.appid)}&qq_number=${encodeURIComponent(qq_number)}`)
        .then(r => r.json())
        .then(data => {
            if (data.code === 200 && data.data) {
                if (data.data.name && data.data.name !== '未知机器人') botName = data.data.name;
                if (data.data.avatar && data.data.avatar.trim()) botAvatar = data.data.avatar;
                if (state.current && state.current.type === 'private') {
                    const sub = document.getElementById('headSub');
                    if (sub) sub.textContent = botName + ' · 私聊 · ' + state.current.id;
                }
                if (state.current && state.msgInit) loadMessages(true);
            }
        })
        .catch(() => {});
}

/* ---------- 批量获取用户昵称 ---------- */
function loadNicknamesBatch(userIds) {
    if (!userIds.length) return Promise.resolve({});
    return fetch(`api/chat.php?type=get_nicknames&appid=${encodeURIComponent(state.appid)}&name=${encodeURIComponent(dateLogName())}`, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `user_ids=${encodeURIComponent(JSON.stringify(userIds))}`
    })
    .then(r => r.json())
    .then(data => data.code===200 ? data.nicknames : {})
    .catch(() => ({}));
}

/* ---------- 机器人选择下拉（显示真实名字） ---------- */
async function loadBots(){
    const path = $('botPicker');
    const data = await fetch('api/bot.php?type=list').then(r=>r.json()).catch(()=>null);
    const list = (data && Array.isArray(data.list)) ? data.list : [];
    path.innerHTML = list.length ? list.map(b=>`<option value="${esc(b.appid)}" ${b.appid===state.appid?'selected':''}>${esc(b.remark||b.appid)}</option>`).join('')
        : '<option value="">未配置机器人</option>';
    if(!state.appid && list.length){ state.appid = list[0].appid; appid = state.appid; }
    if(list.length){
        const options = path.options;
        const promises = list.map((b, idx) => {
            const qq = appidMap[b.appid] || b.appid;
            if(qq){
                return fetch(`api/robot_info.php?type=get_info&appid=${encodeURIComponent(b.appid)}&qq_number=${encodeURIComponent(qq)}`)
                    .then(r => r.json())
                    .then(data2 => {
                        if(data2.code===200 && data2.data && data2.data.name && data2.data.name !== '未知机器人'){
                            options[idx].text = data2.data.name;
                        }
                    })
                    .catch(() => {});
            }
            return Promise.resolve();
        });
        await Promise.all(promises);
        if(state.appid){
            for(let i=0; i<options.length; i++){
                if(options[i].value === state.appid){
                    options[i].selected = true;
                    break;
                }
            }
        }
    }
    path.addEventListener('change', ()=>{
        state.appid = path.value;
        appid = state.appid;
        const qq = appidMap[state.appid] || state.appid;
        botQQ = qq;
        loadBotInfo(qq);
        state.current = null;
        state.msgInit = false;
        state.nicknameMap = {};
        state.groupNamePending = {};
        state.groupNameFailed = {};
        state.logName = '';
        $('msgList').innerHTML = '';
        $('composer').style.display = 'none';
        $('chatHead').style.display = 'none';
        $('chatEmpty').style.display = 'flex';
        loadList();
        loadLogFiles();
        if(window.innerWidth <= 820){
            document.getElementById('sidebar').classList.remove('hide');
        }
    });
    if(state.appid){
        loadList();
        loadBotInfo(botQQ);
    } else {
        $('convList').innerHTML = '<div class="conv empty">请先在「总览」添加机器人</div>';
    }
}

/* ---------- 会话列表 ---------- */
async function loadList(){
    if(!state.appid) return;
    $('convList').innerHTML = '<div class="conv empty">加载中…</div>';
    const data = await fetch(`${API}?type=list&appid=${encodeURIComponent(state.appid)}&name=${encodeURIComponent(dateLogName())}`).then(r=>r.json()).catch(()=>null);
    if(!data){ $('convList').innerHTML = '<div class="conv empty">暂无日志（Log/{appid}/日期.log）</div>'; return; }
    state.convs.group = data.groups || [];
    state.convs.private = data.privates || [];
    renderList();
}
function dateLogName(){
    if (state.logName) return state.logName;
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}.log`;
}
function resetConversation(){
    state.current = null;
    state.msgInit = false;
    state.lastMsgTime = '';
    $('msgList').innerHTML = '';
    $('composer').style.display = 'none';
    $('chatHead').style.display = 'none';
    $('chatEmpty').style.display = 'flex';
}
async function loadLogFiles(){
    const sel = $('logFileSelect');
    if(!state.appid){ sel.innerHTML = '<option value="">选择日期</option>'; return; }
    const today = dateLogName();
    try {
        const data = await fetch(`api/log.php?type=list&appid=${encodeURIComponent(state.appid)}`).then(r=>r.json());
        let files = (data && Array.isArray(data.list)) ? data.list : [];
        files = files.filter(f=>/\.log$/i.test(f)).sort().reverse();
        if(!files.includes(today)) files.unshift(today);
        sel.innerHTML = files.map(f=>`<option value="${esc(f)}" ${f===state.logName?'selected':''}>${esc(f.replace(/\.log$/i,''))}</option>`).join('');
        if(!state.logName || !files.includes(state.logName)){
            state.logName = today;
            sel.value = today;
        }
    } catch (e) {
        sel.innerHTML = '<option value="">选择日期</option>';
    }
}
function previewClean(txt){
    if(txt == null) return '';
    txt = String(txt);
    txt = txt.replace(/https?:\/\/[^\s\)\]]+/g,'[链接]');
    txt = txt.replace(/<@![^>]*>/g,'');
    return txt.slice(0,60);
}
function renderList(){
    const kw = ($('searchInput').value||'').trim().toLowerCase();
    let list = state.convs[state.tab] || [];
    if(kw) list = list.filter(c =>
        String(c.id||'').toLowerCase().includes(kw) ||
        String(c.last_message||'').toLowerCase().includes(kw) ||
        String(c.name||c.title||'').toLowerCase().includes(kw)
    );
    const box = $('convList');
    if(!list.length){ box.innerHTML = '<div class="conv empty">暂无会话</div>'; return; }
    box.innerHTML = list.map(c=>{
        const isActive = state.current && state.current.type===c.type && state.current.id===c.id;
        const defaultName = c.name || c.title || shortId(c.id, c.type);
        const displayName = getDisplayName(c.type, c.id, defaultName);
        let avatarHtml = '';
        if (c.type === 'private') {
            avatarHtml = `<div class="ava sm"><img src="https://q.qlogo.cn/qqapp/${state.appid}/${c.id}/5" loading="lazy" referrerpolicy="no-referrer" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"><span class="ava sm" style="display:none;">${(defaultName || c.id).slice(-2).toUpperCase()}</span></div>`;
        } else {
            avatarHtml = c.avatar_url ? `<div class="ava sm"><img src="${esc(c.avatar_url)}" loading="lazy" referrerpolicy="no-referrer" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">${letterAva(c.id, defaultName === '未知会话' ? '' : defaultName, 'sm').replace('<div class="ava sm"', '<span class="ava sm"').replace('style="', 'style="display:none;').replace('</div>','</span>')}</div>` : letterAva(c.id, defaultName === '未知会话' ? '' : defaultName, 'sm');
        }
        return `<div class="conv ${isActive?'active':''}" data-type="${c.type}" data-id="${esc(c.id)}">
            ${avatarHtml}
            <div class="meta">
                <div class="nm"><b>${esc(displayName)}</b><time>${timeFmt(c.last_message_time)}</time></div>
                <div class="pv"><span>${esc(previewClean(c.last_message))}</span>${c.message_count ? `<span class="badge">${c.message_count}</span>` : ''}</div>
            </div>
        </div>`;
    }).join('');

    // 不再为列表名称绑定点击备注

    if (state.tab === 'private' && list.length > 0) {
        const userIds = list.map(c => c.id);
        loadNicknamesBatch(userIds).then(nicknames => {
            Object.assign(state.nicknameMap, nicknames);
            document.querySelectorAll('.conv').forEach(item => {
                const cid = item.dataset.id;
                const nameEl = item.querySelector('.nm b');
                if (nameEl && nicknames[cid]) {
                    const remark = getRemark('private', cid);
                    if (!remark) {
                        nameEl.textContent = nicknames[cid];
                    }
                }
            });
            if (state.current && state.current.type === 'private' && nicknames[state.current.id]) {
                const remark = getRemark('private', state.current.id);
                if (!remark) {
                    document.getElementById('headName').textContent = nicknames[state.current.id];
                    state.current.name = nicknames[state.current.id];
                }
            }
        });
    }

    box.querySelectorAll('.conv').forEach(el=>el.addEventListener('click', ()=>{
        const type = el.dataset.type, id = el.dataset.id;
        const conv = (state.convs[type]||[]).find(c=>c.id===id);
        openChat(type, id, conv ? (conv.name || conv.title || shortId(id,type)) : shortId(id,type), conv || {});
    }));

    if (state.tab === 'group') fetchGroupNames();
}
function shortId(id, type){
    if(!id) return '未知会话';
    return type==='group' ? ('群 ' + id.slice(-6)) : ('用户 ' + id.slice(-6));
}

/* ---------- 群名异步补齐（官方群信息接口 + 缓存） ---------- */
function updateGroupNameDisplay(id, name){
    document.querySelectorAll('.conv[data-type="group"]').forEach(conv => {
        if (conv.dataset.id !== id) return;
        const nameEl = conv.querySelector('.nm b');
        if (nameEl && !getRemark('group', id)) nameEl.textContent = name;
    });
    if (state.current && state.current.type === 'group' && state.current.id === id) {
        document.getElementById('headName').textContent = getRemark('group', id) || name;
    }
}
function fetchGroupNames(){
    if (!state.appid) return;
    const groups = state.convs.group || [];
    groups.forEach(c => {
        const id = c.id;
        if (c.name) return;
        if (getRemark('group', id)) return;
        if (state.groupNamePending[id] || state.groupNameFailed[id]) return;
        state.groupNamePending[id] = true;
        fetch(`${API}?type=group_name&appid=${encodeURIComponent(state.appid)}&group_id=${encodeURIComponent(id)}`)
            .then(r => r.json())
            .then(d => {
                if (d.code === 200 && d.name) {
                    c.name = d.name;
                    updateGroupNameDisplay(id, d.name);
                } else {
                    state.groupNameFailed[id] = true;
                }
            })
            .catch(() => { state.groupNameFailed[id] = true; })
            .finally(() => { delete state.groupNamePending[id]; });
    });
}

/* ---------- 聊天详情 ---------- */
async function openChat(type, id, name, conv){
    if(window.innerWidth <= 820){ $('sidebar').classList.add('hide'); }
    state.current = {type, id, name, conv};
    $('chatEmpty').style.display = 'none';
    $('chatHead').style.display = 'flex';
    $('composer').style.display = 'block';
    const defaultName = name;
    let displayName = getRemark(type, id);
    if (!displayName) {
        if (type === 'private' && state.nicknameMap[id]) {
            displayName = state.nicknameMap[id];
        } else {
            displayName = defaultName;
        }
    }
    $('headName').textContent = displayName;
    $('headSub').textContent = type==='group' ? ('群聊 · ' + id) : (botName + ' · 私聊 · ' + id);
    // 绑定头部名称点击编辑备注
    const headNameEl = document.getElementById('headName');
    headNameEl.onclick = function(){
        const current = this.textContent;
        const newName = prompt('请输入新的备注名称（留空则清除备注）：', current);
        if (newName !== null) {
            setRemark(type, id, newName);
            const display = newName.trim() || getDisplayName(type, id, defaultName);
            this.textContent = display;
            const convItem = document.querySelector(`.conv[data-type="${type}"][data-id="${id}"] .nm b`);
            if (convItem) convItem.textContent = display;
            state.current.name = display;
        }
    };
    if (type === 'private') {
        $('headAva').innerHTML = `<div class="ava sm"><img src="https://q.qlogo.cn/qqapp/${state.appid}/${id}/5" loading="lazy" referrerpolicy="no-referrer" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"><span class="ava sm" style="display:none;">${(displayName || id).slice(-2).toUpperCase()}</span></div>`;
        if (!state.nicknameMap[id]) {
            loadNicknamesBatch([id]).then(nicknames => {
                if (nicknames[id]) {
                    state.nicknameMap[id] = nicknames[id];
                    const remark = getRemark('private', id);
                    if (!remark) {
                        document.getElementById('headName').textContent = nicknames[id];
                        const convItem = document.querySelector(`.conv[data-type="${type}"][data-id="${id}"] .nm b`);
                        if (convItem) convItem.textContent = nicknames[id];
                        state.current.name = nicknames[id];
                    }
                }
            });
        }
    } else {
        $('headAva').innerHTML = letterAva(id, name, 'sm');
    }
    state.msgInit = false;
    state.lastMsgTime = '';
    renderList();
    await loadMessages();
}
async function loadMessages(initial, force){
    if(!state.current) return;
    const {type, id} = state.current;
    const el = $('msgList');
    const since = (state.msgInit && !initial && !force && state.lastMsgTime) ? `&since=${encodeURIComponent(state.lastMsgTime)}` : '';
    if(initial || force || !state.msgInit){
        state.msgInit = false;
        el.innerHTML = '<div style="text-align:center;color:#9aa3c0;padding:30px">加载中…</div>';
    }
    const data = await fetch(`${API}?type=messages&appid=${encodeURIComponent(state.appid)}&name=${encodeURIComponent(dateLogName())}&chat_type=${type}&chat_id=${encodeURIComponent(id)}${since}`).then(r=>r.json()).catch(()=>null);
    const msgs = (data && Array.isArray(data.messages)) ? data.messages : [];
    if(!msgs.length && !state.msgInit){
        el.innerHTML = '<div style="text-align:center;color:#9aa3c0;padding:30px">暂无消息</div>';
        state.msgInit = true;
        return;
    }
    const userIds = msgs.filter(m => m.type === 'user' && m.user_id).map(m => m.user_id).filter(id => id && !state.nicknameMap[id]);
    if (userIds.length) {
        const nicknames = await loadNicknamesBatch(userIds);
        Object.assign(state.nicknameMap, nicknames);
    }
    if(!state.msgInit){
        el.innerHTML = msgs.length ? msgs.map(m=>renderMsg(m)).join('') : '<div style="text-align:center;color:#9aa3c0;padding:30px">暂无消息</div>';
        state.msgInit = true;
        scrollBottom();
    } else if(msgs.length){
        const nearBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 80;
        el.insertAdjacentHTML('beforeend', msgs.map(m=>renderMsg(m)).join(''));
        if(nearBottom) scrollBottom();
    }
    if(msgs.length){
        state.lastMsgTime = msgs[msgs.length-1].time;
        state.lastKey = state.lastMsgTime + (msgs[msgs.length-1].message_id || '');
    }
}
function scrollBottom(){ const el=$('msgList'); el.scrollTop = el.scrollHeight; }

/* ---------- 表情与富文本 ---------- */
function emojiId(e){
    if(e == null) return '';
    if(typeof e === 'object') return e.url || String(e.id || '').trim();
    return String(e).trim();
}
function emojiSrc(eid){
    if(!eid) return '';
    if(/^https?:/i.test(eid)) return eid;
    return 'https://qzonestyle.gtimg.cn/emoji/emoji200/' + String(eid).replace(/[^\w-]/g,'') + '.gif';
}
function renderEmojis(emojis){
    if(!emojis || !emojis.length) return '';
    const seen = new Set();
    return emojis.map(e=>{
        const id = emojiId(e);
        if(!id || seen.has(id)) return '';
        seen.add(id);
        return `<img class="emoji" src="${esc(emojiSrc(id))}" loading="lazy" referrerpolicy="no-referrer" onerror="this.outerHTML='<span class=&quot;emoji-tok&quot;>{emoji:${esc(id)}}</span>'">`;
    }).join('');
}
function renderRichText(content, emojis){
    let text = content || '';
    text = text.replace(/<faceType[^>]*>/gi, '');
    text = text.replace(/<\/faceType>/gi, '');
    text = text.replace(/&lt;faceType[^&]*&gt;/gi, '');
    text = text.replace(/&lt;\/faceType&gt;/gi, '');
    text = esc(text);
    text = text.replace(/&lt;@![^&]*&gt;/g, m=>`<span class="emoji-tok">@成员</span>`);
    text = text.replace(/\{emoji:([^}]*)\}/g, (m,id)=>{
        const iid = String(id||'').trim();
        return emojiSrc(iid) ? `<img class="emoji" src="${esc(emojiSrc(iid))}" loading="lazy" referrerpolicy="no-referrer" onerror="this.outerHTML='<span class=&quot;emoji-tok&quot;>[表情]</span>'">` : '';
    });
    text = text.replace(/https?:\/\/[^\s<]+/g, u=>{
        if(/\.(png|jpe?g|gif|webp)([?"':)\s]|$)/i.test(u)) return '';
        return `<a href="${u}" target="_blank" rel="noreferrer" style="color:var(--brand);word-break:break-all">${u}</a>`;
    });
    return text + renderEmojis(emojis);
}

/* ---------- 消息渲染 ---------- */
function renderMsg(m){
    if(m.type === 'event'){
        return `<div class="msg event"><div class="bubble"><div class="body time">${esc(m.content)} · ${timeFmt(m.time)}</div></div></div>`;
    }
    const isMe = m.type === 'bot';
    const time = `<span style="font-size:11px;color:var(--muted);display:block;margin:2px 4px 0 0">${timeFmt(m.time)}</span>`;
    let html = '';
    const mt = m.message_type || 'text';

    const imgOnErr = `onerror="this.outerHTML='<span class=&quot;emoji-tok&quot;>[图片]</span>'"`;
    if(m.image_urls && m.image_urls.length){
        html += m.image_urls.map(u=>`<img class="rich" src="${esc(u)}" loading="lazy" referrerpolicy="no-referrer" ${imgOnErr} onclick="openViewer('${esc(u)}')">`).join('');
    } else if(m.image_url){
        html += `<img class="rich" src="${esc(m.image_url)}" loading="lazy" referrerpolicy="no-referrer" ${imgOnErr} onclick="openViewer('${esc(m.image_url)}')">`;
    }
    if(isMe){
        const cStr = (m.content && typeof m.content === 'string') ? m.content : '';
        if(mt==='text' && !m.image_urls && !m.image_url && /^https?:\/\/[^\s]+\.(png|jpe?g|gif|webp)(\?[^\s]*)?$/i.test(cStr.trim())){
            html += `<img class="rich" src="${esc(cStr.trim())}" loading="lazy" referrerpolicy="no-referrer" ${imgOnErr} onclick="openViewer('${esc(cStr.trim())}')">`;
        }
    }

    if(mt==='video' || m.video_url){
        const src = m.video_url || m.content;
        if(src) html += `<video src="${esc(src)}" controls preload="metadata"></video><a class="msg-attach" href="${esc(src)}" target="_blank">查看视频 ↗</a>`;
        else html += '<div>🎞️ 视频</div>';
    }
    else if(mt==='voice' || mt==='audio' || m.voice_url || m.voice_url_silk){
        const src = m.voice_url || m.voice_url_silk || m.content;
        if(src) html += `<audio src="${esc(src)}" controls preload="none"></audio><a class="msg-attach" href="${esc(src)}" target="_blank">播放语音 ↗</a>`;
        else html += '<div>🎙️ 语音</div>';
    }
    else if(mt==='file' || /文件/.test(mt)){
        html += `<div>📎 文件：<span class="emoji-tok">${esc(m.content || '[文件]')}</span></div>`;
    }
    else if(mt==='card' || (m.card_data && m.card_data.length)){
        const items = (m.card_data && m.card_data.length) ? m.card_data : (Array.isArray(m.content) ? m.content : [{text:m.content}]);
        const body = items.map(it=>`<div class="ci">${esc(it.text||it.value||'')}${it.url?`<br><a href="${esc(it.url)}" target="_blank">${esc(it.url)}</a>`:''}</div>`).join('');
        html += `<div class="cardx">${body}</div>`;
    }
    else if(mt==='native_md' || mt==='md'){
        html += `<div class="mdbox">${esc(m.content||'')}</div>`;
    }
    else if(m.content){
        html += renderRichText(m.content, m.emojis);
    }

    let avatarHtml = '';
    if (isMe) {
        avatarHtml = botAva('sm');
    } else {
        const userId = m.user_id || '';
        const fallbackLetter = (m.username || userId || '?').slice(-2).toUpperCase();
        avatarHtml = `<div class="ava sm"><img src="https://q.qlogo.cn/qqapp/${state.appid}/${userId}/5" loading="lazy" referrerpolicy="no-referrer" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"><span class="ava sm" style="display:none;">${esc(fallbackLetter)}</span></div>`;
    }

    let displayName = '';
    if (isMe) {
        displayName = botName;
    } else {
        const uid = m.user_id || '';
        displayName = state.nicknameMap[uid] || m.username || m.raw_username || `用户${uid.slice(-6)}`;
        displayName = cleanName(displayName);
    }

    const tools = [];
    if (isMe && m.message_id && state.current && state.current.id) {
        tools.push(`<button class="mact revoke" data-msgid="${esc(m.message_id)}" data-chatid="${esc(state.current.id)}" data-chattype="${state.current.type}" title="撤回这条消息">↩ 撤回</button>`);
    }
    if (!isMe && state.current && state.current.type === 'group' && m.user_id) {
        tools.push(`<button class="mact mute" data-uid="${esc(m.user_id)}" data-chatid="${esc(state.current.id)}" title="禁言 / 解除禁言（需要机器人有管理权限）">🔇 禁言</button>`);
    }
    const toolsHtml = tools.length ? `<div class="msg-tools">${tools.join('')}</div>` : '';

    return `<div class="msg ${isMe?'me':'them'}" data-id="${esc(m.message_id||'')}">
        ${avatarHtml}
        <div class="bubble">
            <div class="bname">${esc(displayName)}</div>
            <div class="body">${html}${time}</div>
            ${toolsHtml}
        </div>
    </div>`;
}

$('msgList').addEventListener('click', async function(e) {
    const revokeBtn = e.target.closest('.mact.revoke');
    if (revokeBtn) {
        e.preventDefault();
        if (!confirm('确定撤回这条消息吗？（需在发送后 2 分钟内）')) return;
        const {chatid, chattype, msgid} = revokeBtn.dataset;
        try {
            const res = await fetch(`${API}?type=revoke&appid=${encodeURIComponent(state.appid)}&chat_type=${chattype}&chat_id=${encodeURIComponent(chatid)}&msg_id=${encodeURIComponent(msgid)}`).then(r=>r.json());
            if (res.code === 200) {
                toast('撤回成功', true);
                const msgEl = revokeBtn.closest('.msg');
                if (msgEl) msgEl.remove();
            } else {
                toast(res.msg || '撤回失败', false);
            }
        } catch (err) { toast('撤回失败', false); }
        return;
    }
    const muteBtn = e.target.closest('.mact.mute');
    if (muteBtn) {
        e.preventDefault();
        const sec = prompt('禁言时长（秒），输入 0 或留空为解除禁言：', '600');
        if (sec === null) return;
        const seconds = parseInt(sec || '0', 10) || 0;
        try {
            const res = await fetch(`${API}?type=mute&appid=${encodeURIComponent(state.appid)}&group_id=${encodeURIComponent(muteBtn.dataset.chatid)}&user_id=${encodeURIComponent(muteBtn.dataset.uid)}&seconds=${seconds}`).then(r=>r.json());
            if (res.code === 200) toast(res.msg || '操作成功', true);
            else toast(res.msg || '操作失败', false);
        } catch (err) { toast('操作失败', false); }
        return;
    }
});

$('msgList').addEventListener('click', function(e) {
    const avatar = e.target.closest('.msg.them .ava');
    if (!avatar) return;
    const msgEl = avatar.closest('.msg');
    if (!msgEl) return;
    const bnameEl = msgEl.querySelector('.bname');
    if (!bnameEl) return;
    const name = bnameEl.textContent.trim();
    const input = $('msgInput');
    if (input) {
        input.value = '@' + name + ' ';
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);
        toast('已艾特 @' + name, true);
    }
});

/* ---------- 图片查看器 ---------- */
function openViewer(url){
    $('viewerImg').src = url;
    $('imgViewer').classList.add('show');
}
$('closeViewer').addEventListener('click', ()=>$('imgViewer').classList.remove('show'));
$('imgViewer').addEventListener('click', e=>{ if(e.target===e.currentTarget) $('imgViewer').classList.remove('show'); });

/* ---------- 发送 ---------- */
async function sendMessage(payload){
    if(!state.current){ toast('请先选择会话', false); return null; }
    const base = { type:'send', appid:state.appid, name:dateLogName(), chat_type:state.current.type, chat_id:state.current.id, active: state.active?'1':'0' };
    const data = await json(API, Object.assign(base, payload));
    if(data.code === 200){
        toast(data.msg || '发送成功', true);
        state.msgInit = false; state.lastMsgTime = '';
        loadMessages(); loadList();
        return true;
    }
    toast(data.msg || '发送失败', false);
    return false;
}

$('sendBtn').addEventListener('click', doSend);
$('msgInput').addEventListener('keydown', e=>{
    if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); doSend(); }
});
function doSend(){
    const input = $('msgInput');
    let content = input.value;
    if(state.md){ input.value=''; return sendMessage({send_method:'native_md', content}); }
    content = content.trim();
    if(!content){ toast('请输入内容', false); return; }
    input.value = '';
    return sendMessage({send_method:'text', content});
}

$('mdBtn').addEventListener('click', ()=>{ state.md=!state.md; $('mdBtn').classList.toggle('on', state.md); });
$('activeToggle').addEventListener('change', e=>{ state.active = e.target.checked; $('composerHint').textContent = state.active ? '主动消息：无需会话锚点' : '被动回复需会话内有 299 秒内的消息锚点'; });

/* ---------- 卡片模板 ---------- */
const tplEditor = ()=>JSON.stringify({
    name: $('tplName').value.trim() || ('卡片 ' + Date.now()),
    type: $('tplType').value,
    title: $('tplTitle').value,
    desc: $('tplDesc').value,
    image: $('tplImage').value,
    url: $('tplUrl').value,
    lines: $('tplLines').value.split('\n').map(s=>{ const m=s.match(/^(.+?)\|(https?:\/\/.+)$/); return m?{text:m[1].trim(),url:m[2].trim()}:{text:s.trim()}; }).filter(x=>x.text)
});
function loadTplEditor(t){
    if(!t) return;
    $('tplName').value = t.name||''; $('tplType').value = t.type||'文卡';
    $('tplTitle').value = t.title||''; $('tplDesc').value = t.desc||'';
    $('tplImage').value = t.image||''; $('tplUrl').value = t.url||'';
    $('tplLines').value = (t.lines||[]).map(l=>l.url?`${l.text}|${l.url}`:l.text).join('\n');
}
async function loadTemplates(){
    const data = await fetch(`${API}?type=card_templates&action=list`).then(r=>r.json()).catch(()=>null);
    state.cards = (data && Array.isArray(data.templates)) ? data.templates : [];
    $('tplList').innerHTML = state.cards.length ? state.cards.map(t=>`
        <div class="tpl" data-id="${esc(t.id)}">
            <b>${esc(t.name)}</b><span class="tp">${esc(t.type||'文卡')}</span>
            <button class="tpl-del" data-id="${esc(t.id)}">删除</button>
        </div>`).join('') : '<div style="color:var(--muted);font-size:12px">暂无保存的模板</div>';
    $('tplList').querySelectorAll('.tpl').forEach(el=>el.addEventListener('click', e=>{
        if(e.target.classList.contains('tpl-del')) return;
        const t = state.cards.find(x=>x.id===el.dataset.id);
        if(t) loadTplEditor(t);
    }));
    $('tplList').querySelectorAll('.tpl-del').forEach(el=>el.addEventListener('click', async e=>{
        e.stopPropagation();
        await fetch(`${API}?type=card_templates&action=delete&id=${encodeURIComponent(el.dataset.id)}`);
        loadTemplates();
    }));
}
$('cardBtn').addEventListener('click', ()=>{ $('cardModal').classList.add('show'); loadTemplates(); });
$('closeCardBtn').addEventListener('click', ()=>$('cardModal').classList.remove('show'));
$('cardModal').addEventListener('click', e=>{ if(e.target===e.currentTarget) $('cardModal').classList.remove('show'); });
$('saveTplBtn').addEventListener('click', async ()=>{
    await fetch(`${API}?type=card_templates&action=save`, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({template: JSON.parse(tplEditor())})});
    toast('模板已保存', true); loadTemplates();
});
$('deleteTplBtn').addEventListener('click', async ()=>{ toast('请先在列表中选择要删除的模板', false); });
$('sendCardBtn').addEventListener('click', ()=>{
    const card = JSON.parse(tplEditor());
    sendMessage({send_method:'card', card: JSON.stringify(card)});
    $('cardModal').classList.remove('show');
});

/* ---------- 顶部交互 ---------- */
function bindTabs(){
    document.querySelectorAll('.tabs button').forEach(b=>b.addEventListener('click', ()=>{
        document.querySelectorAll('.tabs button').forEach(x=>x.classList.remove('active'));
        b.classList.add('active');
        state.tab = b.dataset.tab;
        renderList();
    }));
}
var listPollTimer = null;
async function pollList(){
    if(!state.appid || $('searchInput').value.trim()) return;
    const data = await fetch(`${API}?type=list&appid=${encodeURIComponent(state.appid)}&name=${encodeURIComponent(dateLogName())}`).then(r=>r.json()).catch(()=>null);
    if(!data) return;
    const g0 = (data.groups||[])[0], p0 = (data.privates||[])[0];
    const sig = JSON.stringify([
        (data.groups||[]).length, (data.privates||[]).length,
        g0 ? g0.id + '|' + g0.last_message_time : '',
        p0 ? p0.id + '|' + p0.last_message_time : ''
    ]);
    if(sig !== state.listSig){
        state.listSig = sig;
        state.convs.group = data.groups || [];
        state.convs.private = data.privates || [];
        renderList();
    }
}
function startPolling(){
    if(listPollTimer) clearInterval(listPollTimer);
    listPollTimer = setInterval(()=>{
        pollList();
        if(state.current && state.msgInit) loadMessages();
    }, 5000);
}
$('refreshBtn').addEventListener('click', loadList);
$('refreshChatBtn').addEventListener('click', ()=>loadMessages(true));
$('backBtn').addEventListener('click', ()=>{ $('sidebar').classList.remove('hide'); });
$('searchInput').addEventListener('input', renderList);
$('logFileSelect').addEventListener('change', (e)=>{
    state.logName = e.target.value || '';
    resetConversation();
    loadList();
});

/* ---------- 初始化 ---------- */
(function init(){
    bindTabs();
    $('chatEmpty').style.display = 'flex';
    loadBots();
    loadLogFiles();
    startPolling();
})();
</script>
</body>
</html>