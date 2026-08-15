<?php
// 尝试加载 Parsedown，不存在时使用内置简易解析
$parsedownPath = dirname(__DIR__) . '/function/Parsedown.php';
if (file_exists($parsedownPath)) {
    require_once $parsedownPath;
    $parsedown = new Parsedown();
    $parsedown->setMarkupEscaped(true);
    $parsedown->setBreaksEnabled(true);
} else {
    // 内置简易 Markdown 解析（兼容无 Parsedown 环境）
    $parsedown = null;
}
// 尝试读取开发文档，不存在时使用默认内容
$mdPath = dirname(__DIR__) . '/插件开发文档.md';
if (!file_exists($mdPath)) {
    $mdPath = dirname(__DIR__) . '/文档.md';
}
if (file_exists($mdPath)) {
    $markdown = file_get_contents($mdPath);
} else {
    $markdown = "# 插件开发文档\n\n文档文件未找到，请将 `插件开发文档.md` 放置于网站根目录。\n";
}
if ($parsedown) {
    $html = $parsedown->text($markdown);
} else {
    $html = '<pre style="white-space:pre-wrap;word-break:break-word;font-family:inherit;">' . htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8') . '</pre>';
}
$active_page = 'doc';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>开发文档</title>
    <link rel="stylesheet" href="assets/markdown.css">
    <link rel="stylesheet" href="assets/highlight/default.min.css">
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
            --code-bg: #f5f6fc;
            --sidebar-width: 240px;
            --header-height: 56px;
        }

        body {
            background: var(--bg);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'PingFang SC', 'Microsoft YaHei', sans-serif;
            color: var(--text-main);
            line-height: 1.6;
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
        .container { padding: 28px 32px; max-width: 1200px; }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px; box-shadow: 0 1px 3px rgba(31,36,55,.04);
            overflow: hidden;
        }
        .card-header { padding: 16px 20px; border-bottom: 1px solid var(--border); }
        .card-header h2 { font-size: 16px; font-weight: 600; color: var(--text-main); }
        .card-header p { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
        .card-body { padding: 24px; }

        .markdown-body {
            font-size: 14px;
            line-height: 1.7;
        }
        .markdown-body h1 { font-size: 24px; margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border); }
        .markdown-body h2 { font-size: 20px; margin: 20px 0 12px; padding-bottom: 6px; border-bottom: 1px solid var(--border); }
        .markdown-body h3 { font-size: 18px; margin: 18px 0 10px; }
        .markdown-body p { margin: 0 0 16px; color: var(--text-sub); }
        .markdown-body pre {
            background: #1f2437;
            color: #c8cef0;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px;
            overflow-x: auto;
            margin: 16px 0;
        }
        .markdown-body code {
            background: #f1f3fb;
            color: var(--primary);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 13px;
        }
        .markdown-body pre code { background: none; padding: 0; }
        .markdown-body table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
        }
        .markdown-body th, .markdown-body td {
            border: 1px solid var(--border);
            padding: 8px 12px;
            text-align: left;
        }
        .markdown-body th { background: #f1f3fb; font-weight: 600; color: var(--text-main); }
        .markdown-body ul, .markdown-body ol { margin: 0 0 16px 24px; }
        .markdown-body li { margin: 4px 0; }

        .btn {
            padding: 6px 14px;
            font-size: 13px;
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
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--brand2, #8f9aff)); color: #fff; box-shadow: 0 2px 8px rgba(91,108,255,.25); }
        .btn-primary:hover { filter: brightness(.95); }
        .btn-secondary { background: #f1f3fb; color: var(--text-sub); border: 1px solid var(--border); }
        .btn-secondary:hover { background: #e4e9f4; }

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
            .card-body { padding: 16px; }
            .markdown-body { font-size: 13px; }
            .markdown-body pre { font-size: 12px; }
        }
</style>

</head>
<body>
<?php include '_nav.php'; ?>
        <main class="main-content">
            <div class="top-bar">
                <div class="page-title">开发文档</div>
                <div>
                    <a href="main.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> 返回后台</a>
                    <?php if (file_exists($mdPath)): ?><a href="<?php echo htmlspecialchars(str_replace(dirname(__DIR__), '..', $mdPath), ENT_QUOTES); ?>" class="btn btn-primary" target="_blank"><i class="fas fa-external-link-alt"></i> 原文</a><?php endif; ?>
                </div>
            </div>

            <div class="container">
                <div class="card">
                    <div class="card-header">
                        <h2>文档内容</h2>
                        <p>支持代码高亮、表格滚动和移动端阅读</p>
                    </div>
                    <div class="card-body">
                        <div class="markdown-body">
                            <?= $html ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/highlight/highlight.min.js"></script>
    <script src="assets/highlight/php.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('pre code').forEach(el => hljs.highlightElement(el));
            document.querySelectorAll('.markdown-body table').forEach(table => {
                const wrapper = document.createElement('div');
                wrapper.style.overflowX = 'auto';
                wrapper.style.marginBottom = '16px';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            });

        });
    </script>
</body>
</html>