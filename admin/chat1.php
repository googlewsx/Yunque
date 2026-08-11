<?php
if (!isset($_COOKIE['admin_token'])) {
    header("Location: index.php");
    exit();
}

$appid = $_GET['appid'] ?? '';
if (empty($appid)) {
    die("缺少appid参数");
}

// 获取main.json中的appid信息
$mainFile = dirname(__DIR__) . "/main.json";
$mainData = [];
if (file_exists($mainFile)) {
    $mainContent = file_get_contents($mainFile);
    $mainData = json_decode($mainContent, true) ?? [];
}
$botInfo = $mainData[$appid] ?? [];
$botQQ = $botInfo['qq_number'] ?? $appid;

// 机器人信息将在JavaScript中异步获取（与main.php保持一致）
$botName = "102295278"; // 默认值
$botAvatar = "iIsT4gJwaEtYEubI0jSCwhSE0naOC1rh"; // 默认值
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>聊天记录 - TC管理后台</title>
    
    <!-- DNS预解析和预连接 -->
    <link rel="dns-prefetch" href="https://cdn.bootcdn.net">
    <link rel="preconnect" href="https://cdn.bootcdn.net" crossorigin>
    
    <!-- 关键CSS - 同步加载 -->
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- 非关键CSS - 异步加载 -->
    <link rel="preload" href="https://cdn.bootcdn.net/ajax/libs/bootstrap-icons/1.11.0/font/bootstrap-icons.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/bootstrap-icons/1.11.0/font/bootstrap-icons.css">
        <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    </noscript>
    <style>
        /* 关键CSS - 防止FOUC */
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-color: rgba(255, 255, 255, 0.15);
            --text-color: #2d3748;
            --shadow-color: rgba(102, 126, 234, 0.3);
            --danger-color: #f56565;
            --success-color: #48bb78;
            --warning-color: #ed8936;
            --highlight-color: #5a67d8;
            --light-bg: rgba(255, 255, 255, 0.9);
            --glass-bg: rgba(255, 255, 255, 0.25);
            --border-color: rgba(255, 255, 255, 0.3);
            --shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Microsoft YaHei", sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            height: 100vh;
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-attachment: fixed;
            color: var(--text-color);
            display: flex;
            flex-direction: column;
            /* 页面加载时的平滑过渡 */
            opacity: 0;
            animation: fadeIn 0.3s ease-in forwards;
        }
        
        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }

        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            z-index: 100;
            flex-shrink: 0;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .back-btn {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s;
            text-decoration: none;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .header-title {
            font-size: 18px;
            font-weight: 600;
            color: white;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            font-size: 14px;
        }

        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--success-color);
            margin-right: 6px;
        }

        .appid-badge {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .main-container {
            flex: 1;
            display: flex;
            overflow: hidden;
            padding: 1rem;
            gap: 1rem;
        }

        /* 左侧聊天列表 - 20%宽度 */
        .sidebar {
            flex: 0 0 20%;
            max-width: 20%;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        
        .sidebar .card {
            min-height: 0;
            display: flex;
            flex-direction: column;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
        }
        
        .sidebar .card-body {
            min-height: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .chat-list-header {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 0;
            flex-wrap: wrap; /* 允许换行 */
        }
        
        .chat-list-header > .bi-people {
            display: none; /* 只隐藏header直接子元素的图标，不影响按钮内的图标 */
        }
        
        .chat-type-buttons {
            display: flex !important;
            gap: 0 !important;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            width: 180px !important; /* 固定宽度，让按钮铺满 */
            margin: 0 !important;
            padding: 0 !important;
        }
        
        /* 覆盖Bootstrap btn-group的所有默认样式 */
        .chat-type-buttons.btn-group > .btn {
            font-size: 0.875rem;
            padding: 8px 0 !important;
            border: none !important;
            background: white !important;
            background-image: none !important;
            color: #666;
            font-weight: 500;
            transition: all 0.2s ease;
            position: relative;
            flex: 1 1 90px !important; /* 每个按钮90px */
            min-width: 90px !important;
            max-width: 90px !important;
            width: 90px !important;
            text-align: center;
            box-sizing: border-box;
            margin: 0 !important;
            border-radius: 0 !important;
        }
        
        .chat-type-buttons.btn-group > .btn:first-child {
            border-radius: 8px 0 0 8px !important;
        }
        
        .chat-type-buttons.btn-group > .btn:last-child {
            border-radius: 0 8px 8px 0 !important;
        }
        
        .chat-type-buttons.btn-group > .btn:hover:not(.active) {
            background: #f8f9fa !important;
            color: #333;
        }
        
        .chat-type-buttons.btn-group > .btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
            border: none !important;
            z-index: 1;
        }
        
        .chat-type-buttons .btn i {
            margin-right: 4px;
        }
        
        /* 优化下拉选择框 */
        .chat-list-header .form-select {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            padding: 8px 32px 8px 12px;
            font-size: 0.875rem;
            color: #333;
            background-color: white;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            flex: 1 0 100%; /* 占满一整行 */
            margin-top: 4px; /* 如果换行，添加上边距 */
        }
        
        .chat-list-header .form-select:hover {
            border-color: #667eea;
            box-shadow: 0 2px 6px rgba(102, 126, 234, 0.2);
        }
        
        .chat-list-header .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }
        
        /* 现代化搜索框样式 */
        .search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 4px 12px 4px 4px; /* 右侧增加padding，因为没有按钮了 */
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .search-wrapper:focus-within {
            background: white;
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }
        
        .search-icon {
            position: absolute;
            left: 16px;
            color: #999;
            font-size: 16px;
            transition: color 0.3s ease;
            z-index: 1;
        }
        
        .search-wrapper:focus-within .search-icon {
            color: #667eea;
        }
        
        .search-input-modern {
            flex: 1;
            border: none;
            background: transparent;
            padding: 10px 16px 10px 40px;
            font-size: 0.9rem;
            color: #333;
            outline: none;
            box-shadow: none !important;
            padding-right: 16px; /* 右侧也留空间 */
        }
        
        .search-input-modern::placeholder {
            color: #aaa;
        }
        
        .search-btn-modern {
            display: none; /* 隐藏搜索按钮 */
        }

        .chat-list {
            flex: 1;
            overflow-y: auto;
            min-height: 0;
        }
        
        .list-group-item {
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0.75rem;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            box-sizing: border-box;
            background: transparent;
        }
        
        .list-group-item:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }
        
        .list-group-item.active {
            background: rgba(255, 255, 255, 0.2);
            border-left: 4px solid white;
        }
        
        /* 卡片内容区域锁定宽度 */
        .list-group-item .d-flex {
            width: 100%;
            min-width: 0;
        }
        
        .list-group-item .flex-grow-1 {
            min-width: 0;
            overflow: hidden;
        }
        
        .list-group-item h6 {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin: 0;
        }
        
        .list-group-item small {
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-list::-webkit-scrollbar {
            width: 6px;
        }

        .chat-list::-webkit-scrollbar-track {
            background: #f5f5f5;
        }

        .chat-list::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 3px;
        }

        .avatar-container,
        .message-avatar-container {
            cursor: pointer;
            user-select: none;
            -webkit-user-select: none;
            position: relative;
        }
        
        .avatar-container:active,
        .message-avatar-container:active {
            transform: scale(0.95);
        }
        
        .message-avatar-container {
            transition: transform 0.2s ease;
        }
        
        .chat-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .chat-avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-color), var(--highlight-color));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        /* 右侧聊天窗口 - 80%宽度 */
        .chat-content {
            flex: 0 0 80%;
            max-width: 80%;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        
        .chat-content .card {
            min-height: 0;
            display: flex;
            flex-direction: column;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
        }
        
        .chat-content .card-body {
            min-height: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            padding: 0;
        }

        .chat-header {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            flex-shrink: 0;
        }

        .chat-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin: 0;
        }

        .chat-id-display {
            font-size: 0.875rem;
            color: #6c757d;
            margin: 0;
        }
        
        /* 新的漂亮按钮样式 */
        .chat-header-action-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
            width: 100%;
        }
        
        .chat-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 20px;
            border-radius: 12px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            color: white;
            flex: 1;
            height: 42px;
            white-space: nowrap;
        }
        
        .chat-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .chat-action-btn:active {
            transform: translateY(0);
        }
        
        .chat-action-btn i {
            font-size: 16px;
        }
        
        /* 返回列表按钮 - 粉色渐变 */
        .back-btn-style {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .back-btn-style:hover {
            background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
        }
        
        /* 复制ID按钮 - 紫色渐变 */
        .copy-btn-style {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .copy-btn-style:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        
        /* ID徽章样式 - 换行显示 */
        .chat-id-badge {
            display: block;
            width: 100%;
            padding: 8px 16px;
            margin-top: 10px;
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #666;
            box-shadow: 0 2px 6px rgba(252, 182, 159, 0.3);
            text-align: center;
        }
        
        /* 手机端适配 */
        @media (max-width: 768px) {
            .chat-action-btn {
                padding: 8px 10px;
                font-size: 12px;
                gap: 4px;
            }
            
            .chat-action-btn i {
                font-size: 14px;
            }
            
            .chat-id-badge {
                font-size: 11px;
                padding: 6px 10px;
                margin-top: 8px;
            }
        }
        
        /* PC端聊天header按钮样式 */
        .chat-header-btn {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 8px;
            background: #f5f5f5;
            border: none;
            cursor: pointer;
            color: #333;
            font-size: 14px;
            transition: all 0.2s ease;
            margin-right: 8px;
        }
        
        .chat-header-btn:hover {
            background: #e0e0e0;
        }
        
        .chat-header-btn:active {
            transform: scale(0.98);
        }
        
        .chat-header-btn i {
            font-size: 14px;
        }
        
        /* 当有聊天内容时显示按钮 */
        .chat-content:not(:has(.empty-state:only-child)) .chat-header-btn {
            display: flex;
        }

        /* 聊天头部按钮容器 */
        .chat-header-buttons {
            display: none;
            gap: 8px;
            margin-bottom: 10px;
        }

        .chat-content:not(:has(.empty-state:only-child)) .chat-header-buttons {
            display: flex;
        }

        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            min-height: 0;
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .messages-container::-webkit-scrollbar {
            width: 8px;
        }

        .messages-container::-webkit-scrollbar-track {
            background: transparent;
        }

        .messages-container::-webkit-scrollbar-thumb {
            background: var(--accent-color);
            border-radius: 4px;
        }

        .message-item {
            display: flex;
            gap: 12px;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message-item {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            align-items: flex-start;
        }

        .message-item.user {
            flex-direction: row;
        }

        .message-item.bot {
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            flex-shrink: 0;
            object-fit: cover;
        }
        
        .message-avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #6c757d;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        .message-main {
            display: flex;
            flex-direction: column;
            max-width: min(72%, 680px);
        }

        .message-bubble {
            max-width: 70%;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            box-sizing: border-box;
        }
        
        .message-item.user .message-bubble {
            margin-right: auto;
        }
        
        .message-item.bot .message-bubble {
            margin-left: auto;
        }
        
        /* 小屏幕设备：消息气泡和图片自适应 */
        @media (max-width: 768px) {
            .message-bubble {
                max-width: 85% !important;
            }
            
            .message-content img {
                max-width: 100% !important;
                width: auto !important;
                height: auto !important;
            }
        }

        .message-name {
            font-size: 0.75rem;
            color: #6c757d;
            padding: 0 0.5rem;
        }
        
        /* 机器人消息昵称右对齐 */
        .message-item.bot .message-name {
            text-align: right;
            align-self: flex-end;
            margin-right: 4px;
        }

        .message-content {
            padding: 0.5rem 1rem;
            border-radius: 1rem;
            word-wrap: break-word;
            word-break: break-word;
            white-space: pre-wrap;
            line-height: 1.5;
            font-size: 0.875rem;
            box-sizing: border-box;
            overflow-wrap: break-word;
            display: inline-block;
            width: fit-content;
            min-width: auto;
        }
        
        /* 图片在消息框内，不超出范围 */
        .message-content img {
            max-width: 100% !important;
            height: auto !important;
            border-radius: 8px;
            margin-top: 8px;
            object-fit: contain;
            display: block;
        }
        
        .message-item.user .message-content {
            background: rgba(255, 255, 255, 0.9);
            color: var(--text-color);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .message-item.bot .message-content {
            background: rgba(102, 126, 234, 0.2);
            color: white;
            padding: 0.5rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 1rem;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        /* 卡片消息保持原样式，不需要蓝色描边 */
        .message-item.bot .message-content.card {
            background: transparent;
            border: none;
            padding: 0;
        }
        
        /* 卡片内容保持原来的橙色描边样式 */
        .message-item.bot .message-content .card-content {
            background: #fff9e6;
            border: 1px solid #ffd700;
            border-radius: 8px;
            padding: 12px;
            margin-top: 4px;
        }

        .message-image {
            max-width: 300px;
            max-height: 400px;
            border-radius: 8px;
            margin-top: 8px;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .message-image:hover {
            transform: scale(1.02);
        }
        
        /* 语音和视频播放器样式 */
        .media-player {
            margin-top: 8px;
            padding: 8px;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .media-player i {
            color: #1976d2;
            font-size: 16px;
        }
        
        .media-player span {
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }
        
        .media-player audio,
        .media-player video {
            flex: 1;
            min-width: 200px;
        }
        
        .media-link {
            color: #1976d2;
            text-decoration: none;
            font-size: 12px;
            padding: 4px 8px;
            border: 1px solid #1976d2;
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .media-link:hover {
            background: #1976d2;
            color: #fff;
        }
        
        .voice-player {
            background: rgba(25, 118, 210, 0.1);
        }
        
        .video-player {
            background: rgba(156, 39, 176, 0.1);
        }
        
        .video-player i {
            color: #9c27b0;
        }

        .message-time {
            font-size: 0.75rem;
            color: #6c757d;
            padding: 0 0.5rem;
            margin-top: 0.25rem;
        }

        .event-message {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 8px 0;
            margin: 8px 0;
        }

        .event-bubble {
            background: var(--event-msg-bg);
            padding: 6px 16px;
            border-radius: 12px;
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .event-bubble.join {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .event-bubble.leave {
            background: #ffebee;
            color: #c62828;
        }

        .empty-state {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 14px;
        }

        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            color: var(--text-light);
        }

        .loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* 手机端切换按钮样式 */
        .mobile-tabs {
            display: none;
            gap: 8px;
            margin-left: auto;
        }

        .mobile-tab {
            padding: 8px 16px;
            border: none;
            border-radius: 20px;
            background: #f5f5f5;
            color: #666;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .mobile-tab.active {
            background: #1976d2;
            color: #fff;
        }

        .mobile-tab:active {
            transform: scale(0.95);
        }

        /* 手机端聊天列表容器 */
        .mobile-chat-list-container {
            display: none;
            flex-direction: column;
            height: 100%;
            width: 100%;
            max-width: 100%;
            background: #fff;
            overflow-x: hidden;
            box-sizing: border-box;
        }

        .mobile-chat-list-header {
            padding: 12px;
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            width: 100%;
            box-sizing: border-box;
        }

        .mobile-chat-list {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 8px;
            -webkit-overflow-scrolling: touch;
            width: 100%;
            box-sizing: border-box;
        }

        /* 手机端响应式设计 - 重写优化 */
        @media (max-width: 768px) {
            * {
                box-sizing: border-box;
            }
            
            body {
                padding: 0;
                overflow-x: hidden;
                width: 100%;
                max-width: 100%;
                height: 100vh;
                height: 100dvh; /* 动态视口高度，解决移动端浏览器底部显示问题 */
            }

            .header {
                height: auto;
                min-height: 48px;
                padding: 6px 8px;
                position: sticky;
                top: 0;
                z-index: 100;
                background: #fff;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                flex-wrap: wrap;
            }
            
            /* 聊天视图时完全隐藏header，只显示chat-header */
            .header.show-chat {
                display: none;
            }

            .header-left {
                gap: 6px;
                width: 100%;
                align-items: center;
            }
            
            /* 聊天视图时隐藏整个header-left区域 */
            .header.show-chat .header-left {
                display: none;
            }

            .header-title {
                font-size: 15px;
                font-weight: 600;
                flex: 1;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                margin: 0 4px;
            }

            .appid-badge {
                display: none;
            }

            /* 显示手机端切换按钮 */
            .mobile-tabs {
                display: flex;
                width: 100%;
                margin-top: 4px;
                margin-left: 0;
                gap: 6px;
            }
            
            .mobile-tab {
                flex: 1;
                padding: 6px 12px;
                font-size: 13px;
            }
            
            /* 返回按钮在列表视图时隐藏，在聊天视图时显示 */
            .back-to-main {
                display: none;
            }
            
            .header.show-chat .back-to-main {
                display: none;
            }

            /* 返回按钮 */
            .back-btn {
                width: 36px;
                height: 36px;
                min-width: 36px;
                min-height: 36px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
                background: #f5f5f5;
                border: none;
                cursor: pointer;
                color: #333;
                transition: all 0.15s ease;
                -webkit-tap-highlight-color: transparent;
                touch-action: manipulation;
                text-decoration: none;
            }

            .back-btn:active {
                background: #e0e0e0;
                transform: scale(0.95);
            }
            
            /* 聊天视图的返回按钮 */
            .back-to-list-btn {
                width: 36px;
                height: 36px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
                background: #f5f5f5;
                border: none;
                cursor: pointer;
                color: #333;
                transition: all 0.15s ease;
                -webkit-tap-highlight-color: transparent;
                touch-action: manipulation;
            }
            
            .back-to-list-btn:active {
                background: #e0e0e0;
                transform: scale(0.95);
            }

            /* 手机端：隐藏PC端布局，显示移动端布局 */
            .sidebar {
                display: none;
            }

            .chat-content {
                display: none;
                flex: 0 0 100% !important;
                max-width: 100% !important;
            }

            /* 显示手机端聊天列表容器 */
            .mobile-chat-list-container {
                display: flex;
                width: 100%;
                max-width: 100%;
                overflow-x: hidden;
            }
            
            /* 手机端列表头部按钮 */
            .mobile-chat-type-buttons {
                width: 100%;
                box-sizing: border-box;
                display: flex;
                gap: 0;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            
            .mobile-chat-type-buttons .btn {
                flex: 1;
                min-width: 0;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                font-size: 0.875rem;
                padding: 10px 12px;
                border: none;
                background: white;
                color: #666;
                font-weight: 500;
                transition: all 0.2s ease;
            }
            
            .mobile-chat-type-buttons .btn:hover:not(.active) {
                background: #f8f9fa;
                color: #333;
            }
            
            .mobile-chat-type-buttons .btn.active {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
            }
            
            .chat-list-header {
                font-size: 0.85rem;
            }
            
            .chat-type-buttons .btn {
                font-size: 0.8rem;
                padding: 6px 12px;
                flex: 1; /* 移动端也要平分宽度 */
                min-width: 0;
                text-align: center;
            }
            
            .list-group-item .chat-avatar,
            .list-group-item .chat-avatar-placeholder {
                width: 45px !important;
                height: 45px !important;
            }

            .main-container {
                height: calc(100vh - var(--mobile-header-height, 80px));
                height: calc(100dvh - var(--mobile-header-height, 80px));
                position: relative;
                overflow: hidden;
                overflow-x: hidden;
                padding: 0 !important;
                gap: 0 !important;
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }
            
            /* 聊天视图时，main-container占满全屏 */
            .chat-content.show {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                z-index: 200;
                background: #fff;
                margin: 0 !important;
                padding: 0 !important;
            }

            .log-file-select {
                font-size: 14px;
                padding: 10px 12px;
                width: 100%;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                background: #fff;
                color: #333;
                -webkit-appearance: none;
                -moz-appearance: none;
                appearance: none;
            }
            
            .log-file-select option {
                color: #333;
                background: #fff;
            }
            
            /* 搜索框样式 */
            .search-input {
                width: 100%;
                max-width: 100%;
                padding: 10px 12px;
                margin-top: 8px;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                font-size: 16px;
                background: #fff;
                color: #333;
                box-sizing: border-box;
            }
            
            .search-input:focus {
                outline: none;
                border-color: #1976d2;
            }
            
            .search-input::placeholder {
                color: #999;
            }

            .chat-item {
                padding: 12px;
                margin-bottom: 4px;
                border-radius: 12px;
                -webkit-tap-highlight-color: transparent;
                touch-action: manipulation;
                background: #fff;
                border: 1px solid #f0f0f0;
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
                overflow: hidden;
            }

            .chat-item:active {
                background: #f5f5f5;
                transform: scale(0.98);
            }
            
            /* 手机端列表项内容不溢出 */
            .chat-item .d-flex {
                width: 100%;
                min-width: 0;
            }
            
            .chat-item .flex-grow-1 {
                min-width: 0;
                overflow: hidden;
            }
            
            .chat-item h6 {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                margin: 0;
            }
            
            .chat-item small {
                display: block;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .chat-avatar {
                width: 48px;
                height: 48px;
            }

            /* 显示聊天内容时隐藏列表 */
            .mobile-chat-list-container.hide {
                display: none;
            }

            .chat-content.show {
                display: flex !important;
                flex-direction: column;
                width: 100% !important;
                max-width: 100% !important;
                height: 100%;
                flex: 1 1 100% !important;
            }
            
            /* 确保聊天内容容器铺满 */
            .chat-content.show .card {
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
            }
            
            .chat-content.show .card-body {
                width: 100% !important;
                max-width: 100% !important;
            }

            .chat-header {
                padding: 0.5rem 0.75rem !important;
                min-height: auto !important;
                height: auto !important;
                background: #fff;
                border-bottom: 1px solid #e0e0e0;
            }
            
            .chat-header .d-flex {
                flex-wrap: nowrap;
                gap: 0.5rem;
                align-items: center;
            }
            
            .chat-header .d-flex > div:first-child {
                flex: 1;
                min-width: 0;
                display: flex;
                align-items: center;
            }
            
            /* 聊天视图时，chat-header紧贴顶部，作为新的header */
            .chat-content.show .chat-header {
                position: sticky;
                top: 0;
                z-index: 100;
                border-top: none;
                width: 100% !important;
                max-width: 100% !important;
            }
            
            /* 手机端聊天头部按钮容器 */
            .mobile-chat-header-buttons {
                display: none;
                width: 100%;
                flex-direction: column;
                gap: 10px;
            }
            
            .chat-content.show .mobile-chat-header-buttons {
                display: flex !important;
            }
            
            /* 按钮行容器 */
            .mobile-header-btn-row {
                display: flex;
                gap: 10px;
                width: 100%;
            }
            
            /* 手机端返回按钮和复制按钮样式 - 均等分布 */
            .mobile-header-action-btn {
                flex: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                height: 40px;
                border-radius: 8px;
                background: #f5f5f5;
                border: none;
                cursor: pointer;
                color: #333;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.15s ease;
                -webkit-tap-highlight-color: transparent;
                touch-action: manipulation;
            }
            
            .mobile-header-action-btn:active {
                background: #e0e0e0;
                transform: scale(0.98);
            }
            
            .mobile-header-action-btn i {
                font-size: 16px;
            }
            
            /* 手机端ID显示 */
            .mobile-chat-id-display {
                display: none;
                width: 100%;
                text-align: center;
                font-size: 13px;
                color: #666;
                padding: 4px 0;
            }
            
            .chat-content.show .mobile-chat-id-display {
                display: block !important;
            }
            
            .chat-header-btn {
                flex: 1;
                height: 36px;
                display: none;
                align-items: center;
                justify-content: center;
                gap: 6px;
                border-radius: 8px;
                background: #f5f5f5;
                border: none;
                cursor: pointer;
                color: #333;
                font-size: 13px;
                transition: all 0.15s ease;
                -webkit-tap-highlight-color: transparent;
                touch-action: manipulation;
            }
            
            .chat-header-btn:active {
                background: #e0e0e0;
                transform: scale(0.98);
            }
            
            .chat-header-btn i {
                font-size: 14px;
            }
            
            .chat-content.show .chat-header-btn {
                display: flex !important;
            }
            
            .chat-title {
                display: none;
            }
            
            /* 聊天视图时隐藏切换按钮和标题 */
            .chat-content.show ~ .header .mobile-tabs,
            .header.show-chat .mobile-tabs {
                display: none;
            }
            
            .header.show-chat .header-title {
                display: none;
            }
            
            /* 聊天视图时显示返回按钮 */
            .header.show-chat .back-to-list-header {
                display: flex;
            }
            
            .back-to-list-header {
                display: none;
                align-items: center;
                gap: 8px;
                width: 100%;
                margin-top: 4px;
            }

            .messages-container {
                padding: 12px 8px;
                padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px));
                gap: 10px;
                -webkit-overflow-scrolling: touch;
                flex: 1;
                overflow-y: auto;
                min-height: 0; /* 确保flex子元素可以正确收缩 */
                width: 100% !important;
                max-width: 100% !important;
            }

            .message-item {
                margin-bottom: 8px;
            }

            .message-avatar,
            .message-avatar-placeholder {
                width: 36px;
                height: 36px;
                flex-shrink: 0;
            }

            .message-bubble {
                max-width: 75%;
            }

            .message-name {
                font-size: 12px;
                margin-bottom: 3px;
            }
            
            /* 移动端机器人消息昵称右对齐 */
            .message-item.bot .message-name {
                text-align: right;
                align-self: flex-end;
            }

            .message-content {
                padding: 8px 12px;
                font-size: 14px;
                line-height: 1.4;
                word-break: break-word;
            }
            
            /* 手机端机器人消息描边 */
            .message-item.bot .message-content {
                border: 2px solid var(--accent-color);
                border-radius: 0.75rem;
            }
            
            /* 手机端卡片消息保持原样式 */
            .message-item.bot .message-content.card {
                border: none;
                padding: 0;
            }
            
            /* 手机端消息项间距优化 */
            .message-item {
                margin-bottom: 8px;
            }
            
            /* 手机端头像大小优化 */
            .message-avatar,
            .message-avatar-placeholder {
                width: 36px;
                height: 36px;
            }

            .message-time {
                font-size: 10px;
                margin-top: 2px;
                padding: 0 0.5rem;
            }

            .event-message {
                margin: 12px 0;
            }

            .event-bubble {
                padding: 8px 16px;
                font-size: 13px;
            }

            /* 优化空状态显示 */
            .empty-state {
                padding: 40px 20px;
            }

            .empty-state i {
                font-size: 48px;
            }

            .empty-state p {
                font-size: 14px;
                margin-top: 12px;
            }

            /* 优化加载状态 */
            .loading {
                padding: 30px;
            }

            /* 优化卡片消息 */
            .card-content {
                padding: 14px;
                font-size: 14px;
            }

            .card-item {
                padding: 8px 0;
            }

            /* 优化图片显示 - 小屏幕设备，图片在消息框内 */
            .message-content img {
                max-width: 100% !important;
                width: auto !important;
                height: auto !important;
                border-radius: 8px;
                margin-top: 8px;
                object-fit: contain;
                display: block;
            }
            
            /* 确保消息内容容器不超出，但保持消息气泡的合理宽度 */
            .message-content {
                box-sizing: border-box;
                overflow: hidden;
            }
            
            .media-player {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .media-player audio,
            .media-player video {
                width: 100%;
                min-width: 100%;
            }
        }

        @media (min-width: 769px) {
            .menu-toggle {
                display: none;
            }
        }

        .menu-toggle {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: #f0f0f0;
            border: none;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            color: #666;
            transition: all 0.2s;
        }

        .menu-toggle:hover {
            background: #e0e0e0;
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 150;
        }

        .overlay.show {
            display: block;
        }

        .card-content {
            background: #fff9e6;
            border: 1px solid #ffd700;
            border-radius: 8px;
            padding: 12px;
            margin-top: 4px;
        }

        .card-item {
            padding: 6px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .card-item:last-child {
            border-bottom: none;
        }

        .card-link {
            color: #ff69b4;
            text-decoration: none;
            margin-left: 8px;
        }

        .card-link:hover {
            text-decoration: underline;
        }
        
        /* 粉色系按钮样式 */
        .btn-outline-primary {
            color: #ff69b4;
            border-color: var(--accent-color);
            background: transparent;
        }
        
        .btn-outline-primary:hover {
            color: white;
            background: linear-gradient(135deg, var(--accent-color), var(--highlight-color));
            border-color: var(--highlight-color);
        }
        
        .btn-outline-primary.active {
            color: white;
            background: linear-gradient(135deg, var(--highlight-color), #ff69b4);
            border-color: #ff69b4;
        }
        
        .btn-outline-secondary {
            color: var(--text-color);
            border-color: var(--accent-color);
        }
        
        .btn-outline-secondary:hover {
            color: white;
            background: var(--accent-color);
            border-color: var(--accent-color);
        }
        
        .spinner-border.text-primary {
            color: #ff69b4 !important;
        }
        
        /* 发送设置弹窗样式 - 全新设计 */
        .settings-modal {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .settings-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 24px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .settings-header-content {
            display: flex;
            gap: 16px;
            align-items: center;
        }
        
        .settings-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .settings-title {
            font-size: 20px;
            font-weight: 700;
            color: white;
            margin: 0;
        }
        
        .settings-subtitle {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.8);
            margin: 4px 0 0 0;
        }
        
        .settings-close {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .settings-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }
        
        .settings-body {
            padding: 24px;
            background: #f8f9fa;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        /* 延迟消息模态框 - 全新设计 */
        .delayed-modal {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            background: white;
            position: relative;
        }
        
        /* 关闭按钮 */
        .delayed-modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.05);
            border: none;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            z-index: 10;
        }
        
        .delayed-modal-close:hover {
            background: rgba(0, 0, 0, 0.1);
            transform: rotate(90deg);
        }
        
        /* 用户信息区域 */
        .delayed-modal-user {
            padding: 40px 24px 24px;
            text-align: center;
            background: white;
            border-bottom: 1px solid #f0f0f0;
        }
        
        /* 用户头像 */
        .delayed-user-avatar {
            width: 80px;
            height: 80px;
            margin: 0 auto 16px;
            border-radius: 50%;
            border: 3px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            overflow: hidden;
        }
        
        .delayed-user-avatar i {
            font-size: 48px;
            color: #999;
        }
        
        .delayed-user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* 用户ID */
        .delayed-user-id {
            margin-bottom: 8px;
            text-align: center;
        }
        
        .delayed-user-id div {
            line-height: 1.4;
        }
        
        /* 消息列表区域 */
        .delayed-modal-body {
            padding: 24px;
            background: white;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        /* 提示文字 */
        .delayed-messages-hint {
            text-align: center;
            color: #999;
            font-size: 14px;
            padding: 20px;
            display: none; /* 默认隐藏，有消息时隐藏 */
        }
        
        .delayed-messages-list:empty ~ .delayed-messages-hint {
            display: block;
        }
        
        .settings-section {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
        }
        
        .section-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            font-weight: 600;
            color: #333;
            margin-bottom: 16px;
        }
        
        .section-label i {
            font-size: 18px;
            color: #667eea;
        }
        
        /* 格式切换开关 - 重新设计 */
        .format-switch {
            position: relative;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            background: #f0f3f7;
            border-radius: 12px;
            padding: 6px;
            cursor: pointer;
        }
        
        .switch-option {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            color: #6c757d;
            transition: all 0.3s;
            z-index: 2;
            position: relative;
        }
        
        .switch-option.active {
            color: white;
        }
        
        .switch-slider {
            position: absolute;
            top: 6px;
            left: 6px;
            width: calc(50% - 10px);
            height: calc(100% - 12px);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            z-index: 1;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .format-switch[data-active="card"] .switch-slider,
        .format-switch[data-active="delayed"] .switch-slider {
            left: calc(50% + 2px);
        }
        
        /* 模板列表样式 */
        .template-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
            min-height: 40px;
        }
        
        .template-list:empty::before {
            content: '暂无模板，快来添加第一个吧！';
            display: block;
            width: 100%;
            text-align: center;
            padding: 20px;
            color: #adb5bd;
            font-size: 13px;
        }
        
        /* 模板标签 - 重新设计 */
        .card-template-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 16px;
            background: white;
            border: none;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #495057;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .card-template-tag:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }
        
        .card-template-tag.has-link {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .card-template-tag .template-delete {
            margin-left: 4px;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.1);
            font-size: 12px;
            transition: all 0.2s;
        }
        
        .card-template-tag .template-delete:hover {
            background: rgba(220, 53, 69, 0.9);
            color: white;
        }
        
        /* 模板创建器 */
        .template-creator {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 16px;
        }
        
        .creator-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .creator-title {
            font-size: 14px;
            font-weight: 600;
            color: #495057;
        }
        
        .add-row-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: white;
            border: 2px solid #667eea;
            border-radius: 8px;
            color: #667eea;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .add-row-btn:hover {
            background: #667eea;
            color: white;
        }
        
        .rows-container {
            margin-bottom: 12px;
        }
        
        /* 模板行 - 重新设计 */
        .template-row {
            position: relative;
            background: white;
            border-radius: 10px;
            padding: 16px 16px 16px 50px;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
        }
        
        .template-row:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .template-row-number {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
        }
        
        .template-row-delete {
            position: absolute;
            right: 12px;
            top: 12px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #fff;
            border: 1px solid #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #6c757d;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .template-row-delete:hover {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        
        .template-row input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 8px;
            transition: all 0.2s;
        }
        
        .template-row input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .template-row input:last-child {
            margin-bottom: 0;
        }
        
        /* 保存模板按钮 */
        .save-template-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .save-template-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
        }
        
        .save-template-btn:active {
            transform: translateY(0);
        }
        
        /* 延迟消息列表样式 */
        .delayed-messages-list {
            min-height: 200px;
        }
        
        .delayed-messages-list:empty::before {
            content: '暂无延迟消息';
            display: block;
            text-align: center;
            padding: 60px 20px;
            color: #adb5bd;
            font-size: 14px;
        }
        
        .delayed-message-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
        }
        
        .delayed-message-card:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .delayed-message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .delayed-message-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .delayed-message-type {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .delayed-message-type.group {
            background: #e7f3ff;
            color: #0066cc;
        }
        
        .delayed-message-type.private {
            background: #fff0e6;
            color: #ff6600;
        }
        
        .delayed-message-id {
            font-size: 12px;
            color: #6c757d;
        }
        
        .delayed-message-content {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            color: #333;
            margin-bottom: 12px;
            max-height: 100px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
        
        .delayed-message-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .delayed-message-time {
            font-size: 12px;
            color: #6c757d;
        }
        
        .delayed-message-actions {
            display: flex;
            gap: 8px;
        }
        
        .delayed-action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .delayed-action-btn.send-now {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .delayed-action-btn.send-now:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.4);
        }
        
        .delayed-action-btn.delete {
            background: #fff;
            border: 1px solid #dee2e6;
            color: #dc3545;
        }
        
        .delayed-action-btn.delete:hover {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        
        /* 延迟消息卡片 - 全新简洁设计 */
        .delayed-msg-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
        }
        
        .delayed-msg-card:hover {
            background: #fff;
            border-color: #dee2e6;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        .delayed-msg-content {
            font-size: 14px;
            color: #333;
            line-height: 1.6;
            margin-bottom: 12px;
            max-height: 80px;
            overflow-y: auto;
            word-wrap: break-word;
        }
        
        .delayed-msg-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .delayed-msg-time {
            font-size: 12px;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .delayed-msg-time i {
            font-size: 13px;
        }
        
        .delayed-msg-method {
            font-size: 11px;
            padding: 3px 8px;
            background: #e7f3ff;
            color: #0066cc;
            border-radius: 4px;
            font-weight: 600;
        }
        
        .delayed-msg-actions {
            display: flex;
            gap: 8px;
        }
        
        .delayed-btn {
            flex: 1;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .delayed-btn-send {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .delayed-btn-send:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .delayed-btn-send:active {
            transform: translateY(0);
        }
        
        .delayed-btn-delete {
            background: white;
            color: #dc3545;
            border: 1px solid #f8d7da;
            flex: 0 0 auto;
            padding: 8px 12px;
        }
        
        .delayed-btn-delete:hover {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
        }
        
        /* 无消息提示 */
        .no-messages {
            text-align: center;
            padding: 40px 20px;
            color: #adb5bd;
        }
        
        .no-messages i {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
            opacity: 0.5;
        }
        
        .no-messages p {
            margin: 0;
            font-size: 14px;
        }
    

        /* ===== 官机1.0 统一风格 + 手机端适配修复 ===== */
        :root {
            --primary-color: #f8fafc;
            --secondary-color: #ffffff;
            --accent-color: #2c6b9e;
            --text-color: #1a2c3e;
            --shadow-color: rgba(26, 44, 62, 0.08);
            --danger-color: #c23d2e;
            --success-color: #2c6e2c;
            --warning-color: #b7791f;
            --highlight-color: #235b87;
            --light-pink: #f1f5f9;
            --medium-pink: #e9edf2;
            --border-color: #e9edf2;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.03);
        }

        body {
            background: var(--primary-color) !important;
            background-image: none !important;
            color: var(--text-color) !important;
        }

        .header {
            background: var(--secondary-color) !important;
            border-bottom: 1px solid var(--border-color) !important;
            box-shadow: var(--shadow) !important;
        }

        .header-title {
            color: var(--text-color) !important;
            text-shadow: none !important;
        }

        .appid-badge {
            background: #f1f5f9 !important;
            color: #2c6b9e !important;
        }

        .back-btn,
        .chat-action-btn,
        .chat-header-btn,
        .mobile-header-action-btn {
            background: #f1f5f9 !important;
            color: #4a5b6e !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: none !important;
        }

        .back-btn:hover,
        .chat-action-btn:hover,
        .chat-header-btn:hover,
        .mobile-header-action-btn:hover {
            background: #e6e9ef !important;
            color: #235b87 !important;
        }

        .chat-type-buttons.btn-group > .btn.active,
        .mobile-chat-type-buttons .btn.active {
            background: #2c6b9e !important;
            color: #fff !important;
            box-shadow: none !important;
        }

        .chat-type-buttons.btn-group > .btn:hover:not(.active),
        .mobile-chat-type-buttons .btn:hover:not(.active) {
            background: #f1f5f9 !important;
            color: #2c6b9e !important;
        }

        .card,
        .list-group-item,
        .chat-content .card {
            border-color: var(--border-color) !important;
            box-shadow: none !important;
        }

        @media (max-width: 768px) {
            html, body {
                width: 100% !important;
                max-width: 100% !important;
                overflow-x: hidden !important;
            }

            .header {
                background: #fff !important;
                border-bottom: 1px solid #e9edf2 !important;
                box-shadow: 0 1px 2px rgba(0,0,0,.04) !important;
            }

            .main-container,
            .mobile-chat-list-container,
            .mobile-chat-list,
            .chat-content,
            .chat-content.show,
            .chat-content.show .card,
            .chat-content.show .card-body {
                width: 100% !important;
                max-width: 100% !important;
                overflow-x: hidden !important;
            }

            .chat-content.show {
                background: #f8fafc !important;
            }

            .mobile-chat-list-header,
            .chat-header,
            .message-area,
            .settings-modal-content,
            .creator-modal-content {
                border-radius: 0 !important;
            }

            .search-input,
            .log-file-select,
            textarea,
            input,
            select,
            button {
                font-size: 16px !important;
            }
        }



        /* 手机端修复：发送设置不再整页硬覆盖，改为底部抽屉 */
        @media (max-width: 768px) {
            #formatSelectorModal .modal-dialog {
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                margin: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
                transform: none !important;
                pointer-events: none;
            }

            #formatSelectorModal .modal-content.settings-modal {
                pointer-events: auto;
                border-radius: 16px 16px 0 0 !important;
                border: 1px solid #e9edf2 !important;
                border-bottom: none !important;
                max-height: 82dvh !important;
                box-shadow: 0 -8px 30px rgba(0, 0, 0, 0.18) !important;
                overflow: hidden !important;
            }

            #formatSelectorModal .settings-header {
                padding: 14px 16px !important;
            }

            #formatSelectorModal .settings-title {
                font-size: 16px !important;
            }

            #formatSelectorModal .settings-subtitle {
                font-size: 12px !important;
            }

            #formatSelectorModal .settings-body {
                max-height: calc(82dvh - 88px) !important;
                overflow-y: auto !important;
                -webkit-overflow-scrolling: touch;
                padding: 14px !important;
                background: #f8fafc !important;
            }
        }


        #formatSelectorModal { display: none !important; }
        #delayedMessagesModal { display: none !important; }


        /* ===== 视觉重构：更现代、清爽 ===== */
        :root {
            --ui-bg: #f4f7fb;
            --ui-card: #ffffff;
            --ui-border: #e6ebf2;
            --ui-text: #1f2d3d;
            --ui-sub: #607086;
            --ui-primary: #2c6b9e;
            --ui-primary-2: #3f86be;
            --ui-soft: #eef4fa;
        }

        body {
            background: linear-gradient(180deg, #f8fbff 0%, var(--ui-bg) 100%) !important;
            color: var(--ui-text) !important;
        }

        .header {
            height: 56px !important;
            background: rgba(255,255,255,.92) !important;
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--ui-border) !important;
            box-shadow: 0 2px 10px rgba(17, 35, 63, .05) !important;
        }

        .main-container { padding: 14px !important; gap: 14px !important; }

        .sidebar .card,
        .chat-content .card {
            border: 1px solid var(--ui-border) !important;
            border-radius: 14px !important;
            overflow: hidden;
            background: var(--ui-card) !important;
            box-shadow: 0 8px 24px rgba(17, 35, 63, .05) !important;
        }

        .card-header {
            background: #fbfdff !important;
            border-bottom: 1px solid var(--ui-border) !important;
        }

        .chat-item,
        .list-group-item {
            border-color: #edf2f8 !important;
            transition: all .18s ease;
        }

        .chat-item:hover,
        .list-group-item:hover {
            background: #f7fbff !important;
            transform: translateY(-1px);
        }

        .chat-item.active,
        .list-group-item.active {
            background: linear-gradient(90deg, #eef6ff 0%, #f7fbff 100%) !important;
            border-left: 3px solid var(--ui-primary) !important;
        }

        .chat-type-buttons.btn-group > .btn.active,
        .mobile-chat-type-buttons .btn.active {
            background: linear-gradient(135deg, var(--ui-primary) 0%, var(--ui-primary-2) 100%) !important;
        }

        #sendArea {
            background: #fbfdff !important;
            border-top: 1px solid var(--ui-border) !important;
        }

        #sendInput {
            border: 1px solid #d8e3ef !important;
            border-radius: 10px !important;
            box-shadow: inset 0 1px 2px rgba(15, 38, 70, .04);
        }

        #sendInput:focus {
            border-color: #8db9db !important;
            box-shadow: 0 0 0 3px rgba(44,107,158,.12) !important;
        }

        #sendBtn {
            border-radius: 10px !important;
            background: linear-gradient(135deg, var(--ui-primary) 0%, var(--ui-primary-2) 100%) !important;
            border: none !important;
            box-shadow: 0 6px 14px rgba(44,107,158,.25);
        }

        #sendBtn:hover { filter: brightness(1.03); transform: translateY(-1px); }

        .quick-send-tools .btn {
            border-radius: 999px !important;
            font-weight: 600;
            border: 1px solid #dbe6f2 !important;
            background: #fff;
            color: #42566f;
        }

        .quick-send-tools .btn.btn-primary,
        .quick-send-tools .btn.btn-warning {
            color: #fff !important;
            border: none !important;
        }

        @media (max-width: 768px) {
            .main-container { padding: 0 !important; gap: 0 !important; }
            .chat-content .card,
            .sidebar .card,
            .mobile-chat-list-container {
                border-radius: 0 !important;
                box-shadow: none !important;
            }
            .header { height: 52px !important; }
        }



        /* ===== QQ聊天框风格重构 ===== */
        .message-area {
            background: #f2f3f5 !important;
            padding: 16px 12px !important;
        }

        .message-item {
            margin-bottom: 14px !important;
            align-items: flex-start !important;
        }

        .message-item.user {
            flex-direction: row !important;
        }

        .message-item.bot {
            flex-direction: row-reverse !important;
        }

        .message-avatar,
        .message-avatar-placeholder {
            width: 34px !important;
            height: 34px !important;
            border-radius: 10px !important;
        }

        .message-bubble {
            max-width: min(72%, 680px) !important;
            border-radius: 14px !important;
            box-shadow: 0 1px 2px rgba(0,0,0,.06) !important;
            border: 1px solid #e8ebf0 !important;
            overflow: hidden;
        }

        .message-item.user .message-bubble {
            background: #ffffff !important;
            border-top-left-radius: 6px !important;
        }

        .message-item.bot .message-bubble {
            background: #d7ebff !important;
            border-color: #c6def7 !important;
            border-top-right-radius: 6px !important;
        }

        .message-name {
            font-size: 12px !important;
            color: #7b8896 !important;
            margin-bottom: 4px !important;
        }

        .message-content {
            font-size: 14px !important;
            line-height: 1.5 !important;
            color: #1f2d3d !important;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .message-time {
            font-size: 11px !important;
            color: #97a3af !important;
            margin-top: 4px !important;
        }

        #sendArea {
            background: #fff !important;
            border-top: 1px solid #e6ebf2 !important;
            box-shadow: 0 -2px 8px rgba(0,0,0,.04) !important;
        }

        #sendInput {
            border-radius: 18px !important;
            padding: 9px 14px !important;
            background: #f6f8fb !important;
            border: 1px solid #e2e7ef !important;
            min-height: 38px !important;
        }

        #sendBtn {
            border-radius: 18px !important;
            min-width: 78px !important;
            height: 38px !important;
            box-shadow: none !important;
            background: #12b7f5 !important;
        }

        @media (max-width: 768px) {
            .message-area { padding: 12px 8px !important; }
            .message-bubble { max-width: 78% !important; }
            .message-avatar,
            .message-avatar-placeholder { width: 30px !important; height: 30px !important; }
        }



        /* 机器人消息可见性修复 */
        .message-item.bot {
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        .message-item.bot .message-bubble {
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
            background: #d7ebff !important;
            border: 1px solid #c6def7 !important;
            margin-left: auto !important;
            max-width: min(72%, 680px) !important;
        }

        .message-item.bot .message-content {
            display: block !important;
            color: #1f2d3d !important;
            background: transparent !important;
            border: 0 !important;
            width: auto !important;
        }

        .message-item.bot .message-name,
        .message-item.bot .message-time {
            color: #5f6f82 !important;
        }



        /* ===== QQ细节精修（气泡/昵称/间距/对齐） ===== */
        .messages-container {
            padding: 14px 10px 10px !important;
            background: #f2f3f5 !important;
        }

        .message-item {
            gap: 8px !important;
            margin-bottom: 12px !important;
            align-items: flex-start !important;
        }

        .message-main {
            display: flex !important;
            flex-direction: column !important;
            max-width: min(74%, 700px) !important;
        }

        .message-item.user .message-main {
            align-items: flex-start !important;
        }

        .message-item.bot .message-main {
            align-items: flex-end !important;
        }

        .message-name {
            display: block !important;
            font-size: 12px !important;
            line-height: 1.2 !important;
            color: #8a97a6 !important;
            margin: 0 0 4px 2px !important;
            padding: 0 !important;
            background: transparent !important;
            border: 0 !important;
        }

        .message-item.bot .message-name {
            margin: 0 2px 4px 0 !important;
            text-align: right !important;
        }

        .message-bubble {
            border-radius: 14px !important;
            padding: 0 !important;
            overflow: hidden !important;
            box-shadow: 0 1px 2px rgba(0,0,0,.05) !important;
        }

        .message-item.user .message-bubble {
            background: #fff !important;
            border: 1px solid #e7ebf0 !important;
            border-top-left-radius: 6px !important;
        }

        .message-item.bot .message-bubble {
            background: #cfe7ff !important;
            border: 1px solid #bfdcff !important;
            border-top-right-radius: 6px !important;
        }

        .message-content {
            padding: 8px 12px !important;
            margin: 0 !important;
            font-size: 14px !important;
            line-height: 1.5 !important;
            color: #1f2d3d !important;
            background: transparent !important;
            border: 0 !important;
            width: auto !important;
            max-width: 100% !important;
        }

        .message-time {
            font-size: 11px !important;
            color: #97a3af !important;
            padding: 0 10px 8px !important;
            margin: 0 !important;
        }

        #sendArea {
            position: sticky !important;
            bottom: 0 !important;
            z-index: 8 !important;
            padding: 10px !important;
            background: #fff !important;
            border-top: 1px solid #e7ebf0 !important;
        }

        #sendInput {
            min-height: 38px !important;
            max-height: 120px !important;
            border-radius: 18px !important;
            padding: 8px 14px !important;
            background: #f6f8fb !important;
            border: 1px solid #e1e7ef !important;
        }

        #sendBtn {
            height: 38px !important;
            min-width: 74px !important;
            border-radius: 18px !important;
            background: #12b7f5 !important;
        }

        @media (max-width: 768px) {
            .message-main { max-width: 80% !important; }
            .message-content { font-size: 15px !important; }
            .message-item { margin-bottom: 10px !important; }
            .messages-container { padding: 10px 6px 8px !important; }
        }



        /* 输入框与发送按钮同排 + 输入高度随内容自适应 */
        #sendArea .d-flex.gap-2.align-items-stretch {
            align-items: flex-end !important;
            flex-wrap: nowrap !important;
        }

        #sendArea #sendInput {
            width: 100% !important;
            resize: none !important;
            overflow-y: hidden !important;
            min-height: 38px !important;
            max-height: 150px !important;
            line-height: 1.45 !important;
        }

        #sendArea #sendBtn {
            flex: 0 0 auto !important;
            align-self: flex-end !important;
            height: 38px !important;
        }



        /* 发送区同排修复：按钮不换行 */
        #sendArea .d-flex.gap-2.align-items-stretch {
            display: flex !important;
            flex-wrap: nowrap !important;
            align-items: flex-end !important;
            width: 100% !important;
        }

        #sendArea .d-flex.gap-2.align-items-stretch > div:first-child {
            flex: 1 1 auto !important;
            min-width: 0 !important;
        }

        #sendArea .d-flex.gap-2.align-items-stretch > div:last-child {
            flex: 0 0 auto !important;
            width: auto !important;
        }

        #sendArea #sendBtn {
            white-space: nowrap !important;
            min-width: 74px !important;
        }



        /* 时间改为分隔条：隐藏气泡内时间 */
        .message-time { display: none !important; }
        .event-message .event-bubble {
            background: rgba(153, 164, 178, .18) !important;
            color: #7b8794 !important;
            font-size: 11px !important;
            border-radius: 10px !important;
            padding: 3px 10px !important;
        }



        /* 文本换行修复：避免一字一行 */
        .message-main { min-width: 0 !important; }
        .message-bubble { max-width: 100% !important; min-width: 36px !important; }

        .message-content,
        .message-item.user .message-content,
        .message-item.bot .message-content {
            display: block !important;
            width: auto !important;
            max-width: 100% !important;
            min-width: 0 !important;
            white-space: pre-wrap !important;
            word-break: break-word !important;
            overflow-wrap: anywhere !important;
            writing-mode: horizontal-tb !important;
        }



        /* 终极兜底：防止气泡被压成一字一行 */
        .message-item .message-main { min-width: 180px !important; }
        .message-item .message-bubble { width: auto !important; min-width: 120px !important; }
        .message-item .message-content { width: auto !important; max-width: 100% !important; }



        /* 按字体和内容长度自适应气泡大小（高优先级覆盖） */
        .message-item .message-main {
            max-width: 74% !important;
            min-width: 0 !important;
        }

        .message-item .message-bubble {
            display: inline-block !important;
            width: fit-content !important;
            max-width: 100% !important;
            min-width: 2.5em !important;
        }

        .message-item .message-content,
        .message-item.user .message-content,
        .message-item.bot .message-content {
            display: inline-block !important;
            width: auto !important;
            max-width: 100% !important;
            white-space: pre-wrap !important;
            word-break: break-word !important;
            overflow-wrap: break-word !important;
            line-height: 1.5 !important;
        }



        /* 中文一字一行终修：按内容宽度展示，禁止任意断字 */
        .message-item .message-bubble {
            width: max-content !important;
            max-width: min(74vw, 680px) !important;
            min-width: 4.5em !important;
        }

        .message-item .message-content,
        .message-item.user .message-content,
        .message-item.bot .message-content {
            display: block !important;
            white-space: pre-wrap !important;
            word-break: normal !important;
            overflow-wrap: break-word !important;
            line-break: auto !important;
        }



        /* 原生MD轻渲染样式 */
        .message-content .md-h1,.message-content .md-h2,.message-content .md-h3{
            margin: 2px 0 6px;
            line-height: 1.3;
            font-weight: 700;
        }
        .message-content .md-h1{font-size:16px;}
        .message-content .md-h2{font-size:15px;}
        .message-content .md-h3{font-size:14px;}
        .message-content .md-quote{
            margin: 6px 0;
            padding: 4px 10px;
            border-left: 3px solid #9fb7d3;
            color:#5f6f82;
            background: rgba(255,255,255,.4);
        }
        .message-content .md-code{
            padding: 1px 4px;
            border-radius: 4px;
            background: rgba(0,0,0,.08);
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }
        .message-content .md-pre{
            margin: 6px 0;
            padding: 8px;
            border-radius: 6px;
            background: rgba(0,0,0,.06);
            overflow: auto;
        }
        .message-content .md-hr{margin:8px 0;border:0;border-top:1px solid rgba(0,0,0,.15);} 


        .message-content .md-img {
            display:block;
            margin:8px 0;
            max-width:100%;
            border-radius:8px;
            cursor:pointer;
        }
        .message-content .md-link {
            color:#1f6fb2;
            text-decoration:underline;
            word-break:break-all;
        }


        </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <a href="main.php" class="back-btn back-to-main">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="header-title">聊天窗口</h1>
        </div>
        <div class="header-right">
            <span>检测时间：<?php echo date('Y-m-d H:i:s'); ?></span>
        </div>
    </div>

    <div class="main-container">
        <!-- 左侧：聊天列表 -->
        <div class="sidebar" id="sidebar">
            <div class="card h-100">
                <div class="card-header flex-shrink-0">
                    <div class="d-flex align-items-center chat-list-header">
                        <i class="bi bi-people me-2"></i>
                        <div class="btn-group btn-group-sm chat-type-buttons me-2" role="group">
                            <button type="button" class="btn btn-outline-primary active" id="btn-users" data-tab="private">
                                <i class="bi bi-person"></i> 好友
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="btn-groups" data-tab="group">
                                <i class="bi bi-people"></i> 群聊
                            </button>
                        </div>
                        <select class="form-select form-select-sm" id="logFileSelect" style="width: auto; min-width: 100px;">
                            <option value="">加载中...</option>
                        </select>
                    </div>
                </div>
                <div class="card-body p-0 flex-grow-1 d-flex flex-column" style="min-height: 0; overflow: hidden;">
                    <!-- 搜索框 -->
                    <div class="p-3 border-bottom flex-shrink-0">
                        <div class="search-wrapper">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" class="form-control search-input-modern" id="searchInput" placeholder="搜索聊天...">
                            <button class="btn search-btn-modern" type="button" onclick="performSearch()">
                                <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- 聊天列表 -->
                    <div id="chatList" class="list-group list-group-flush flex-grow-1" style="overflow-y: auto; min-height: 0;">
                        <div class="text-center p-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">加载中...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 手机端聊天列表容器 -->
        <div class="mobile-chat-list-container" id="mobileChatListContainer">
            <div class="mobile-chat-list-header">
                <select id="mobileLogFileSelect" class="log-file-select">
                    <option value="">加载日志文件中...</option>
                </select>
                <!-- 切换按钮 -->
                <div class="btn-group btn-group-sm mobile-chat-type-buttons" role="group" style="width: 100%; margin-top: 8px;">
                    <button type="button" class="btn btn-outline-primary active" id="mobile-btn-users" data-tab="private">
                        <i class="bi bi-person"></i> 好友
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="mobile-btn-groups" data-tab="group">
                        <i class="bi bi-people"></i> 群聊
                    </button>
                </div>
                <!-- 搜索框 -->
                <input type="text" id="mobileSearchInput" class="search-input" placeholder="搜索消息内容或ID...">
            </div>
            <div class="mobile-chat-list" id="mobileChatList">
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <p>请选择日志文件</p>
                </div>
            </div>
        </div>

        <!-- 右侧：聊天窗口 -->
        <div class="chat-content">
            <div class="card h-100">
                <div class="card-header flex-shrink-0" id="chatHeader">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center flex-grow-1" style="min-width: 0;">
                            <!-- 手机端/电脑端按钮布局 -->
                            <div style="width: 100%;">
                                <div class="chat-header-action-buttons">
                                    <button class="chat-action-btn back-btn-style" onclick="backToChatList()" id="back-list-btn">
                                        <i class="bi bi-arrow-left"></i>
                                        <span>返回列表</span>
                                    </button>
                                    <button class="chat-action-btn copy-btn-style" onclick="copyChatId()" id="copy-id-btn">
                                        <i class="bi bi-clipboard"></i>
                                        <span>复制ID</span>
                                    </button>
                                </div>
                                <div class="chat-id-badge" id="chat-id-display">ID: --</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0 d-flex flex-column flex-grow-1" style="min-height: 0; overflow: hidden;">
                    <div id="messagesContainer" class="messages-container">
                        <div class="text-center text-muted">
                            <i class="bi bi-chat-text" style="font-size: 3rem;"></i>
                            <p class="mt-2">选择聊天对象开始查看消息</p>
                        </div>
                    </div>
                    <!-- 发送文字消息区域（仅支持文字） -->
                    <div class="border-top p-3" id="sendArea" style="background-color: #fff;">
                        <!-- 群聊目标用户选择提示 -->
                        <div id="targetUserHint" style="display: none; margin-bottom: 8px; padding: 6px 12px; background: #e7f3ff; border-left: 3px solid #2196F3; border-radius: 4px; font-size: 13px; color: #1976D2;">
                            <i class="bi bi-person-check"></i>
                            <span>目标用户：</span>
                            <strong id="targetUserIdDisplay"></strong>
                            <button onclick="clearTargetUser()" style="float: right; background: none; border: none; color: #1976D2; cursor: pointer; padding: 0; font-size: 16px;" title="取消选择">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </div>

                        <!-- 输入框快捷设置 -->
                        <div class="quick-send-tools" style="display:flex; gap:8px; margin-bottom:8px; flex-wrap:wrap; align-items:center;">
                            <label for="sendTypeSelect" style="font-size:13px;color:#607086;">发送类型</label>
                            <select id="sendTypeSelect" class="form-select form-select-sm" style="width:auto; min-width:120px;" onchange="onSendTypeChange(this.value)">
                                <option value="text">文字</option>
                                <option value="card">文卡</option>
                                <option value="native_md">原生MD</option>
                            </select>
                        </div>

                        <div class="d-flex gap-2 align-items-stretch">
                            <div style="flex: 1;">
                                <textarea class="form-control" id="sendInput" rows="1" placeholder="输入要发送的文字消息..." style="resize: none; overflow-y: hidden; border-radius: 8px; min-height: 38px; max-height: 150px;"></textarea>
                            </div>
                            <div style="flex-shrink: 0;">
                                <button class="btn btn-primary h-100" type="button" onclick="sendMessage()" id="sendBtn" style="min-width: 80px; border-radius: 8px;">
                                    <i class="bi bi-send"></i>
                                    <span class="ms-1 d-none d-sm-inline" id="sendBtnText">发送</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 发送格式选择弹窗 - 重新设计 -->
    <div class="modal fade" id="formatSelectorModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content settings-modal">
                <!-- 头部 -->
                <div class="settings-header">
                    <div class="settings-header-content">
                        <div class="settings-icon">
                            <i class="bi bi-gear-fill"></i>
                        </div>
                        <div>
                            <h5 class="settings-title">发送设置</h5>
                            <p class="settings-subtitle">配置消息发送格式和快捷模板</p>
                        </div>
                    </div>
                    <button type="button" class="settings-close" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                
                <div class="settings-body">
                    <!-- 发送类型选择 -->
                    <div class="settings-section">
                        <div class="section-label">
                            <i class="bi bi-send-fill"></i>
                            <span>发送类型</span>
                        </div>
                        <div class="format-switch" id="formatToggle" onclick="toggleFormat()">
                            <div class="switch-option" data-format="text">
                                <i class="bi bi-chat-text"></i>
                                <span>文字消息</span>
                            </div>
                            <div class="switch-option" data-format="card">
                                <i class="bi bi-card-text"></i>
                                <span>文卡消息</span>
                            </div>
                            <div class="switch-slider" id="formatToggleSlider"></div>
                        </div>
                    </div>
                    
                    <!-- 回复模式选择 -->
                    <div class="settings-section">
                        <div class="section-label">
                            <i class="bi bi-clock-fill"></i>
                            <span>回复模式</span>
                        </div>
                        <div class="format-switch" id="replyModeToggle" onclick="toggleReplyMode()">
                            <div class="switch-option" data-mode="instant">
                                <i class="bi bi-lightning-fill"></i>
                                <span>立即回复</span>
                            </div>
                            <div class="switch-option" data-mode="delayed">
                                <i class="bi bi-hourglass-split"></i>
                                <span>下一次回复</span>
                            </div>
                            <div class="switch-slider" id="replyModeSlider"></div>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted" id="replyModeHint">消息将立即发送</small>
                        </div>
                    </div>
                    
                    <!-- 文卡模板区域 -->
                    <div id="cardTemplateArea" class="settings-section" style="display: none;">
                        <div class="section-label">
                            <i class="bi bi-bookmark-star-fill"></i>
                            <span>文卡消息快捷模板</span>
                        </div>
                        
                        <!-- 已保存的模板 -->
                        <div id="cardTemplateList" class="template-list"></div>
                        
                        <!-- 新建模板区域 -->
                        <div class="template-creator">
                            <div class="creator-header">
                                <span class="creator-title">新建模板</span>
                                <button class="add-row-btn" onclick="addTemplateRow()">
                                    <i class="bi bi-plus-circle-fill"></i>
                                    添加行
                                </button>
                            </div>
                            
                            <div id="templateRowsContainer" class="rows-container"></div>
                            
                            <button class="save-template-btn" onclick="saveNewTemplate()">
                                <i class="bi bi-check-circle-fill"></i>
                                保存模板
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 延迟消息管理窗口 - 全新设计 -->
    <div class="modal fade" id="delayedMessagesModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content delayed-modal">
                <!-- 关闭按钮 -->
                <button type="button" class="delayed-modal-close" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i>
                </button>
                
                <!-- 用户信息区域 -->
                <div class="delayed-modal-user">
                    <div class="delayed-user-avatar" id="delayedUserAvatar">
                        <i class="bi bi-person-circle"></i>
                    </div>
                    <div class="delayed-user-id" id="delayedUserId">用户ID</div>
                </div>
                
                <!-- 消息列表区域 -->
                <div class="delayed-modal-body">
                    <div class="delayed-messages-hint">这里就是那些延迟消息内容</div>
                    <div id="delayedMessagesList" class="delayed-messages-list"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS - 延迟加载 -->
    <script src="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js" defer></script>
    
    <script>
        const appid = '<?php echo $appid; ?>';
        const botQQ = '<?php echo $botQQ; ?>';
        let botName = '机器人'; // 默认值，将从API获取
        let botAvatar = ''; // 默认值，将从API获取
        let currentLogFile = '';
        let currentChatType = 'private'; // 默认显示好友（私聊）
        let currentChatId = '';
        let groups = [];
        let privates = [];
        let allMessages = []; // 存储所有消息用于搜索
        let searchKeyword = ''; // 当前搜索关键词
        let autoRefreshTimer = null; // 自动刷新定时器
        let autoRefreshInterval = 5000; // 自动刷新间隔（毫秒）
        let lastMessageCount = 0; // 上次的消息数量，用于检测新消息
        let currentSendFormat = 'text'; // 当前发送格式：text(普通文字) 或 card(文卡)
        let longPressTimer = null; // 长按定时器
        let cardTemplates = []; // 文卡模板列表
        let templateRowCounter = 0; // 模板行计数器
        let currentReplyMode = 'instant'; // 当前回复模式：instant(立即回复) 或 delayed(下一次回复)
        let avatarLongPressTimer = null; // 头像长按定时器
        let currentTargetUserId = ''; // 群聊模式下的目标用户ID
        let modalTargetUserId = ''; // 延迟消息管理窗口的目标用户ID（用于群聊中查看特定用户的延迟消息）

        document.addEventListener('DOMContentLoaded', function() {
            // 关键功能：立即执行
            bindEvents(); // 绑定事件（必须先执行，否则无法交互）
            loadLogFiles(); // 加载日志文件列表（用户需要立即看到）
            
            // 手机端默认显示好友列表（必须同步执行）
            const isMobile = window.innerWidth <= 768;
            if (isMobile) {
                currentChatType = 'private';
                // 确保好友按钮是激活状态（包括手机端切换按钮）
                const privateTabs = document.querySelectorAll('.mobile-chat-type-buttons .btn[data-tab="private"], .chat-type-buttons .btn[data-tab="private"]');
                privateTabs.forEach(tab => {
                    document.querySelectorAll('.mobile-chat-type-buttons .btn, .chat-type-buttons .btn').forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                });
            }
            
            // 非关键功能：延迟执行（使用requestIdleCallback或setTimeout）
            if ('requestIdleCallback' in window) {
                requestIdleCallback(() => {
                    loadBotInfo(); // 加载机器人信息（不影响核心功能）
                    loadSendFormat(); // 加载发送格式设置
                    loadReplyMode(); // 加载回复模式设置
                });
            } else {
                setTimeout(() => {
                    loadBotInfo();
                    loadSendFormat();
                    loadReplyMode();
                }, 100);
            }
            
            // 延迟消息窗口关闭时清除modalTargetUserId
            const delayedModal = document.getElementById('delayedMessagesModal');
            if (delayedModal) {
                delayedModal.addEventListener('hidden.bs.modal', function() {
                    modalTargetUserId = '';
                });
            }
        });

        // 加载机器人信息（与main.php保持一致的方式）
        function loadBotInfo() {
            if (!botQQ) return;
            
            fetch(`api/robot_info.php?type=get_info&appid=${encodeURIComponent(appid)}&qq_number=${encodeURIComponent(botQQ)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.code === 200 && data.data) {
                        const robotInfo = data.data;
                        // 更新名字（如果API返回了更准确的名称）
                        if (robotInfo.name && robotInfo.name !== '未知机器人') {
                            botName = robotInfo.name;
                        }
                        // 更新头像（如果API返回了头像）
                        if (robotInfo.avatar && robotInfo.avatar.trim() !== '') {
                            botAvatar = robotInfo.avatar;
                        }
                        // 如果当前有显示的消息，重新渲染以更新头像和名字
                        if (currentChatId) {
                            loadMessages(currentChatType, currentChatId);
                        }
                    }
                })
                .catch(error => {
                    console.error('获取机器人信息失败:', error);
                });
        }

        // 启动自动刷新
        function startAutoRefresh() {
            stopAutoRefresh(); // 先停止之前的定时器
            if (currentChatId && currentLogFile) {
                autoRefreshTimer = setInterval(() => {
                    // 静默检查新消息
                    checkNewMessages();
                }, autoRefreshInterval);
                console.log('自动刷新已启动，间隔: ' + (autoRefreshInterval / 1000) + '秒');
            }
        }

        // 检查并增量添加新消息
        function checkNewMessages() {
            if (!currentChatId || !currentLogFile) return;

            fetch(`api/chat.php?type=messages&appid=${encodeURIComponent(appid)}&name=${encodeURIComponent(currentLogFile)}&chat_type=${currentChatType}&chat_id=${encodeURIComponent(currentChatId)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.code === 200) {
                        const newMessages = data.messages || [];
                        const newCount = newMessages.length;
                        
                        // 只有消息数量增加时才更新（有新消息）
                        if (newCount > lastMessageCount) {
                            const addedMessages = newMessages.slice(lastMessageCount);
                            appendNewMessages(addedMessages);
                            lastMessageCount = newCount;
                            console.log(`检测到 ${addedMessages.length} 条新消息`);
                        }
                    }
                })
                .catch(error => {
                    console.error('检查新消息失败:', error);
                });
        }

        // 停止自动刷新
        function stopAutoRefresh() {
            if (autoRefreshTimer) {
                clearInterval(autoRefreshTimer);
                autoRefreshTimer = null;
                console.log('自动刷新已停止');
            }
        }

        function bindEvents() {
            const isMobile = window.innerWidth <= 768;
            
            // PC端日志文件选择
            const logFileSelect = document.getElementById('logFileSelect');
            if (logFileSelect) {
                logFileSelect.addEventListener('change', function() {
                    stopAutoRefresh(); // 切换日志文件时停止自动刷新
                    currentLogFile = this.value;
                    currentChatId = ''; // 清空当前选中的聊天
                    lastMessageCount = 0; // 重置消息计数
                    // 清空搜索
                    const searchInput = document.getElementById('searchInput');
                    if (searchInput) {
                        searchInput.value = '';
                        searchKeyword = '';
                    }
                    if (currentLogFile) {
                        loadChatList();
                    } else {
                        showEmptyChatList();
                    }
                });
            }
            
            // 手机端日志文件选择
            const mobileLogFileSelect = document.getElementById('mobileLogFileSelect');
            if (mobileLogFileSelect) {
                mobileLogFileSelect.addEventListener('change', function() {
                    stopAutoRefresh(); // 切换日志文件时停止自动刷新
                    currentLogFile = this.value;
                    currentChatId = ''; // 清空当前选中的聊天
                    lastMessageCount = 0; // 重置消息计数
                    // 清空搜索
                    const mobileSearchInput = document.getElementById('mobileSearchInput');
                    if (mobileSearchInput) {
                        mobileSearchInput.value = '';
                        searchKeyword = '';
                    }
                    if (currentLogFile) {
                        loadChatList();
                    } else {
                        showEmptyChatList();
                    }
                });
            }
            
            // PC端搜索框
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    searchKeyword = this.value.trim();
                    performSearch();
                });
                
                // 支持回车键搜索
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        searchKeyword = this.value.trim();
                        performSearch();
                    }
                });
            }
            
            // 手机端搜索框
            const mobileSearchInput = document.getElementById('mobileSearchInput');
            if (mobileSearchInput) {
                mobileSearchInput.addEventListener('input', function() {
                    searchKeyword = this.value.trim();
                    performSearch();
                });
                
                // 支持回车键搜索
                mobileSearchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        searchKeyword = this.value.trim();
                        performSearch();
                    }
                });
            }

            // PC端和手机端标签页切换
            const allTabs = document.querySelectorAll('.sidebar-tab, .chat-type-buttons .btn, .mobile-chat-type-buttons .btn');
            if (allTabs.length > 0) {
                allTabs.forEach(tab => {
                    tab.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const tabType = this.dataset.tab || 
                            (this.id === 'btn-users' || this.id === 'mobile-btn-users' ? 'private' : 
                             (this.id === 'btn-groups' || this.id === 'mobile-btn-groups' ? 'group' : 'private'));
                        
                        // 更新所有按钮组的状态
                        document.querySelectorAll('.sidebar-tab, .chat-type-buttons .btn, .mobile-chat-type-buttons .btn').forEach(t => {
                            if (t === this || (t.dataset.tab === tabType)) {
                                t.classList.add('active');
                            } else {
                                t.classList.remove('active');
                            }
                        });
                        
                        currentChatType = tabType;
                        // 如果有搜索关键词，重新搜索；否则渲染列表
                        if (searchKeyword) {
                            performSearch();
                        } else {
                            renderChatList();
                        }
                    });
                });
            }

            // 优化菜单按钮响应
            const menuToggle = document.getElementById('menuToggle');
            if (menuToggle) {
                menuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const sidebar = document.getElementById('sidebar');
                    const overlay = document.getElementById('overlay');
                    const isOpen = sidebar.classList.contains('show');
                    
                    if (isOpen) {
                        sidebar.classList.remove('show');
                        overlay.classList.remove('show');
                    } else {
                        sidebar.classList.add('show');
                        overlay.classList.add('show');
                    }
                });
            }

            // 优化遮罩层点击响应
            const overlay = document.getElementById('overlay');
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    e.preventDefault();
                    const sidebar = document.getElementById('sidebar');
                    sidebar.classList.remove('show');
                    this.classList.remove('show');
                });
                
                // 防止侧边栏内的点击事件冒泡到遮罩层
                const sidebar = document.getElementById('sidebar');
                if (sidebar) {
                    sidebar.addEventListener('click', function(e) {
                        e.stopPropagation();
                    });
                }
            }
            
            // 发送按钮长按事件
            const sendBtn = document.getElementById('sendBtn');
            if (sendBtn) {
                sendBtn.addEventListener('mousedown', function(e) {
                    // 已改为输入框快捷设置，禁用长按弹层
                });
                
                sendBtn.addEventListener('mouseup', function(e) {
                    if (longPressTimer) {
                        clearTimeout(longPressTimer);
                        longPressTimer = null;
                    }
                });
                
                sendBtn.addEventListener('mouseleave', function(e) {
                    if (longPressTimer) {
                        clearTimeout(longPressTimer);
                        longPressTimer = null;
                    }
                });
                
                // 触摸设备支持
                sendBtn.addEventListener('touchstart', function(e) {
                    // 已改为输入框快捷设置，禁用长按弹层
                });
                
                sendBtn.addEventListener('touchend', function(e) {
                    if (longPressTimer) {
                        clearTimeout(longPressTimer);
                        longPressTimer = null;
                    }
                });
            }
        }
        
        // 显示格式选择器
        function showFormatSelector() {
            // 按宿主要求：不再使用抽屉/弹层设置
            refreshQuickSendTools();
        }
        // 刷新输入框快捷设置控件状态
        function refreshQuickSendTools() {
            const typeSelect = document.getElementById('sendTypeSelect');
            if (typeSelect) typeSelect.value = currentSendFormat;
        }
        
        // 下拉选择发送格式
        function onSendTypeChange(format) {
            selectFormat(format);
        }
        
        // 切换格式
        function toggleFormat() {
            let newFormat = 'text';
            if (currentSendFormat === 'text') newFormat = 'card';
            else if (currentSendFormat === 'card') newFormat = 'native_md';
            else newFormat = 'text';
            selectFormat(newFormat);
        }
        
        // 更新开关UI
        function updateToggleUI() {
            const track = document.getElementById('formatToggle');
            const options = track.querySelectorAll('.switch-option');
            
            track.setAttribute('data-active', currentSendFormat);
            
            options.forEach(opt => {
                if (opt.getAttribute('data-format') === currentSendFormat) {
                    opt.classList.add('active');
                } else {
                    opt.classList.remove('active');
                }
            });
            
            // 显示/隐藏文卡模板区域
            const templateArea = document.getElementById('cardTemplateArea');
            if (currentSendFormat === 'card') {
                templateArea.style.display = 'block';
                loadCardTemplates();
                // 初始化时添加一行
                const container = document.getElementById('templateRowsContainer');
                if (container && container.children.length === 0) {
                    addTemplateRow();
                }
            } else {
                templateArea.style.display = 'none';
            }
        }
        
        // 选择发送格式
        function selectFormat(format) {
            currentSendFormat = format;
            const sendBtnText = document.getElementById('sendBtnText');
            
            if (format === 'text') {
                if (sendBtnText) sendBtnText.textContent = '发送';
            } else if (format === 'card') {
                if (sendBtnText) sendBtnText.textContent = '发送卡片';
            } else if (format === 'native_md') {
                if (sendBtnText) sendBtnText.textContent = '发送MD';
            }
            
            // 更新开关UI
            updateToggleUI();
            
            // 保存设置到服务器
            saveSendFormat(format);
            refreshQuickSendTools();
        }
        
        // 保存发送格式设置
        function saveSendFormat(format) {
            fetch(`api/chat.php?type=save_format&appid=${encodeURIComponent(appid)}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `format=${format}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.code === 200) {
                    console.log('发送格式已保存');
                }
            })
            .catch(error => {
                console.error('保存设置失败:', error);
            });
        }
        
        // 读取发送格式设置
        function loadSendFormat() {
            fetch(`api/chat.php?type=get_format&appid=${encodeURIComponent(appid)}`)
            .then(response => response.json())
            .then(data => {
                if (data.code === 200 && data.format) {
                    currentSendFormat = data.format;
                    const sendBtnText = document.getElementById('sendBtnText');
                    if (sendBtnText) {
                        sendBtnText.textContent = data.format === 'card' ? '发送卡片' : (data.format === 'native_md' ? '发送MD' : '发送');
                    }
                    refreshQuickSendTools();
                }
            })
            .catch(error => {
                console.error('读取设置失败:', error);
            });
        }
        
        // 加载文卡模板
        function loadCardTemplates() {
            fetch(`api/chat.php?type=get_templates&appid=${encodeURIComponent(appid)}`)
            .then(response => response.json())
            .then(data => {
                if (data.code === 200 && data.templates) {
                    cardTemplates = data.templates;
                    renderCardTemplates();
                }
            })
            .catch(error => {
                console.error('读取模板失败:', error);
            });
        }
        
        // 渲染文卡模板列表
        function renderCardTemplates() {
            const listContainer = document.getElementById('cardTemplateList');
            if (!listContainer) return;
            
            if (cardTemplates.length === 0) {
                listContainer.innerHTML = '';
                return;
            }
            
            listContainer.innerHTML = cardTemplates.map((template, index) => {
                // template.items 是行数组
                const items = template.items || [];
                const itemsText = items.map(item => {
                    const hasLink = item.url && item.url.trim() !== '';
                    return hasLink ? `🔗 ${item.text}` : item.text;
                }).join(' | ');
                
                const hasAnyLink = items.some(item => item.url && item.url.trim() !== '');
                
                return `
                    <span class="card-template-tag ${hasAnyLink ? 'has-link' : ''}" onclick="useTemplate(${index})" title="${escapeHtml(itemsText)}">
                        ${items.length > 1 ? `<span style="background: rgba(255,255,255,0.2); padding: 3px 8px; border-radius: 10px; font-size: 11px; margin-right: 4px;">${items.length}行</span>` : ''}
                        ${escapeHtml(items[0]?.text || '空')}${items.length > 1 ? '...' : ''}
                        <span class="template-delete" onclick="event.stopPropagation(); deleteTemplate(${index})">×</span>
                    </span>
                `;
            }).join('');
        }
        
        // 添加模板行
        function addTemplateRow() {
            templateRowCounter++;
            const container = document.getElementById('templateRowsContainer');
            const rowNumber = container.children.length + 1;
            
            const rowHtml = `
                <div class="template-row" id="templateRow${templateRowCounter}">
                    <div class="template-row-number">${rowNumber}</div>
                    <div class="template-row-delete" onclick="removeTemplateRow(${templateRowCounter})">×</div>
                    <input type="text" class="row-text" placeholder="文字内容...">
                    <input type="text" class="row-url" placeholder="链接地址（可选）">
                </div>
            `;
            container.insertAdjacentHTML('beforeend', rowHtml);
            updateRowNumbers();
        }
        
        // 删除模板行
        function removeTemplateRow(id) {
            const row = document.getElementById(`templateRow${id}`);
            if (row) {
                row.remove();
                updateRowNumbers();
            }
        }
        
        // 更新行号
        function updateRowNumbers() {
            const container = document.getElementById('templateRowsContainer');
            const rows = container.querySelectorAll('.template-row');
            rows.forEach((row, index) => {
                const numberEl = row.querySelector('.template-row-number');
                if (numberEl) {
                    numberEl.textContent = index + 1;
                }
            });
        }
        
        // 保存新模板
        function saveNewTemplate() {
            const container = document.getElementById('templateRowsContainer');
            const rows = container.querySelectorAll('.template-row');
            
            if (rows.length === 0) {
                alert('请至少添加一行内容');
                return;
            }
            
            const items = [];
            let hasContent = false;
            
            rows.forEach(row => {
                const text = row.querySelector('.row-text').value.trim();
                const url = row.querySelector('.row-url').value.trim();
                
                if (text) {
                    hasContent = true;
                    items.push({ text, url });
                }
            });
            
            if (!hasContent) {
                alert('请至少输入一行文字内容');
                return;
            }
            
            const template = { items };
            cardTemplates.push(template);
            saveCardTemplates();
            renderCardTemplates();
            
            // 清空所有行
            container.innerHTML = '';
            templateRowCounter = 0;
            addTemplateRow(); // 重新添加一行
        }
        
        // 删除模板
        function deleteTemplate(index) {
            if (confirm('确定删除这个模板吗？')) {
                cardTemplates.splice(index, 1);
                saveCardTemplates();
                renderCardTemplates();
            }
        }
        
        // 使用模板（插入到输入框）
        function useTemplate(index) {
            const template = cardTemplates[index];
            const input = document.getElementById('sendInput');
            if (input && template.items) {
                // 将多行内容格式化
                const content = template.items.map(item => {
                    let line = item.text;
                    if (item.url && item.url.trim() !== '') {
                        line += `\n链接: ${item.url}`;
                    }
                    return line;
                }).join('\n---\n'); // 使用 --- 分隔不同行
                
                input.value = content;
                input.focus();
            }
            
            // 关闭模态框
            const modal = bootstrap.Modal.getInstance(document.getElementById('formatSelectorModal'));
            if (modal) {
                modal.hide();
            }
        }
        
        // 保存模板到服务器
        function saveCardTemplates() {
            fetch(`api/chat.php?type=save_templates&appid=${encodeURIComponent(appid)}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ templates: cardTemplates })
            })
            .then(response => response.json())
            .then(data => {
                if (data.code === 200) {
                    console.log('模板已保存');
                }
            })
            .catch(error => {
                console.error('保存模板失败:', error);
            });
        }
        
        // HTML转义
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // 切换回复模式
        function toggleReplyMode() {
            const newMode = currentReplyMode === 'instant' ? 'delayed' : 'instant';
            selectReplyMode(newMode);
        }
        
        // 选择回复模式
        function selectReplyMode(mode) {
            currentReplyMode = mode;
            updateReplyModeUI();
            saveReplyMode(mode);
            refreshQuickSendTools();
        }
        
        // 更新回复模式UI
        function updateReplyModeUI() {
            const track = document.getElementById('replyModeToggle');
            const options = track.querySelectorAll('.switch-option');
            const hint = document.getElementById('replyModeHint');
            
            track.setAttribute('data-active', currentReplyMode);
            
            options.forEach(opt => {
                if (opt.getAttribute('data-mode') === currentReplyMode) {
                    opt.classList.add('active');
                } else {
                    opt.classList.remove('active');
                }
            });
            
            // 更新提示文字
            if (currentReplyMode === 'instant') {
                hint.textContent = '消息将立即发送';
            } else {
                hint.textContent = '消息将在下一次该用户触发指令时发送（24小时内有效）';
            }
        }
        
        // 保存回复模式
        function saveReplyMode(mode) {
            fetch(`api/chat.php?type=save_reply_mode&appid=${encodeURIComponent(appid)}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `mode=${mode}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.code === 200) {
                    console.log('回复模式已保存');
                }
            })
            .catch(error => {
                console.error('保存回复模式失败:', error);
            });
        }
        
        // 读取回复模式
        function loadReplyMode() {
            fetch(`api/chat.php?type=get_reply_mode&appid=${encodeURIComponent(appid)}`)
            .then(response => response.json())
            .then(data => {
                if (data.code === 200 && data.mode) {
                    currentReplyMode = data.mode;
                    updateReplyModeUI();
                    refreshQuickSendTools();
                }
            })
            .catch(error => {
                console.error('读取回复模式失败:', error);
            });
        }
        
        // 打开延迟消息管理窗口
        function showDelayedMessagesModal() {
            // 按宿主要求：禁用延迟消息抽屉/弹窗
            if (typeof showNotification === 'function') {
                showNotification('已关闭弹窗模式：请使用输入框附近快捷设置', true);
            }
            return;
        }
        
        // 更新模态框中的用户信息
        function updateDelayedModalUserInfo() {
            console.log('=== 更新模态框用户信息 ===');
            console.log('currentChatType:', currentChatType);
            console.log('currentChatId(群聊ID):', currentChatId);
            console.log('modalTargetUserId(应该是用户ID):', modalTargetUserId);
            
            const avatarEl = document.getElementById('delayedUserAvatar');
            const userIdEl = document.getElementById('delayedUserId');
            
            if (!avatarEl || !userIdEl) return;
            
            // 更新用户ID显示
            if (currentChatType === 'group' && modalTargetUserId) {
                // 群聊模式且有特定用户：显示两行（群聊ID + 个人ID）
                userIdEl.innerHTML = `
                    <div style="color: #999; font-size: 12px; font-weight: 400; margin-bottom: 6px;">群聊 ${currentChatId}</div>
                    <div style="color: #333; font-size: 12px; font-weight: 600;">用户 ${modalTargetUserId}</div>
                `;
            } else if (currentChatType === 'group') {
                // 群聊模式但没有特定用户：只显示群聊ID
                userIdEl.innerHTML = `<div style="color: #333; font-size: 12px; font-weight: 600;">群聊 ${currentChatId}</div>`;
            } else {
                // 私聊模式
                userIdEl.innerHTML = `<div style="color: #333; font-size: 12px; font-weight: 600;">私聊 ${currentChatId}</div>`;
            }
            
            // 尝试获取并显示头像
            let avatarUrl = '';
            
            // 群聊模式：只在有特定用户时才显示用户头像
            if (currentChatType === 'group' && modalTargetUserId) {
                console.log('查找用户头像，用户ID:', modalTargetUserId);
                
                // 从消息容器中查找该用户的头像
                const messagesContainer = document.getElementById('messagesContainer');
                if (messagesContainer) {
                    // 查找所有消息头像容器（正确的类名）
                    const avatarContainers = messagesContainer.querySelectorAll('.message-avatar-container');
                    console.log('找到头像容器数量:', avatarContainers.length);
                    
                    for (let container of avatarContainers) {
                        const userId = container.getAttribute('data-user-id');
                        console.log('容器的user-id:', userId);
                        
                        // 严格匹配用户ID（使用modalTargetUserId，绝不用currentChatId）
                        if (userId && userId === modalTargetUserId) {
                            const avatarImg = container.querySelector('img.message-avatar');
                            if (avatarImg && avatarImg.src) {
                                avatarUrl = avatarImg.src;
                                console.log('✅ 找到用户头像:', modalTargetUserId, avatarUrl);
                                break;
                            }
                        }
                    }
                    
                    // 如果没找到，不要fallback到群聊头像，直接使用默认图标
                    if (!avatarUrl) {
                        console.log('❌ 未找到用户头像，使用默认图标. 用户ID:', modalTargetUserId);
                    }
                }
            } else if (currentChatType === 'group') {
                // 群聊整体：使用群聊头像
                const chatListItem = document.querySelector(`[data-chat-id="${currentChatId}"]`);
                if (chatListItem) {
                    const avatarImg = chatListItem.querySelector('.chat-avatar, .chat-avatar-placeholder img');
                    if (avatarImg && avatarImg.src) {
                        avatarUrl = avatarImg.src;
                    }
                }
            } else {
                // 私聊模式：使用用户头像
                const chatListItem = document.querySelector(`[data-chat-id="${currentChatId}"]`);
                if (chatListItem) {
                    const avatarImg = chatListItem.querySelector('.chat-avatar, .chat-avatar-placeholder img');
                    if (avatarImg && avatarImg.src) {
                        avatarUrl = avatarImg.src;
                    }
                }
            }
            
            // 设置头像
            if (avatarUrl && avatarUrl !== '' && !avatarUrl.includes('undefined')) {
                avatarEl.innerHTML = `<img src="${avatarUrl}" alt="头像">`;
            } else {
                // 使用默认图标
                avatarEl.innerHTML = '<i class="bi bi-person-circle"></i>';
            }
        }
        
        // 加载延迟消息列表
        function loadDelayedMessages() {
            if (!currentChatId) return;
            
            // 构建查询URL
            let url = `api/chat.php?type=get_delayed_messages&appid=${encodeURIComponent(appid)}&chat_type=${currentChatType}&chat_id=${currentChatId}`;
            
            // 如果是查看特定用户的延迟消息（群聊模式），添加user_id参数
            if (modalTargetUserId) {
                url += `&user_id=${encodeURIComponent(modalTargetUserId)}`;
            }
            
            fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.code === 200) {
                    renderDelayedMessages(data.messages || []);
                }
            })
            .catch(error => {
                console.error('加载延迟消息失败:', error);
            });
        }
        
        // 渲染延迟消息列表
        function renderDelayedMessages(messages) {
            const container = document.getElementById('delayedMessagesList');
            const hintEl = document.querySelector('.delayed-messages-hint');
            if (!container) return;
            
            if (messages.length === 0) {
                container.innerHTML = '<div class="no-messages"><i class="bi bi-inbox"></i><p>暂无延迟消息</p></div>';
                if (hintEl) hintEl.style.display = 'none';
                return;
            }
            
            if (hintEl) hintEl.style.display = 'none';
            
            container.innerHTML = messages.map((msg, index) => {
                const expiresTime = new Date(msg.expires_at * 1000);
                const now = new Date();
                const remainingHours = Math.max(0, Math.floor((expiresTime - now) / (1000 * 60 * 60)));
                const remainingMinutes = Math.max(0, Math.floor((expiresTime - now) / (1000 * 60)) % 60);
                
                return `
                    <div class="delayed-msg-card" data-msg-id="${msg.id}">
                        <div class="delayed-msg-content">${escapeHtml(msg.content)}</div>
                        <div class="delayed-msg-meta">
                            <span class="delayed-msg-time">
                                <i class="bi bi-clock"></i> ${remainingHours}h ${remainingMinutes}m
                            </span>
                            <span class="delayed-msg-method">${msg.send_method === 'card' ? '文卡' : (msg.send_method === 'native_md' ? '原生MD' : '文字')}</span>
                        </div>
                        <div class="delayed-msg-actions">
                            <button class="delayed-btn delayed-btn-send" onclick="sendDelayedMessageNow('${msg.id}')">
                                <i class="bi bi-send-fill"></i> 立即发送
                            </button>
                            <button class="delayed-btn delayed-btn-delete" onclick="deleteDelayedMessage('${msg.id}')">
                                <i class="bi bi-trash3-fill"></i>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        // 立即发送延迟消息
        function sendDelayedMessageNow(messageId) {
            if (!confirm('确定要立即发送这条消息吗？')) return;
            
            fetch(`api/chat.php?type=send_delayed_message&appid=${encodeURIComponent(appid)}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `message_id=${messageId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.code === 200) {
                    alert('消息发送成功！');
                    loadDelayedMessages(); // 刷新列表
                    checkNewMessages(); // 刷新聊天消息
                } else {
                    alert('发送失败：' + (data.msg || '未知错误'));
                }
            })
            .catch(error => {
                console.error('发送消息失败:', error);
                alert('发送失败：网络错误');
            });
        }
        
        // 删除延迟消息
        function deleteDelayedMessage(messageId) {
            if (!confirm('确定要删除这条延迟消息吗？')) return;
            
            fetch(`api/chat.php?type=delete_delayed_message&appid=${encodeURIComponent(appid)}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `message_id=${messageId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.code === 200) {
                    loadDelayedMessages(); // 刷新列表
                } else {
                    alert('删除失败：' + (data.msg || '未知错误'));
                }
            })
            .catch(error => {
                console.error('删除消息失败:', error);
                alert('删除失败：网络错误');
            });
        }

        function loadLogFiles() {
            const select = document.getElementById('logFileSelect');
            const mobileSelect = document.getElementById('mobileLogFileSelect');
            
            if (select) {
                select.disabled = true;
                select.innerHTML = '<option value="">加载中...</option>';
            }
            if (mobileSelect) {
                mobileSelect.disabled = true;
                mobileSelect.innerHTML = '<option value="">加载中...</option>';
            }

            fetch(`api/log.php?type=list&appid=${encodeURIComponent(appid)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.code === 200 && data.list && data.list.length > 0) {
                        const options = data.list.map(file => {
                            const option = document.createElement('option');
                            option.value = file;
                            option.textContent = file;
                            return option;
                        });
                        
                        if (select) {
                            select.innerHTML = '';
                            options.forEach(opt => select.appendChild(opt.cloneNode(true)));
                        }
                        if (mobileSelect) {
                            mobileSelect.innerHTML = '';
                            options.forEach(opt => mobileSelect.appendChild(opt.cloneNode(true)));
                        }

                        // 默认选择今天的日志
                        const today = new Date();
                        const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}.log`;
                        if (data.list.includes(todayStr)) {
                            if (select) select.value = todayStr;
                            if (mobileSelect) mobileSelect.value = todayStr;
                            currentLogFile = todayStr;
                            loadChatList();
                        }
                    } else {
                        if (select) select.innerHTML = '<option value="">没有可用的日志文件</option>';
                        if (mobileSelect) mobileSelect.innerHTML = '<option value="">没有可用的日志文件</option>';
                        showEmptyChatList();
                    }
                })
                .catch(error => {
                    console.error('加载日志文件列表失败:', error);
                    if (select) select.innerHTML = '<option value="">加载失败，请刷新重试</option>';
                    if (mobileSelect) mobileSelect.innerHTML = '<option value="">加载失败，请刷新重试</option>';
                })
                .finally(() => {
                    if (select) select.disabled = false;
                    if (mobileSelect) mobileSelect.disabled = false;
                });
        }

        function loadChatList() {
            if (!currentLogFile) {
                showEmptyChatList();
                return;
            }

            // 如果有搜索关键词，执行搜索
            if (searchKeyword) {
                performSearch();
                return;
            }

            const isMobile = window.innerWidth <= 768;
            const chatList = isMobile ? document.getElementById('mobileChatList') : document.getElementById('chatList');
            if (chatList) {
                chatList.innerHTML = '<div class="text-center p-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">加载中...</span></div></div>';
            }

            fetch(`api/chat.php?type=list&appid=${encodeURIComponent(appid)}&name=${encodeURIComponent(currentLogFile)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.code === 200) {
                        groups = data.groups || [];
                        privates = data.privates || [];
                        renderChatList();
                    } else {
                        showEmptyChatList();
                    }
                })
                .catch(error => {
                    console.error('加载聊天列表失败:', error);
                    showEmptyChatList();
                });
        }
        
        // 执行搜索
        function performSearch() {
            if (!currentLogFile || !searchKeyword) {
                // 如果没有搜索关键词，加载正常列表
                loadChatList();
                return;
            }

            const isMobile = window.innerWidth <= 768;
            const chatList = isMobile ? document.getElementById('mobileChatList') : document.getElementById('chatList');
            if (chatList) {
                chatList.innerHTML = '<div class="text-center p-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">搜索中...</span></div></div>';
            }

            const chatTypeParam = currentChatType === 'group' ? 'group' : (currentChatType === 'private' ? 'private' : '');
            
            fetch(`api/chat.php?type=search&appid=${encodeURIComponent(appid)}&name=${encodeURIComponent(currentLogFile)}&keyword=${encodeURIComponent(searchKeyword)}&chat_type=${chatTypeParam}`)
                .then(response => response.json())
                .then(data => {
                    if (data.code === 200) {
                        const results = data.results || [];
                        // 将搜索结果按类型分组
                        groups = results.filter(r => r.type === 'group');
                        privates = results.filter(r => r.type === 'private');
                        renderChatList();
                    } else {
                        if (chatList) {
                            chatList.innerHTML = `
                                <div class="empty-state">
                                    <i class="fas fa-search"></i>
                                    <p>搜索失败</p>
                                </div>
                            `;
                        }
                    }
                })
                .catch(error => {
                    console.error('搜索失败:', error);
                    if (chatList) {
                        chatList.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-exclamation-circle"></i>
                                <p>搜索失败: ${error.message}</p>
                            </div>
                        `;
                    }
                });
        }

        function renderChatList() {
            const isMobile = window.innerWidth <= 768;
            const chatList = isMobile ? document.getElementById('mobileChatList') : document.getElementById('chatList');
            const list = currentChatType === 'group' ? groups : privates;

            if (!chatList) return;

            if (list.length === 0) {
                const emptyText = searchKeyword 
                    ? `未找到匹配"${searchKeyword}"的${currentChatType === 'group' ? '群聊' : '私聊'}记录`
                    : `暂无${currentChatType === 'group' ? '群聊' : '私聊'}记录`;
                chatList.innerHTML = `
                    <div class="text-center p-3 text-muted">
                        <i class="bi bi-${searchKeyword ? 'search' : 'chat-dots'}" style="font-size: 2rem;"></i>
                        <p class="mt-2">${emptyText}</p>
                    </div>
                `;
                return;
            }

            let html = '';
            list.forEach(chat => {
                const avatarUrl = currentChatType === 'group' 
                    ? `https://p.qlogo.cn/gh/${chat.id}/${chat.id}/0`
                    : `https://q.qlogo.cn/qqapp/${appid}/${chat.id}/5`;
                
                const preview = chat.last_message || '暂无消息';
                const time = formatTime(chat.last_message_time);
                
                // 格式化ID显示：前8位 + ***
                const displayId = chat.id.length > 8 ? chat.id.substring(0, 8) + '***' : chat.id;
                
                html += `
                    <div class="list-group-item chat-item" data-type="${chat.type}" data-id="${chat.id}">
                        <div class="d-flex align-items-center">
                            <div class="me-3 avatar-container" data-chat-id="${chat.id}" data-chat-type="${chat.type}">
                                ${currentChatType === 'group' 
                                    ? `<div class="chat-avatar-placeholder">${chat.id[0].toUpperCase()}</div>`
                                    : `<img src="${avatarUrl}" class="chat-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                       <div class="chat-avatar-placeholder" style="display: none;">${chat.id.slice(-2).toUpperCase()}</div>`
                                }
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">
                                    <span class="chat-name" data-user-id="${chat.id}">${chat.id}</span>
                                    <small class="text-muted ms-2">${time}</small>
                                </h6>
                                <small class="text-muted">
                                    ${escapeHtml(preview.substring(0, 30))}${preview.length > 30 ? '...' : ''}
                                </small>
                            </div>
                        </div>
                    </div>
                `;
            });

            chatList.innerHTML = html;

            // 如果是私聊，批量获取用户昵称
            if (currentChatType === 'private' && list.length > 0) {
                const userIds = list.map(chat => chat.id);
                loadNicknamesBatch(userIds).then(nicknames => {
                    document.querySelectorAll('.chat-item').forEach(item => {
                        const chatId = item.dataset.id;
                        const nameElement = item.querySelector('.chat-name');
                        if (nameElement && nicknames[chatId]) {
                            nameElement.textContent = nicknames[chatId];
                        }
                    });
                });
            }

            // 优化聊天项点击响应
            document.querySelectorAll('.chat-item').forEach(item => {
                // 添加触摸反馈
                item.addEventListener('touchstart', function() {
                    this.style.opacity = '0.7';
                }, { passive: true });
                
                item.addEventListener('touchend', function() {
                    this.style.opacity = '';
                }, { passive: true });
                
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    document.querySelectorAll('.chat-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                    
                    const chatType = this.dataset.type;
                    const chatId = this.dataset.id;
                    currentChatId = chatId;
                    lastMessageCount = 0; // 切换聊天时重置消息计数
                    loadMessages(chatType, chatId);
                    
                    // 手机端切换到聊天内容视图
                    if (isMobile) {
                        const listContainer = document.getElementById('mobileChatListContainer');
                        const chatContent = document.querySelector('.chat-content');
                        if (listContainer) listContainer.classList.add('hide');
                        if (chatContent) chatContent.classList.add('show');
                    }
                });
            });
            
            // 绑定头像长按事件
            bindAvatarLongPressEvents();
        }
        
        // 设置群聊目标用户
        function setTargetUser(userId) {
            currentTargetUserId = userId;
            const hintDiv = document.getElementById('targetUserHint');
            const displaySpan = document.getElementById('targetUserIdDisplay');
            
            if (hintDiv && displaySpan) {
                displaySpan.textContent = userId;
                hintDiv.style.display = 'block';
            }
        }
        
        // 清除目标用户选择
        function clearTargetUser() {
            currentTargetUserId = '';
            const hintDiv = document.getElementById('targetUserHint');
            if (hintDiv) {
                hintDiv.style.display = 'none';
            }
        }
        
        // 为消息中的用户头像绑定事件（群聊：单击选择用户+长按管理，私聊：长按管理）
        function bindMessageAvatarEvents() {
            document.querySelectorAll('.message-avatar-container').forEach(avatarContainer => {
                const userId = avatarContainer.dataset.userId;
                const chatType = avatarContainer.dataset.chatType;
                
                // 移除旧的事件监听器（通过克隆）
                const newContainer = avatarContainer.cloneNode(true);
                avatarContainer.parentNode.replaceChild(newContainer, avatarContainer);
                
                if (chatType === 'group') {
                    // 群聊模式：单击选择目标用户，长按查看该用户的延迟消息
                    
                    // 单击事件（短按）
                    let clickTimer = null;
                    let longPressTriggered = false;
                    
                    newContainer.addEventListener('mousedown', function(e) {
                        e.stopPropagation();
                        longPressTriggered = false;
                        
                        // 长按定时器
                        avatarLongPressTimer = setTimeout(() => {
                            longPressTriggered = true;
                            // 长按：查看该用户的延迟消息
                            console.log('长按群聊用户头像，设置modalTargetUserId为:', userId);
                            console.log('当前currentChatId(群聊ID):', currentChatId);
                            modalTargetUserId = userId; // 设置要查看的用户ID（这必须是用户ID，不能是群聊ID）
                            showDelayedMessagesModal();
                        }, 800);
                    });
                    
                    newContainer.addEventListener('mouseup', function(e) {
                        e.stopPropagation();
                        if (avatarLongPressTimer) {
                            clearTimeout(avatarLongPressTimer);
                            avatarLongPressTimer = null;
                        }
                        
                        // 短按：选择目标用户
                        if (!longPressTriggered) {
                            setTargetUser(userId);
                        }
                    });
                    
                    newContainer.addEventListener('mouseleave', function() {
                        if (avatarLongPressTimer) {
                            clearTimeout(avatarLongPressTimer);
                            avatarLongPressTimer = null;
                        }
                    });
                    
                    // 触摸事件
                    newContainer.addEventListener('touchstart', function(e) {
                        e.stopPropagation();
                        longPressTriggered = false;
                        
                        avatarLongPressTimer = setTimeout(() => {
                            longPressTriggered = true;
                            console.log('[触摸]长按群聊用户头像，设置modalTargetUserId为:', userId);
                            console.log('[触摸]当前currentChatId(群聊ID):', currentChatId);
                            modalTargetUserId = userId; // 设置要查看的用户ID（这必须是用户ID，不能是群聊ID）
                            showDelayedMessagesModal();
                        }, 800);
                    }, { passive: true });
                    
                    newContainer.addEventListener('touchend', function(e) {
                        e.stopPropagation();
                        if (avatarLongPressTimer) {
                            clearTimeout(avatarLongPressTimer);
                            avatarLongPressTimer = null;
                        }
                        
                        if (!longPressTriggered) {
                            setTargetUser(userId);
                        }
                    }, { passive: true });
                    
                    // 添加视觉反馈
                    newContainer.style.cursor = 'pointer';
                    newContainer.title = '点击选择目标用户，长按管理延迟消息';
                } else {
                    // 私聊模式：长按打开延迟消息管理
                    // 鼠标事件
                    newContainer.addEventListener('mousedown', function(e) {
                        e.stopPropagation();
                        avatarLongPressTimer = setTimeout(() => {
                            modalTargetUserId = ''; // 私聊模式不需要特定用户ID
                            showDelayedMessagesModal();
                        }, 800);
                    });
                    
                    newContainer.addEventListener('mouseup', function(e) {
                        e.stopPropagation();
                        if (avatarLongPressTimer) {
                            clearTimeout(avatarLongPressTimer);
                            avatarLongPressTimer = null;
                        }
                    });
                    
                    newContainer.addEventListener('mouseleave', function() {
                        if (avatarLongPressTimer) {
                            clearTimeout(avatarLongPressTimer);
                            avatarLongPressTimer = null;
                        }
                    });
                    
                    // 触摸事件
                    newContainer.addEventListener('touchstart', function(e) {
                        e.stopPropagation();
                        avatarLongPressTimer = setTimeout(() => {
                            modalTargetUserId = ''; // 私聊模式不需要特定用户ID
                            showDelayedMessagesModal();
                        }, 800);
                    }, { passive: true });
                    
                    newContainer.addEventListener('touchend', function(e) {
                        e.stopPropagation();
                        if (avatarLongPressTimer) {
                            clearTimeout(avatarLongPressTimer);
                            avatarLongPressTimer = null;
                        }
                    }, { passive: true });
                    
                    newContainer.title = '长按管理延迟消息';
                }
            });
        }
        
        // 为头像绑定长按事件（聊天列表中的头像，代表整个聊天）
        function bindAvatarLongPressEvents() {
            document.querySelectorAll('.avatar-container').forEach(avatarContainer => {
                // 鼠标事件
                avatarContainer.addEventListener('mousedown', function(e) {
                    e.stopPropagation();
                    const chatId = this.dataset.chatId;
                    const chatType = this.dataset.chatType;
                    avatarLongPressTimer = setTimeout(() => {
                        currentChatId = chatId;
                        currentChatType = chatType;
                        modalTargetUserId = ''; // 聊天列表头像：查看整个聊天的延迟消息，不限定特定用户
                        showDelayedMessagesModal();
                    }, 800);
                });
                
                avatarContainer.addEventListener('mouseup', function(e) {
                    e.stopPropagation();
                    if (avatarLongPressTimer) {
                        clearTimeout(avatarLongPressTimer);
                        avatarLongPressTimer = null;
                    }
                });
                
                avatarContainer.addEventListener('mouseleave', function() {
                    if (avatarLongPressTimer) {
                        clearTimeout(avatarLongPressTimer);
                        avatarLongPressTimer = null;
                    }
                });
                
                // 触摸事件
                avatarContainer.addEventListener('touchstart', function(e) {
                    e.stopPropagation();
                    const chatId = this.dataset.chatId;
                    const chatType = this.dataset.chatType;
                    avatarLongPressTimer = setTimeout(() => {
                        currentChatId = chatId;
                        currentChatType = chatType;
                        modalTargetUserId = ''; // 聊天列表头像：查看整个聊天的延迟消息
                        showDelayedMessagesModal();
                    }, 800);
                }, { passive: true });
                
                avatarContainer.addEventListener('touchend', function(e) {
                    e.stopPropagation();
                    if (avatarLongPressTimer) {
                        clearTimeout(avatarLongPressTimer);
                        avatarLongPressTimer = null;
                    }
                }, { passive: true });
            });
        }

        function loadMessages(chatType, chatId, scrollToBottom = true) {
            const messagesContainer = document.getElementById('messagesContainer');
            const chatTitle = document.getElementById('chatTitle');
            const headerTitle = document.querySelector('.header-title');
            const header = document.querySelector('.header');
            const isMobile = window.innerWidth <= 768;
            
            if (!messagesContainer) return;
            
            // 切换聊天时清除目标用户选择
            clearTargetUser();
            
            // 只在首次加载时显示loading，自动刷新时不显示
            if (scrollToBottom) {
                messagesContainer.innerHTML = '<div class="text-center p-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">加载中...</span></div></div>';
            }
            
            // 更新手机端ID显示
            const mobileChatId = document.getElementById('mobile-chat-id');
            if (mobileChatId) {
                mobileChatId.textContent = `ID: ${chatId}`;
            }
            
            // 更新聊天ID徽章显示（显示类型）
            const chatIdBadge = document.getElementById('chat-id-display');
            if (chatIdBadge) {
                const typeText = chatType === 'group' ? '群聊' : '私聊';
                chatIdBadge.textContent = `${typeText} ID: ${chatId}`;
            }
            
            // 保存当前聊天ID用于复制
            window.currentChatId = chatId;
            window.currentChatType = chatType;
            
            // 手机端切换到聊天视图（仅首次）
            if (isMobile && scrollToBottom) {
                if (header) header.classList.add('show-chat');
                
                const listContainer = document.getElementById('mobileChatListContainer');
                const chatContent = document.querySelector('.chat-content');
                if (listContainer) listContainer.classList.add('hide');
                if (chatContent) chatContent.classList.add('show');
            }

             fetch(`api/chat.php?type=messages&appid=${encodeURIComponent(appid)}&name=${encodeURIComponent(currentLogFile)}&chat_type=${chatType}&chat_id=${encodeURIComponent(chatId)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.code === 200) {
                        renderMessages(data.messages || [], scrollToBottom);
                        // 首次加载成功后启动自动刷新
                        if (scrollToBottom) {
                            startAutoRefresh();
                        }
                    } else {
                        if (scrollToBottom) {
                            messagesContainer.innerHTML = `
                                <div class="text-center text-danger p-3">
                                    <i class="bi bi-exclamation-circle" style="font-size: 2rem;"></i>
                                    <p class="mt-2">加载消息失败</p>
                                </div>
                            `;
                        }
                    }
                })
                .catch(error => {
                    console.error('加载消息失败:', error);
                    if (scrollToBottom) {
                        messagesContainer.innerHTML = `
                            <div class="text-center text-danger p-3">
                                <i class="bi bi-exclamation-circle" style="font-size: 2rem;"></i>
                                <p class="mt-2">加载消息失败: ${error.message}</p>
                            </div>
                        `;
                    }
                });
        }

        // 增量添加新消息（不闪烁）
        function appendNewMessages(newMessages) {
            if (!newMessages || newMessages.length === 0) return;
            
            const messagesContainer = document.getElementById('messagesContainer');
            if (!messagesContainer) return;

            newMessages.forEach(msg => {
                const messageElement = createMessageElement(msg);
                messagesContainer.insertAdjacentHTML('beforeend', messageElement);
            });
            
            // 绑定新消息头像的长按事件
            bindMessageAvatarEvents();

            // 如果用户在底部附近，自动滚动到新消息
            const isNearBottom = messagesContainer.scrollHeight - messagesContainer.scrollTop - messagesContainer.clientHeight < 100;
            if (isNearBottom) {
                messagesContainer.scrollTo({
                    top: messagesContainer.scrollHeight,
                    behavior: 'smooth'
                });
            }
        }

        // 将 ![alt]() 空URL占位按顺序填充为附件图片URL
        function hydrateMdImagePlaceholders(text, imageUrls = []) {
            if (!text) return text || '';
            let idx = 0;
            return String(text).replace(/!\[([^\]]*)\]\(\s*\)/g, (m, alt) => {
                const url = imageUrls[idx++] || '';
                return url ? `![${alt}](${url})` : m;
            });
        }

        // 轻量MD渲染（安全：先转义后格式化）
        function renderMarkdownLite(text) {
            let html = escapeHtml(text || '');
            // code block
            html = html.replace(/```([\s\S]*?)```/g, '<pre class="md-pre"><code>$1</code></pre>');
            // inline code
            html = html.replace(/`([^`]+)`/g, '<code class="md-code">$1</code>');
            // heading
            html = html.replace(/^###\s+(.+)$/gm, '<h6 class="md-h3">$1</h6>');
            html = html.replace(/^##\s+(.+)$/gm, '<h5 class="md-h2">$1</h5>');
            html = html.replace(/^#\s+(.+)$/gm, '<h4 class="md-h1">$1</h4>');
            // image ![alt #90px #30px](url) 规则：前宽后高
            html = html.replace(/!\[([^\]]*)\]\((https?:\/\/[^\s)]+)?\)/g, (m, altRaw, url) => {
                const alt = (altRaw || "").trim();
                const sizeMatch = alt.match(/#(\d+)px(?:\s*#(\d+)px)?/i);
                let width = "";
                let height = "";
                let cleanAlt = alt;
                if (sizeMatch) {
                    width = sizeMatch[1] ? `width:${sizeMatch[1]}px;` : "";
                    height = sizeMatch[2] ? `height:${sizeMatch[2]}px;` : "";
                    cleanAlt = alt.replace(sizeMatch[0], "").trim();
                }
                const style = `${width}${height}max-width:100%;border-radius:8px;cursor:pointer;`;
                return `<img class="md-img" src="${url}" alt="${cleanAlt}" style="${style}" onclick="window.open('${url}', '_blank')" onerror="this.style.display='none'">`;
            });
            // link [text](url)
            html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a class="md-link" href="$2" target="_blank">$1</a>');
            // quote
            html = html.replace(/^&gt;\s?(.+)$/gm, '<blockquote class="md-quote">$1</blockquote>');
            // bold/italic
            html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
            // hr
            html = html.replace(/^(?:\*\*\*|---)\s*$/gm, '<hr class="md-hr">');
            // plain image url line
            html = html.replace(/(^|<br>)(https?:\/\/[^\s<]+\.(?:png|jpe?g|gif|webp))(?:<br>|$)/gi, '$1<img class=\"md-img\" src=\"$2\" alt=\"image\" onclick=\"window.open(\'$2\', \'_blank\')\" onerror=\"this.style.display=\'none\'\">');
            // line break
            html = html.replace(/\n/g, '<br>');
            return html;
        }

        // 创建单条消息的HTML（与renderMessages保持完全一致的结构）
        function createMessageElement(msg) {
            let html = '';
            
            if (msg.type === 'event') {
                // 事件消息
                let eventClass = '';
                let eventIcon = '';
                if (msg.event_type === 'group_join') {
                    eventClass = 'join';
                    eventIcon = 'fa-user-plus';
                } else if (msg.event_type === 'group_leave') {
                    eventClass = 'leave';
                    eventIcon = 'fa-user-minus';
                } else if (msg.event_type === 'friend_add') {
                    eventClass = 'join';
                    eventIcon = 'fa-user-plus';
                } else if (msg.event_type === 'friend_delete') {
                    eventClass = 'leave';
                    eventIcon = 'fa-user-times';
                }
                html = `
                    <div class="event-message">
                        <div class="event-bubble ${eventClass}">
                            <i class="fas ${eventIcon}"></i>
                            <span>${escapeHtml(msg.content)}</span>
                        </div>
                    </div>
                `;
            } else if (msg.type === 'user') {
                // 用户消息（完全按照renderMessages的结构）
                const avatarUrl = `https://q.qlogo.cn/qqapp/${appid}/${msg.user_id}/5`;
                let contentHtml = escapeHtml(msg.content).replace(/\n/g, '<br>');
                
                // 显示图片
                if (msg.image_urls && msg.image_urls.length > 0) {
                    msg.image_urls.forEach(url => {
                        contentHtml += `<img src="${url}" alt="图片" style="display: block; margin-top: 8px; border-radius: 8px; cursor: pointer; max-width: 300px; max-height: 400px;" onclick="window.open('${url}', '_blank')" onerror="this.style.display='none'">`;
                    });
                }

                const displayName = msg.username || `用户${msg.user_id.substring(msg.user_id.length - 6)}`;
                const chatType = currentChatType || 'private';
                html = `
                    <div class="message-item user">
                        <div class="message-avatar-container" data-user-id="${msg.user_id}" data-chat-type="${chatType}">
                            <img src="${avatarUrl}" class="message-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="message-avatar-placeholder" style="display: none;">${msg.user_id.slice(-2).toUpperCase()}</div>
                        </div>
                        <div class="message-main">
                            <div class="message-name">${escapeHtml(displayName)}</div>
                            <div class="message-bubble">
                                <div class="message-content">${contentHtml}</div>
                                <div class="message-time">${msg.time.substring(11, 19)}</div>
                            </div>
                        </div>
                    </div>
                `;
            } else if (msg.type === 'bot') {
                // 机器人消息（完全按照renderMessages的结构）
                let avatarUrl = botAvatar || `https://q.qlogo.cn/qqapp/${appid}/${botQQ}/5`;
                let contentHtml = '';
                let bubbleClass = '';

                if (msg.message_type === 'card' || msg.message_type === 'ark') {
                    // 卡片消息
                    bubbleClass = 'card';
                    contentHtml = '<div class="card-content">';
                    
                    if (msg.card_data && Array.isArray(msg.card_data)) {
                        msg.card_data.forEach((item, index) => {
                            contentHtml += `<div class="card-item">`;
                            contentHtml += `<strong>${escapeHtml(item.text || '')}</strong>`;
                            if (item.url) {
                                contentHtml += `<a href="${item.url}" target="_blank" class="card-link">[链接]</a>`;
                            }
                            contentHtml += `</div>`;
                        });
                    } else if (msg.content) {
                        contentHtml += escapeHtml(msg.content).replace(/\n/g, '<br>');
                    }
                    contentHtml += '</div>';
                } else {
                    // 普通文字/原生MD消息
                    if (msg.message_type === 'native_md' || /!\[[^\]]*\]\(https?:\/\/[^\s)]+\)/.test(msg.content || '') || /^\s{0,3}(#|>|\*\*|```|\[[^\]]+\]\(https?:\/\/)/m.test(msg.content || '')) {
                        const rawMd = msg.content || '';
                        const mdText = hydrateMdImagePlaceholders(rawMd, msg.image_urls || []);
                        contentHtml = renderMarkdownLite(mdText);
                        const hasMdImage = /!\[[^\]]*\]\([^)]*\)/.test(rawMd);
                        if (!hasMdImage && msg.image_urls && msg.image_urls.length > 0) {
                            msg.image_urls.forEach(url => {
                                contentHtml += `<img class="md-img" src="${url}" alt="图片" onclick="window.open('${url}', '_blank')" onerror="this.style.display='none'">`;
                            });
                        }
                    } else {
                        contentHtml = escapeHtml(msg.content || '').replace(/\n/g, '<br>');
                    }
                }

                // 显示图片
                if (msg.image_url) {
                    contentHtml += `<img src="${msg.image_url}" alt="图片" style="display: block; margin-top: 8px; border-radius: 8px; cursor: pointer; max-width: 300px; max-height: 400px;" onclick="window.open('${msg.image_url}', '_blank')" onerror="this.style.display='none'">`;
                }
                
                // 显示语音
                if (msg.message_type === 'voice' || msg.voice_url) {
                    const voiceUrl = msg.voice_url || '';
                    let extractedUrl = voiceUrl;
                    if (!extractedUrl && msg.content) {
                        const urlMatch = msg.content.match(/\(?(https?:\/\/[^\s\)]+)\)?/);
                        if (urlMatch) {
                            extractedUrl = urlMatch[1];
                        }
                    }
                    
                    contentHtml += `
                        <div class="media-player voice-player">
                            <i class="fas fa-volume-up"></i>
                            <span>语音</span>
                            ${extractedUrl ? `
                                <audio controls preload="metadata" style="max-width: 200px; height: 32px;">
                                    <source src="${extractedUrl}" type="audio/mpeg">
                                    <source src="${extractedUrl}" type="audio/silk">
                                    <a href="${extractedUrl}" target="_blank">下载语音</a>
                                </audio>
                                <a href="${extractedUrl}" target="_blank" class="media-link" title="${extractedUrl}">[链接]</a>
                            ` : '<span style="color: #999; font-size: 12px;">(无链接)</span>'}
                        </div>
                    `;
                }
                
                // 显示视频
                if (msg.message_type === 'video' || msg.video_url) {
                    const videoUrl = msg.video_url || '';
                    let extractedUrl = videoUrl;
                    if (!extractedUrl && msg.content) {
                        const urlMatch = msg.content.match(/\(?(https?:\/\/[^\s\)]+)\)?/);
                        if (urlMatch) {
                            extractedUrl = urlMatch[1];
                        }
                    }
                    
                    contentHtml += `
                        <div class="media-player video-player">
                            <i class="fas fa-video"></i>
                            <span>视频</span>
                            ${extractedUrl ? `
                                <video controls preload="metadata" style="max-width: 100%; max-height: 400px;">
                                    <source src="${extractedUrl}" type="video/mp4">
                                    <source src="${extractedUrl}" type="video/webm">
                                    <a href="${extractedUrl}" target="_blank">下载视频</a>
                                </video>
                                <a href="${extractedUrl}" target="_blank" class="media-link" title="${extractedUrl}">[链接]</a>
                            ` : '<span style="color: #999; font-size: 12px;">(无链接)</span>'}
                        </div>
                    `;
                }

                html = `
                    <div class="message-item bot">
                        <img src="${avatarUrl}" class="message-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="message-avatar-placeholder" style="display: none; background-color: #0d6efd;">
                            <i class="bi bi-robot" style="color: white;"></i>
                        </div>
                        <div class="message-main">
                            <div class="message-name">${escapeHtml(botName)}</div>
                            <div class="message-bubble">
                                <div class="message-content ${bubbleClass}">${contentHtml}</div>
                                <div class="message-time">${msg.time.substring(11, 19)}</div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            return html;
        }

        function renderMessages(messages, scrollToBottom = true) {
            const messagesContainer = document.getElementById('messagesContainer');

            if (messages.length === 0) {
                messagesContainer.innerHTML = `
                    <div class="text-center text-muted">
                        <i class="bi bi-chat-text" style="font-size: 3rem;"></i>
                        <p class="mt-2">暂无消息记录</p>
                    </div>
                `;
                lastMessageCount = 0;
                return;
            }

            // 记录消息数量
            lastMessageCount = messages.length;

            let html = '';
            let lastTime = '';

            messages.forEach(msg => {
                // 显示时间分隔
                const msgTime = msg.time.substring(0, 16);
                if (msgTime !== lastTime) {
                    html += `<div class="event-message"><div class="event-bubble">${msgTime}</div></div>`;
                    lastTime = msgTime;
                }

                if (msg.type === 'event') {
                    // 事件消息（加群、退群、加好友、删除好友等）
                    let eventClass = '';
                    let eventIcon = '';
                    if (msg.event_type === 'group_join') {
                        eventClass = 'join';
                        eventIcon = 'fa-user-plus';
                    } else if (msg.event_type === 'group_leave') {
                        eventClass = 'leave';
                        eventIcon = 'fa-user-minus';
                    } else if (msg.event_type === 'friend_add') {
                        eventClass = 'join';
                        eventIcon = 'fa-user-plus';
                    } else if (msg.event_type === 'friend_delete') {
                        eventClass = 'leave';
                        eventIcon = 'fa-user-times';
                    }
                    html += `
                        <div class="event-message">
                            <div class="event-bubble ${eventClass}">
                                <i class="fas ${eventIcon}"></i>
                                <span>${escapeHtml(msg.content)}</span>
                            </div>
                        </div>
                    `;
                } else if (msg.type === 'user') {
                    // 用户消息
                    const avatarUrl = `https://q.qlogo.cn/qqapp/${appid}/${msg.user_id}/5`;
                    let contentHtml = escapeHtml(msg.content).replace(/\n/g, '<br>');
                    
                    // 显示图片
                    if (msg.image_urls && msg.image_urls.length > 0) {
                        msg.image_urls.forEach(url => {
                            contentHtml += `<img src="${url}" alt="图片" style="display: block; margin-top: 8px; border-radius: 8px; cursor: pointer; max-width: 300px; max-height: 400px;" onclick="window.open('${url}', '_blank')" onerror="this.style.display='none'">`;
                        });
                    }

                    const displayName = msg.username || `用户${msg.user_id.substring(msg.user_id.length - 6)}`;
                    const chatType = currentChatType || 'private';
                    html += `
                        <div class="message-item user">
                            <div class="message-avatar-container" data-user-id="${msg.user_id}" data-chat-type="${chatType}">
                                <img src="${avatarUrl}" class="message-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="message-avatar-placeholder" style="display: none;">${msg.user_id.slice(-2).toUpperCase()}</div>
                            </div>
                            <div class="message-main">
                                <div class="message-name">${escapeHtml(displayName)}</div>
                                <div class="message-bubble">
                                    <div class="message-content">${contentHtml}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                } else if (msg.type === 'bot') {
                    // 机器人消息
                    // 优先使用API获取的头像，如果没有则使用QQ头像
                    let avatarUrl = botAvatar || `https://q.qlogo.cn/qqapp/${appid}/${botQQ}/5`;
                    let contentHtml = '';
                    let bubbleClass = '';

                    if (msg.message_type === 'card' || msg.message_type === 'ark') {
                        // 卡片消息
                        bubbleClass = 'card';
                        contentHtml = '<div class="card-content">';
                        
                        if (msg.card_data && Array.isArray(msg.card_data)) {
                            msg.card_data.forEach((item, index) => {
                                contentHtml += `<div class="card-item">`;
                                contentHtml += `<strong>${escapeHtml(item.text || '')}</strong>`;
                                if (item.url) {
                                    contentHtml += `<a href="${item.url}" target="_blank" class="card-link">[链接]</a>`;
                                }
                                contentHtml += `</div>`;
                            });
                        } else if (msg.content) {
                            contentHtml += escapeHtml(msg.content).replace(/\n/g, '<br>');
                        }
                        contentHtml += '</div>';
                    } else {
                        // 普通文字消息
                        contentHtml = escapeHtml(msg.content || '').replace(/\n/g, '<br>');
                    }

                    // 显示图片
                    if (msg.image_url) {
                        contentHtml += `<img src="${msg.image_url}" alt="图片" style="display: block; margin-top: 8px; border-radius: 8px; cursor: pointer; max-width: 300px; max-height: 400px;" onclick="window.open('${msg.image_url}', '_blank')" onerror="this.style.display='none'">`;
                    }
                    
                    // 显示语音（检查message_type或voice_url）
                    if (msg.message_type === 'voice' || msg.voice_url) {
                        const voiceUrl = msg.voice_url || '';
                        // 如果content中包含链接，尝试提取
                        let extractedUrl = voiceUrl;
                        if (!extractedUrl && msg.content) {
                            const urlMatch = msg.content.match(/\(?(https?:\/\/[^\s\)]+)\)?/);
                            if (urlMatch) {
                                extractedUrl = urlMatch[1];
                            }
                        }
                        
                        contentHtml += `
                            <div class="media-player voice-player">
                                <i class="fas fa-volume-up"></i>
                                <span>语音</span>
                                ${extractedUrl ? `
                                    <audio controls preload="metadata" style="max-width: 200px; height: 32px;">
                                        <source src="${extractedUrl}" type="audio/mpeg">
                                        <source src="${extractedUrl}" type="audio/silk">
                                        <a href="${extractedUrl}" target="_blank">下载语音</a>
                                    </audio>
                                    <a href="${extractedUrl}" target="_blank" class="media-link" title="${extractedUrl}">[链接]</a>
                                ` : '<span style="color: #999; font-size: 12px;">(无链接)</span>'}
                            </div>
                        `;
                    }
                    
                    // 显示视频（检查message_type或video_url）
                    if (msg.message_type === 'video' || msg.video_url) {
                        const videoUrl = msg.video_url || '';
                        // 如果content中包含链接，尝试提取
                        let extractedUrl = videoUrl;
                        if (!extractedUrl && msg.content) {
                            const urlMatch = msg.content.match(/\(?(https?:\/\/[^\s\)]+)\)?/);
                            if (urlMatch) {
                                extractedUrl = urlMatch[1];
                            }
                        }
                        
                        contentHtml += `
                            <div class="media-player video-player">
                                <i class="fas fa-video"></i>
                                <span>视频</span>
                                ${extractedUrl ? `
                                    <video controls preload="metadata" style="max-width: 100%; max-height: 400px;">
                                        <source src="${extractedUrl}" type="video/mp4">
                                        <source src="${extractedUrl}" type="video/webm">
                                        <a href="${extractedUrl}" target="_blank">下载视频</a>
                                    </video>
                                    <a href="${extractedUrl}" target="_blank" class="media-link" title="${extractedUrl}">[链接]</a>
                                ` : '<span style="color: #999; font-size: 12px;">(无链接)</span>'}
                            </div>
                        `;
                    }

                    html += `
                        <div class="message-item bot">
                            <img src="${avatarUrl}" class="message-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="message-avatar-placeholder" style="display: none; background-color: #0d6efd;">
                                <i class="bi bi-robot" style="color: white;"></i>
                            </div>
                            <div class="message-main">
                                <div class="message-name">${escapeHtml(botName)}</div>
                                <div class="message-bubble">
                                    <div class="message-content ${bubbleClass}">${contentHtml}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }
            });

            messagesContainer.innerHTML = html;
            
            // 绑定消息头像长按事件
            bindMessageAvatarEvents();
            
            // 优化滚动到底部（使用requestAnimationFrame确保DOM更新完成）
            // 只在首次加载或用户主动操作时滚动，自动刷新时保持当前位置
            if (scrollToBottom) {
                requestAnimationFrame(() => {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    // 平滑滚动（如果浏览器支持）
                    if ('scrollBehavior' in document.documentElement.style) {
                        messagesContainer.scrollTo({
                            top: messagesContainer.scrollHeight,
                            behavior: 'smooth'
                        });
                    }
                });
            }
        }

        function showEmptyChatList() {
            const isMobile = window.innerWidth <= 768;
            const chatList = isMobile ? document.getElementById('mobileChatList') : document.getElementById('chatList');
            if (chatList) {
                chatList.innerHTML = `
                    <div class="text-center p-3 text-muted">
                        <i class="bi bi-chat-dots" style="font-size: 2rem;"></i>
                        <p class="mt-2">请选择日志文件</p>
                    </div>
                `;
            }
        }
        
        // 返回列表视图
        function backToChatList() {
            const isMobile = window.innerWidth <= 768;
            if (isMobile) {
                const listContainer = document.getElementById('mobileChatListContainer');
                const chatContent = document.querySelector('.chat-content');
                const header = document.querySelector('.header');
                
                if (listContainer) listContainer.classList.remove('hide');
                if (chatContent) chatContent.classList.remove('show');
                if (header) header.classList.remove('show-chat');
                
                // 更新header标题
                const headerTitle = document.querySelector('.header-title');
                if (headerTitle) headerTitle.textContent = '聊天记录';
            } else {
                // PC端：清空消息容器，显示空状态
                const messagesContainer = document.getElementById('messagesContainer');
                if (messagesContainer) {
                    messagesContainer.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-comment-dots"></i>
                            <p>选择一个聊天会话查看消息</p>
                        </div>
                    `;
                }
            }
            
            // 隐藏复制ID按钮
            
            // 重置手机端ID显示
            const mobileChatId = document.getElementById('mobile-chat-id');
            if (mobileChatId) {
                mobileChatId.textContent = 'ID: --';
            }
        }
        
        // 复制聊天ID
        function copyChatId() {
            const chatId = window.currentChatId;
            if (!chatId) return;
            
            // 使用现代API复制到剪贴板
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(chatId).then(() => {
                    showCopySuccess();
                }).catch(() => {
                    fallbackCopy(chatId);
                });
            } else {
                fallbackCopy(chatId);
            }
        }
        
        // 备用复制方法
        function fallbackCopy(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                showCopySuccess();
            } catch (err) {
                console.error('复制失败:', err);
            }
            document.body.removeChild(textArea);
        }
        
        // 显示复制成功提示
        function showCopySuccess() {
            // 创建临时提示
            const toast = document.createElement('div');
            toast.textContent = 'ID已复制';
            toast.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: rgba(0, 0, 0, 0.8);
                color: #fff;
                padding: 12px 24px;
                border-radius: 8px;
                font-size: 14px;
                z-index: 10000;
                pointer-events: none;
            `;
            document.body.appendChild(toast);
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 2000);
        }

        function formatTime(timeStr) {
            if (!timeStr) return '';
            const date = new Date(timeStr);
            const now = new Date();
            const diff = now - date;
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            
            if (days === 0) {
                return timeStr.substring(11, 16);
            } else if (days === 1) {
                return '昨天';
            } else if (days < 7) {
                return `${days}天前`;
            } else {
                return timeStr.substring(5, 10);
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // 批量加载用户昵称
        function loadNicknamesBatch(userIds) {
            if (!userIds || userIds.length === 0) {
                return Promise.resolve({});
            }
            
            return fetch(`api/chat.php?type=get_nicknames&appid=${encodeURIComponent(appid)}&name=${encodeURIComponent(currentLogFile)}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `user_ids=${encodeURIComponent(JSON.stringify(userIds))}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.code === 200) {
                    return data.nicknames || {};
                }
                return {};
            })
            .catch(error => {
                console.error('批量加载昵称失败:', error);
                return {};
            });
        }

        // 输入框高度随内容自适应
        function autoResizeSendInput() {
            const input = document.getElementById('sendInput');
            if (!input) return;
            input.style.height = '38px';
            const newHeight = Math.min(input.scrollHeight, 150);
            input.style.height = Math.max(38, newHeight) + 'px';
            input.style.overflowY = input.scrollHeight > 150 ? 'auto' : 'hidden';
        }

        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById('sendInput');
            if (input) {
                input.addEventListener('input', autoResizeSendInput);
                autoResizeSendInput();
            }
        });

        // 发送文字消息（后台聊天 → QQ 群聊 / 私聊）
        function sendMessage() {
            const input = document.getElementById('sendInput');
            const btn = document.getElementById('sendBtn');

            if (!input || !btn) return;

            const content = input.value.trim();
            if (!currentChatId) {
                alert('请先在左侧选择一个聊天对象');
                return;
            }
            if (!content) {
                alert('请输入要发送的文字内容');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>发送中...';

            const payload = new URLSearchParams();
            
            // 群聊模式下的处理
            if (currentChatType === 'group') {
                payload.append('chat_type', 'group');
                payload.append('chat_id', currentChatId); // 群聊ID
                
                // 如果选择了目标用户，添加 user_id 参数
                if (currentTargetUserId) {
                    payload.append('user_id', currentTargetUserId);
                }
            } else {
                // 私聊模式
                payload.append('chat_type', 'private');
                payload.append('chat_id', currentChatId);
            }
            
            payload.append('reply_mode', 'instant'); // 固定立即回复
            
            // 根据当前选择的格式设置发送方式
            if (currentSendFormat === 'card') {
                payload.append('send_method', 'card');
            } else if (currentSendFormat === 'native_md') {
                payload.append('send_method', 'native_md');
            } else {
                payload.append('send_method', 'text');
            }
            payload.append('content', content);

            const apiType = 'send';
            fetch(`api/chat.php?type=${apiType}&appid=${encodeURIComponent(appid)}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: payload.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data.code === 200) {
                    input.value = '';
                    autoResizeSendInput();
                    // 清除群聊目标用户选择
                    if (currentTargetUserId) {
                        clearTargetUser();
                    }
                    
                    if (currentChatId) {
                        // 发送成功后立即检查新消息（包括刚发送的）
                        setTimeout(() => {
                            checkNewMessages();
                        }, 500);
                    }
                } else {
                    alert('失败：' + (data.msg || '未知错误'));
                }
            })
            .catch(error => {
                console.error('发送消息失败:', error);
                
            })
            .finally(() => {
                btn.disabled = false;
                // 根据当前格式恢复按钮文字
                const btnText = currentSendFormat === 'card' ? '发送卡片' : (currentSendFormat === 'native_md' ? '发送MD' : '发送');
                btn.innerHTML = `<i class="bi bi-send"></i><span class="ms-1 d-none d-sm-inline" id="sendBtnText">${btnText}</span>`;
            });
        }
    </script>
</body>
</html>
