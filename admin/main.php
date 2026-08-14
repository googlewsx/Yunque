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
    <title>管理后台</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e9edf2;
            --text-main: #1a2c3e;
            --text-sub: #5e6f8d;
            --text-muted: #8b9ab0;
            --primary: #2c6b9e;
            --primary-hover: #235b87;
            --danger: #c23d2e;
            --danger-hover: #a83426;
            --success: #2c6e2c;
            --sidebar-width: 240px;
            --header-height: 52px;
        }

        body {
            background: var(--bg);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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
            padding: 16px 0;
        }

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

        .nav-item:hover {
            background: #f1f5f9;
            color: var(--primary);
        }

        .nav-item.active {
            background: #f1f5f9;
            color: var(--primary);
            border-left: 3px solid var(--primary);
            padding-left: 21px;
        }

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
            border-radius: 10px;
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
            border-radius: 6px;
            cursor: pointer;
            border: none;
            font-family: inherit;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: var(--text-sub);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: #e9edf2;
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
            border-radius: 10px;
            padding: 16px;
            background: white;
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
            background: #f8fafc;
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
            padding: 2px 8px;
            border-radius: 20px;
            background: #f1f5f9;
            color: var(--text-sub);
        }

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
            max-width: 480px;
            max-height: 90vh;
            overflow: auto;
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

        /* iOS 风开关 */
        .switch-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-bottom: 8px;
            background: rgba(255,255,255,.7);
            cursor: pointer;
            transition: background .15s;
        }
        .switch-row:hover { background: rgba(255,255,255,.9); }
        .switch-info { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .switch-title { display: block; font-size: 14px; font-weight: 600; color: var(--text-main); }
        .switch-desc { display: block; font-size: 12px; color: var(--text-muted); margin-top: 2px; line-height: 1.4; word-break: break-word; }
        .switch-toggle {
            position: relative;
            display: inline-block;
            width: 46px;
            height: 26px;
            flex-shrink: 0;
        }
        .switch-toggle input { opacity: 0; width: 0; height: 0; position: absolute; -webkit-appearance: none; appearance: none; }
        .switch-slider {
            position: absolute;
            top: 0; right: 0; bottom: 0; left: 0;
            background: #d1d5db;
            border-radius: 26px;
            transition: background .2s;
            cursor: pointer;
            z-index: 1;
        }
        .switch-slider::before {
            content: '';
            position: absolute;
            left: 3px; top: 3px;
            width: 20px; height: 20px;
            background: #fff;
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0,0,0,.2);
            transition: transform .2s;
            z-index: 2;
        }
        .switch-toggle input:checked + .switch-slider { background: var(--primary); }
        .switch-toggle input:checked + .switch-slider::before { transform: translateX(20px); }
        .switch-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 10px;
            flex-shrink: 0;
            white-space: nowrap;
        }
        .switch-badge.on { background: #dcfce7; color: #16a34a; }
        .switch-badge.off { background: #f3f4f6; color: #9ca3af; }

        /* 主人列表 */
        .owner-row {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
            align-items: center;
        }
        .owner-row .form-control { flex: 1; min-width: 0; }

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

        /* 机器人设置内嵌面板 */
        .bot-settings-panel {
            display: none;
            border-top: 1px solid var(--border);
            margin-top: 12px;
            padding-top: 12px;
        }
        .bot-settings-panel.open {
            display: block;
        }
        .settings-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-main);
        }
        .settings-panel-time {
            font-size: 11px;
            font-weight: 400;
            color: var(--text-muted);
        }
        .settings-panel-actions {
            display: flex;
            gap: 10px;
            margin-top: 4px;
            flex-wrap: wrap;
        }
        .settings-panel-actions .btn { flex: 1; justify-content: center; }

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
.main-content{height:calc(100vh - 60px);overflow-y:auto !important;overflow-x:hidden !important;}
</style>

