<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $error = null;
    
    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = '所有字段都必须填写';
    } elseif ($password !== $confirm_password) {
        $error = '两次输入的密码不一致';
    } elseif (strlen($password) < 6) {
        $error = '密码长度至少6位';
    } else {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()['count'] > 0) {
                $error = '用户名已存在';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO users (username, password_hash)
                    VALUES (?, ?)
                ");
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
                
                $_SESSION['flash'] = '注册成功，请登录';
                header('Location: ?route=login');
                exit;
            }
        } catch (Exception $e) {
            $error = '注册失败，请稍后重试';
        }
    }
}

// 获取页面内容
ob_start();
?>

<div class="container">
    <div class="auth-form">
        <h2>注册新账号</h2>
        
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
                <div class="form-hint">密码长度至少6位</div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">确认密码：</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="button primary">注册</button>
                <a href="?route=login" class="button secondary">返回登录</a>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require '../templates/layout/base.php';
?> 