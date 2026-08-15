<?php
if (!isset($_COOKIE['admin_token'])) {
    header("Location: index.php");
    exit();
}

$appid = $_GET['appid'] ?? '';
if (empty($appid)) {
    die("缺少appid参数");
}
$active_page = 'plugin';
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
            --bg: #eef1f8;
            --card: #ffffff;
            --border: #e4e9f4;
            --text-main: #1f2437;
            --text-sub: #6b7396;
            --text-muted: #9aa3c0;
            --primary: #5b6cff;
            --brand: #5b6cff;
            --brand2: #8f9aff;
            --primary-hover: #4a5ae8;
            --success: #2ecc71;
            --warning: #b85c1a;
            --danger: #ff6b6b;
            --sidebar-width: 240px;
            --header-height: 56px;
        }

        body {
            background: var(--bg);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'PingFang SC', 'Microsoft YaHei', sans-serif;
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
        .sidebar-nav { flex: 1; padding: 14px 12px; }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px; margin-bottom: 2px; border-radius: 9px;
            color: var(--text-sub);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.15s;
        }
        .nav-item:hover { background: #f1f3fb; color: var(--primary); }
        .nav-item.active { background: var(--primary-light, #eef0ff); color: var(--primary); font-weight: 600; }
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
            border-radius: 12px; box-shadow: 0 1px 3px rgba(31,36,55,.04);
            padding: 14px 18px;
        }
        .stat-label { font-size: 12px; color: var(--text-muted); margin-bottom: 6px; }
        .stat-value { font-size: 26px; font-weight: 600; color: var(--text-main); line-height: 1.2; }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px; box-shadow: 0 1px 3px rgba(31,36,55,.04);
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
            background: #f1f3fb;
            color: var(--text-sub);
            transition: all 0.15s;
        }
        .tab.active { background: linear-gradient(135deg, var(--primary), var(--brand2)); color: #fff; box-shadow: 0 2px 8px rgba(91,108,255,.25); }
        .tab-content { display: none; padding: 20px; }
        .tab-content.active { display: block; }

        .plugin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
        }
        .plugin-card {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            background: #fff;
            box-shadow: 0 1px 3px rgba(31,36,55,.04);
            transition: box-shadow .15s, transform .15s;
        }
        .plugin-card:hover {
            box-shadow: 0 4px 16px rgba(91,108,255,.1);
            transform: translateY(-1px);
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
            background: #f1f3fb;
        }
        .badge.enabled { background: #e8f8ef; color: var(--success); }
        .badge.disabled { background: #fff0f0; color: var(--danger); }
        .plugin-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
        .plugin-actions .btn { flex: 1 1 calc(50% - 4px); min-width: 0; justify-content: center; padding: 7px 8px; font-size: 12px; }

        .btn {
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 500;
            border-radius: 10px;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.15s;
        }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--brand2)); color: #fff; box-shadow: 0 2px 8px rgba(91,108,255,.25); }
        .btn-primary:hover { filter: brightness(.95); }
        .btn-secondary { background: #f1f3fb; color: var(--text-sub); border: 1px solid var(--border); }
        .btn-secondary:hover { background: #e4e9f4; }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { filter: brightness(.95); }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { filter: brightness(.95); }
        .btn-warning { background: var(--warning); color: white; }
        .btn-warning:hover { filter: brightness(.95); }

        .empty-state { text-align: center; padding: 48px 20px; color: var(--text-muted); }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(31,36,55,.45);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-content {
            background: #fff;
            border-radius: 16px;
            width: 100%;
            max-width: 680px;
            max-height: 85vh;
            overflow: auto;
            box-shadow: 0 20px 60px rgba(31,36,55,.2);
        }
        #editModal .modal-content { max-width: min(1100px, 96vw); }
        #editModal .modal-body { padding: 16px 20px; }
        .code-editor-wrap {
            border: 1px solid var(--border);
            border-radius: 10px;
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
            padding: 9px 13px;
            font-size: 13px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-family: inherit;
            background: #f7f8fd;
            transition: all .15s;
        }
        .form-textarea { min-height: 400px; font-family: 'SF Mono', monospace; font-size: 12px; resize: vertical; }
        .form-control:focus, .form-textarea:focus { outline: none; border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px rgba(91,108,255,.1); }

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
</style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/material-darker.min.css">
    <link rel="stylesheet" href="admin-common.css">

</head>
<body>
<?php include '_nav.php'; ?>
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
                    <input type="text" class="form-control" id="scopeSearch" placeholder="搜索群名 / ID…">
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
                const enabledSet = new Set(enabled);
                renderPluginList(enabled, 'enabledList', enabledSet);
                renderPluginList(disabled, 'disabledList', enabledSet);
                renderPluginList(allPlugins, 'allList', enabledSet);
            } catch (err) {
                showMsg('加载失败', false);
            }
        }

        function renderPluginList(plugins, containerId, enabledSet) {
            const container = document.getElementById(containerId);
            if (!plugins.length) { container.innerHTML = '<div class="empty-state">暂无插件</div>'; return; }
            container.innerHTML = plugins.map(plugin => {
                const on = enabledSet.has(plugin);
                // 已启用：禁用 / 作用域 / 编辑（不可删除）
                // 未启用：启用 / 编辑 / 删除（不可调作用域）
                const toggleBtn = on
                    ? `<button class="btn btn-warning toggle-plugin" data-plugin="${plugin}" data-action="disable"><i class="fas fa-toggle-off"></i> 禁用</button>`
                    : `<button class="btn btn-success toggle-plugin" data-plugin="${plugin}" data-action="enable"><i class="fas fa-toggle-on"></i> 启用</button>`;
                const scopeBtn = on
                    ? `<button class="btn btn-secondary scope-plugin" data-plugin="${plugin}"><i class="fas fa-location-arrow"></i> 作用域</button>`
                    : '';
                const deleteBtn = on
                    ? ''
                    : `<button class="btn btn-danger delete-plugin" data-plugin="${plugin}"><i class="fas fa-trash"></i> 删除</button>`;
                return `
                <div class="plugin-card">
                    <div class="plugin-header">
                        <div><div class="plugin-name">${escapeHtml(plugin)}</div><div class="plugin-file">${plugin}.php</div></div>
                        <span class="badge ${on ? 'enabled' : 'disabled'}">${on ? '已启用' : '未启用'}</span>
                    </div>
                    <div class="plugin-actions">
                        ${toggleBtn}
                        ${scopeBtn}
                        <button class="btn btn-secondary edit-plugin" data-plugin="${plugin}"><i class="fas fa-edit"></i> 编辑</button>
                        ${deleteBtn}
                    </div>
                </div>`;
            }).join('');
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

        /* ---------- 插件作用域（从日志遍历选择群，群名异步补齐） ---------- */
        let scopePlugin = null;
        let scopeGroupsData = [];
        let scopeSelectedGroups = new Set();
        let scopeGroupNameCache = {};

        async function openScopeModal(plugin) {
            scopePlugin = plugin;
            scopeSelectedGroups = new Set();
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
            // 异步加载群名（模仿聊天记录页，调用官方群信息接口并缓存）
            fetchScopeGroupNames();
        }

        function syncScopeWrap() {
            document.getElementById('scopeGroupsWrap').style.display = document.getElementById('scopeMode').value === 'specified' ? 'block' : 'none';
        }

        // 逐个请求群名，命中缓存则跳过，返回后更新对应 DOM
        function fetchScopeGroupNames() {
            scopeGroupsData.forEach(g => {
                const id = g.id;
                if (scopeGroupNameCache[id] !== undefined) return;
                scopeGroupNameCache[id] = ''; // 占位，防止重复请求
                fetch(`api/chat.php?type=group_name&appid=${encodeURIComponent(appid)}&group_id=${encodeURIComponent(id)}`)
                    .then(r => r.json())
                    .then(d => {
                        if (d.code === 200 && d.name) {
                            scopeGroupNameCache[id] = d.name;
                            updateScopeGroupName(id, d.name);
                        } else {
                            scopeGroupNameCache[id] = '';
                        }
                    })
                    .catch(() => { scopeGroupNameCache[id] = ''; });
            });
        }

        function updateScopeGroupName(id, name) {
            const box = document.getElementById('scopeGroups');
            const nameEl = box.querySelector(`[data-gid="${id}"] .gname`);
            if (nameEl) nameEl.textContent = name;
        }

        function renderScopeGroups() {
            const kw = document.getElementById('scopeSearch').value.trim().toLowerCase();
            const box = document.getElementById('scopeGroups');
            const list = scopeGroupsData.filter(g => {
                if (!kw) return true;
                const name = String(scopeGroupNameCache[g.id] || '').toLowerCase();
                return String(g.id).toLowerCase().includes(kw) || name.includes(kw) || String(g.last_time || '').includes(kw);
            });
            if (!list.length) {
                box.innerHTML = '<div class="empty-state">暂无群记录（需要先有群聊日志）</div>';
                return;
            }
            box.innerHTML = list.map(g => {
                const checked = scopeSelectedGroups.has(g.id) ? 'checked' : '';
                const gname = scopeGroupNameCache[g.id] || '';
                return `<label data-gid="${escapeHtml(g.id)}" style="display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid var(--border);border-radius:8px;cursor:pointer;background:white;">
                    <input type="checkbox" value="${escapeHtml(g.id)}" ${checked}>
                    <span style="min-width:0;flex:1;">
                        <span class="gname" style="display:block;font-size:13px;font-weight:600;color:var(--text-main);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${gname ? escapeHtml(gname) : '<span style="color:var(--text-muted);font-weight:400;">群名加载中…</span>'}</span>
                        <span style="display:block;font-size:11px;color:var(--text-muted);word-break:break-all;">${escapeHtml(g.id)}</span>
                    </span>
                    <span style="font-size:10px;color:var(--text-muted);white-space:nowrap;">${escapeHtml(g.last_time || '')}</span>
                </label>`;
            }).join('');
            box.querySelectorAll('input[type=checkbox]').forEach(cb => {
                cb.addEventListener('change', () => {
                    if (cb.checked) scopeSelectedGroups.add(cb.value); else scopeSelectedGroups.delete(cb.value);
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

        loadPlugins();
    </script>
</body>
</html>