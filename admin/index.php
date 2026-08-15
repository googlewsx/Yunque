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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #5b6cff 0%, #8f9aff 50%, #a8b2ff 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'PingFang SC', 'Microsoft YaHei', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(31,36,55,.25), 0 2px 8px rgba(31,36,55,.08);
            overflow: hidden;
        }
        .login-header {
            padding: 36px 32px 24px;
            text-align: center;
            background: linear-gradient(135deg, #f5f6ff, #eef0ff);
        }
        .login-header .logo {
            width: 56px; height: 56px;
            margin: 0 auto 14px;
            border-radius: 16px;
            background: linear-gradient(135deg, #5b6cff, #8f9aff);
            display: flex; align-items: center; justify-content: center;
            font-size: 26px; color: #fff;
            box-shadow: 0 6px 18px rgba(91,108,255,.35);
        }
        .login-header h1 { font-size: 22px; font-weight: 700; color: #1f2437; margin-bottom: 4px; }
        .login-header p { font-size: 13px; color: #9aa3c0; }
        .login-body { padding: 28px 32px 24px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 12.5px; font-weight: 600; color: #6b7396; margin-bottom: 7px; }
        .form-control {
            width: 100%; padding: 11px 14px; font-size: 14px;
            border: 1.5px solid #e4e9f4; border-radius: 12px;
            background: #f7f8fd; transition: all 0.15s ease; font-family: inherit;
        }
        .form-control:focus { outline: none; border-color: #5b6cff; background: #fff; box-shadow: 0 0 0 4px rgba(91,108,255,.1); }
        .form-control::placeholder { color: #9aa3c0; }
        .btn {
            width: 100%; padding: 11px 16px; font-size: 14px; font-weight: 600;
            border-radius: 12px; cursor: pointer; transition: all 0.15s ease;
            font-family: inherit; border: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #5b6cff, #8f9aff);
            color: #fff; box-shadow: 0 4px 14px rgba(91,108,255,.35);
        }
        .btn-primary:hover { filter: brightness(1.05); transform: translateY(-1px); }
        .btn-primary:active { transform: translateY(0); }
        .btn-secondary { background: #f1f3fb; color: #6b7396; border: 1.5px solid #e4e9f4; }
        .btn-secondary:hover { background: #e8ebf8; }
        .btn-group { display: flex; gap: 12px; margin-top: 24px; }
        .btn-group .btn { width: auto; flex: 1; }
        .message { margin-top: 16px; padding: 10px 14px; border-radius: 10px; font-size: 13px; display: none; }
        .message.error { display: block; background: #fff0f0; color: #ff6b6b; border: 1px solid #ffd9d9; }
        .message.success { display: block; background: #e8f8ef; color: #1f9d55; border: 1px solid #c8ecd8; }
        .login-footer { padding: 16px 32px 24px; font-size: 11.5px; color: #9aa3c0; text-align: center; }
    </style>

</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="logo"><i class="fas fa-robot"></i></div>
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