<?php
if (!isset($_COOKIE['admin_token'])) {
    header("Location: index.php");
    exit();
}
$active_page = 'set';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>账号设置</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin-common.css">
    <style>
        .container{max-width:960px}
        .actions{display:flex;gap:12px;margin-top:8px;flex-wrap:wrap}
        .message{
            margin-bottom:16px;padding:10px 12px;border-radius:8px;
            font-size:13px;display:none;
        }
        .message.error{display:block;background:var(--danger-light);color:var(--danger);border:1px solid #ffd9d9}
        .message.success{display:block;background:var(--success-light);color:#1fa65a;border:1px solid #c8ecd8}
        .tips{background:#f3f5fc;border-radius:10px;padding:16px}
        .tip{margin-bottom:12px}
        .tip:last-child{margin-bottom:0}
        .tip strong{font-size:13px;color:var(--text-main);display:block;margin-bottom:4px}
        .tip p{font-size:12px;color:var(--text-muted);line-height:1.6}
    </style>
</head>
<body>
<?php include '_nav.php'; ?>
    <main class="main-content">
        <div class="top-bar">
            <div class="top-bar-left">
                <a href="main.php" class="back-link"><i class="fas fa-arrow-left"></i> 返回后台</a>
                <span class="page-title">账号设置</span>
            </div>
        </div>
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2>管理员信息</h2>
                        <p>修改后台登录使用的账号和密码</p>
                    </div>
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
                    <div>
                        <h2>说明</h2>
                        <p>避免把自己锁在后台外面</p>
                    </div>
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
</script>
</body>
</html>
