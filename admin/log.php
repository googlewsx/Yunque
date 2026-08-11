<?php
if (!isset($_COOKIE['admin_token'])) {
    header("Location: index.php");
    exit();
}

$appid = $_GET['appid'] ?? '';
if (empty($appid)) {
    die("缺少appid参数");
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>官机框架2.0 · 日志管理 - <?php echo htmlspecialchars($appid); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e9edf2;
            --text-main: #1a2c3e;
            --text-sub: #5e6f8d;
            --text-muted: #8b9ab0;
            --primary: #2c6b9e;
            --primary-hover: #235b87;
            --send: #2c6b9e;
            --receive: #2c6e2c;
            --event: #8b9ab0;
            --danger: #c23d2e;
            --sidebar-width: 240px;
            --header-height: 52px;
        }

        body {
            background: var(--bg);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--text-main);
            line-height: 1.5;
        }

        .desktop-layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: var(--sidebar-width);
            background: var(--card);
            border-right: 1px solid var(--border);
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            display: flex;
            flex-direction: column;
        }
        .sidebar-header { padding: 20px 24px; border-bottom: 1px solid var(--border); }
        .sidebar-header h1 { font-size: 18px; font-weight: 600; color: var(--text-main); }
        .sidebar-header p { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
        .sidebar-nav { flex: 1; padding: 16px 0; }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 24px;
            color: var(--text-sub);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.15s;
        }
        .nav-item:hover { background: #f1f5f9; color: var(--primary); }
        .nav-item.active { background: #f1f5f9; color: var(--primary); border-left: 3px solid var(--primary); padding-left: 21px; }
        .nav-item i { width: 20px; font-size: 15px; }
        .sidebar-footer { padding: 16px 24px; border-top: 1px solid var(--border); font-size: 11px; color: var(--text-muted); }

        .main-content { flex: 1; margin-left: var(--sidebar-width); min-height: 100vh; }
        .top-bar {
            background: var(--card);
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            height: var(--header-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .page-title { font-size: 15px; font-weight: 500; color: var(--text-main); }
        .container { padding: 28px 32px; max-width: 1400px; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 18px;
        }
        .stat-label { font-size: 12px; color: var(--text-muted); margin-bottom: 6px; }
        .stat-value { font-size: 20px; font-weight: 600; color: var(--text-main); word-break: break-all; }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .card-header {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .card-header h2 { font-size: 16px; font-weight: 600; color: var(--text-main); }
        .card-body { padding: 20px; }

        .log-select {
            width: 100%;
            max-width: 320px;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
            background: white;
        }
        .log-actions { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }

        .log-list { display: flex; flex-direction: column; gap: 12px; }
        .log-card {
            border: 1px solid var(--border);
            border-left: 4px solid var(--event);
            border-radius: 8px;
            padding: 14px 16px;
            cursor: pointer;
            transition: all 0.15s;
            background: white;
        }
        .log-card:hover { background: #fafcff; border-color: #cdd9ed; }
        .log-card.send { border-left-color: var(--send); }
        .log-card.receive { border-left-color: var(--receive); }
        .log-card.event { border-left-color: var(--event); }
        .log-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 8px; }
        .log-time { font-size: 11px; color: var(--text-muted); }
        .log-badge {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 20px;
            background: #f1f5f9;
        }
        .log-badge.send { background: #eef2fc; color: var(--send); }
        .log-badge.receive { background: #eef6ec; color: var(--receive); }
        .log-badge.event { background: #f1f5f9; color: var(--event); }
        .log-summary { font-size: 13px; color: var(--text-sub); word-break: break-word; }

        .btn {
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 6px;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.15s;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-secondary { background: #f1f5f9; color: var(--text-sub); border: 1px solid var(--border); }
        .btn-secondary:hover { background: #e9edf2; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #a83426; }

        .empty-state { text-align: center; padding: 48px 20px; color: var(--text-muted); }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 100%;
            max-width: 780px;
            max-height: 85vh;
            overflow: auto;
        }
        .modal-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-size: 16px; font-weight: 600; }
        .close-btn { background: none; border: none; font-size: 18px; cursor: pointer; color: var(--text-muted); }
        .modal-body { padding: 20px; }
        .modal-footer { padding: 16px 20px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; }

        .detail-row { display: flex; margin-bottom: 14px; }
        .detail-label { width: 80px; font-size: 12px; color: var(--text-muted); font-weight: 500; }
        .detail-value { flex: 1; font-size: 13px; color: var(--text-main); word-break: break-word; }
        .raw-box {
            background: #f1f5f9;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px;
            font-family: 'SF Mono', monospace;
            font-size: 11px;
            line-height: 1.5;
            overflow-x: auto;
            white-space: pre-wrap;
            max-height: 320px;
        }

        .mobile-header {
            display: none;
            padding: 12px 16px;
            background: white;
            border-bottom: 1px solid var(--border);
            align-items: center;
            justify-content: space-between;
        }
        .menu-toggle { background: none; border: none; font-size: 20px; cursor: pointer; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.2s; z-index: 200; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-header { display: flex; }
            .top-bar { display: none; }
            .container { padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .log-select { max-width: 100%; }
            .detail-row { flex-direction: column; }
            .detail-label { width: auto; margin-bottom: 4px; }
        }

        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 13px;
            background: var(--text-main);
            color: white;
            z-index: 1100;
            transform: translateX(120%);
            transition: transform 0.2s;
        }
        .notification.show { transform: translateX(0); }
        .notification.success { background: #2c6e2c; }
        .notification.error { background: #c23d2e; }
    
        /* FIX_SCROLL_HANDLE */
        html, body { height: 100%; overflow: hidden !important; }
        body { position: relative; }
        .main-content { height: calc(100vh - 60px); overflow-y: auto !important; overflow-x: hidden !important; -webkit-overflow-scrolling: touch; }
        .log-list { overflow-y: auto !important; overflow-x: hidden !important; }
        .main-content::-webkit-scrollbar, .log-list::-webkit-scrollbar { width: 0 !important; height: 0 !important; display: none !important; }

    .main-content{height:calc(100vh - 60px);overflow-y:auto !important;overflow-x:hidden !important;}
</style>
    <link rel="stylesheet" href="theme-align.css">
    <link rel="stylesheet" href="theme-pixel.css">

<style id="manual-scrollbar-hide">
/* 手动逐页写入：滚动可用但滚动条不显示 */
html,body,*{scrollbar-width:none !important;-ms-overflow-style:none !important;}
*::-webkit-scrollbar{width:0 !important;height:0 !important;display:none !important;background:transparent !important;}
*::-webkit-scrollbar-thumb,*::-webkit-scrollbar-track{background:transparent !important;}
/* 当前页常见滚动容器 */
.messages,.menu,.log-list,.plugin-grid,.card-body,.table-responsive,textarea,#chatInput,.main-content,.container{scrollbar-width:none !important;-ms-overflow-style:none !important;}
.messages::-webkit-scrollbar,.menu::-webkit-scrollbar,.log-list::-webkit-scrollbar,.plugin-grid::-webkit-scrollbar,.card-body::-webkit-scrollbar,.table-responsive::-webkit-scrollbar,textarea::-webkit-scrollbar,#chatInput::-webkit-scrollbar,.main-content::-webkit-scrollbar,.container::-webkit-scrollbar{width:0 !important;height:0 !important;display:none !important;}

        /* FIX_SCROLL_HANDLE */
        html, body { height: 100%; overflow: hidden !important; }
        body { position: relative; }
        .main-content { height: calc(100vh - 60px); overflow-y: auto !important; overflow-x: hidden !important; -webkit-overflow-scrolling: touch; }
        .log-list { overflow-y: auto !important; overflow-x: hidden !important; }
        .main-content::-webkit-scrollbar, .log-list::-webkit-scrollbar { width: 0 !important; height: 0 !important; display: none !important; }

    .main-content{height:calc(100vh - 60px);overflow-y:auto !important;overflow-x:hidden !important;}
</style>

<style id="manual-scroll-fix2">
/* 二次强制：禁用系统滚动浮标（全页只允许指定容器滚动） */
html,body{height:100%;overflow:hidden !important;overscroll-behavior:none !important;-webkit-overflow-scrolling:auto !important;scrollbar-width:none !important;scrollbar-color:transparent transparent !important;
  scrollbar-gutter:stable both-edges !important;}
body{position:relative;min-height:100%;}
.main-content,.messages,.menu,.log-list,.plugin-grid,.table-responsive,textarea,#chatInput{
  touch-action:pan-y;
  overflow:auto !important;
  -webkit-overflow-scrolling:auto !important;
  scrollbar-width:none !important;
  -ms-overflow-style:none !important;
  scrollbar-color:transparent transparent !important;
  scrollbar-gutter:stable both-edges !important;
}
.main-content::-webkit-scrollbar,.container::-webkit-scrollbar,.messages::-webkit-scrollbar,.menu::-webkit-scrollbar,.log-list::-webkit-scrollbar,.plugin-grid::-webkit-scrollbar,.card-body::-webkit-scrollbar,.table-responsive::-webkit-scrollbar,textarea::-webkit-scrollbar,#chatInput::-webkit-scrollbar{width:0 !important;height:0 !important;background:transparent !important;display:none !important;}
.main-content{height:calc(100vh - 60px);overflow-y:auto !important;overflow-x:hidden !important;}
</style>

</head>
<body>
    <div class="mobile-header">
        <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
        <span style="font-weight:500;">官机框架2.0</span>
        <div></div>
    </div>

    <div class="desktop-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h1>官机框架2.0</h1>
                <p>机器人管理后台</p>
            </div>
            <nav class="sidebar-nav">
                <a href="main.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> 总览</a>
                <a href="main.php" class="nav-item"><i class="fas fa-plus-circle"></i> 添加机器人</a>
                <a href="set.php" class="nav-item"><i class="fas fa-user-cog"></i> 账号设置</a>
                <a href="doc.php" class="nav-item"><i class="fas fa-file-alt"></i> 开发文档</a>
                <a href="http://qwq.nki.pw/plugin/index.html" class="nav-item" target="_blank"><i class="fas fa-puzzle-piece"></i> 插件商城</a>
            </nav>
            <div class="sidebar-footer">保留 1.0 原有逻辑 · 简洁商务版</div>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <div class="page-title">日志管理 · <?php echo htmlspecialchars($appid); ?></div>
                <a href="main.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> 返回后台</a>
            </div>

            <div class="container">
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-label">当前日志</div><div class="stat-value" id="currentFile" style="font-size:13px;">未选择</div></div>
                    <div class="stat-card"><div class="stat-label">记录数</div><div class="stat-value" id="recordCount">0</div></div>
                    <div class="stat-card"><div class="stat-label">文件数</div><div class="stat-value" id="fileCount">0</div></div>
                    <div class="stat-card"><div class="stat-label">AppID</div><div class="stat-value" style="font-size:12px;"><?php echo htmlspecialchars($appid); ?></div></div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>日志文件</h2>
                        <div class="log-actions">
                            <select id="logFileSelect" class="log-select"><option>加载中...</option></select>
                            <button id="refreshBtn" class="btn btn-secondary"><i class="fas fa-sync-alt"></i> 刷新</button>
                            <button id="deleteFileBtn" class="btn btn-danger"><i class="fas fa-trash"></i> 删除当前文件</button>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>日志内容</h2>
                        <span id="logCount" class="stat-label">0 条记录</span>
                    </div>
                    <div class="card-body">
                        <div id="logList" class="log-list"><div class="empty-state">请选择日志文件</div></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- 日志详情模态框 -->
    <div class="modal" id="detailModal">
        <div class="modal-content">
            <div class="modal-header"><h3>日志详情</h3><button class="close-btn" data-close="detailModal">&times;</button></div>
            <div class="modal-body">
                <div class="detail-row"><div class="detail-label">时间</div><div class="detail-value" id="detailTime"></div></div>
                <div class="detail-row"><div class="detail-label">类型</div><div class="detail-value" id="detailType"></div></div>
                <div class="detail-row"><div class="detail-label">目标</div><div class="detail-value" id="detailTarget"></div></div>
                <div class="detail-row"><div class="detail-label">内容</div><div class="detail-value" id="detailContent"></div></div>
                <div class="detail-row"><div class="detail-label">原始数据</div><div class="detail-value"><pre class="raw-box" id="detailRaw"></pre></div></div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-close="detailModal">关闭</button></div>
        </div>
    </div>

    <!-- 删除确认模态框 -->
    <div class="modal" id="confirmModal">
        <div class="modal-content" style="max-width:400px;">
            <div class="modal-header"><h3>确认删除</h3><button class="close-btn" data-close="confirmModal">&times;</button></div>
            <div class="modal-body" style="text-align:center;"><p>确定要删除日志文件 <strong id="confirmFileName"></strong> 吗？</p><p style="font-size:12px; color:var(--text-muted);">此操作不可恢复</p></div>
            <div class="modal-footer"><button class="btn btn-secondary" data-close="confirmModal">取消</button><button class="btn btn-danger" id="confirmDeleteBtn">确认删除</button></div>
        </div>
    </div>

    <div id="notification" class="notification"></div>

    <script>
        const appid = '<?php echo addslashes($appid); ?>';
        let currentLogFile = '';
        let deleteTargetFile = '';

        function showMsg(text, isSuccess) {
            const el = document.getElementById('notification');
            el.textContent = text;
            el.className = 'notification ' + (isSuccess ? 'success' : 'error') + ' show';
            setTimeout(() => el.classList.remove('show'), 2500);
        }

        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        document.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', () => closeModal(btn.dataset.close)));

        async function loadFileList() {
            const select = document.getElementById('logFileSelect');
            try {
                const res = await fetch(`api/log.php?type=list&appid=${encodeURIComponent(appid)}`);
                const data = await res.json();
                if (data.code === 200 && data.list?.length) {
                    const files = data.list.sort().reverse();
                    document.getElementById('fileCount').textContent = files.length;
                    select.innerHTML = files.map(f => `<option value="${escapeHtml(f)}">${escapeHtml(f)}</option>`).join('');
                    if (currentLogFile && files.includes(currentLogFile)) select.value = currentLogFile;
                    else { select.value = files[0]; currentLogFile = files[0]; }
                    document.getElementById('currentFile').textContent = currentLogFile;
                    loadLogContent(currentLogFile);
                } else { select.innerHTML = '<option>暂无日志文件</option>'; showEmptyState(); }
            } catch (err) { showMsg('加载文件列表失败', false); }
        }

        async function loadLogContent(file) {
            document.getElementById('logList').innerHTML = '<div class="empty-state">加载中...</div>';
            try {
                const res = await fetch(`api/log.php?type=read&appid=${encodeURIComponent(appid)}&name=${encodeURIComponent(file)}`);
                const data = await res.json();
                if (data.code === 200) {
                    renderLogs(data.list || []);
                    document.getElementById('logCount').textContent = `${(data.list || []).length} 条记录`;
                    document.getElementById('recordCount').textContent = (data.list || []).length;
                } else { showEmptyState('加载失败：' + (data.msg || '')); }
            } catch (err) { showEmptyState('加载失败'); }
        }

        function renderLogs(logs) {
            const container = document.getElementById('logList');
            if (!logs.length) { container.innerHTML = '<div class="empty-state">暂无日志记录</div>'; return; }
            container.innerHTML = logs.map((log, idx) => {
                const parsed = parseLog(log);
                return `
                    <div class="log-card ${parsed.typeClass}" data-index="${idx}">
                        <div class="log-header">
                            <span class="log-time">${escapeHtml(parsed.time)}</span>
                            <span class="log-badge ${parsed.typeClass}">${escapeHtml(parsed.typeText)}</span>
                        </div>
                        <div class="log-summary">${parsed.summary}</div>
                    </div>
                `;
            }).join('');
            container.querySelectorAll('.log-card').forEach((card, i) => {
                card.addEventListener('click', () => showDetail(logs[i]));
            });
        }

        function parseLog(log) {
            try {
                const data = JSON.parse(log.raw);
                if (data.direction === '发送') {
                    const typeMap = { '发送文字': '📝', '发送图片': '🖼️', '发送语音': '🎤', '发送视频': '🎬', '发送文件': '📎', '发送按钮': '🔘', '发送Markdown': '📝' };
                    const icon = typeMap[data.action] || '📨';
                    return { typeClass: 'send', typeText: data.action || '发送', time: data.time || log.time, summary: `${icon} ${escapeHtml(String(data.content || '').substring(0, 100))}` };
                }
                if (data.t === 'GROUP_AT_MESSAGE_CREATE') {
                    return { typeClass: 'receive', typeText: '群聊消息', time: log.time, summary: `👥 ${escapeHtml((data.d?.content || '').substring(0, 100))}` };
                }
                if (data.t === 'INTERACTION_CREATE') {
                    return { typeClass: 'receive', typeText: '按钮互动', time: log.time, summary: `🔉 ${escapeHtml((data.d?.content || '').substring(0, 100))}` };
                }
                if (data.t === 'GROUP_MESSAGE_CREATE') {
                    return { typeClass: 'receive', typeText: '群聊消息', time: log.time, summary: `👥 ${escapeHtml((data.d?.content || '').substring(0, 100))}` };
                }
                if (data.t === 'C2C_MESSAGE_CREATE') {
                    return { typeClass: 'receive', typeText: '私聊消息', time: log.time, summary: `💬 ${escapeHtml((data.d?.content || '').substring(0, 100))}` };
                }
                if (data.t === 'DIRECT_MESSAGE_CREATE') {
                    return { typeClass: 'receive', typeText: '频道私聊消息', time: log.time, summary: `💬 ${escapeHtml((data.d?.content || '').substring(0, 100))}` };
                }
                return { typeClass: 'event', typeText: data.t || '事件', time: log.time, summary: escapeHtml(log.summary || '事件记录') };
            } catch (e) {
                return { typeClass: 'event', typeText: '解析错误', time: log.time, summary: '日志格式异常' };
            }
        }

        function showDetail(log) {
            try {
                const data = JSON.parse(log.raw);
                document.getElementById('detailTime').textContent = log.time;
                document.getElementById('detailType').textContent = data.direction || data.t || '事件';
                document.getElementById('detailTarget').textContent = data.target_id || data.d?.group_id || data.d?.author?.id || '-';
                document.getElementById('detailContent').textContent = data.content || data.d?.content || '-';
                document.getElementById('detailRaw').textContent = JSON.stringify(data, null, 2);
            } catch (e) {
                document.getElementById('detailRaw').textContent = log.raw;
            }
            document.getElementById('detailModal').style.display = 'flex';
        }

        function showEmptyState(msg = '请选择日志文件') {
            document.getElementById('logList').innerHTML = `<div class="empty-state">${msg}</div>`;
            document.getElementById('logCount').textContent = '0 条记录';
            document.getElementById('recordCount').textContent = '0';
        }

        document.getElementById('logFileSelect').addEventListener('change', (e) => {
            currentLogFile = e.target.value;
            document.getElementById('currentFile').textContent = currentLogFile;
            if (currentLogFile) loadLogContent(currentLogFile);
            else showEmptyState();
        });
        document.getElementById('refreshBtn').addEventListener('click', () => { loadFileList(); if (currentLogFile) loadLogContent(currentLogFile); });
        document.getElementById('deleteFileBtn').addEventListener('click', () => {
            if (!currentLogFile) { showMsg('请先选择日志文件', false); return; }
            deleteTargetFile = currentLogFile;
            document.getElementById('confirmFileName').textContent = currentLogFile;
            document.getElementById('confirmModal').style.display = 'flex';
        });
        document.getElementById('confirmDeleteBtn').addEventListener('click', async () => {
            if (!deleteTargetFile) return;
            try {
                const res = await fetch(`api/log.php?type=delete&appid=${encodeURIComponent(appid)}&name=${encodeURIComponent(deleteTargetFile)}`);
                const data = await res.json();
                if (data.code === 200) { showMsg('删除成功', true); closeModal('confirmModal'); loadFileList(); }
                else showMsg(data.msg || '删除失败', false);
            } catch (err) { showMsg('删除失败', false); }
            deleteTargetFile = '';
        });

        function escapeHtml(str) { if (!str) return ''; return String(str).replace(/[&<>]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[m])); }

        // 移动端侧边栏
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        if (menuToggle) {
            menuToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) sidebar.classList.remove('open');
            });
        }
        loadFileList();
    </script>
</body>
</html>