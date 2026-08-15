<?php
if (!isset($_COOKIE['admin_token'])) {
    header("Location: index.php");
    exit();
}
$appid = isset($_GET['appid']) ? trim($_GET['appid']) : '';
$active_page = 'bot_set';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>机器人设置</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin-common.css">
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
            --primary-light: #eef0ff;
            --danger: #ff6b6b;
            --danger-light: #fef0ee;
            --success: #1f9d57;
            --success-light: #e8f7ef;
            --warning: #d98324;
            --warning-light: #fdf4e6;
            --sidebar-width: 230px;
            --header-height: 56px;
            --radius: 12px;
            --shadow-sm: 0 1px 3px rgba(26,44,62,.06);
            --shadow-md: 0 4px 16px rgba(26,44,62,.08);
        }
        body {
            background: var(--bg);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'PingFang SC', 'Microsoft YaHei', sans-serif;
            color: var(--text-main);
            line-height: 1.55;
        }
        .desktop-layout { display: flex; min-height: 100vh; }
        /* ---------- 侧边栏 ---------- */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--card);
            border-right: 1px solid var(--border);
            position: fixed; top: 0; bottom: 0; left: 0;
            display: flex; flex-direction: column;
            z-index: 50;
        }
        .sidebar-header { padding: 22px 24px 18px; border-bottom: 1px solid var(--border); }
        .sidebar-header h1 { font-size: 17px; font-weight: 700; letter-spacing: .3px; }
        .sidebar-header p { font-size: 11.5px; color: var(--text-muted); margin-top: 4px; }
        .sidebar-nav { flex: 1; padding: 14px 12px; overflow-y: auto; }
        .nav-item {
            display: flex; align-items: center; gap: 11px;
            padding: 10px 14px; color: var(--text-sub);
            text-decoration: none; font-size: 13.5px; font-weight: 500;
            border-radius: 9px; margin-bottom: 2px; transition: all .15s;
        }
        .nav-item:hover { background: #f1f3fb; color: var(--primary); }
        .nav-item.active { background: var(--primary-light); color: var(--primary); }
        .nav-item i { width: 18px; font-size: 14px; text-align: center; }
        .sidebar-footer { padding: 14px 20px; border-top: 1px solid var(--border); font-size: 11px; color: var(--text-muted); }
        /* ---------- 主区 ---------- */
        .main-content { flex: 1; margin-left: var(--sidebar-width); min-height: 100vh; display: flex; flex-direction: column; }
        .top-bar {
            background: var(--card);
            border-bottom: 1px solid var(--border);
            padding: 0 28px; height: var(--header-height);
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 30;
        }
        .top-bar-left { display: flex; align-items: center; gap: 14px; }
        .back-link {
            display: inline-flex; align-items: center; gap: 6px;
            color: var(--text-sub); text-decoration: none; font-size: 13px;
            padding: 6px 12px; border-radius: 8px; transition: all .15s;
        }
        .back-link:hover { background: #f1f3fb; color: var(--primary); }
        .page-title { font-size: 15px; font-weight: 600; }
        .top-bar-right { display: flex; align-items: center; gap: 16px; }
        /* 同步状态 */
        .sync-status {
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 12px; color: var(--text-muted);
            padding: 5px 12px; background: #f1f3fb; border-radius: 20px;
        }
        .sync-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--success); position: relative;
        }
        .sync-dot.syncing { background: var(--warning); }
        .sync-dot.syncing::after {
            content: ''; position: absolute; inset: -3px;
            border-radius: 50%; border: 2px solid var(--warning);
            border-top-color: transparent; animation: spin .8s linear infinite;
        }
        .sync-dot.error { background: var(--danger); }
        @keyframes spin { to { transform: rotate(360deg); } }
        .sync-dot::before {
            content: ''; position: absolute; inset: 0; border-radius: 50%;
            background: inherit; opacity: .5; animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse { 0%,100%{transform:scale(1);opacity:.5} 50%{transform:scale(2.2);opacity:0} }
        /* ---------- 内容 ---------- */
        .container { padding: 24px 28px 100px; max-width: 920px; width: 100%; }
        /* 机器人头部卡片 */
        .bot-hero {
            background: linear-gradient(135deg, #5b6cff 0%, #8f9aff 100%);
            border-radius: var(--radius); padding: 22px 26px;
            color: #fff; display: flex; align-items: center; gap: 18px;
            margin-bottom: 22px; box-shadow: var(--shadow-md);
            position: relative; overflow: hidden;
        }
        .bot-hero::after {
            content: ''; position: absolute; right: -40px; top: -40px;
            width: 180px; height: 180px; border-radius: 50%;
            background: rgba(255,255,255,.08);
        }
        .bot-hero::before {
            content: ''; position: absolute; right: 60px; bottom: -60px;
            width: 140px; height: 140px; border-radius: 50%;
            background: rgba(255,255,255,.06);
        }
        .bot-hero-avatar {
            width: 60px; height: 60px; border-radius: 16px;
            object-fit: cover; border: 2px solid rgba(255,255,255,.3);
            background: rgba(255,255,255,.15); flex-shrink: 0;
            z-index: 1;
        }
        .bot-hero-info { z-index: 1; min-width: 0; }
        .bot-hero-name { font-size: 19px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .env-badge {
            font-size: 11px; padding: 2px 9px; border-radius: 20px;
            background: rgba(255,255,255,.22); font-weight: 500;
        }
        .bot-hero-id { font-size: 12.5px; opacity: .82; margin-top: 3px; font-family: ui-monospace, Consolas, monospace; }
        /* 外部变更提示条 */
        .change-banner {
            display: none; align-items: center; gap: 12px;
            background: var(--warning-light); border: 1px solid #f0d9a8;
            color: #8a5a14; padding: 12px 18px; border-radius: 10px;
            margin-bottom: 18px; font-size: 13px;
        }
        .change-banner.show { display: flex; }
        .change-banner i { font-size: 16px; }
        .change-banner .spacer { flex: 1; }
        .change-banner button {
            border: none; cursor: pointer; font-size: 12.5px; font-weight: 600;
            padding: 6px 14px; border-radius: 7px; font-family: inherit;
        }
        .btn-reload { background: var(--warning); color: #fff; }
        .btn-reload:hover { filter: brightness(.95); }
        .btn-keep { background: transparent; color: #8a5a14; border: 1px solid #e0c088 !important; }
        /* 不存在提示 */
        .gone-banner {
            display: none; align-items: center; gap: 12px;
            background: var(--danger-light); border: 1px solid #f5c6c0;
            color: #9c2f22; padding: 14px 18px; border-radius: 10px;
            margin-bottom: 18px; font-size: 13.5px;
        }
        .gone-banner.show { display: flex; }
        .gone-banner i { font-size: 18px; }
        /* 卡片 */
        .card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: var(--radius); margin-bottom: 18px;
            box-shadow: var(--shadow-sm); overflow: hidden;
        }
        .card-header {
            padding: 16px 22px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 10px;
        }
        .card-header .ci {
            width: 30px; height: 30px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; flex-shrink: 0;
        }
        .ci-blue { background: var(--primary-light); color: var(--primary); }
        .ci-green { background: var(--success-light); color: var(--success); }
        .ci-purple { background: #f0ecfb; color: #6b4fb8; }
        .ci-orange { background: var(--warning-light); color: var(--warning); }
        .card-header h2 { font-size: 15px; font-weight: 650; }
        .card-header p { font-size: 11.5px; color: var(--text-muted); margin-top: 1px; }
        .card-body { padding: 20px 22px; }
        /* 表单 */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
        .form-group { margin-bottom: 16px; }
        .form-group:last-child { margin-bottom: 0; }
        .form-group label {
            display: block; font-size: 12.5px; font-weight: 600;
            color: var(--text-sub); margin-bottom: 6px;
        }
        .form-control, .form-select {
            width: 100%; padding: 9px 13px; font-size: 13.5px;
            border: 1px solid var(--border); border-radius: 9px;
            font-family: inherit; background: #f7f8fd; transition: all .15s;
            color: var(--text-main);
        }
        .form-control:focus, .form-select:focus {
            outline: none; border-color: var(--primary);
            background: #fff; box-shadow: 0 0 0 3px rgba(91,108,255,.1);
        }
        .form-control[readonly] { background: #f1f3fb; color: var(--text-muted); cursor: not-allowed; }
        .input-with-btn { position: relative; }
        .input-with-btn .form-control { padding-right: 42px; }
        .toggle-secret {
            position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: var(--text-muted); padding: 6px; font-size: 14px;
        }
        .toggle-secret:hover { color: var(--primary); }
        .field-hint { font-size: 11.5px; color: var(--text-muted); margin-top: 5px; }
        /* 开关列表 */
        .switch-list { display: grid; gap: 8px; }
        .switch-item {
            display: flex; align-items: center; gap: 13px;
            padding: 12px 16px; border: 1px solid var(--border);
            border-radius: 10px; background: #f7f8fd; cursor: pointer;
            transition: all .15s;
        }
        .switch-item:hover { border-color: #c5cdf0; background: #fff; }
        .switch-item.switch-on { border-color: #b9c0f0; background: var(--primary-light); }
        .switch-track {
            position: relative; width: 44px; height: 25px; border-radius: 13px;
            background: #c4cfdd; transition: background .2s; flex-shrink: 0;
        }
        .switch-item.switch-on .switch-track { background: var(--primary); }
        .switch-thumb {
            position: absolute; top: 2.5px; left: 2.5px;
            width: 20px; height: 20px; border-radius: 50%;
            background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.22);
            transition: transform .2s;
        }
        .switch-item.switch-on .switch-thumb { transform: translateX(19px); }
        .switch-info { flex: 1; min-width: 0; }
        .switch-name { font-size: 13.5px; font-weight: 600; color: var(--text-main); }
        .switch-desc { font-size: 11.5px; color: var(--text-muted); margin-top: 1px; }
        .switch-status {
            font-size: 11.5px; font-weight: 600; padding: 3px 10px;
            border-radius: 20px; flex-shrink: 0;
        }
        .switch-item.switch-on .switch-status { color: var(--primary); background: rgba(91,108,255,.12); }
        .switch-item:not(.switch-on) .switch-status { color: #94a3b8; background: #eef1f6; }
        /* 主人 */
        .owner-row {
            display: flex; gap: 10px; margin-bottom: 10px; align-items: center;
        }
        .owner-row .form-control { flex: 1; min-width: 0; }
        .owner-row .owner-name { flex: 0 0 130px; }
        .owner-del {
            background: var(--danger-light); color: var(--danger);
            border: none; cursor: pointer; width: 36px; height: 36px;
            border-radius: 9px; font-size: 14px; flex-shrink: 0;
            transition: all .15s;
        }
        .owner-del:hover { background: var(--danger); color: #fff; }
        .owner-actions { display: flex; gap: 10px; align-items: center; margin-top: 12px; flex-wrap: wrap; }
        .owner-select { flex: 1; min-width: 200px; }
        .empty-owners {
            font-size: 12.5px; color: var(--text-muted);
            padding: 14px; text-align: center; background: #f1f3fb;
            border-radius: 9px; border: 1px dashed var(--border);
        }
        /* 按钮 */
        .btn {
            padding: 8px 18px; font-size: 13px; font-weight: 600;
            border-radius: 9px; cursor: pointer; border: none;
            font-family: inherit; display: inline-flex; align-items: center;
            gap: 7px; transition: all .15s;
        }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--brand2, #8f9aff)); color: #fff; box-shadow: 0 2px 8px rgba(91,108,255,.25); }
        .btn-primary:hover { filter: brightness(.95); }
        .btn-primary:disabled { opacity: .6; cursor: not-allowed; }
        .btn-secondary { background: #f1f3fb; color: var(--text-sub); border: 1px solid var(--border); }
        .btn-secondary:hover { background: #e4e9f4; }
        .btn-success { background: var(--success); color: #fff; }
        .btn-success:hover { filter: brightness(.95); }
        .btn-sm { padding: 6px 13px; font-size: 12px; }
        /* 底部保存栏 */
        .save-bar {
            position: fixed; bottom: 0; right: 0; left: var(--sidebar-width);
            background: rgba(255,255,255,.92); backdrop-filter: blur(10px);
            border-top: 1px solid var(--border);
            padding: 12px 28px; display: flex; align-items: center;
            justify-content: space-between; z-index: 40;
            transform: translateY(100%); transition: transform .25s;
        }
        .save-bar.show { transform: translateY(0); }
        .save-bar-info { font-size: 12.5px; color: var(--text-muted); display: flex; align-items: center; gap: 8px; }
        .save-bar-info .dirty-dot {
            width: 8px; height: 8px; border-radius: 50%; background: var(--warning);
        }
        .save-bar-actions { display: flex; gap: 10px; }
        /* 通知 */
        .notification {
            position: fixed; bottom: 80px; right: 28px;
            padding: 11px 18px; border-radius: 10px; font-size: 13px;
            background: var(--text-main); color: #fff; z-index: 1200;
            transform: translateX(130%); transition: transform .25s;
            box-shadow: var(--shadow-md); max-width: 320px;
        }
        .notification.show { transform: translateX(0); }
        .notification.success { background: var(--success); }
        .notification.error { background: var(--danger); }
        /* 移动端 */
        .mobile-header {
            display: none; padding: 12px 16px; background: #fff;
            border-bottom: 1px solid var(--border);
            align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 60;
        }
        .menu-toggle { background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text-main); }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform .25s; box-shadow: 2px 0 14px rgba(0,0,0,.15); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-header { display: flex; }
            .save-bar { left: 0; }
            .container { padding: 18px 14px 100px; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .owner-row .owner-name { flex: 0 0 100px; }
            .top-bar { padding: 0 14px; }
            .sync-text { display: none; }
            .bot-hero { padding: 18px; }
        }
    </style>
</head>
<body>
<?php include '_nav.php'; ?>
    <main class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <a href="main.php" class="back-link"><i class="fas fa-arrow-left"></i> 返回后台</a>
                    <span class="page-title">机器人设置</span>
                </div>
                <div class="top-bar-right">
                    <div class="sync-status" id="syncStatus" title="main.json 文件实时同步状态">
                        <span class="sync-dot" id="syncDot"></span>
                        <span class="sync-text" id="syncText">已同步</span>
                    </div>
                    <button class="btn btn-secondary btn-sm" id="manualRefresh"><i class="fas fa-sync-alt"></i> 刷新</button>
                </div>
            </div>
            <div class="container">
                <!-- 机器人头部 -->
                <div class="bot-hero">
                    <img class="bot-hero-avatar" id="botAvatar" src="" alt="" onerror="this.style.visibility='hidden'">
                    <div class="bot-hero-info">
                        <div class="bot-hero-name">
                            <span id="botName">加载中...</span>
                            <span class="env-badge" id="botEnv">正式</span>
                        </div>
                        <div class="bot-hero-id" id="botAppid"><?php echo htmlspecialchars($appid); ?></div>
                    </div>
                </div>
                <!-- 外部变更提示 -->
                <div class="change-banner" id="changeBanner">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>main.json 已被外部修改，当前页面数据可能不是最新的。</span>
                    <div class="spacer"></div>
                    <button class="btn-keep" id="keepMine">保留我的修改</button>
                    <button class="btn-reload" id="reloadExternal">加载最新配置</button>
                </div>
                <!-- 机器人已被删除 -->
                <div class="gone-banner" id="goneBanner">
                    <i class="fas fa-unlink"></i>
                    <span>该机器人已从 main.json 中移除，配置无法保存。<a href="main.php" style="color:#9c2f22;font-weight:600;margin-left:6px;">返回后台</a></span>
                </div>
                <!-- 基础信息 -->
                <div class="card">
                    <div class="card-header">
                        <span class="ci ci-blue"><i class="fas fa-key"></i></span>
                        <div>
                            <h2>基础信息</h2>
                            <p>机器人的凭证与运行环境</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>AppID</label>
                            <input type="text" class="form-control" id="fAppid" readonly>
                        </div>
                        <div class="form-group">
                            <label>Secret（机器人密钥）</label>
                            <div class="input-with-btn">
                                <input type="password" class="form-control" id="fSecret" placeholder="未设置" autocomplete="off">
                                <button type="button" class="toggle-secret" id="toggleSecret" title="显示/隐藏"><i class="fas fa-eye"></i></button>
                            </div>
                            <div class="field-hint">修改 Secret 后需保存，机器人将使用新密钥鉴权。</div>
                        </div>
                        <div class="form-row">
                            <div class="form-group" style="margin-bottom:0;">
                                <label>运行环境</label>
                                <select class="form-select" id="fEnv">
                                    <option value="正式">正式环境</option>
                                    <option value="沙箱">沙箱环境</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>QQ 号（不填聊天不显名与头）</label>
                                <input type="text" class="form-control" id="fQq" placeholder="建议必须填">
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label>备注（选填）</label>
                            <input type="text" class="form-control" id="fRemark" placeholder="例如：主号 / 测试号">
                        </div>
                    </div>
                </div>
                <!-- 功能开关 -->
                <div class="card">
                    <div class="card-header">
                        <span class="ci ci-green"><i class="fas fa-toggle-on"></i></span>
                        <div>
                            <h2>功能开关</h2>
                            <p>消息处理与过滤规则，实时对应 main.json 的 settings 字段</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="switch-list" id="switchList"></div>
                    </div>
                </div>
                <!-- 主人管理 -->
                <div class="card">
                    <div class="card-header">
                        <span class="ci ci-purple"><i class="fas fa-crown"></i></span>
                        <div>
                            <h2>主人管理</h2>
                            <p>拥有最高权限的用户，支持多人；可从日志中快速选择</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="ownerList"></div>
                        <div class="owner-actions">
                            <button class="btn btn-secondary btn-sm" id="addOwnerBtn" type="button"><i class="fas fa-plus"></i> 添加主人</button>
                            <select class="form-select owner-select" id="ownerSelect">
                                <option value="">— 从日志选择主人 —</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <!-- 底部保存栏 -->
            <div class="save-bar" id="saveBar">
                <div class="save-bar-info">
                    <span class="dirty-dot"></span>
                    <span id="saveBarText">有未保存的修改</span>
                </div>
                <div class="save-bar-actions">
                    <button class="btn btn-secondary" id="resetBtn"><i class="fas fa-undo"></i> 放弃修改</button>
                    <button class="btn btn-primary" id="saveBtn"><i class="fas fa-save"></i> 保存配置</button>
                </div>
            </div>
        </main>
    </div>
    <div class="notification" id="notification"></div>
    <script>
    const APPID = <?php echo json_encode($appid); ?>;
    /* ---------- 常量 ---------- */
    const SETTING_SWITCHES = [
        ['群非艾特', '接收群内非艾特消息'],
        ['排除机器人', '忽略所有机器人账号消息'],
        ['自动去艾特', '去掉消息中的艾特标记'],
        ['处理自己消息', '处理机器人自己发出的消息'],
        ['仅群主可用', '仅群主可触发插件'],
        ['屏蔽其他机器人', '忽略其他机器人消息（不影响自己）'],
        ['自动去开头艾特', '非艾特消息自动去掉开头艾特机器人+空格'],
    ];
    const DEFAULTS = {
        '群非艾特': true, '排除机器人': true, '自动去艾特': true,
        '处理自己消息': false, '仅群主可用': false,
        '屏蔽其他机器人': false, '自动去开头艾特': true
    };
    let state = {
        bot: null,           // 从 API 读到的当前机器人配置
        botExists: false,    // 机器人是否还在 main.json
        owners: [],          // 主人列表（编辑中）
        logUsers: [],        // 日志用户
        dirty: false,        // 是否有未保存修改
        saving: false,
        fileMtime: 0,        // main.json 修改时间戳（用于外部变更检测）
        pollTimer: null,
        hiddenEditing: false // 用户正在编辑时暂停覆盖
    };
    /* ---------- 工具 ---------- */
    function $(id) { return document.getElementById(id); }
    function esc(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }
    function toast(msg, type) {
        const el = $('notification');
        el.textContent = msg;
        el.className = 'notification ' + (type || '') + ' show';
        setTimeout(() => el.classList.remove('show'), 2600);
    }
    function setSync(status, text) {
        const dot = $('syncDot'), t = $('syncText');
        dot.className = 'sync-dot ' + status;
        if (text) t.textContent = text;
    }
    function fmtTime(d) {
        const p = n => String(n).padStart(2, '0');
        return p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
    }
    /* ---------- 渲染开关 ---------- */
    function renderSwitches(settings) {
        const box = $('switchList');
        box.innerHTML = SETTING_SWITCHES.map(([key, desc]) => {
            const on = settings[key] === undefined ? DEFAULTS[key] : (settings[key] === true);
            return `<label class="switch-item ${on ? 'switch-on' : ''}" data-key="${esc(key)}">
                <span class="switch-track"><span class="switch-thumb"></span></span>
                <span class="switch-info">
                    <span class="switch-name">${esc(key)}</span>
                    <span class="switch-desc">${esc(desc)}</span>
                </span>
                <span class="switch-status">${on ? '已开启' : '已关闭'}</span>
                <input type="checkbox" data-key="${esc(key)}" ${on ? 'checked' : ''} style="display:none;">
            </label>`;
        }).join('');
        box.querySelectorAll('.switch-item').forEach(item => {
            item.addEventListener('click', e => {
                e.preventDefault();
                const cb = item.querySelector('input[type=checkbox]');
                cb.checked = !cb.checked;
                item.classList.toggle('switch-on', cb.checked);
                item.querySelector('.switch-status').textContent = cb.checked ? '已开启' : '已关闭';
                markDirty();
            });
        });
    }
    /* ---------- 渲染主人 ---------- */
    function renderOwners() {
        const box = $('ownerList');
        if (!state.owners.length) {
            box.innerHTML = '<div class="empty-owners">暂无主人，点击下方按钮添加</div>';
            return;
        }
        box.innerHTML = state.owners.map((o, i) => `
            <div class="owner-row" data-idx="${i}">
                <input type="text" class="form-control owner-id" placeholder="主人 openid" value="${esc(o.id || '')}">
                <input type="text" class="form-control owner-name" placeholder="名称（选填）" value="${esc(o.name || '')}">
                <button class="owner-del" type="button" title="删除"><i class="fas fa-times"></i></button>
            </div>`).join('');
        box.querySelectorAll('.owner-row').forEach(row => {
            const i = parseInt(row.dataset.idx);
            row.querySelector('.owner-id').addEventListener('input', e => { state.owners[i].id = e.target.value; markDirty(); });
            row.querySelector('.owner-name').addEventListener('input', e => { state.owners[i].name = e.target.value; markDirty(); });
            row.querySelector('.owner-del').addEventListener('click', () => { state.owners.splice(i, 1); renderOwners(); markDirty(); });
        });
    }
    /* ---------- 收集表单 ---------- */
    function collectForm() {
        const settings = {};
        document.querySelectorAll('#switchList input[type=checkbox]').forEach(cb => {
            settings[cb.dataset.key] = cb.checked;
        });
        return {
            secret: $('fSecret').value,
            environment: $('fEnv').value,
            qq_number: $('fQq').value.trim(),
            remark: $('fRemark').value.trim(),
            settings: settings,
            owners: state.owners.filter(o => (o.id || '').trim() !== '').map(o => ({
                id: (o.id || '').trim(), name: (o.name || '').trim(),
                qq_number: (o.qq_number || '').trim(), remark: (o.remark || '').trim()
            }))
        };
    }
    /* ---------- 脏检查 ---------- */
    function markDirty() {
        state.dirty = true;
        $('saveBar').classList.add('show');
        $('saveBarText').textContent = '有未保存的修改';
    }
    function clearDirty() {
        state.dirty = false;
        $('saveBar').classList.remove('show');
    }
    function isDirtyCompared(bot) {
        // 比较当前表单与磁盘配置是否一致
        const cur = collectForm();
        const diskOwners = normalizeOwners(bot['主人']);
        if (cur.secret !== (bot.secret || '')) return true;
        if (cur.environment !== (bot.type || '正式')) return true;
        if (cur.qq_number !== (bot.qq_number || '')) return true;
        if (cur.remark !== (bot.remark || '')) return true;
        const diskSettings = bot.settings || DEFAULTS;
        for (const [k] of SETTING_SWITCHES) {
            const dv = DEFAULTS[k];
            const a = cur.settings[k] === undefined ? dv : cur.settings[k];
            const b = diskSettings[k] === undefined ? dv : diskSettings[k];
            if (a !== b) return true;
        }
        if (JSON.stringify(cur.owners) !== JSON.stringify(diskOwners)) return true;
        return false;
    }
    function normalizeOwners(owner) {
        if (!owner) return [];
        let list = [];
        if (Array.isArray(owner)) {
            list = owner;
        } else if (typeof owner === 'object') {
            list = (owner.id || owner.name) ? [owner] : [];
        }
        return list.filter(o => o && (o.id || o.name)).map(o => ({
            id: o.id || '', name: o.name || '',
            qq_number: o.qq_number || '', remark: o.remark || ''
        }));
    }
    /* ---------- 从 API 加载并填充 ---------- */
    async function loadFromServer(showSyncing) {
        if (showSyncing) setSync('syncing', '同步中...');
        try {
            const res = await fetch('api/bot.php?type=list&_t=' + Date.now());
            const data = await res.json();
            if (data.code !== 200) throw new Error(data.msg || '加载失败');
            const bot = (data.list || []).find(b => String(b.appid) === String(APPID));
            state.fileMtime = data.mtime || Date.now();
            if (!bot) {
                // 机器人已被删除
                state.botExists = false;
                state.bot = null;
                $('goneBanner').classList.add('show');
                $('saveBtn').disabled = true;
                setSync('error', '机器人不存在');
                return;
            }
            state.botExists = true;
            $('goneBanner').classList.remove('show');
            $('saveBtn').disabled = false;
            const oldBot = state.bot;
            state.bot = bot;
            // 填充头部
            $('botAppid').textContent = bot.appid;
            $('fAppid').value = bot.appid;
            $('botEnv').textContent = bot.type || '正式';
            // 填充基础字段（仅在用户未编辑时覆盖）
            if (!state.hiddenEditing) {
                if (document.activeElement !== $('fSecret')) $('fSecret').value = bot.secret || '';
                if (document.activeElement !== $('fQq')) $('fQq').value = bot.qq_number || '';
                if (document.activeElement !== $('fRemark')) $('fRemark').value = bot.remark || '';
                $('fEnv').value = bot.type || '正式';
            }
            // 开关与主人：若用户没有未保存修改，则直接刷新
            if (!state.dirty) {
                renderSwitches(bot.settings || {});
                state.owners = normalizeOwners(bot['主人']);
                renderOwners();
                clearDirty();
            } else if (oldBot) {
                // 有未保存修改：检测磁盘是否被外部改动
                const externalChanged = JSON.stringify({
                    secret: oldBot.secret, type: oldBot.type, qq_number: oldBot.qq_number,
                    remark: oldBot.remark, settings: oldBot.settings, 主人: oldBot['主人']
                }) !== JSON.stringify({
                    secret: bot.secret, type: bot.type, qq_number: bot.qq_number,
                    remark: bot.remark, settings: bot.settings, 主人: bot['主人']
                });
                if (externalChanged) {
                    $('changeBanner').classList.add('show');
                }
            }
            setSync('', '已同步 · ' + fmtTime(new Date()));
        } catch (err) {
            setSync('error', '同步失败');
        }
    }
    /* ---------- 加载机器人昵称头像 ---------- */
    async function loadBotInfo() {
        try {
            const res = await fetch('api/info.php?type=list&_t=' + Date.now());
            const list = await res.json();
            const info = Array.isArray(list) ? list.find(b => String(b.appid) === String(APPID)) : null;
            if (info) {
                $('botName').textContent = info.name || ('机器人 ' + APPID);
                if (info.avatar) {
                    $('botAvatar').src = info.avatar;
                    $('botAvatar').style.visibility = 'visible';
                }
            } else {
                $('botName').textContent = '机器人 ' + APPID;
            }
        } catch (e) {
            $('botName').textContent = '机器人 ' + APPID;
        }
    }
    /* ---------- 加载日志用户（选主人） ---------- */
    async function loadLogUsers() {
        const sel = $('ownerSelect');
        try {
            const res = await fetch('api/bot.php?type=users&appid=' + encodeURIComponent(APPID) + '&_t=' + Date.now());
            const data = await res.json();
            state.logUsers = (data.code === 200 && Array.isArray(data.users)) ? data.users : [];
            sel.innerHTML = '<option value="">— 从日志选择主人 —</option>' +
                state.logUsers.map(x => `<option value="${esc(x.id)}">${esc(x.username || x.id)} · ${esc((x.id || '').slice(-6))} · ${esc(x.last_time || '')}</option>`).join('');
            sel.disabled = !state.logUsers.length;
        } catch (e) { sel.disabled = true; }
    }
    /* ---------- 保存 ---------- */
    async function saveAll() {
        if (!state.botExists || state.saving) return;
        const form = collectForm();
        if (!form.secret) { toast('Secret 不能为空', 'error'); return; }
        state.saving = true;
        $('saveBtn').disabled = true;
        $('saveBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中...';
        try {
            const params = new URLSearchParams();
            params.set('type', 'update');
            params.set('appid', APPID);
            params.set('secret', form.secret);
            params.set('environment', form.environment);
            params.set('qq_number', form.qq_number);
            params.set('remark', form.remark);
            params.set('settings', JSON.stringify(form.settings));
            params.set('主人', JSON.stringify(form.owners));
            const res = await fetch('api/bot.php?' + params.toString());
            const data = await res.json();
            if (data.code === 200) {
                toast('配置已保存到 main.json', 'success');
                clearDirty();
                await loadFromServer(false);
            } else {
                toast(data.msg || '保存失败', 'error');
            }
        } catch (err) {
            toast('保存失败：' + err.message, 'error');
        } finally {
            state.saving = false;
            $('saveBtn').disabled = false;
            $('saveBtn').innerHTML = '<i class="fas fa-save"></i> 保存配置';
        }
    }
    /* ---------- 事件绑定 ---------- */
    $('fSecret').addEventListener('input', markDirty);
    $('fQq').addEventListener('input', markDirty);
    $('fRemark').addEventListener('input', markDirty);
    $('fEnv').addEventListener('change', markDirty);
    // 编辑时暂停覆盖
    ['fSecret','fQq','fRemark'].forEach(id => {
        $(id).addEventListener('focus', () => state.hiddenEditing = true);
        $(id).addEventListener('blur', () => { state.hiddenEditing = false; });
    });
    $('toggleSecret').addEventListener('click', () => {
        const inp = $('fSecret'), icon = $('toggleSecret').querySelector('i');
        if (inp.type === 'password') { inp.type = 'text'; icon.className = 'fas fa-eye-slash'; }
        else { inp.type = 'password'; icon.className = 'fas fa-eye'; }
    });
    $('addOwnerBtn').addEventListener('click', () => { state.owners.push({id:'',name:''}); renderOwners(); markDirty(); });
    $('ownerSelect').addEventListener('change', e => {
        const v = e.target.value; if (!v) return;
        const u = state.logUsers.find(x => x.id === v);
        state.owners.push({ id: v, name: (u && u.username) || '' });
        renderOwners(); markDirty(); e.target.value = '';
    });
    $('saveBtn').addEventListener('click', saveAll);
    $('resetBtn').addEventListener('click', () => {
        if (!state.bot) return;
        renderSwitches(state.bot.settings || {});
        $('fSecret').value = state.bot.secret || '';
        $('fQq').value = state.bot.qq_number || '';
        $('fRemark').value = state.bot.remark || '';
        $('fEnv').value = state.bot.type || '正式';
        state.owners = normalizeOwners(state.bot['主人']);
        renderOwners();
        $('changeBanner').classList.remove('show');
        clearDirty();
        toast('已还原为已保存配置', 'success');
    });
    $('reloadExternal').addEventListener('click', async () => {
        $('changeBanner').classList.remove('show');
        await loadFromServer(false);
        clearDirty();
        toast('已加载最新配置', 'success');
    });
    $('keepMine').addEventListener('click', () => {
        $('changeBanner').classList.remove('show');
        toast('将保留你的修改，记得保存', 'success');
    });
    $('manualRefresh').addEventListener('click', async () => {
        await Promise.all([loadFromServer(true), loadLogUsers()]);
    });
    // 离开页面前提醒
    window.addEventListener('beforeunload', e => {
        if (state.dirty) { e.preventDefault(); e.returnValue = ''; }
    });
    /* ---------- 轮询（与聊天记录页同样的实时读取机制） ---------- */
    function startPolling() {
        if (state.pollTimer) clearInterval(state.pollTimer);
        state.pollTimer = setInterval(() => {
            loadFromServer(false);
        }, 3000);
    }
    /* ---------- 初始化 ---------- */
    (async function init() {
        if (!APPID) {
            $('botName').textContent = '缺少 AppID 参数';
            toast('缺少 appid 参数', 'error');
            return;
        }
        await Promise.all([loadFromServer(true), loadBotInfo(), loadLogUsers()]);
        startPolling();
    })();
    </script>
</body>
</html>
