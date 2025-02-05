<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/DataCollector.php';

$db = Database::getInstance()->getConnection();
$collector = new DataCollector($db);

// 检查是否是工作日
$weekday = date('N');
if ($weekday >= 6) {
    exit('今天是周末，不执行更新');
}

// 获取需要更新的UID
$current_time = date('H:i:00');
$stmt = $db->prepare("
    SELECT * FROM uid_settings 
    WHERE auto_update = 1 
    AND update_time = ?
");
$stmt->execute([$current_time]);
$settings = $stmt->fetchAll();

foreach ($settings as $setting) {
    try {
        // 设置日期范围为最近7天
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-7 days'));
        
        $result = $collector->collect($setting['url'], $setting['uid'], $start_date, $end_date);
        
        if ($result['success']) {
            echo sprintf(
                "成功更新 UID %s 的数据，共 %d 条记录\n",
                $setting['uid'],
                $result['data']['total']
            );
        } else {
            echo sprintf(
                "更新 UID %s 失败：%s\n",
                $setting['uid'],
                $result['error']
            );
        }
    } catch (Exception $e) {
        echo sprintf(
            "处理 UID %s 时发生错误：%s\n",
            $setting['uid'],
            $e->getMessage()
        );
    }
} 