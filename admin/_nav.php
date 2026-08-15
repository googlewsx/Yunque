<?php
/**
 * 统一后台导航侧边栏
 * 使用前在页面中设置：$active_page = 'main|bot_set|chat|log|plugin|custom_api|set|doc';
 * 若页面有 $appid，机器人相关链接会自动带上 appid。
 */
$active_page = isset($active_page) ? $active_page : '';
$_nav_appid = (isset($appid) && $appid !== '') ? $appid : '';
$_nav_q = $_nav_appid !== '' ? '?appid=' . urlencode($_nav_appid) : '';

function _nav_item($current, $key, $href, $icon, $label, $extra = '') {
    $active = ($current === $key) ? ' active' : '';
    echo '<a href="' . $href . '" class="nav-item' . $active . '"' . $extra . '><i class="fas ' . $icon . '"></i> ' . $label . '</a>';
}
?>
<div class="mobile-header">
    <button class="menu-toggle" id="menuToggle" aria-label="打开菜单"><i class="fas fa-bars"></i></button>
    <span class="mtitle"><i class="brand-ico">✦</i>云雀 Yunque</span>
    <span class="mright"><a href="main.php" style="color:var(--primary);font-size:13px;font-weight:600;">返回</a></span>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="desktop-layout">
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a class="sidebar-brand" href="main.php">
            <i class="brand-ico">✦</i>
            <span class="brand-txt">云雀 Yunque<small class="brand-sub">机器人管理后台</small></span>
        </a>
    </div>
    <nav class="sidebar-nav">
        <?php
        _nav_item($active_page, 'main',       'main.php',                                     'fa-tachometer-alt', '总览');
        _nav_item($active_page, 'add',        'main.php#add',                                 'fa-plus-circle',    '添加机器人');
        _nav_item($active_page, 'bot_set',    $_nav_appid !== '' ? 'bot_set.php' . $_nav_q : 'main.php#bot_set', 'fa-sliders-h', '机器人设置');
        _nav_item($active_page, 'chat',       $_nav_appid !== '' ? 'chat.php' . $_nav_q : 'main.php#chat', 'fa-comments', '聊天记录');
        _nav_item($active_page, 'log',        $_nav_appid !== '' ? 'log.php' . $_nav_q : 'main.php#log', 'fa-list-alt', '日志管理');
        _nav_item($active_page, 'plugin',     $_nav_appid !== '' ? 'plugin.php' . $_nav_q : 'main.php#plugin', 'fa-puzzle-piece', '插件管理');
        _nav_item($active_page, 'custom_api', 'custom_api.php',                               'fa-plug',           '自定义API');
        _nav_item($active_page, 'set',        'set.php',                                      'fa-user-cog',       '账号设置');
        _nav_item($active_page, 'doc',        'doc.php',                                      'fa-file-alt',       '开发文档');
        ?>
    </nav>
    <div class="sidebar-footer">云雀 Yunque · 机器人管理后台</div>
</aside>
<script>
(function(){
    var toggle = document.getElementById('menuToggle');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if(!toggle || !sidebar) return;
    function openSidebar(){ sidebar.classList.add('open'); overlay.classList.add('show'); }
    function closeSidebar(){ sidebar.classList.remove('open'); overlay.classList.remove('show'); }
    toggle.addEventListener('click', function(e){
        e.stopPropagation();
        if(sidebar.classList.contains('open')) closeSidebar(); else openSidebar();
    });
    if(overlay) overlay.addEventListener('click', closeSidebar);
    sidebar.addEventListener('click', function(e){
        if(e.target.closest('a')) closeSidebar();
    });
    document.addEventListener('click', function(e){
        if(window.innerWidth <= 768 &&
           !sidebar.contains(e.target) &&
           !toggle.contains(e.target)) closeSidebar();
    });
})();
</script>
