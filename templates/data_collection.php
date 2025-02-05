<?php
// 获取已保存的设置
$stmt = $db->prepare("
    SELECT s.*, u.nickname
    FROM uid_settings s
    LEFT JOIN uids u ON s.uid = u.uid AND s.created_by = u.created_by
    WHERE s.created_by = ?
    ORDER BY s.updated_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$settings = $stmt->fetchAll();

// 如果有URL参数，优先使用参数值
$selected_uid = $_GET['uid'] ?? '';
$selected_setting = null;

if ($selected_uid) {
    foreach ($settings as $setting) {
        if ($setting['uid'] === $selected_uid) {
            $selected_setting = $setting;
            break;
        }
    }
}

// 处理定时设置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $uid = $_POST['uid'] ?? '';
    $auto_update = isset($_POST['auto_update']);
    $update_time = $_POST['update_time'] ?? '21:00';
    
    try {
        $stmt = $db->prepare("
            UPDATE uid_settings 
            SET auto_update = ?, update_time = ?
            WHERE uid = ? AND created_by = ?
        ");
        $stmt->execute([$auto_update, $update_time, $uid, $_SESSION['user_id']]);
        
        $_SESSION['flash'] = '定时设置已更新';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } catch (Exception $e) {
        $error = '更新设置失败';
    }
}

// 处理数据采集
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $url = $_POST['url'] ?? '';
    $uid = $_POST['uid'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    
    $error = null;
    
    if (empty($url) || empty($uid)) {
        $error = 'URL和UID不能为空';
    } else {
        require_once '../includes/DataCollector.php';
        $collector = new DataCollector($db);
        $result = $collector->collect($url, $uid, $start_date, $end_date);
        
        if ($result['success']) {
            $_SESSION['flash'] = sprintf(
                '数据采集成功，共采集 %d 条数据（%s 至 %s）',
                $result['data']['total'],
                $result['data']['start_date'],
                $result['data']['end_date']
            );
            header("Location: ?route=data_analysis&uid=" . urlencode($uid));
            exit;
        }
        
        $error = $result['error'];
    }
}

// 获取页面内容
ob_start();
?>

<div class="container">
    <h2>数据采集</h2>
    
    <?php if (isset($error)): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="collection-form">
        <form method="POST" class="form" id="collection-form">
            <div class="form-group">
                <label for="url">URL：</label>
                <input type="text" id="url" name="url" required 
                       placeholder="请输入数据页面的URL"
                       value="<?= htmlspecialchars($selected_setting['url'] ?? '') ?>">
                <div class="form-hint">例如：https://dt.bd.cn/main/quark_list?bs=xxx</div>
            </div>
            
            <div class="form-group">
                <label for="uid">UID：</label>
                <input type="text" id="uid" name="uid" required 
                       list="uid-suggestions"
                       class="form-input"
                       placeholder="输入或选择UID"
                       value="<?= htmlspecialchars($selected_uid ?? '') ?>">
                <datalist id="uid-suggestions">
                    <?php foreach ($settings as $setting): ?>
                        <option value="<?= htmlspecialchars($setting['uid']) ?>"
                                data-url="<?= htmlspecialchars($setting['url']) ?>">
                            <?= htmlspecialchars($setting['nickname'] ?: $setting['uid']) ?>
                        </option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="start_date">开始日期：</label>
                    <input type="date" id="start_date" name="start_date"
                           value="<?= htmlspecialchars($start_date ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="end_date">结束日期：</label>
                    <input type="date" id="end_date" name="end_date"
                           value="<?= htmlspecialchars($end_date ?? '') ?>">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="button primary">开始采集</button>
                <a href="?route=home" class="button secondary">返回</a>
            </div>
        </form>
        
        <?php if ($selected_setting): ?>
        <div class="settings-form">
            <h3>定时更新设置</h3>
            <form method="POST" class="form">
                <input type="hidden" name="action" value="update_settings">
                <input type="hidden" name="uid" value="<?= htmlspecialchars($selected_setting['uid']) ?>">
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="auto_update"
                               <?= $selected_setting['auto_update'] ? 'checked' : '' ?>>
                        启用自动更新（周一至周五）
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="update_time">更新时间：</label>
                    <input type="time" id="update_time" name="update_time"
                           value="<?= htmlspecialchars(substr($selected_setting['update_time'], 0, 5)) ?>">
                </div>
                
                <button type="submit" class="button secondary">保存设置</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const uidInput = document.getElementById('uid');
    const urlInput = document.getElementById('url');
    const form = document.getElementById('collection-form');
    const suggestions = document.getElementById('uid-suggestions');
    
    // UID输入变化时处理
    uidInput.addEventListener('input', function() {
        const option = Array.from(suggestions.options).find(opt => opt.value === this.value);
        if (option) {
            // 如果选择了已有的UID，填充对应的URL
            urlInput.value = option.dataset.url;
            urlInput.setAttribute('readonly', true);
        } else {
            // 如果是新UID，清空URL并允许编辑
            urlInput.value = '';
            urlInput.removeAttribute('readonly');
        }
    });
    
    // 表单提交前验证
    form.addEventListener('submit', function(e) {
        if (!uidInput.value.trim()) {
            e.preventDefault();
            alert('请输入UID');
            return;
        }
        if (!urlInput.value.trim()) {
            e.preventDefault();
            alert('请输入URL');
            return;
        }
    });
});
</script>

<style>
.form-input {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}

.form-input:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52,152,219,0.2);
}

datalist {
    display: none;
}
</style>

<?php
$content = ob_get_clean();
require '../templates/layout/base.php';
?> 