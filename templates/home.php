<?php
// 获取用户统计数据
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT u.uid) as total_uids,
        (
            SELECT COUNT(*) 
            FROM data_records 
            WHERE DATE(created_at) = CURDATE()
        ) as today_records,
        SUM(dr.mobile_new) as total_mobile_new,
        SUM(dr.pc_new) as total_pc_new,
        SUM(dr.saves) as total_saves,
        SUM(dr.mobile_income + dr.pc_income) as total_income
    FROM uids u
    LEFT JOIN data_records dr ON u.uid = dr.uid
    WHERE u.created_by = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

// 获取最近更新的UID数据
$stmt = $db->prepare("
    SELECT 
        u.uid,
        u.nickname,
        MAX(dr.created_at) as last_update,
        COUNT(dr.id) as record_count,
        SUM(dr.mobile_new) as total_mobile_new,
        SUM(dr.pc_new) as total_pc_new,
        SUM(dr.saves) as total_saves,
        SUM(dr.mobile_income + dr.pc_income) as total_income,
        MIN(dr.date_str) as start_date,
        MAX(dr.date_str) as end_date
    FROM uids u
    LEFT JOIN data_records dr ON u.uid = dr.uid
    WHERE u.created_by = ?
    GROUP BY u.uid, u.nickname
    ORDER BY last_update DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_updates = $stmt->fetchAll();

// 单独查询每个 UID 的最近三天数据
foreach ($recent_updates as $key => $update) {
    $stmt = $db->prepare("
        SELECT 
            date_str as date,
            mobile_new,
            pc_new,
            saves,
            (mobile_income + pc_income) as income
        FROM data_records
        WHERE uid = ?
        ORDER BY date_str DESC
        LIMIT 3
    ");
    
    // 添加调试输出
    echo "<!-- Debug: Querying for UID " . htmlspecialchars($update['uid']) . " -->\n";
    
    $stmt->execute([$update['uid']]);
    $recent_days = $stmt->fetchAll();
    
    // 添加调试输出
    echo "<!-- Debug: Found " . count($recent_days) . " days of data -->\n";
    echo "<!-- Debug: " . print_r($recent_days, true) . " -->\n";
    
    $update['recent_days'] = $recent_days;
    $recent_updates[$key] = $update;
}

// 获取页面内容
ob_start();
?>

<div class="container">
    <h2>欢迎回来，<?= htmlspecialchars($_SESSION['username']) ?></h2>
    
    <!-- 总数据统计 -->
    <div class="stats-section">
        <h3>总数据统计</h3>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📱</div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($stats['total_mobile_new']) ?></div>
                    <div class="stat-label">移动端拉新</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💻</div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($stats['total_pc_new']) ?></div>
                    <div class="stat-label">PC端拉新</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💾</div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($stats['total_saves']) ?></div>
                    <div class="stat-label">转存数</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-content">
                    <div class="stat-value">¥<?= number_format($stats['total_income'], 2) ?></div>
                    <div class="stat-label">会员分成</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 快捷操作 -->
    <div class="quick-actions">
        <h3>快捷操作</h3>
        <div class="action-buttons">
            <a href="?route=data_collection" class="button primary">
                <span class="icon">📥</span>
                采集数据
            </a>
            <a href="?route=uid_list" class="button secondary">
                <span class="icon">📋</span>
                管理UID
            </a>
            <?php if ($_SESSION['is_admin']): ?>
            <a href="?route=admin_users" class="button accent">
                <span class="icon">👥</span>
                用户管理
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 最近更新 -->
    <div class="recent-updates">
        <h3>最近更新</h3>
        <?php if ($recent_updates): ?>
        <div class="recent-list">
            <?php foreach ($recent_updates as $update): ?>
            <div class="recent-item">
                <div class="recent-header">
                    <span class="recent-uid"><?= htmlspecialchars($update['nickname'] ?: $update['uid']) ?></span>
                    <div class="recent-dates">
                        <span class="recent-date">最后更新: <?= date('Y-m-d H:i', strtotime($update['last_update'])) ?></span>
                        <span class="date-range">数据范围: <?= $update['start_date'] ?> 至 <?= $update['end_date'] ?></span>
                    </div>
                </div>
                
                <!-- 最近三天数据 -->
                <?php 
                // 调试输出
                echo "<!-- Debug: recent_days for " . htmlspecialchars($update['uid']) . ": " . 
                     print_r($update['recent_days'], true) . " -->\n";

                if (!empty($update['recent_days'])): 
                ?>
                <div class="recent-days-data">
                    <h4>最近三天数据</h4>
                    <div class="days-grid">
                        <?php foreach ($update['recent_days'] as $day): ?>
                        <div class="day-stats">
                            <div class="day-header">
                                <span class="day-date">
                                    <?= date('m-d', strtotime($day['date'])) ?>
                                    (<?= date('D', strtotime($day['date'])) ?>)
                                </span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">移动端拉新</span>
                                <span class="stat-value"><?= number_format($day['mobile_new']) ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">PC端拉新</span>
                                <span class="stat-value"><?= number_format($day['pc_new']) ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">转存数</span>
                                <span class="stat-value"><?= number_format($day['saves']) ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">会员分成</span>
                                <span class="stat-value">¥<?= number_format($day['income'], 2) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- 总计数据 -->
                <div class="total-stats">
                    <h4>总计数据</h4>
                    <div class="recent-stats">
                        <div class="stat-row">
                            <span class="stat-label">记录数</span>
                            <span class="stat-value"><?= number_format($update['record_count']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">移动端拉新</span>
                            <span class="stat-value"><?= number_format($update['total_mobile_new']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">PC端拉新</span>
                            <span class="stat-value"><?= number_format($update['total_pc_new']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">转存数</span>
                            <span class="stat-value"><?= number_format($update['total_saves']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">会员分成</span>
                            <span class="stat-value">¥<?= number_format($update['total_income'], 2) ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="recent-actions">
                    <a href="?route=data_analysis&uid=<?= urlencode($update['uid']) ?>" class="button small">
                        查看详情
                    </a>
                    <a href="?route=data_collection&uid=<?= urlencode($update['uid']) ?>" class="button small secondary">
                        更新数据
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="no-data">暂无数据更新</p>
        <?php endif; ?>
    </div>
</div>

<style>
/* 总数据统计样式 */
.stats-section {
    margin-bottom: 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    font-size: 2rem;
    color: #3498db;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: #2c3e50;
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
}

/* 最近三天数据样式 */
.recent-days {
    margin-bottom: 2rem;
}

.days-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.day-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.day-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #eee;
}

.day-header h4 {
    margin: 0;
    font-size: 1.2rem;
    color: #2c3e50;
}

.day-name {
    color: #666;
    font-size: 0.9rem;
}

.day-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.day-stat {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.day-stat .label {
    color: #666;
    font-size: 0.9rem;
}

.day-stat .value {
    font-weight: bold;
    color: #2c3e50;
}

@media (max-width: 768px) {
    .days-grid {
        grid-template-columns: 1fr;
    }
    
    .day-stats {
        grid-template-columns: 1fr;
    }
}

.recent-item {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 1rem;
}

.recent-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.recent-uid {
    font-size: 1.2rem;
    font-weight: bold;
    color: #2c3e50;
}

.recent-dates {
    text-align: right;
    font-size: 0.9rem;
    color: #666;
}

.date-range {
    display: block;
    margin-top: 0.25rem;
    color: #999;
}

.recent-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.stat-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem;
    background: #f8f9fa;
    border-radius: 4px;
}

.stat-label {
    color: #666;
}

.stat-value {
    font-weight: bold;
    color: #2c3e50;
}

.recent-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

@media (max-width: 768px) {
    .recent-header {
        flex-direction: column;
    }
    
    .recent-dates {
        text-align: left;
        margin-top: 0.5rem;
    }
    
    .recent-stats {
        grid-template-columns: 1fr;
    }
}

/* 添加最近三天数据的样式 */
.recent-days-data {
    margin: 1rem 0;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.recent-days-data h4,
.total-stats h4 {
    margin: 0 0 1rem 0;
    color: #666;
    font-size: 1rem;
}

.days-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.day-stats {
    background: white;
    padding: 1rem;
    border-radius: 4px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.day-header {
    margin-bottom: 0.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #eee;
}

.day-date {
    font-weight: bold;
    color: #2c3e50;
}

.total-stats {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #eee;
}

@media (max-width: 768px) {
    .days-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
$content = ob_get_clean();
require '../templates/layout/base.php';
?> 