<?php
// 获取当前用户的UID列表
$stmt = $db->prepare("
    SELECT u.*, us.username as creator 
    FROM uids u
    JOIN users us ON u.created_by = us.id
    " . (!$_SESSION['is_admin'] ? "WHERE u.created_by = ?" : "") . "
    ORDER BY u.created_at DESC
");

if (!$_SESSION['is_admin']) {
    $stmt->execute([$_SESSION['user_id']]);
} else {
    $stmt->execute();
}
$uids = $stmt->fetchAll();

// 获取页面内容
ob_start();
?>

<div class="container">
    <h2>已查询的UID列表</h2>
    <div class="uid-grid">
        <?php foreach ($uids as $uid_info): ?>
        <div class="uid-card">
            <div class="uid-info">
                <h3><?= htmlspecialchars($uid_info['nickname'] ?: $uid_info['uid']) ?></h3>
                <p class="uid-detail">UID: <?= htmlspecialchars($uid_info['uid']) ?></p>
                <p class="uid-detail">添加时间: <?= date('Y-m-d H:i', strtotime($uid_info['created_at'])) ?></p>
                <?php if ($_SESSION['is_admin']): ?>
                <p class="uid-detail">创建者: <?= htmlspecialchars($uid_info['creator']) ?></p>
                <?php endif; ?>
            </div>
            <div class="uid-actions">
                <button class="button edit-nickname" data-uid="<?= htmlspecialchars($uid_info['uid']) ?>">
                    修改备注
                </button>
                <a href="?route=data_analysis&uid=<?= urlencode($uid_info['uid']) ?>" class="button">
                    查看数据
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- 修改备注弹窗 -->
<div id="nickname-modal" class="modal">
    <div class="modal-content">
        <h3>修改备注名称</h3>
        <form id="nickname-form">
            <input type="hidden" id="modal-uid">
            <div class="form-group">
                <label for="nickname">备注名称：</label>
                <input type="text" id="nickname" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="button">保存</button>
                <button type="button" class="button cancel">取消</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('nickname-modal');
    const modalUid = document.getElementById('modal-uid');
    const nicknameInput = document.getElementById('nickname');
    
    // 打开弹窗
    document.querySelectorAll('.edit-nickname').forEach(button => {
        button.addEventListener('click', function() {
            modalUid.value = this.dataset.uid;
            modal.style.display = 'block';
        });
    });
    
    // 关闭弹窗
    document.querySelector('.cancel').addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    // 提交表单
    document.getElementById('nickname-form').addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('?route=update_nickname', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `uid=${modalUid.value}&nickname=${nicknameInput.value}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || '更新失败');
            }
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require '../templates/layout/base.php';
?> 