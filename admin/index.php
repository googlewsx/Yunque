<?php
if (isset($_COOKIE['admin_token'])) {
    header("Location: main.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>后台登录</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f7fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .login-card {
            width: 100%;
            max-width: 380px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.03);
            border: 1px solid #e9ecef;
        }

        .login-header {
            padding: 28px 28px 0;
            border-bottom: 1px solid #eef2f5;
        }

        .login-header h1 {
            font-size: 22px;
            font-weight: 600;
            color: #1a2634;
            letter-spacing: -0.2px;
            margin-bottom: 4px;
        }

        .login-header p {
            font-size: 13px;
            color: #6c7a8a;
            margin-bottom: 20px;
        }

        .login-body {
            padding: 24px 28px 32px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #2c3e44;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            font-size: 14px;
            border: 1px solid #dce3e9;
            border-radius: 8px;
            background: #ffffff;
            transition: all 0.15s ease;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #2c6b9e;
            box-shadow: 0 0 0 3px rgba(44, 107, 158, 0.08);
        }

        .form-control::placeholder {
            color: #9aaebf;
        }

        .btn {
            width: 100%;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 500;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s ease;
            font-family: inherit;
            border: none;
        }

        .btn-primary {
            background: #2c6b9e;
            color: white;
        }

        .btn-primary:hover {
            background: #235b87;
        }

        .btn-secondary {
            background: #f0f2f5;
            color: #4a5b6e;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e6e9ef;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 28px;
        }

        .btn-group .btn {
            width: auto;
            flex: 1;
        }

        .message {
            margin-top: 16px;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 13px;
            display: none;
        }

        .message.error {
            display: block;
            background: #fef2f0;
            color: #c23d2e;
            border: 1px solid #ffe0db;
        }

        .message.success {
            display: block;
            background: #eef6ec;
            color: #2c6e2c;
            border: 1px solid #d4e6d1;
        }

        .login-footer {
            padding: 16px 28px 24px;
            border-top: 1px solid #eef2f5;
            font-size: 12px;
            color: #8a99a8;
            text-align: center;
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
    <div class="login-card">
        <div class="login-header">
            <h1>QQ机器人框架</h1>
            <p>后台管理系统</p>
        </div>

        <div class="login-body">
            <form id="loginForm">
                <div class="form-group">
                    <label for="admin">管理员账号</label>
                    <input type="text" class="form-control" id="admin" name="admin" placeholder="请输入账号" autocomplete="username" required>
                </div>

                <div class="form-group">
                    <label for="password">密码</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="请输入密码" autocomplete="current-password" required>
                </div>

                <div id="message" class="message"></div>

                <div class="btn-group">
                    <button type="button" class="btn btn-secondary" id="resetBtn">清空</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">登录</button>
                </div>
            </form>
        </div>

        <div class="login-footer">
            保留 1.0 原有登录逻辑 · 界面已升级
        </div>
    </div>

    <script>
        const form = document.getElementById('loginForm');
        const adminInput = document.getElementById('admin');
        const passwordInput = document.getElementById('password');
        const messageBox = document.getElementById('message');
        const submitBtn = document.getElementById('submitBtn');
        const resetBtn = document.getElementById('resetBtn');

        function showMessage(text, type) {
            messageBox.className = 'message ' + type;
            messageBox.textContent = text;
        }

        function clearMessage() {
            messageBox.className = 'message';
            messageBox.textContent = '';
        }

        resetBtn.addEventListener('click', function () {
            form.reset();
            clearMessage();
            adminInput.focus();
        });

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            clearMessage();

            const admin = adminInput.value.trim();
            const password = passwordInput.value.trim();

            if (!admin || !password) {
                showMessage('请输入账号和密码', 'error');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = '登录中...';

            try {
                const formData = new FormData();
                formData.append('type', 'login');
                formData.append('admin', admin);
                formData.append('password', password);

                const response = await fetch('api/login.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.code === 200) {
                    const expires = new Date();
                    expires.setTime(expires.getTime() + 7 * 24 * 60 * 60 * 1000);
                    document.cookie = 'admin_token=' + encodeURIComponent(admin) + '; expires=' + expires.toUTCString() + '; path=/';
                    showMessage(data.msg || '登录成功，正在跳转...', 'success');
                    setTimeout(function () {
                        window.location.href = 'main.php';
                    }, 500);
                } else {
                    showMessage(data.msg || '账号或密码错误', 'error');
                }
            } catch (error) {
                showMessage('网络请求失败，请稍后重试', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = '登录';
            }
        });
    </script>
</body>
</html>