<style id="manual-scroll-fix2">
/* 二次强制：禁用系统滚动浮标（全页只允许指定容器滚动） */
html,body{height:100%;overflow:auto !important;overscroll-behavior:auto !important;scrollbar-width:none !important;scrollbar-color:transparent transparent !important;}
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


<style id="main-scroll-final-fix">
html,body{height:auto !important;min-height:100%;overflow-y:auto !important;overflow-x:hidden !important;}
body{position:relative !important;}
.main-content{
  height:auto !important;
  min-height:calc(100vh - 60px) !important;
  overflow:visible !important;
  touch-action:pan-y !important;
}
/* 隐藏滚动条但保留滚动 */
html,body,.main-content{scrollbar-width:none !important;-ms-overflow-style:none !important;}
html::-webkit-scrollbar,body::-webkit-scrollbar,.main-content::-webkit-scrollbar{width:0 !important;height:0 !important;display:none !important;}
</style>

</head>
<body>
    <div class="mobile-header">
        <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
        <span style="font-weight:500;">QQ机器人框架</span>
        <div></div>
    </div>

    <div class="desktop-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h1>QQ机器人框架</h1>
                <p>机器人管理后台</p>
            </div>
            <nav class="sidebar-nav">
                <a href="#" class="nav-item active">
                    <i class="fas fa-tachometer-alt"></i> 总览
                </a>
                <a href="#" class="nav-item" id="navAddBot">
                    <i class="fas fa-plus-circle"></i> 添加机器人
                </a>
                <a href="set.php" class="nav-item">
                    <i class="fas fa-user-cog"></i> 账号设置
                </a>
                <a href="#" class="nav-item" id="navChatLogs">
                    <i class="fas fa-comments"></i> 聊天记录
                </a>
                <a href="custom_api.php" class="nav-item">
                    <i class="fas fa-plug"></i> 自定义API
                </a>
                <a href="doc.php" class="nav-item">
                    <i class="fas fa-file-alt"></i> 开发文档
                </a>
                <a href="http://qwq.nki.pw/plugin/index.html" class="nav-item" target="_blank">
                    <i class="fas fa-puzzle-piece"></i> 插件商城
                </a>
            </nav>
            <div class="sidebar-footer">
                保留 1.0 原有逻辑 · 简洁商务版
            </div>
        </aside>

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

    <!-- 删除确认模态框 -->


    <div class="modal" id="chatSelectModal">
        <div class="modal-content" style="max-width:460px;">
            <div class="modal-header">
                <h3><i class="fas fa-comments"></i> 选择机器人查看聊天记录</h3>
                <button class="close-btn" data-close="chatSelectModal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="chatBotList" style="display:grid;gap:8px;max-height:52vh;overflow:auto;"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-close="chatSelectModal">取消</button>
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
                <i class="fas fa-exclamation-triangle" style="font-size:32px; color:#c23d2e; margin-bottom:12px; display:block;"></i>
                <p style="margin-bottom:8px;">确定删除该机器人吗？</p>
                <p style="font-size:12px; color:#8b9ab0;">此操作不可恢复</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-close="confirmModal">取消</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">确认删除</button>
            </div>
        </div>
    </div>

    <div id="notification" class="notification"></div>

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



        function openChatSelectModal() {
            const box = document.getElementById('chatBotList');
            if (!box) return;
            if (!currentBots.length) {
                box.innerHTML = '<div class="empty-state">暂无机器人可选</div>';
            } else {
                box.innerHTML = currentBots.map(bot => `
                    <a href="chat.php?appid=${encodeURIComponent(bot.appid)}" class="bot-card" style="display:flex;align-items:center;gap:10px;padding:10px 12px;text-decoration:none;color:inherit;">
                        <img src="${escapeHtml(bot.avatar) || 'https://via.placeholder.com/38'}" style="width:38px;height:38px;border-radius:50%;object-fit:cover;" onerror="this.src='https://via.placeholder.com/38'">
                        <div style="min-width:0;flex:1;">
                            <div style="font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(bot.name || '未命名机器人')}</div>
                            <div style="font-size:12px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(bot.appid || '')}</div>
                        </div>
                        <i class="fas fa-chevron-right" style="color:#8b93c3;"></i>
                    </a>
                `).join('');
            }
            document.getElementById('chatSelectModal').style.display = 'flex';
        }

        async function fetchBots() {
            try {
                const res = await fetch('api/info.php?type=list&_t=' + Date.now(), {cache: 'no-store'});
                const data = await res.json();
                currentBots = Array.isArray(data) ? data : [];
                renderStats();
                renderBots();
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
                        <span class="env-badge">${escapeHtml(bot.type || '正式')}</span>
                    </div>
                    <div class="bot-stats">
                        <div class="stat-item"><div class="num">${bot.data?.群聊 || 0}</div><div class="label">群聊</div></div>
                        <div class="stat-item"><div class="num">${bot.data?.私聊 || 0}</div><div class="label">私聊</div></div>
                        <div class="stat-item"><div class="num">${bot.data?.加群 || 0}</div><div class="label">加群</div></div>
                    </div>
                    <div class="bot-actions">
                        <button class="btn btn-secondary btn-sm bot-settings" data-appid="${escapeHtml(bot.appid)}"><i class="fas fa-sliders-h"></i> 设置</button>
                        <a href="plugin.php?appid=${encodeURIComponent(bot.appid)}" class="btn btn-secondary btn-sm"><i class="fas fa-puzzle-piece"></i> 插件</a>
                        <a href="log.php?appid=${encodeURIComponent(bot.appid)}" class="btn btn-secondary btn-sm"><i class="fas fa-chart-line"></i> 日志</a>
                        <a href="chat.php?appid=${encodeURIComponent(bot.appid)}" class="btn btn-secondary btn-sm"><i class="fas fa-comments"></i> 聊天</a>
                        <button class="btn btn-danger btn-sm delete-bot" data-appid="${escapeHtml(bot.appid)}"><i class="fas fa-trash"></i> 删除</button>
                    </div>
                    <div class="bot-settings-panel" id="settingsPanel_${escapeHtml(bot.appid)}">
                        <div class="settings-panel-head">
                            <span><i class="fas fa-sliders-h"></i> 机器人设置</span>
                            <span class="settings-panel-time"></span>
                        </div>
                        <div class="form-group"><label>机器人 AppID</label><input type="text" class="form-control settings-appid" readonly value="${escapeHtml(bot.appid)}"></div>
                        <div class="form-group">
                            <label>主人（可从日志中遍历选择，或手动输入 openid，支持多人）</label>
                            <div class="owner-list"></div>
                            <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">
                                <button class="btn btn-secondary btn-sm add-owner-btn" type="button" style="flex-shrink:0;"><i class="fas fa-plus"></i> 添加主人</button>
                                <select class="form-select owner-select" style="flex:1;min-width:160px;">
                                    <option value="">— 从日志选择主人（添加到列表） —</option>
                                </select>
                            </div>
                            <div style="font-size:11px;color:var(--text-muted);margin-top:6px;">群聊/私聊用户从该机器人的日志中自动汇总，按最近活跃排序。</div>
                        </div>
                        <div class="form-group">
                            <label>功能开关</label>
                            <div class="switch-list"></div>
                        </div>
                        <div class="settings-panel-actions">
                            <button class="btn btn-primary btn-sm save-owner-btn" type="button"><i class="fas fa-user-cog"></i> 保存主人</button>
                            <button class="btn btn-primary btn-sm save-settings-btn" type="button"><i class="fas fa-save"></i> 保存开关</button>
                            <button class="btn btn-secondary btn-sm settings-refresh-btn" type="button"><i class="fas fa-sync-alt"></i> 刷新</button>
                        </div>
                    </div>
                </div>
            `).join('');

            // 绑定设置按钮事件
            document.querySelectorAll('.bot-settings').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    toggleSettingsPanel(btn.dataset.appid);
                });
            });
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
                const res = await fetch(`api/bot.php?type=del&appid=${encodeURIComponent(deleteTargetAppid)}`, {cache: 'no-store'});
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

        /* ---------- 机器人设置（内嵌面板：实时读取 + 实时刷新） ---------- */
        const SETTING_SWITCHES = [
            ['群非艾特', '接收群内非艾特消息'],
            ['排除机器人', '忽略所有机器人账号消息'],
            ['自动去艾特', '去掉消息中的艾特标记'],
            ['处理自己消息', '处理机器人自己发出的消息'],
            ['仅群主可用', '仅群主可触发插件'],
            ['屏蔽其他机器人', '忽略其他机器人消息（不影响自己）'],
            ['自动去开头艾特', '自动去掉开头艾特机器人（不影响艾特其他人）'],
        ];
        const settingsTimers = {};

        function stopSettingsTimer(appid) {
            if (settingsTimers[appid]) {
                clearInterval(settingsTimers[appid]);
                delete settingsTimers[appid];
            }
        }

        function renderSwitchList(panel, settings) {
            const box = panel.querySelector('.switch-list');
            const defaults = {};
            SETTING_SWITCHES.forEach(([key]) => defaults[key] = true);
            defaults['处理自己消息'] = false;
            defaults['仅群主可用'] = false;
            defaults['屏蔽其他机器人'] = false;
            const merged = Object.assign({}, defaults, settings || {});
            box.innerHTML = SETTING_SWITCHES.map(([key, desc]) => {
                const on = merged[key] === true;
                return `<label class="switch-row">
                    <span class="switch-info">
                        <span class="switch-title">${escapeHtml(key)}</span>
                        <span class="switch-desc">${escapeHtml(desc)}</span>
                    </span>
                    <span class="switch-badge ${on ? 'on' : 'off'}">${on ? '已开启' : '已关闭'}</span>
                    <span class="switch-toggle">
                        <input type="checkbox" data-key="${key}" ${on ? 'checked' : ''}>
                        <span class="switch-slider"></span>
                    </span>
                </label>`;
            }).join('');
        }

        function renderOwnerList(panel) {
            const box = panel.querySelector('.owner-list');
            const owners = panel._owners || [];
            if (!owners.length) {
                box.innerHTML = '<div style="font-size:13px;color:var(--text-muted);padding:8px 0;">暂无主人，点击下方按钮添加</div>';
                return;
            }
            box.innerHTML = '';
            owners.forEach((o) => {
                const row = document.createElement('div');
                row.className = 'owner-row';
                row.innerHTML = `
                    <input type="hidden" class="owner-remark" value="${escapeHtml(o.remark || '')}">
                    <input type="text" class="form-control owner-openid" placeholder="主人 openid / QQ" value="${escapeHtml(o.id || o.qq_number || '')}">
                    <input type="text" class="form-control owner-name" placeholder="名称（选填）" value="${escapeHtml(o.name || '')}">
                    <button class="btn btn-secondary btn-sm owner-del" type="button">移除</button>`;
                box.appendChild(row);
            });
        }

        function collectOwners(panel) {
            const rows = panel.querySelectorAll('.owner-list .owner-openid');
            const names = panel.querySelectorAll('.owner-list .owner-name');
            const remarks = panel.querySelectorAll('.owner-list .owner-remark');
            const list = [];
            rows.forEach((r, i) => {
                const val = r.value.trim();
                if (!val) return;
                const name = (names[i] || {}).value ? names[i].value.trim() : '';
                const remark = (remarks[i] || {}).value ? remarks[i].value.trim() : '';
                if (/^\d{5,12}$/.test(val)) {
                    list.push({ id: '', qq_number: val, name: name, remark: remark });
                } else {
                    list.push({ id: val, qq_number: '', name: name, remark: remark });
                }
            });
            return list;
        }

        // 实时读取：拉取该机器人的最新配置并渲染面板
        async function loadSettingsPanel(panel, appid) {
            let settings = {};
            try {
                const res = await fetch('api/bot.php?type=list&_t=' + Date.now(), {cache: 'no-store'});
                const data = await res.json();
                const bot = (data.list || []).find(b => b.appid === appid);
                if (bot) {
                    settings = bot.settings || {};
                    const owner = bot.主人;
                    if (Array.isArray(owner)) {
                        panel._owners = owner.filter(o => o && (o.id || o.qq_number || o.name));
                    } else if (owner && (owner.id || owner.qq_number)) {
                        panel._owners = [{ id: owner.id || '', qq_number: owner.qq_number || '', name: owner.name || '', remark: owner.remark || '' }];
                    } else {
                        panel._owners = [];
                    }
                }
            } catch (e) {}
            renderSwitchList(panel, settings);
            const editing = panel.contains(document.activeElement) && document.activeElement.matches('.owner-openid, .owner-name');
            if (!editing) renderOwnerList(panel);

            const ownerSelect = panel.querySelector('.owner-select');
            try {
                const u = await fetch(`api/bot.php?type=users&appid=${encodeURIComponent(appid)}&_t=${Date.now()}`, {cache: 'no-store'});
                const ud = await u.json();
                panel._users = (ud.code === 200 && Array.isArray(ud.users)) ? ud.users : [];
                ownerSelect.innerHTML = '<option value="">— 从日志选择主人（添加到列表） —</option>' +
                    panel._users.map(x => `<option value="${escapeHtml(x.id)}">${escapeHtml(x.username || x.id)}${x.id !== (x.username || '') ? ' · ' + escapeHtml(x.id.slice(-6)) : ''} · ${escapeHtml(x.last_time || '')}</option>`).join('');
                ownerSelect.disabled = !panel._users.length;
            } catch (e) { ownerSelect.disabled = true; }

            const timeEl = panel.querySelector('.settings-panel-time');
            if (timeEl) timeEl.textContent = '最近刷新：' + new Date().toLocaleTimeString();
        }

        // 切换内嵌设置面板的展开/收起
        async function toggleSettingsPanel(appid) {
            const panel = document.getElementById('settingsPanel_' + appid);
            if (!panel) return;
            if (panel.style.display === 'block') {
                panel.style.display = 'none';
                stopSettingsTimer(appid);
                return;
            }
            document.querySelectorAll('.bot-settings-panel').forEach(p => {
                if (p !== panel && p.style.display === 'block') {
                    p.style.display = 'none';
                    stopSettingsTimer(p.id.replace('settingsPanel_', ''));
                }
            });
            panel.style.display = 'block';
            panel._owners = [];
            panel._users = [];
            await loadSettingsPanel(panel, appid);
            stopSettingsTimer(appid);
            settingsTimers[appid] = setInterval(() => {
                if (panel.style.display !== 'block' || !document.body.contains(panel)) {
                    stopSettingsTimer(appid);
                    return;
                }
                loadSettingsPanel(panel, appid);
            }, 3000);
        }

        async function saveOwnerPanel(panel) {
            const appid = panel.querySelector('.settings-appid').value.trim();
            const owners = collectOwners(panel);
            const params = new URLSearchParams();
            params.set('type', 'update');
            params.set('appid', appid);
            params.set('主人', JSON.stringify(owners));
            try {
                const res = await fetch('api/bot.php?' + params.toString(), {cache: 'no-store'});
                const data = await res.json();
                if (data.code === 200) { showMsg('主人已保存', true); loadSettingsPanel(panel, appid); }
                else showMsg(data.msg || '保存失败', false);
            } catch (err) { showMsg('保存失败', false); }
        }

        async function saveSettingsPanel(panel) {
            const appid = panel.querySelector('.settings-appid').value.trim();
            const settings = {};
            panel.querySelectorAll('.switch-list input[type=checkbox]').forEach(cb => {
                settings[cb.dataset.key] = cb.checked;
            });
            const params = new URLSearchParams();
            params.set('type', 'update');
            params.set('appid', appid);
            params.set('settings', JSON.stringify(settings));
            try {
                const res = await fetch('api/bot.php?' + params.toString(), {cache: 'no-store'});
                const data = await res.json();
                if (data.code === 200) { showMsg('开关已保存', true); loadSettingsPanel(panel, appid); }
                else showMsg(data.msg || '保存失败', false);
            } catch (err) { showMsg('保存失败', false); }
        }

        // 内嵌面板事件：委托到面板区域
        document.addEventListener('click', (e) => {
            const panel = e.target.closest('.bot-settings-panel');
            if (!panel) return;
            if (e.target.closest('.add-owner-btn')) {
                panel._owners.push({ id: '', qq_number: '', name: '', remark: '' });
                renderOwnerList(panel);
            } else if (e.target.closest('.owner-del')) {
                const rows = Array.from(panel.querySelectorAll('.owner-list .owner-row'));
                const idx = rows.indexOf(e.target.closest('.owner-row'));
                if (idx >= 0) { panel._owners.splice(idx, 1); renderOwnerList(panel); }
            } else if (e.target.closest('.save-owner-btn')) {
                saveOwnerPanel(panel);
            } else if (e.target.closest('.save-settings-btn')) {
                saveSettingsPanel(panel);
            } else if (e.target.closest('.settings-refresh-btn')) {
                loadSettingsPanel(panel, panel.querySelector('.settings-appid').value.trim());
            }
        });

        document.addEventListener('change', (e) => {
            const panel = e.target.closest('.bot-settings-panel');
            if (!panel) return;
            if (e.target.matches('.owner-select')) {
                const v = e.target.value;
                if (!v) return;
                const user = (panel._users || []).find(x => x.id === v);
                panel._owners.push({ id: v, qq_number: '', name: (user && user.username) || '', remark: '' });
                renderOwnerList(panel);
                e.target.value = '';
            } else if (e.target.matches('.switch-toggle input')) {
                const row = e.target.closest('.switch-row');
                const badge = row.querySelector('.switch-badge');
                if (e.target.checked) {
                    badge.textContent = '已开启';
                    badge.className = 'switch-badge on';
                } else {
                    badge.textContent = '已关闭';
                    badge.className = 'switch-badge off';
                }
                saveSettingsPanel(panel);
            } else if (e.target.matches('.owner-openid') || e.target.matches('.owner-name')) {
                clearTimeout(panel._saveTimer);
                panel._saveTimer = setTimeout(() => saveOwnerPanel(panel), 400);
            }
        });

        // 添加机器人
        document.getElementById('addForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const appid = document.getElementById('appid').value.trim();
            const secret = document.getElementById('secret').value.trim();
            const env = document.getElementById('environment').value;
            if (!appid || !secret) {
                showMsg('请填写完整信息', false);
                return;
            }
            try {
                const res = await fetch(`api/bot.php?type=add&appid=${encodeURIComponent(appid)}&secret=${encodeURIComponent(secret)}&environment=${encodeURIComponent(env)}`, {cache: 'no-store'});
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
        document.getElementById('navAddBot').addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById('addModal').style.display = 'flex';
        });
        const navChatLogs = document.getElementById('navChatLogs');
        if (navChatLogs) {
            navChatLogs.addEventListener('click', (e) => {
                e.preventDefault();
                openChatSelectModal();
            });
        }
        document.getElementById('refreshListBtn').addEventListener('click', fetchBots);
        document.getElementById('refreshTopBtn').addEventListener('click', fetchBots);

        // 移动端侧边栏
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        if (menuToggle) {
            menuToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('open');
                }
            });
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        fetchBots();
    </script>
</body>
</html>