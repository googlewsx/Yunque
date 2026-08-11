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
    <title>账号设置</title>
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

        /* 布局 */
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
        .container { padding: 28px 32px; max-width: 1000px; }

        /* 卡片 */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .card-header { padding: 16px 20px; border-bottom: 1px solid var(--border); }
        .card-header h2 { font-size: 16px; font-weight: 600; color: var(--text-main); margin-bottom: 4px; }
        .card-header p { font-size: 12px; color: var(--text-muted); }
        .card-body { padding: 20px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; font-weight: 500; color: var(--text-sub); margin-bottom: 6px; }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            font-size: 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: inherit;
            background: var(--card);
        }
        .form-control:focus { outline: none; border-color: var(--primary); }

        .btn {
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 6px;
            cursor: pointer;
            border: none;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-secondary { background: #f1f5f9; color: var(--text-sub); border: 1px solid var(--border); }
        .btn-secondary:hover { background: #e9edf2; }

        .actions { display: flex; gap: 12px; margin-top: 8px; }
        .message {
            margin-bottom: 16px;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 13px;
            display: none;
        }
        .message.error { display: block; background: #fef2f0; color: #c23d2e; border: 1px solid #ffe0db; }
        .message.success { display: block; background: #eef6ec; color: #2c6e2c; border: 1px solid #d4e6d1; }

        .tips { background: #f8fafc; border-radius: 8px; padding: 16px; }
        .tip { margin-bottom: 12px; }
        .tip strong { font-size: 13px; color: var(--text-main); display: block; margin-bottom: 4px; }
        .tip p { font-size: 12px; color: var(--text-muted); line-height: 1.5; }

        /* 移动端 */
        .mobile-header {
            display: none;
            padding: 12px 16px;
            background: white;
            border-bottom: 1px solid var(--border);
            align-items: center;
            justify-content: space-between;
        }
        .menu-toggle { background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text-main); }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.2s; z-index: 200; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-header { display: flex; }
            .top-bar { display: none; }
            .container { padding: 20px 16px; }
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
                <a href="set.php" class="nav-item active"><i class="fas fa-user-cog"></i> 账号设置</a>
                <a href="doc.php" class="nav-item"><i class="fas fa-file-alt"></i> 开发文档</a>
                <a href="http://qwq.nki.pw/plugin/index.html" class="nav-item" target="_blank"><i class="fas fa-puzzle-piece"></i> 插件商城</a>
            </nav>
            <div class="sidebar-footer">保留 1.0 原有逻辑 · 简洁商务版</div>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <div class="page-title">账号设置</div>
                <a href="main.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> 返回后台</a>
            </div>

            <div class="container">
                <div class="card">
                    <div class="card-header">
                        <h2>管理员信息</h2>
                        <p>修改后台登录使用的账号和密码</p>
                    </div>
                    <div class="card-body">
                        <div id="message" class="message"></div>
                        <form id="settingsForm">
                            <input type="hidden" name="type" value="set">
                            <div class="form-group">
                                <label>管理员账号</label>
                                <input type="text" class="form-control" id="admin" name="admin" placeholder="请输入新的管理员账号" required>
                            </div>
                            <div class="form-group">
                                <label>管理员密码</label>
                                <input type="password" class="form-control" id="password" name="password" placeholder="请输入新的管理员密码" required>
                            </div>
                            <div class="actions">
                                <button type="button" id="resetBtn" class="btn btn-secondary">清空</button>
                                <button type="submit" id="submitBtn" class="btn btn-primary"><i class="fas fa-save"></i> 保存设置</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>说明</h2>
                        <p>避免把自己锁在后台外面</p>
                    </div>
                    <div class="card-body">
                        <div class="tips">
                            <div class="tip"><strong>保存后生效</strong><p>提交成功后，后续登录会使用新账号和新密码。</p></div>
                            <div class="tip"><strong>建议先记下来</strong><p>改密码前先把新凭据记好，免得改完自己忘了。</p></div>
                            <div class="tip"><strong>这是 1.0 真接口</strong><p>保存会直接调用 api/login.php 对配置生效。</p></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="notification" class="notification"></div>

    <script>
        const form = document.getElementById('settingsForm');
        const messageBox = document.getElementById('message');
        const submitBtn = document.getElementById('submitBtn');
        const resetBtn = document.getElementById('resetBtn');

        function showMsg(text, type) {
            messageBox.className = 'message ' + type;
            messageBox.textContent = text;
        }

        resetBtn.addEventListener('click', () => { form.reset(); messageBox.className = 'message'; });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            messageBox.className = 'message';
            const admin = document.getElementById('admin').value.trim();
            const password = document.getElementById('password').value.trim();
            if (!admin || !password) {
                showMsg('账号和密码不能为空', 'error');
                return;
            }
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中...';
            try {
                const formData = new FormData(form);
                const res = await fetch('api/login.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.code === 200) {
                    showMsg(data.msg || '保存成功', 'success');
                } else {
                    showMsg(data.msg || '保存失败', 'error');
                }
            } catch (err) {
                showMsg('请求失败：' + err.message, 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> 保存设置';
            }
        });

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
    </script>
</body>
</html>