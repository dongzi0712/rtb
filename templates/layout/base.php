<?php
if (!isset($route)) {
    $route = $_GET['route'] ?? 'home';
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
    <nav class="main-nav">
        <div class="nav-container">
            <div class="nav-brand">
                <?= APP_NAME ?>
            </div>
            <ul class="nav-menu">
                <li><a href="?route=home" class="nav-link <?= $route === 'home' ? 'active' : '' ?>">
                    <span class="nav-icon">📊</span>首页
                </a></li>
                <li><a href="?route=data_collection" class="nav-link <?= $route === 'data_collection' ? 'active' : '' ?>">
                    <span class="nav-icon">📥</span>数据采集
                </a></li>
                <li><a href="?route=uid_list" class="nav-link <?= $route === 'uid_list' ? 'active' : '' ?>">
                    <span class="nav-icon">📋</span>UID管理
                </a></li>
                <?php if ($_SESSION['is_admin']): ?>
                <li><a href="?route=admin_users" class="nav-link <?= $route === 'admin_users' ? 'active' : '' ?>">
                    <span class="nav-icon">👥</span>用户管理
                </a></li>
                <?php endif; ?>
            </ul>
            <div class="nav-user">
                <span class="user-name">
                    <?= htmlspecialchars($_SESSION['username']) ?>
                    <?php if ($_SESSION['is_admin']): ?>
                        <span class="admin-badge">管理员</span>
                    <?php endif; ?>
                </span>
                <div class="user-menu">
                    <a href="?route=change_password" class="nav-link">
                        <span class="nav-icon">🔑</span>修改密码
                    </a>
                    <a href="?route=logout" class="nav-link logout">
                        <span class="nav-icon">🚪</span>退出
                    </a>
                </div>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <main class="main-content">
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="flash-message">
                <?= htmlspecialchars($_SESSION['flash']) ?>
                <?php unset($_SESSION['flash']); ?>
            </div>
        <?php endif; ?>
        
        <?= $content ?? '' ?>
    </main>
    
    <footer class="main-footer">
        <div class="footer-content">
            &copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.
        </div>
    </footer>
</body>
</html> 