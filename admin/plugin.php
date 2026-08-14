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
    <title>插件管理 - <?php echo htmlspecialchars($appid); ?></title>
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
            --success: #2c6e2c;
            --warning: #b85c1a;
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
        .stat-value { font-size: 26px; font-weight: 600; color: var(--text-main); line-height: 1.2; }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
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
        .tabs { display: flex; gap: 8px; }
        .tab {
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 20px;
            cursor: pointer;
            background: #f1f5f9;
            color: var(--text-sub);
            transition: all 0.15s;
        }
        .tab.active { background: var(--primary); color: white; }
        .tab-content { display: none; padding: 20px; }
        .tab-content.active { display: block; }

        .plugin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
        }
        .plugin-card {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px;
            background: white;
        }
        .plugin-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .plugin-name { font-size: 15px; font-weight: 600; color: var(--text-main); }
        .plugin-file { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
        .badge {
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 20px;
            background: #f1f5f9;
        }
        .badge.enabled { background: #eef6ec; color: var(--success); }
        .badge.disabled { background: #fef2f0; color: var(--danger); }
        .plugin-actions { display: flex; gap: 8px; margin-top: 14px; }
        .plugin-actions .btn { flex: 1; justify-content: center; }

        .btn {
            padding: 6px 12px;
            font-size: 12px;
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
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #235b23; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #a83426; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-warning:hover { background: #9e4a15; }

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
            max-width: 680px;
            max-height: 85vh;
            overflow: auto;
        }
        #editModal .modal-content { max-width: min(1100px, 96vw); }
        #editModal .modal-body { padding: 16px 20px; }
        .code-editor-wrap {
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            background: #0f172a;
        }
        .CodeMirror {
            height: 58vh;
            min-height: 420px;
            font-family: 'SF Mono', 'Consolas', 'Monaco', monospace;
            font-size: 13px;
            line-height: 1.55;
        }
        .CodeMirror-focused { outline: 1px solid var(--primary); }
        .CodeMirror-gutters { border-right: 1px solid rgba(255,255,255,.08); }
        .modal-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-size: 16px; font-weight: 600; }
        .close-btn { background: none; border: none; font-size: 18px; cursor: pointer; color: var(--text-muted); }
        .modal-body { padding: 20px; }
        .modal-footer { padding: 16px 20px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 500; color: var(--text-sub); margin-bottom: 6px; }
        .form-control, .form-textarea {
            width: 100%;
            padding: 10px 12px;
            font-size: 13px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: inherit;
        }
        .form-textarea { min-height: 400px; font-family: 'SF Mono', monospace; font-size: 12px; resize: vertical; }
        .form-control:focus, .form-textarea:focus { outline: none; border-color: var(--primary); }

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
            .plugin-grid { grid-template-columns: 1fr; }
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
        .notification.success { background: var(--success); }
        .notification.error { background: var(--danger); }
    .main-content{height:calc(100vh - 60px);overflow-y:auto !important;overflow-x:hidden !important;}
</style>
    <link rel="stylesheet" href="theme-align.css">
    <link rel="stylesheet" href="theme-pixel.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/material-darker.min.css">

<style id="manual-scrollbar-hide">
/* 手动逐页写入：滚动可用但滚动条不显示 */
html,body,*{scrollbar-width:none !important;-ms-overflow-style:none !important;}
*::-webkit-scrollbar{width:0 !important;height:0 !important;display:none !important;background:transparent !important;}
*::-webkit-scrollbar-thumb,*::-webkit-scrollbar-track{background:transparent !important;}
/* 当前页常见滚动容器 */
.messages,.menu,.log-list,.plugin-grid,.card-body,.table-responsive,textarea,#chatInput,.main-content,.container{scrollbar-width:none !important;-ms-overflow-style:none !important;}
.messages::-webkit-scrollbar,.menu::-webkit-scrollbar,.log-list::-webkit-scrollbar,.plugin-grid::-webkit-scrollbar,.card-body::-webkit-scrollbar,.table-responsive::-webkit-scrollbar,textarea::-webkit-scrollbar,#chatInput::-webkit-scrollbar,.main-content::-webkit-scrollbar,.container::-webkit-scrollbar{width:0 !important;height:0 !important;display:none !important;}
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
                <div class="page-title">插件管理 · <?php echo htmlspecialchars($appid); ?></div>
                <div>
                    <a href="main.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> 返回后台</a>
                    <button class="btn btn-primary" id="addPluginBtn"><i class="fas fa-plus"></i> 添加插件</button>
                </div>
            </div>

            <div class="container">
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-label">已启用</div><div class="stat-value" id="enabledCount">0</div></div>
                    <div class="stat-card"><div class="stat-label">未启用</div><div class="stat-value" id="disabledCount">0</div></div>
                    <div class="stat-card"><div class="stat-label">全部插件</div><div class="stat-value" id="allCount">0</div></div>
                    <div class="stat-card"><div class="stat-label">AppID</div><div class="stat-value" style="font-size:12px; word-break:break-all;"><?php echo htmlspecialchars($appid); ?></div></div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>插件列表</h2>
                        <div class="tabs">
                            <div class="tab active" data-tab="enabled">已启用</div>
                            <div class="tab" data-tab="disabled">未启用</div>
                            <div class="tab" data-tab="all">全部</div>
                        </div>
                    </div>
                    <div id="enabledPlugins" class="tab-content active"><div class="plugin-grid" id="enabledList"><div class="empty-state">加载中...</div></div></div>
                    <div id="disabledPlugins" class="tab-content"><div class="plugin-grid" id="disabledList"><div class="empty-state">加载中...</div></div></div>
                    <div id="allPlugins" class="tab-content"><div class="plugin-grid" id="allList"><div class="empty-state">加载中...</div></div></div>
                </div>
            </div>
        </main>
    </div>

    <!-- 添加插件模态框 -->
    <div class="modal" id="addModal">
        <div class="modal-content" style="max-width:480px;">
            <div class="modal-header"><h3>添加插件</h3><button class="close-btn" data-close="addModal">&times;</button></div>
            <div class="modal-body">
                <div class="form-group"><label>插件名称</label><input type="text" class="form-control" id="pluginName" placeholder="请输入插件名称，不用带 .php"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-close="addModal">取消</button><button class="btn btn-primary" id="confirmAddBtn">确认添加</button></div>
        </div>
    </div>

    <!-- 编辑插件模态框 -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header"><h3>编辑插件</h3><button class="close-btn" data-close="editModal">&times;</button></div>
            <div class="modal-body">
                <div class="form-group"><label>插件名称</label><input type="text" class="form-control" id="editPluginName" readonly></div>
                <div class="form-group"><label>插件内容</label><div class="code-editor-wrap"><textarea class="form-textarea" id="pluginContent"></textarea></div></div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-close="editModal">取消</button><button class="btn btn-primary" id="savePluginBtn"><i class="fas fa-save"></i> 保存</button></div>
        </div>
    </div>

    <!-- 删除确认模态框 -->
    <div class="modal" id="confirmModal">
        <div class="modal-content" style="max-width:400px;">
            <div class="modal-header"><h3>确认删除</h3><button class="close-btn" data-close="confirmModal">&times;</button></div>
            <div class="modal-body" style="text-align:center;"><p>确定要删除插件 <strong id="deletePluginName"></strong> 吗？</p><p style="font-size:12px; color:var(--text-muted);">此操作不可恢复</p></div>
            <div class="modal-footer"><button class="btn btn-secondary" data-close="confirmModal">取消</button><button class="btn btn-danger" id="confirmDeleteBtn">确认删除</button></div>
        </div>
    </div>

    <!-- 插件作用域模态框 -->
    <div class="modal" id="scopeModal">
        <div class="modal-content" style="max-width:640px;">
            <div class="modal-header"><h3>插件作用域</h3><button class="close-btn" data-close="scopeModal">&times;</button></div>
            <div class="modal-body">
                <div class="form-group"><label>插件</label><input type="text" class="form-control" id="scopePluginName" readonly></div>
                <div class="form-group">
                    <label>生效范围</label>
                    <select class="form-control" id="scopeMode">
                        <option value="all">全部群</option>
                        <option value="specified">仅指定群</option>
                    </select>
                </div>
                <div class="form-group" id="scopeGroupsWrap">
                    <label>指定生效的群（从日志中遍历选择）</label>
                    <input type="text" class="form-control" id="scopeSearch" placeholder="搜索群名…">
                    <div id="scopeGroups" style="margin-top:8px;display:grid;gap:6px;max-height:42vh;overflow:auto;"></div>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-close="scopeModal">取消</button><button class="btn btn-primary" id="saveScopeBtn"><i class="fas fa-save"></i> 保存作用域</button></div>
        </div>
    </div>

    <div id="notification" class="notification"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/clike/clike.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js"></script>
    <script>
        const appid = '<?php echo addslashes($appid); ?>';
        let currentEditingPlugin = null;
        let deleteTargetPlugin = null;
        let pluginEditor = null;

        function initPluginEditor() {
            if (pluginEditor || typeof CodeMirror === 'undefined') return;
            pluginEditor = CodeMirror.fromTextArea(document.getElementById('pluginContent'), {
                mode: 'application/x-httpd-php',
                theme: 'material-darker',
                lineNumbers: true,
                lineWrapping: true,
                indentUnit: 4,
                tabSize: 4,
                indentWithTabs: false,
                matchBrackets: true,
                autoCloseBrackets: true,
                viewportMargin: Infinity
            });
        }

        function setPluginContent(value) {
            initPluginEditor();
            if (pluginEditor) pluginEditor.setValue(value || '');
            else document.getElementById('pluginContent').value = value || '';
        }

        function getPluginContent() {
            return pluginEditor ? pluginEditor.getValue() : document.getElementById('pluginContent').value;
        }

        function showMsg(text, isSuccess) {
            const el = document.getElementById('notification');
            el.textContent = text;
            el.className = 'notification ' + (isSuccess ? 'success' : 'error') + ' show';
            setTimeout(() => el.classList.remove('show'), 2500);
        }

        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        document.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', () => closeModal(btn.dataset.close)));

        async function loadPlugins() {
            try {
                const [enabledRes, allRes] = await Promise.all([
                    fetch(`api/plugin.php?type=list&appid=${encodeURIComponent(appid)}`).then(r => r.json()),
                    fetch('api/plugin.php?type=filelist').then(r => r.json())
                ]);
                if (allRes.code !== 200) throw new Error('加载失败');
                const enabledPlugins = Object.keys(enabledRes || {});
                const allPlugins = allRes.list || [];
                const enabled = [], disabled = [];
                allPlugins.forEach(p => enabledPlugins.includes(p) ? enabled.push(p) : disabled.push(p));
                document.getElementById('enabledCount').textContent = enabled.length;
                document.getElementById('disabledCount').textContent = disabled.length;
                document.getElementById('allCount').textContent = allPlugins.length;
                renderPluginList(enabled, 'enabledList', true);
                renderPluginList(disabled, 'disabledList', false);
                renderPluginList(allPlugins, 'allList', null);
            } catch (err) {
                showMsg('加载失败', false);
            }
        }

        function renderPluginList(plugins, containerId, isEnabled) {
            const container = document.getElementById(containerId);
            if (!plugins.length) { container.innerHTML = '<div class="empty-state">暂无插件</div>'; return; }
            container.innerHTML = plugins.map(plugin => `
                <div class="plugin-card">
                    <div class="plugin-header">
                        <div><div class="plugin-name">${escapeHtml(plugin)}</div><div class="plugin-file">${plugin}.php</div></div>
                        <span class="badge ${isEnabled === true ? 'enabled' : isEnabled === false ? 'disabled' : ''}">${isEnabled === true ? '已启用' : isEnabled === false ? '未启用' : '插件'}</span>
                    </div>
                    <div class="plugin-actions">
                        ${isEnabled !== null ? (isEnabled ? 
                            `<button class="btn btn-warning toggle-plugin" data-plugin="${plugin}" data-action="disable"><i class="fas fa-toggle-off"></i> 禁用</button>` :
                            `<button class="btn btn-success toggle-plugin" data-plugin="${plugin}" data-action="enable"><i class="fas fa-toggle-on"></i> 启用</button>`) : ''}
                        <button class="btn btn-secondary scope-plugin" data-plugin="${plugin}"><i class="fas fa-location-arrow"></i> 作用域</button>
                        <button class="btn btn-secondary edit-plugin" data-plugin="${plugin}"><i class="fas fa-edit"></i> 编辑</button>
                        <button class="btn btn-danger delete-plugin" data-plugin="${plugin}"><i class="fas fa-trash"></i> 删除</button>
                    </div>
                </div>
            `).join('');
            container.querySelectorAll('.toggle-plugin').forEach(btn => btn.addEventListener('click', () => togglePlugin(btn.dataset.plugin, btn.dataset.action)));
            container.querySelectorAll('.scope-plugin').forEach(btn => btn.addEventListener('click', () => openScopeModal(btn.dataset.plugin)));
            container.querySelectorAll('.edit-plugin').forEach(btn => btn.addEventListener('click', () => openEditModal(btn.dataset.plugin)));
            container.querySelectorAll('.delete-plugin').forEach(btn => btn.addEventListener('click', () => openDeleteModal(btn.dataset.plugin)));
        }

        async function togglePlugin(plugin, action) {
            const url = `api/plugin.php?type=${action === 'enable' ? 'open' : 'close'}&appid=${encodeURIComponent(appid)}&name=${encodeURIComponent(plugin)}`;
            try {
                const res = await fetch(url);
                const data = await res.json();
                if (data.code === 200) { showMsg(`${action === 'enable' ? '启用' : '禁用'}成功`, true); loadPlugins(); }
                else showMsg(data.msg || '操作失败', false);
            } catch (err) { showMsg('操作失败', false); }
        }

        async function openEditModal(plugin) {
            currentEditingPlugin = plugin;
            document.getElementById('editPluginName').value = plugin;
            setPluginContent('加载中...');
            document.getElementById('editModal').style.display = 'flex';
            setTimeout(() => pluginEditor && pluginEditor.refresh(), 80);
            try {
                const res = await fetch(`api/plugin.php?type=read&name=${encodeURIComponent(plugin)}`);
                const data = await res.json();
                if (data.code === 200) setPluginContent(data.msg);
                else showMsg(data.msg || '读取失败', false);
                setTimeout(() => pluginEditor && pluginEditor.refresh(), 80);
            } catch (err) { showMsg('读取失败', false); }
        }

        document.getElementById('savePluginBtn').addEventListener('click', async () => {
            if (!currentEditingPlugin) return;
            const content = getPluginContent();
            try {
                const res = await fetch(`api/plugin.php?type=write&name=${encodeURIComponent(currentEditingPlugin)}`, {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ content })
                });
                const data = await res.json();
                if (data.code === 200) { showMsg('保存成功', true); closeModal('editModal'); loadPlugins(); }
                else showMsg(data.msg || '保存失败', false);
            } catch (err) { showMsg('保存失败', false); }
        });

        function openDeleteModal(plugin) {
            deleteTargetPlugin = plugin;
            document.getElementById('deletePluginName').textContent = plugin;
            document.getElementById('confirmModal').style.display = 'flex';
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', async () => {
            if (!deleteTargetPlugin) return;
            try {
                const res = await fetch(`api/plugin.php?type=delete&name=${encodeURIComponent(deleteTargetPlugin)}`);
                const data = await res.json();
                if (data.code === 200) { showMsg('删除成功', true); closeModal('confirmModal'); loadPlugins(); }
                else showMsg(data.msg || '删除失败', false);
            } catch (err) { showMsg('删除失败', false); }
            deleteTargetPlugin = null;
        });

        document.getElementById('addPluginBtn').addEventListener('click', () => document.getElementById('addModal').style.display = 'flex');
        document.getElementById('confirmAddBtn').addEventListener('click', async () => {
            const name = document.getElementById('pluginName').value.trim();
            if (!name) { showMsg('请输入插件名称', false); return; }
            try {
                const res = await fetch(`api/plugin.php?type=add&name=${encodeURIComponent(name)}`);
                const data = await res.json();
                if (data.code === 200) { showMsg('添加成功', true); closeModal('addModal'); document.getElementById('pluginName').value = ''; loadPlugins(); }
                else showMsg(data.msg || '添加失败', false);
            } catch (err) { showMsg('添加失败', false); }
        });

        /* ---------- 插件作用域（从日志遍历选择群） ---------- */
        let scopePlugin = null;
        let scopeGroupsData = [];
        let scopeSelectedGroups = new Set();

        async function openScopeModal(plugin) {
            scopePlugin = plugin;
            scopeSelectedGroups = new Set();
            scopeNameLoading.clear();
            document.getElementById('scopePluginName').value = plugin;
            document.getElementById('scopeMode').value = 'all';
            document.getElementById('scopeSearch').value = '';
            document.getElementById('scopeGroupsWrap').style.display = 'block';
            const g = await fetch(`api/bot.php?type=groups&appid=${encodeURIComponent(appid)}`).then(r => r.json()).catch(() => null);
            scopeGroupsData = (g && g.code === 200 && Array.isArray(g.groups)) ? g.groups : [];
            const s = await fetch(`api/plugin.php?type=scopes&appid=${encodeURIComponent(appid)}`).then(r => r.json()).catch(() => null);
            if (s && s.code === 200 && s.scopes && s.scopes[plugin]) {
                const cfg = s.scopes[plugin];
                document.getElementById('scopeMode').value = cfg.scope === 'specified' ? 'specified' : 'all';
                (cfg.groups || []).forEach(id => scopeSelectedGroups.add(id));
            }
            renderScopeGroups();
            syncScopeWrap();
            document.getElementById('scopeModal').style.display = 'flex';
        }

        function syncScopeWrap() {
            document.getElementById('scopeGroupsWrap').style.display = document.getElementById('scopeMode').value === 'specified' ? 'block' : 'none';
        }

        function renderScopeGroups() {
            const kw = document.getElementById('scopeSearch').value.trim().toLowerCase();
            const box = document.getElementById('scopeGroups');
            const list = scopeGroupsData.filter(g => {
                if (!kw) return true;
                const name = (g.name || '').toLowerCase();
                const id = String(g.id).toLowerCase();
                return name.includes(kw) || id.includes(kw);
            });
            if (!list.length) {
                box.innerHTML = '<div class="empty-state">暂无群记录（需要先有群聊日志）</div>';
                return;
            }
            box.innerHTML = list.map(g => {
                const checked = scopeSelectedGroups.has(g.id) ? 'checked' : '';
                const displayName = g.name ? escapeHtml(g.name) : '<span style="color:var(--text-muted)">加载群名中…</span>';
                return `<label style="display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid var(--border);border-radius:8px;cursor:pointer;background:white;">
                    <input type="checkbox" value="${escapeHtml(g.id)}" ${checked}>
                    <span style="flex:1;min-width:0;">
                        <span style="display:block;font-size:13px;font-weight:500;word-break:break-word;" data-gid="${escapeHtml(g.id)}">${displayName}</span>
                        <span style="display:block;font-size:10px;color:var(--text-muted);margin-top:2px;">${escapeHtml(g.last_time)}</span>
                    </span>
                </label>`;
            }).join('');
            box.querySelectorAll('input[type=checkbox]').forEach(cb => {
                cb.addEventListener('change', () => {
                    if (cb.checked) scopeSelectedGroups.add(cb.value); else scopeSelectedGroups.delete(cb.value);
                });
            });
            loadMissingGroupNames();
        }

        const scopeNameLoading = new Set();
        function loadMissingGroupNames() {
            scopeGroupsData.forEach(g => {
                if (g.name || scopeNameLoading.has(g.id)) return;
                scopeNameLoading.add(g.id);
                fetch(`api/chat.php?type=group_name&appid=${encodeURIComponent(appid)}&group_id=${encodeURIComponent(g.id)}`)
                    .then(r => r.json())
                    .then(d => {
                        if (d.code === 200 && d.name) {
                            g.name = d.name;
                            const el = document.querySelector(`#scopeGroups [data-gid="${CSS.escape(g.id)}"]`);
                            if (el) el.textContent = d.name;
                        } else {
                            g.name = g.id;
                            const el = document.querySelector(`#scopeGroups [data-gid="${CSS.escape(g.id)}"]`);
                            if (el) { el.textContent = g.id; el.style.color = 'var(--text-muted)'; }
                        }
                    })
                    .catch(() => {
                        g.name = g.id;
                        const el = document.querySelector(`#scopeGroups [data-gid="${CSS.escape(g.id)}"]`);
                        if (el) { el.textContent = g.id; el.style.color = 'var(--text-muted)'; }
                    });
            });
        }

        document.getElementById('scopeMode').addEventListener('change', syncScopeWrap);
        document.getElementById('scopeSearch').addEventListener('input', renderScopeGroups);
        document.getElementById('saveScopeBtn').addEventListener('click', async () => {
            if (!scopePlugin) return;
            const scope = document.getElementById('scopeMode').value;
            const groups = scope === 'specified' ? Array.from(scopeSelectedGroups) : [];
            try {
                const res = await fetch(`api/plugin.php?type=scope&appid=${encodeURIComponent(appid)}&name=${encodeURIComponent(scopePlugin)}&scope=${encodeURIComponent(scope)}&groups=${encodeURIComponent(JSON.stringify(groups))}`);
                const data = await res.json();
                if (data.code === 200) { showMsg('作用域已保存', true); closeModal('scopeModal'); loadPlugins(); }
                else showMsg(data.msg || '保存失败', false);
            } catch (err) { showMsg('保存失败', false); }
        });

        // Tab切换
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const tabId = tab.dataset.tab;
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                document.getElementById(tabId + 'Plugins').classList.add('active');
            });
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
        loadPlugins();
    </script>
</body>
</html>