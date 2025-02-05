<?php
$uid = $_GET['uid'] ?? '';
if (!$uid) {
    header('Location: ?route=uid_list');
    exit;
}

// 获取价格设置
try {
    $stmt = $db->prepare("
        SELECT * FROM price_settings 
        ORDER BY start_date DESC
    ");
    $stmt->execute();
    $price_settings = $stmt->fetchAll();
} catch (Exception $e) {
    // 如果表不存在，创建表并添加默认设置
    $db->exec("
        CREATE TABLE IF NOT EXISTS price_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            mobile_new_price DECIMAL(10,2) NOT NULL,
            pc_new_price DECIMAL(10,2) NOT NULL,
            save_price DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_date_range (start_date, end_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // 添加默认价格设置
    $db->exec("
        INSERT INTO price_settings (start_date, end_date, mobile_new_price, pc_new_price, save_price)
        VALUES ('2024-01-01', '2024-12-31', 0.3, 0.2, 0.1)
    ");
    
    // 重新获取价格设置
    $stmt = $db->prepare("
        SELECT * FROM price_settings 
        ORDER BY start_date DESC
    ");
    $stmt->execute();
    $price_settings = $stmt->fetchAll();
}

// 获取数据
$stmt = $db->prepare("
    SELECT dr.*, u.nickname
    FROM data_records dr
    JOIN uids u ON dr.uid = u.uid
    WHERE dr.uid = ? 
    ORDER BY dr.date_str DESC
");
$stmt->execute([$uid]);
$records = $stmt->fetchAll();

// 计算汇总数据
$total_mobile_new = 0;
$total_pc_new = 0;
$total_saves = 0;
$total_member_income = 0;
$total_daily_income = 0;

// 获取适用的价格
function getPriceForDate($date, $price_settings) {
    foreach ($price_settings as $setting) {
        if ($date >= $setting['start_date'] && $date <= $setting['end_date']) {
            return [
                'mobile_new_price' => $setting['mobile_new_price'],
                'pc_new_price' => $setting['pc_new_price'],
                'save_price' => $setting['save_price']
            ];
        }
    }
    // 默认价格
    return [
        'mobile_new_price' => 0.3,
        'pc_new_price' => 0.2,
        'save_price' => 0.1
    ];
}

foreach ($records as &$record) {
    $total_mobile_new += $record['mobile_new'];
    $total_pc_new += $record['pc_new'];
    $total_saves += $record['saves'];
    $total_member_income += ($record['mobile_income'] + $record['pc_income']);
    
    // 获取当天适用的价格
    $prices = getPriceForDate($record['date_str'], $price_settings);
    
    // 计算每日收入
    $daily_income = 
        $record['mobile_new'] * $prices['mobile_new_price'] + 
        $record['pc_new'] * $prices['pc_new_price'] + 
        $record['saves'] * $prices['save_price'] + 
        ($record['mobile_income'] + $record['pc_income']);
    
    $record['daily_income'] = $daily_income;
    $record['prices'] = $prices;
    $total_daily_income += $daily_income;
}

// 获取页面内容
ob_start();
?>

<div class="container">
    <h2>数据分析 - UID: <?= htmlspecialchars($records[0]['nickname'] ?: $uid) ?></h2>
    
    <?php if ($_SESSION['is_admin']): ?>
    <div class="page-actions">
        <button class="button primary" id="openPriceSettings">
            <span class="icon">💰</span>
            设置价格
        </button>
    </div>
    <?php endif; ?>
    
    <!-- 价格设置弹窗 -->
    <?php if ($_SESSION['is_admin']): ?>
    <div id="priceSettingsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>价格设置</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="?route=update_prices" class="form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_date">开始日期：</label>
                            <input type="date" id="start_date" name="start_date" required>
                        </div>
                        <div class="form-group">
                            <label for="end_date">结束日期：</label>
                            <input type="date" id="end_date" name="end_date" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>移动端拉新单价：</label>
                            <input type="number" name="mobile_new_price" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>PC端拉新单价：</label>
                            <input type="number" name="pc_new_price" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>转存单价：</label>
                            <input type="number" name="save_price" step="0.01" required>
                        </div>
                    </div>
                    <button type="submit" class="button primary">添加价格设置</button>
                </form>
                
                <!-- 显示现有价格设置 -->
                <div class="price-list">
                    <h4>现有价格设置</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>日期范围</th>
                                <th>移动端拉新单价</th>
                                <th>PC端拉新单价</th>
                                <th>转存单价</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($price_settings as $setting): ?>
                            <tr>
                                <td><?= $setting['start_date'] ?> 至 <?= $setting['end_date'] ?></td>
                                <td>¥<?= number_format($setting['mobile_new_price'], 2) ?></td>
                                <td>¥<?= number_format($setting['pc_new_price'], 2) ?></td>
                                <td>¥<?= number_format($setting['save_price'], 2) ?></td>
                                <td>
                                    <button class="button small delete-price" 
                                            data-id="<?= $setting['id'] ?>">删除</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 汇总信息 -->
    <div class="summary">
        <h3>数据汇总</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <span class="label">移动端拉新</span>
                <span class="value"><?= number_format($total_mobile_new) ?></span>
            </div>
            <div class="summary-item">
                <span class="label">PC端拉新</span>
                <span class="value"><?= number_format($total_pc_new) ?></span>
            </div>
            <div class="summary-item">
                <span class="label">转存数</span>
                <span class="value"><?= number_format($total_saves) ?></span>
            </div>
            <div class="summary-item">
                <span class="label">会员分成</span>
                <span class="value">¥<?= number_format($total_member_income, 2) ?></span>
            </div>
            <div class="summary-item">
                <span class="label">总收入</span>
                <span class="value">¥<?= number_format($total_daily_income, 2) ?></span>
            </div>
        </div>
    </div>
    
    <!-- 图表 -->
    <div class="charts">
        <!-- 趋势图 -->
        <div class="chart-container">
            <canvas id="newUsersChart"></canvas>
        </div>
        <div class="chart-container">
            <canvas id="incomeChart"></canvas>
        </div>
        <!-- 饼图 -->
        <div class="chart-container">
            <canvas id="usersPieChart"></canvas>
        </div>
        <div class="chart-container">
            <canvas id="savesChart"></canvas>
        </div>
        <!-- 柱状图 -->
        <div class="chart-container">
            <canvas id="dailyIncomeChart"></canvas>
        </div>
    </div>
    
    <!-- 详细数据表格 -->
    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>日期</th>
                    <th>移动端拉新</th>
                    <th>PC端拉新</th>
                    <th>转存数</th>
                    <th>会员分成</th>
                    <th>每日收入</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $record): ?>
                <tr>
                    <td><?= htmlspecialchars($record['date_str']) ?></td>
                    <td><?= number_format($record['mobile_new']) ?></td>
                    <td><?= number_format($record['pc_new']) ?></td>
                    <td><?= number_format($record['saves']) ?></td>
                    <td>¥<?= number_format($record['mobile_income'] + $record['pc_income'], 2) ?></td>
                    <td>¥<?= number_format($record['daily_income'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 图表脚本 -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 准备数据
    const records = <?= json_encode($records) ?>;
    const dates = records.map(r => r.date_str).reverse();
    const mobileNew = records.map(r => r.mobile_new).reverse();
    const pcNew = records.map(r => r.pc_new).reverse();
    const saves = records.map(r => r.saves).reverse();
    const memberIncome = records.map(r => r.mobile_income + r.pc_income).reverse();
    const dailyIncome = records.map(r => r.daily_income).reverse();
    
    // 拉新趋势图
    new Chart(document.getElementById('newUsersChart'), {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: '移动端拉新',
                data: mobileNew,
                borderColor: '#4CAF50',
                fill: false
            }, {
                label: 'PC端拉新',
                data: pcNew,
                borderColor: '#2196F3',
                fill: false
            }]
        },
        options: {
            responsive: true,
            title: {
                display: true,
                text: '拉新趋势'
            }
        }
    });
    
    // 收入趋势图
    new Chart(document.getElementById('incomeChart'), {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: '会员分成',
                data: memberIncome,
                borderColor: '#F44336',
                fill: false
            }, {
                label: '每日收入',
                data: dailyIncome,
                borderColor: '#9C27B0',
                fill: false
            }]
        },
        options: {
            responsive: true,
            title: {
                display: true,
                text: '收入趋势'
            }
        }
    });
    
    // 拉新来源饼图
    new Chart(document.getElementById('usersPieChart'), {
        type: 'pie',
        data: {
            labels: ['移动端拉新', 'PC端拉新'],
            datasets: [{
                data: [
                    mobileNew.reduce((a, b) => a + b, 0),
                    pcNew.reduce((a, b) => a + b, 0)
                ],
                backgroundColor: ['#4CAF50', '#2196F3']
            }]
        },
        options: {
            responsive: true,
            title: {
                display: true,
                text: '拉新来源分布'
            }
        }
    });
    
    // 转存趋势图
    new Chart(document.getElementById('savesChart'), {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: '转存数',
                data: saves,
                borderColor: '#FF9800',
                fill: false
            }]
        },
        options: {
            responsive: true,
            title: {
                display: true,
                text: '转存趋势'
            }
        }
    });
    
    // 每日收入柱状图
    new Chart(document.getElementById('dailyIncomeChart'), {
        type: 'bar',
        data: {
            labels: dates,
            datasets: [{
                label: '每日收入',
                data: dailyIncome,
                backgroundColor: '#9C27B0'
            }]
        },
        options: {
            responsive: true,
            title: {
                display: true,
                text: '每日收入统计'
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // 添加价格设置弹窗相关代码
    const modal = document.getElementById('priceSettingsModal');
    const openButton = document.getElementById('openPriceSettings');
    const closeButton = document.querySelector('.close-modal');

    if (openButton) {
        openButton.addEventListener('click', function() {
            modal.style.display = 'block';
        });
    }

    if (closeButton) {
        closeButton.addEventListener('click', function() {
            modal.style.display = 'none';
        });
    }

    // 点击弹窗外部关闭
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });

    // 删除价格设置
    document.querySelectorAll('.delete-price').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation(); // 防止触发弹窗关闭
            if (confirm('确定要删除这个价格设置吗？')) {
                fetch('?route=delete_price', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${this.dataset.id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.error || '删除失败');
                    }
                });
            }
        });
    });
});
</script>

<style>
.charts {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 2rem;
    margin: 2rem 0;
}

.chart-container {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.price-settings {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.price-list {
    margin-top: 1.5rem;
}

.form-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .charts {
        grid-template-columns: 1fr;
    }
    .form-row {
        flex-direction: column;
    }
}

.page-actions {
    margin-bottom: 2rem;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    z-index: 1000;
}

.modal-content {
    position: relative;
    background-color: white;
    margin: 2rem auto;
    padding: 0;
    width: 90%;
    max-width: 800px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #eee;
}

.modal-header h3 {
    margin: 0;
}

.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    color: #666;
}

.modal-body {
    padding: 1.5rem;
    max-height: calc(100vh - 200px);
    overflow-y: auto;
}

.price-list {
    margin-top: 2rem;
}

.price-list h4 {
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
    }
    
    .modal-content {
        width: 95%;
        margin: 1rem auto;
    }
}
</style>

<?php
$content = ob_get_clean();
require '../templates/layout/base.php';
?> 