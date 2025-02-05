<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $error = null;
    
    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $error = '所有字段都必须填写';
    } elseif ($new_password !== $confirm_password) {
        $error = '新密码两次输入不一致';
    } elseif (strlen($new_password) < 6) {
        $error = '新密码长度至少6位';
    } else {
        try {
            $stmt = $db->prepare("
                SELECT password_hash 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($old_password, $user['password_hash'])) {
                $error = '原密码错误';
            } else {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET password_hash = ? 
                    WHERE id = ?
                ");
                $stmt->execute([
                    password_hash($new_password, PASSWORD_DEFAULT),
                    $_SESSION['user_id']
                ]);
                
                $_SESSION['flash'] = '密码修改成功';
                header('Location: ?route=home');
                exit;
            }
        } catch (Exception $e) {
            $error = '修改失败，请稍后重试';
        }
    }
}

// 获取页面内容
ob_start();
?>

<div class="container">
    <div class="auth-form">
        <h2>修改密码</h2>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" class="form">
            <div class="form-group">
                <label for="old_password">原密码：</label>
                <input type="password" id="old_password" name="old_password" required>
            </div>
            
            <div class="form-group">
                <label for="new_password">新密码：</label>
                <input type="password" id="new_password" name="new_password" required>
                <div class="form-hint">密码长度至少6位</div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">确认新密码：</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="button primary">修改密码</button>
                <a href="?route=home" class="button secondary">返回</a>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require '../templates/layout/base.php';
?> 