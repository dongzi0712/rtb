<?php
if (!isset($auth)) {
    require_once dirname(__DIR__) . '/includes/Auth.php';
    $auth = new Auth();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } else {
        if ($auth->login($username, $password)) {
            // 登录成功，重定向到首页
            header('Location: ?route=home');
            exit;
        } else {
            $error = '用户名或密码错误';
        }
    }
}

// 获取页面内容
ob_start();
?>

<div class="container">
    <div class="auth-form">
        <h2>登录</h2>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" class="form">
            <div class="form-group">
                <label for="username">用户名：</label>
                <input type="text" id="username" name="username" required 
                       value="<?= htmlspecialchars($username ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="password">密码：</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="button primary">登录</button>
                <a href="?route=register" class="button secondary">注册新账号</a>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require '../templates/layout/base.php';
?> 