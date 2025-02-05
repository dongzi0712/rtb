<?php
if (!$_SESSION['is_admin']) {
    header('Location: ?route=home');
    exit;
}

// 获取所有用户
$stmt = $db->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM uids WHERE created_by = u.id) as uid_count
    FROM users u
    ORDER BY u.created_at DESC
");
$stmt->execute();
$users = $stmt->fetchAll();

// 获取页面内容
ob_start();
?>

<div class="container">
    <h2>用户管理</h2>
    <div class="user-list">
        <?php foreach ($users as $user): ?>
        <div class="user-card">
            <div class="user-info">
                <h3><?= htmlspecialchars($user['username']) ?></h3>
                <p class="user-detail">注册时间: <?= date('Y-m-d H:i', strtotime($user['created_at'])) ?></p>
                <p class="user-detail">UID数量: <?= number_format($user['uid_count']) ?></p>
                <p class="user-detail">
                    身份: 
                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                        <?php if ($user['is_admin']): ?>管理员<?php else: ?>普通用户<?php endif; ?>
                    <?php else: ?>
                        <select class="role-select" data-user-id="<?= $user['id'] ?>">
                            <option value="0" <?= !$user['is_admin'] ? 'selected' : '' ?>>普通用户</option>
                            <option value="1" <?= $user['is_admin'] ? 'selected' : '' ?>>管理员</option>
                        </select>
                    <?php endif; ?>
                </p>
            </div>
            <?php if ($user['id'] != $_SESSION['user_id']): ?>
            <div class="user-actions">
                <button class="button reset-password" data-user-id="<?= $user['id'] ?>">
                    重置密码
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- 重置密码弹窗 -->
<div id="password-modal" class="modal">
    <div class="modal-content">
        <h3>重置用户密码</h3>
        <form id="password-form">
            <input type="hidden" id="modal-user-id">
            <div class="form-group">
                <label for="new-password">新密码：</label>
                <input type="password" id="new-password" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="button">确定</button>
                <button type="button" class="button cancel">取消</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 角色切换
    document.querySelectorAll('.role-select').forEach(select => {
        select.addEventListener('change', function() {
            const userId = this.dataset.userId;
            const isAdmin = this.value === '1';
            
            fetch('?route=toggle_admin', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `user_id=${userId}&is_admin=${isAdmin}`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert(data.error || '操作失败');
                    location.reload();
                }
            });
        });
    });
    
    // 重置密码
    const modal = document.getElementById('password-modal');
    const modalUserId = document.getElementById('modal-user-id');
    const passwordInput = document.getElementById('new-password');
    
    document.querySelectorAll('.reset-password').forEach(button => {
        button.addEventListener('click', function() {
            modalUserId.value = this.dataset.userId;
            modal.style.display = 'block';
        });
    });
    
    document.querySelector('.cancel').addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    document.getElementById('password-form').addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('?route=reset_password', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `user_id=${modalUserId.value}&new_password=${passwordInput.value}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('密码重置成功');
                modal.style.display = 'none';
            } else {
                alert(data.error || '重置失败');
            }
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require '../templates/layout/base.php';
?> 