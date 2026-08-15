<?php
if (!isset($_COOKIE['admin_token'])) {
    header("Location: index.php");
    exit();
}
$active_page = 'main';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
            --danger: #ff6b6b;
            --danger-hover: #e85555;
            --success: #2ecc71;
            --sidebar-width: 240px;
            --header-height: 56px;
        }

        body {
            background: var(--bg);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'PingFang SC', 'Microsoft YaHei', sans-serif;
            color: var(--text-main);
            line-height: 1.5;
        }

        /* 布局 */
        .desktop-layout {
            display: flex;
            min-height: 100vh;
        }

        /* 侧边栏 */
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

        .sidebar-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-header h1 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-main);
        }

        .sidebar-header p {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .sidebar-nav {
            flex: 1;
            padding: 14px 12px;
        }

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

        .nav-item:hover {
            background: #f1f3fb;
            color: var(--primary);
        }

        .nav-item.active { background: var(--primary-light, #eef0ff); color: var(--primary); font-weight: 600; }

        .nav-item i {
            width: 20px;
            font-size: 15px;
        }

        .sidebar-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            font-size: 11px;
            color: var(--text-muted);
        }

        /* 主内容区 */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

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

        .page-title {
            font-size: 15px;
            font-weight: 500;
            color: var(--text-main);
        }

        .top-actions {
            display: flex;
            gap: 12px;
        }

        .container {
            padding: 28px 32px;
            max-width: 1400px;
        }

        /* 统计卡片 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px; box-shadow: 0 1px 3px rgba(31,36,55,.04);
            padding: 16px 20px;
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 600;
            color: var(--text-main);
            line-height: 1.2;
        }

        .stat-hint {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 6px;
        }

        /* 机器人列表卡片 */
        .section-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
        }

        .section-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-main);
        }

        .section-sub {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .btn {
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 10px;
            cursor: pointer;
            border: none;
            font-family: inherit;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--brand2));
            color: #fff;
            box-shadow: 0 2px 8px rgba(91,108,255,.25);
        }

        .btn-primary:hover {
            filter: brightness(.95);
        }

        .btn-secondary {
            background: #f1f3fb;
            color: var(--text-sub);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: #e4e9f4;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: var(--danger-hover);
        }

        .btn-sm {
            padding: 4px 10px;
            font-size: 12px;
        }

        /* 机器人卡片网格 */
        .bots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
            padding: 20px;
        }

        .bot-card {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            background: #fff;
            box-shadow: 0 1px 3px rgba(31,36,55,.04);
            transition: box-shadow .15s, transform .15s;
        }
        .bot-card:hover {
            box-shadow: 0 4px 16px rgba(91,108,255,.1);
            transform: translateY(-1px);
        }

        .bot-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .bot-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid var(--border);
            background: #eef1f8;
        }

        .bot-info h4 {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 2px;
        }

        .bot-info p {
            font-size: 11px;
            color: var(--text-muted);
            word-break: break-all;
        }

        .bot-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .env-badge {
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 600;
        }
        .env-badge.prod { background: #e8f8ef; color: #1f9d55; }
        .env-badge.sandbox { background: #fff5e6; color: #d4880f; }

        .bot-stats {
            display: flex;
            gap: 12px;
            border-top: 1px solid var(--border);
            padding-top: 12px;
            margin-bottom: 14px;
        }

        .stat-item {
            flex: 1;
            text-align: center;
        }

        .stat-item .num {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-main);
        }

        .stat-item .label {
            font-size: 10px;
            color: var(--text-muted);
        }

        /* 管理按钮组 */
        .bot-actions {
            display: flex;
            gap: 10px;
            border-top: 1px solid var(--border);
            padding-top: 12px;
        }

        .bot-actions .btn {
            flex: 1;
            justify-content: center;
            padding: 6px 0;
        }

        .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: var(--text-muted);
            font-size: 14px;
        }

        /* 模态框 */
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
            max-width: 480px;
            max-height: 90vh;
            overflow: auto;
            box-shadow: 0 20px 60px rgba(31,36,55,.2);
        }

        .modal-header {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 16px;
            font-weight: 600;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: var(--text-muted);
            padding: 4px;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-sub);
            margin-bottom: 6px;
        }

        .form-control, .form-select {
            width: 100%;
            padding: 8px 12px;
            font-size: 13px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: inherit;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
        }
        /* 功能开关 - 滑动开关样式 */
        .switch-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 8px;
            background: white;
            cursor: pointer;
            transition: border-color 0.15s, background 0.15s;
        }
        .switch-item:hover {
            border-color: var(--primary);
        }
        .switch-item.switch-on {
            border-color: var(--primary);
            background: #f0f6fb;
        }
        .switch-track {
            position: relative;
            width: 42px;
            height: 24px;
            border-radius: 12px;
            background: #cbd5e1;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .switch-item.switch-on .switch-track {
            background: var(--primary);
        }
        .switch-thumb {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
            transition: transform 0.2s;
        }
        .switch-item.switch-on .switch-thumb {
            transform: translateX(18px);
        }
        .switch-info {
            flex: 1;
            min-width: 0;
        }
        .switch-name {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-main);
        }
        .switch-desc {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 1px;
        }
        .switch-status {
            font-size: 12px;
            font-weight: 600;
            flex-shrink: 0;
            padding: 2px 8px;
            border-radius: 10px;
        }
        .switch-item.switch-on .switch-status {
            color: var(--primary);
            background: #e0edf7;
        }
        .switch-item:not(.switch-on) .switch-status {
            color: #94a3b8;
            background: #f1f3fb;
        }
        /* 主人列表行 */
        .owner-row {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .owner-row .form-control {
            flex: 1;
            min-width: 120px;
        }

        /* 移动端适配 */
        .mobile-header {
            display: none;
            padding: 12px 16px;
            background: white;
            border-bottom: 1px solid var(--border);
            align-items: center;
            justify-content: space-between;
        }

        .menu-toggle {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--text-main);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.2s;
                z-index: 200;
                box-shadow: 2px 0 12px rgba(0,0,0,0.1);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .mobile-header {
                display: flex;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            .container {
                padding: 20px 16px;
            }
            .top-bar {
                display: none;
            }
            .bots-grid {
                grid-template-columns: 1fr;
            }
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

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            background: var(--success);
        }

        .notification.error {
            background: var(--danger);
        }
    </style>
    <link rel="stylesheet" href="admin-common.css">

</head>
<body>
<?php include '_nav.php'; ?>
    <main class="main-content">
            <div class="top-bar">
                <div class="page-title">机器人管理</div>
                <div class="top-actions">
                    <button class="btn btn-secondary btn-sm" id="refreshTopBtn"><i class="fas fa-sync-alt"></i> 刷新</button>
                    <button class="btn btn-primary btn-sm" id="addTopBtn"><i class="fas fa-plus"></i> 添加</button>
                </div>
            </div>

            <div class="container">
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-label">机器人总数</div><div class="stat-value" id="statTotal">0</div><div class="stat-hint">已接入</div></div>
                    <div class="stat-card"><div class="stat-label">今日群聊</div><div class="stat-value" id="statGroup">0</div><div class="stat-hint">聚合统计</div></div>
                    <div class="stat-card"><div class="stat-label">今日私聊</div><div class="stat-value" id="statPrivate">0</div><div class="stat-hint">聚合统计</div></div>
                    <div class="stat-card"><div class="stat-label">今日加群</div><div class="stat-value" id="statJoin">0</div><div class="stat-hint">聚合统计</div></div>
                </div>

                <div class="section-card">
                    <div class="section-header">
                        <div>
                            <div class="section-title">机器人列表</div>
                            <div class="section-sub">每个机器人独立管理插件和日志</div>
                        </div>
                        <button class="btn btn-secondary" id="refreshListBtn"><i class="fas fa-sync-alt"></i> 刷新</button>
                    </div>
                    <div class="bots-grid" id="botsGrid">
                        <div class="empty-state">加载中...</div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- 添加机器人模态框 -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>添加机器人</h3>
                <button class="close-btn" data-close="addModal">&times;</button>
            </div>
            <form id="addForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label>AppID</label>
                        <input type="text" class="form-control" id="appid" required placeholder="请输入机器人 AppID">
                    </div>
                    <div class="form-group">
                        <label>Secret</label>
                        <input type="text" class="form-control" id="secret" required placeholder="请输入机器人 Secret">
                    </div>
                    <div class="form-group">
                        <label>QQ</label>
                        <input type="text" class="form-control" id="qq_number" required placeholder="请输入机器人 QQ 号">
                    </div>
                    <div class="form-group">
                        <label>环境</label>
                        <select class="form-select" id="environment">
                            <option value="正式">正式环境</option>
                            <option value="沙箱">沙箱环境</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-close="addModal">取消</button>
                    <button type="submit" class="btn btn-primary">添加</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 通用选择机器人弹窗（聊天记录/机器人设置/日志/插件） -->
    <div class="modal" id="botPickerModal">
        <div class="modal-content" style="max-width:460px;">
            <div class="modal-header">
                <h3 id="botPickerTitle"><i class="fas fa-robot"></i> 选择机器人</h3>
                <button class="close-btn" data-close="botPickerModal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="botPickerList" style="display:grid;gap:8px;max-height:52vh;overflow:auto;"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-close="botPickerModal">取消</button>
            </div>
        </div>
    </div>

    <div class="modal" id="confirmModal">
        <div class="modal-content" style="max-width:380px;">
            <div class="modal-header">
                <h3>确认删除</h3>
                <button class="close-btn" data-close="confirmModal">&times;</button>
            </div>
            <div class="modal-body" style="text-align:center;">
                <i class="fas fa-exclamation-triangle" style="font-size:32px; color:#ff6b6b; margin-bottom:12px; display:block;"></i>
                <p style="margin-bottom:8px;">确定删除该机器人吗？</p>
                <p style="font-size:12px; color:#9aa3c0;">此操作不可恢复</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-close="confirmModal">取消</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">确认删除</button>
            </div>
        </div>
    </div>

    <div id="notification" class="notification"></div>

    <!-- 机器人设置模态框：开关 + 主人 -->
    <div class="modal" id="settingsModal">
        <div class="modal-content" style="max-width:560px;">
            <div class="modal-header">
                <h3><i class="fas fa-sliders-h"></i> 机器人设置</h3>
                <button class="close-btn" data-close="settingsModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group"><label>机器人 AppID</label><input type="text" class="form-control" id="setAppid" readonly></div>
                <div class="form-group">
                    <label>主人（可从日志中遍历选择，或手动输入 openid，支持多人）</label>
                    <div id="ownerList"></div>
                    <button class="btn btn-secondary btn-sm" id="addOwnerBtn" type="button" style="margin-top:8px;"><i class="fas fa-plus"></i> 添加主人</button>
                    <select class="form-select" id="ownerSelect" style="margin-top:8px;">
                        <option value="">— 从日志选择主人（添加到列表） —</option>
                    </select>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:6px;">群聊/私聊用户从该机器人的日志中自动汇总，按最近活跃排序。</div>
                </div>
                <div class="form-group">
                    <label>功能开关</label>
                    <div id="switchList"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-close="settingsModal">取消</button>
                <button class="btn btn-primary" id="saveOwnerBtn"><i class="fas fa-user-cog"></i> 保存主人</button>
                <button class="btn btn-primary" id="saveSettingsBtn"><i class="fas fa-save"></i> 保存开关</button>
            </div>
        </div>
    </div>

    <script>
        let currentBots = [];
        let deleteTargetAppid = null;

        function showMsg(text, isSuccess) {
            const el = document.getElementById('notification');
            el.textContent = text;
            el.className = 'notification ' + (isSuccess ? 'success' : 'error') + ' show';
            setTimeout(() => el.classList.remove('show'), 2500);
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        document.querySelectorAll('[data-close]').forEach(btn => {
            btn.addEventListener('click', () => closeModal(btn.dataset.close));
        });
        
        window.addEventListener('click', e => {
            if (e.target.classList.contains('modal')) e.target.style.display = 'none';
        });



        function openBotPicker(page, icon, title) {
            const box = document.getElementById('botPickerList');
            const titleEl = document.getElementById('botPickerTitle');
            if (!box || !titleEl) return;
            titleEl.innerHTML = '<i class="fas ' + icon + '"></i> ' + escapeHtml(title);
            if (!currentBots.length) {
                box.innerHTML = '<div class="empty-state">暂无机器人可选</div>';
            } else {
                box.innerHTML = currentBots.map(bot => `
                    <a href="${page}.php?appid=${encodeURIComponent(bot.appid)}" class="bot-card" style="display:flex;align-items:center;gap:10px;padding:10px 12px;text-decoration:none;color:inherit;">
                        <img src="${escapeHtml(bot.avatar) || 'https://via.placeholder.com/38'}" style="width:38px;height:38px;border-radius:50%;object-fit:cover;" onerror="this.src='https://via.placeholder.com/38'">
                        <div style="min-width:0;flex:1;">
                            <div style="font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(bot.name || '未命名机器人')}</div>
                            <div style="font-size:12px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(bot.appid || '')}</div>
                        </div>
                        <i class="fas fa-chevron-right" style="color:#9aa3c0;"></i>
                    </a>
                `).join('');
            }
            document.getElementById('botPickerModal').style.display = 'flex';
        }

        async function fetchBots() {
            try {
                const res = await fetch('api/info.php?type=list');
                const deletedRaw = res.headers.get('X-Deleted-Bots') || '';
                const deleted = deletedRaw.split(',').map(s => s.trim()).filter(Boolean);
                const data = await res.json();
                currentBots = Array.isArray(data) ? data : [];
                renderStats();
                renderBots();
                if (deleted.length) {
                    showMsg('机器人 ' + deleted.join('、') + ' 账号或密钥错误，已自动删除', false);
                }
            } catch (err) {
                document.getElementById('botsGrid').innerHTML = '<div class="empty-state">加载失败，请刷新重试</div>';
                showMsg('加载机器人列表失败', false);
            }
        }

        function renderStats() {
            let total = currentBots.length;
            let group = 0, privateChat = 0, join = 0;
            currentBots.forEach(b => {
                group += Number(b.data?.群聊 || 0);
                privateChat += Number(b.data?.私聊 || 0);
                join += Number(b.data?.加群 || 0);
            });
            document.getElementById('statTotal').textContent = total;
            document.getElementById('statGroup').textContent = group;
            document.getElementById('statPrivate').textContent = privateChat;
            document.getElementById('statJoin').textContent = join;
        }

        function renderBots() {
            const grid = document.getElementById('botsGrid');
            if (!currentBots.length) {
                grid.innerHTML = '<div class="empty-state">暂无机器人，点击“添加机器人”开始接入</div>';
                return;
            }
            grid.innerHTML = currentBots.map(bot => `
                <div class="bot-card" data-appid="${escapeHtml(bot.appid)}">
                    <div class="bot-header">
                        <img src="${escapeHtml(bot.avatar) || 'https://via.placeholder.com/44'}" class="bot-avatar" onerror="this.src='https://via.placeholder.com/44'">
                        <div class="bot-info">
                            <h4>${escapeHtml(bot.name || '未命名机器人')}</h4>
                            <p>${escapeHtml(bot.appid || '')}</p>
                        </div>
                    </div>
                    <div class="bot-meta">
                        <span class="env-badge ${(bot.type==='沙箱'||bot.type==='sandbox')?'sandbox':'prod'}">${escapeHtml(bot.type || '正式')}</span>
                    </div>
                    <div class="bot-stats">
                        <div class="stat-item"><div class="num">${bot.data?.群聊 || 0}</div><div class="label">群聊</div></div>
                        <div class="stat-item"><div class="num">${bot.data?.私聊 || 0}</div><div class="label">私聊</div></div>
                        <div class="stat-item"><div class="num">${bot.data?.加群 || 0}</div><div class="label">加群</div></div>
                    </div>
                    <div class="bot-actions">
                        <a href="bot_set.php?appid=${encodeURIComponent(bot.appid)}" class="btn btn-secondary btn-sm bot-settings"><i class="fas fa-sliders-h"></i> 设置</a>
                        <a href="plugin.php?appid=${encodeURIComponent(bot.appid)}" class="btn btn-secondary btn-sm"><i class="fas fa-puzzle-piece"></i> 插件</a>
                        <a href="log.php?appid=${encodeURIComponent(bot.appid)}" class="btn btn-secondary btn-sm"><i class="fas fa-chart-line"></i> 日志</a>
                        <a href="chat.php?appid=${encodeURIComponent(bot.appid)}" class="btn btn-secondary btn-sm"><i class="fas fa-comments"></i> 聊天</a>
                        <button class="btn btn-danger btn-sm delete-bot" data-appid="${escapeHtml(bot.appid)}"><i class="fas fa-trash"></i> 删除</button>
                    </div>
                </div>
            `).join('');

            // 设置按钮已改为独立页面 bot_set.php，无需绑定弹窗事件
            // 绑定删除按钮事件
            document.querySelectorAll('.delete-bot').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    deleteTargetAppid = btn.dataset.appid;
                    document.getElementById('confirmModal').style.display = 'flex';
                });
            });
        }

        // 确认删除
        document.getElementById('confirmDeleteBtn').addEventListener('click', async () => {
            if (!deleteTargetAppid) return;
            try {
                const res = await fetch(`api/bot.php?type=del&appid=${encodeURIComponent(deleteTargetAppid)}`);
                const data = await res.json();
                if (data.code === 200) {
                    showMsg('删除成功', true);
                    closeModal('confirmModal');
                    fetchBots();
                } else {
                    showMsg(data.msg || '删除失败', false);
                }
            } catch (err) {
                showMsg('网络错误', false);
            }
            deleteTargetAppid = null;
        });

        /* ---------- 机器人设置（开关 + 主人） ---------- */
        const SETTING_SWITCHES = [
            ['群非艾特', '接收群内非艾特消息'],
            ['排除机器人', '忽略所有机器人账号消息'],
            ['自动去艾特', '去掉消息中的艾特标记'],
            ['处理自己消息', '处理机器人自己发出的消息'],
            ['仅群主可用', '仅群主可触发插件'],
            ['屏蔽其他机器人', '忽略其他机器人消息（不影响自己）'],
            ['自动去开头艾特', '非艾特消息自动去掉开头艾特机器人+空格'],
        ];
        let settingsAppid = '';
        let settingsUsers = [];
        let settingsOwners = [];

        function renderSwitchList(settings) {
            const box = document.getElementById('switchList');
            // 显式读取 true/false；缺失项按框架默认值（与 云雀_默认设置() 一致）
            const DEFAULTS = {
                '群非艾特': true, '排除机器人': true, '自动去艾特': true,
                '处理自己消息': false, '仅群主可用': false,
                '屏蔽其他机器人': false, '自动去开头艾特': true
            };
            box.innerHTML = SETTING_SWITCHES.map(([key, desc]) => {
                const on = settings[key] === undefined ? (DEFAULTS[key] !== false) : (settings[key] === true);
                return `<label class="switch-item ${on ? 'switch-on' : ''}" data-key="${key}">
                    <span class="switch-track"><span class="switch-thumb"></span></span>
                    <span class="switch-info">
                        <span class="switch-name">${escapeHtml(key)}</span>
                        <span class="switch-desc">${escapeHtml(desc)}</span>
                    </span>
                    <span class="switch-status">${on ? '已开启' : '已关闭'}</span>
                    <input type="checkbox" data-key="${key}" ${on ? 'checked' : ''} style="display:none;">
                </label>`;
            }).join('');
            // 点击切换
            box.querySelectorAll('.switch-item').forEach(item => {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    const cb = item.querySelector('input[type=checkbox]');
                    cb.checked = !cb.checked;
                    item.classList.toggle('switch-on', cb.checked);
                    item.querySelector('.switch-status').textContent = cb.checked ? '已开启' : '已关闭';
                });
            });
        }

        function renderOwnerList() {
            const box = document.getElementById('ownerList');
            box.innerHTML = '';
            if (!settingsOwners.length) {
                box.innerHTML = '<div style="font-size:12px;color:var(--text-muted);padding:8px 0;">暂无主人，点击下方按钮添加</div>';
                return;
            }
            settingsOwners.forEach((o, i) => {
                const row = document.createElement('div');
                row.className = 'owner-row';
                row.innerHTML = `
                    <input type="text" class="form-control owner-openid" placeholder="主人 openid" value="${escapeHtml(o.id || '')}" style="flex:2;min-width:160px;">
                    <input type="text" class="form-control owner-name" placeholder="名称（选填）" value="${escapeHtml(o.name || '')}" style="flex:1;min-width:100px;">
                    <button class="btn btn-danger btn-sm owner-del" type="button"><i class="fas fa-trash"></i> 删除</button>`;
                row.querySelector('.owner-del').addEventListener('click', () => {
                    settingsOwners.splice(i, 1);
                    renderOwnerList();
                });
                box.appendChild(row);
            });
        }

        async function openSettingsModal(appid) {
            settingsAppid = appid;
            settingsOwners = [];
            document.getElementById('setAppid').value = appid;
            const ownerSelect = document.getElementById('ownerSelect');
            ownerSelect.innerHTML = '<option value="">— 从日志选择主人（添加到列表） —</option>';
            ownerSelect.disabled = true;

            // 读取当前机器人配置（含默认开关、当前主人）
            let settings = {};
            try {
                const res = await fetch('api/bot.php?type=list');
                const data = await res.json();
                const bot = (data.list || []).find(b => b.appid === appid);
                if (bot) {
                    settings = bot.settings || {};
                    const owner = bot.主人;
                    if (Array.isArray(owner)) {
                        settingsOwners = owner.filter(o => o && (o.id || o.name)).map(o => ({
                            id: o.id || '', name: o.name || '',
                            qq_number: o.qq_number || '', remark: o.remark || ''
                        }));
                    } else if (owner && (owner.id || owner.name)) {
                        settingsOwners = [{
                            id: owner.id || '', name: owner.name || '',
                            qq_number: owner.qq_number || '', remark: owner.remark || ''
                        }];
                    }
                }
            } catch (e) {}
            renderSwitchList(settings);
            renderOwnerList();

            // 从日志加载用户列表（用于选主人）
            try {
                const u = await fetch(`api/bot.php?type=users&appid=${encodeURIComponent(appid)}`);
                const ud = await u.json();
                settingsUsers = (ud.code === 200 && Array.isArray(ud.users)) ? ud.users : [];
                ownerSelect.innerHTML = '<option value="">— 从日志选择主人（添加到列表） —</option>' +
                    settingsUsers.map(x => `<option value="${escapeHtml(x.id)}">${escapeHtml(x.username || x.id)}${x.id !== (x.username || '') ? ' · ' + escapeHtml(x.id.slice(-6)) : ''} · ${escapeHtml(x.last_time || '')}</option>`).join('');
                ownerSelect.disabled = !settingsUsers.length;
            } catch (e) { ownerSelect.disabled = true; }

            document.getElementById('settingsModal').style.display = 'flex';
        }

        document.getElementById('addOwnerBtn').addEventListener('click', () => {
            settingsOwners.push({ id: '', name: '' });
            renderOwnerList();
        });

        document.getElementById('ownerSelect').addEventListener('change', (e) => {
            const v = e.target.value;
            if (!v) return;
            const user = settingsUsers.find(x => x.id === v);
            settingsOwners.push({ id: v, name: (user && user.username) || '' });
            renderOwnerList();
            e.target.value = '';
        });

        function collectOwners() {
            const rows = document.querySelectorAll('#ownerList .owner-openid');
            const names = document.querySelectorAll('#ownerList .owner-name');
            const list = [];
            rows.forEach((r, i) => {
                const id = r.value.trim();
                if (!id) return;
                list.push({ id, name: (names[i] || {}).value ? names[i].value.trim() : '' });
            });
            return list;
        }

        document.getElementById('saveOwnerBtn').addEventListener('click', async () => {
            if (!settingsAppid) return;
            const owners = collectOwners();
            const params = new URLSearchParams();
            params.set('type', 'update');
            params.set('appid', settingsAppid);
            params.set('主人', JSON.stringify(owners));
            try {
                const res = await fetch('api/bot.php?' + params.toString());
                const data = await res.json();
                if (data.code === 200) { showMsg('主人已保存', true); closeModal('settingsModal'); fetchBots(); }
                else showMsg(data.msg || '保存失败', false);
            } catch (err) { showMsg('保存失败', false); }
        });

        document.getElementById('saveSettingsBtn').addEventListener('click', async () => {
            if (!settingsAppid) return;
            const settings = {};
            document.querySelectorAll('#switchList input[type=checkbox]').forEach(cb => {
                settings[cb.dataset.key] = cb.checked;
            });
            const params = new URLSearchParams();
            params.set('type', 'update');
            params.set('appid', settingsAppid);
            params.set('settings', JSON.stringify(settings));
            try {
                const res = await fetch('api/bot.php?' + params.toString());
                const data = await res.json();
                if (data.code === 200) { showMsg('开关已保存', true); closeModal('settingsModal'); fetchBots(); }
                else showMsg(data.msg || '保存失败', false);
            } catch (err) { showMsg('保存失败', false); }
        });

        // 添加机器人
        document.getElementById('addForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const appid = document.getElementById('appid').value.trim();
            const secret = document.getElementById('secret').value.trim();
            const qq_number = document.getElementById('qq_number').value.trim();
            const env = document.getElementById('environment').value;
            if (!appid || !secret) {
                showMsg('请填写完整信息', false);
                return;
            }
            try {
                const res = await fetch(`api/bot.php?type=add&appid=${encodeURIComponent(appid)}&secret=${encodeURIComponent(secret)}&qq_number=${encodeURIComponent(qq_number)}&environment=${encodeURIComponent(env)}`);
                const data = await res.json();
                if (data.code === 200) {
                    showMsg('添加成功', true);
                    closeModal('addModal');
                    document.getElementById('addForm').reset();
                    fetchBots();
                } else {
                    showMsg(data.msg || '添加失败', false);
                }
            } catch (err) {
                showMsg('网络错误', false);
            }
        });

        // 打开添加模态框
        document.getElementById('addTopBtn').addEventListener('click', () => document.getElementById('addModal').style.display = 'flex');
        document.getElementById('refreshListBtn').addEventListener('click', fetchBots);
        document.getElementById('refreshTopBtn').addEventListener('click', fetchBots);

        // 通过侧边栏链接的 hash 打开对应弹窗（#add / #chat / #bot_set / #log / #plugin）
        function handleHash() {
            const h = location.hash;
            if (h === '#add') {
                document.getElementById('addModal').style.display = 'flex';
            } else if (h === '#chat') {
                openBotPicker('chat', 'fa-comments', '选择机器人查看聊天记录');
            } else if (h === '#bot_set') {
                openBotPicker('bot_set', 'fa-sliders-h', '选择机器人进行设置');
            } else if (h === '#log') {
                openBotPicker('log', 'fa-list-alt', '选择机器人查看日志');
            } else if (h === '#plugin') {
                openBotPicker('plugin', 'fa-puzzle-piece', '选择机器人管理插件');
            }
            // 消费掉 hash，使再次点击同一侧边栏链接仍能触发 hashchange
            if (h) {
                history.replaceState(null, '', location.pathname + location.search);
            }
        }
        // 已在总览页时，点击侧边栏 hash 链接不会刷新页面，需监听 hashchange
        window.addEventListener('hashchange', handleHash);

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        // #add 弹窗不依赖机器人列表，立即打开；选机器人类弹窗等列表加载完再打开
        if (location.hash === '#add') handleHash();
        fetchBots().then(() => { if (location.hash) handleHash(); });
    </script>
</body>
</html>