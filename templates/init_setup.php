<?php
// 检查配置文件是否存在
$config_file = dirname(__DIR__) . '/config/config.php';
$config_exists = file_exists($config_file);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$config_exists) {
            // 保存数据库配置
            $db_host = $_POST['db_host'] ?? '';
            $db_name = $_POST['db_name'] ?? '';
            $db_user = $_POST['db_user'] ?? '';
            $db_pass = $_POST['db_pass'] ?? '';
            
            if (empty($db_host) || empty($db_name) || empty($db_user)) {
                throw new Exception('请填写所有数据库配置信息');
            }
            
            // 测试数据库连接
            try {
                $test_conn = new PDO(
                    "mysql:host=$db_host;charset=utf8mb4",
                    $db_user,
                    $db_pass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                
                // 创建数据库（如果不存在）
                $test_conn->exec("CREATE DATABASE IF NOT EXISTS `$db_name`");
                
                // 生成配置文件
                $config_content = "<?php\n";
                $config_content .= "// 数据库配置\n";
                $config_content .= "define('DB_HOST', " . var_export($db_host, true) . ");\n";
                $config_content .= "define('DB_NAME', " . var_export($db_name, true) . ");\n";
                $config_content .= "define('DB_USER', " . var_export($db_user, true) . ");\n";
                $config_content .= "define('DB_PASS', " . var_export($db_pass, true) . ");\n\n";
                $config_content .= "// 应用配置\n";
                $config_content .= "define('APP_NAME', '数据分析平台');\n";
                $config_content .= "define('APP_URL', 'http://' . \$_SERVER['HTTP_HOST']);\n";
                $config_content .= "define('SESSION_LIFETIME', 7 * 24 * 60 * 60);\n\n";
                $config_content .= "// 错误报告\n";
                $config_content .= "error_reporting(E_ALL);\n";
                $config_content .= "ini_set('display_errors', 1);\n\n";
                $config_content .= "// 会话配置\n";
                $config_content .= "ini_set('session.gc_maxlifetime', SESSION_LIFETIME);\n";
                $config_content .= "ini_set('session.cookie_lifetime', SESSION_LIFETIME);\n";
                $config_content .= "session_set_cookie_params(SESSION_LIFETIME);\n";
                
                // 保存配置文件
                if (!is_dir(dirname($config_file))) {
                    mkdir(dirname($config_file), 0755, true);
                }
                if (file_put_contents($config_file, $config_content) === false) {
                    throw new Exception('无法写入配置文件');
                }
                
                // 重新加载页面以使用新配置
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            } catch (PDOException $e) {
                throw new Exception('数据库连接失败：' . $e->getMessage());
            }
        } else {
            // 创建管理员账号
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($username) || empty($password) || empty($confirm_password)) {
                throw new Exception('所有字段都必须填写');
            }
            
            if ($password !== $confirm_password) {
                throw new Exception('两次输入的密码不一致');
            }
            
            if (strlen($password) < 6) {
                throw new Exception('密码长度至少6位');
            }
            
            // 创建数据表
            $db->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    is_admin BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
            
            $db->exec("
                CREATE TABLE IF NOT EXISTS uids (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    uid VARCHAR(50) NOT NULL,
                    nickname VARCHAR(100),
                    created_by INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_uid_user (uid, created_by),
                    FOREIGN KEY (created_by) REFERENCES users(id)
                )
            ");
            
            $db->exec("
                CREATE TABLE IF NOT EXISTS data_records (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    uid VARCHAR(50) NOT NULL,
                    date_str DATE NOT NULL,
                    mobile_new INT DEFAULT 0,
                    pc_new INT DEFAULT 0,
                    saves INT DEFAULT 0,
                    mobile_income DECIMAL(10,2) DEFAULT 0.00,
                    pc_income DECIMAL(10,2) DEFAULT 0.00,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_uid_date (uid, date_str)
                )
            ");
            
            $db->exec("
                CREATE TABLE IF NOT EXISTS uid_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    uid VARCHAR(50) NOT NULL,
                    url TEXT NOT NULL,
                    auto_update BOOLEAN DEFAULT TRUE,
                    update_time TIME DEFAULT '21:00:00',
                    created_by INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_uid_user (uid, created_by),
                    FOREIGN KEY (created_by) REFERENCES users(id)
                )
            ");
            
            // 在创建其他表之后添加价格设置表
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
            
            // 创建管理员账号
            $stmt = $db->prepare("
                INSERT INTO users (username, password_hash, is_admin)
                VALUES (?, ?, 1)
            ");
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            
            $_SESSION['flash'] = '系统初始化成功，请使用新创建的管理员账号登录';
            header('Location: ?route=login');
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// 获取页面内容
ob_start();
?>

<div class="container">
    <div class="auth-form">
        <h2>系统初始化</h2>
        
        <?php if (!$config_exists): ?>
            <p class="setup-info">首次使用系统，请配置数据库信息</p>
            
            <?php if (isset($error)): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" class="form">
                <div class="form-group">
                    <label for="db_host">数据库主机：</label>
                    <input type="text" id="db_host" name="db_host" required 
                           value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>">
                </div>
                
                <div class="form-group">
                    <label for="db_name">数据库名：</label>
                    <input type="text" id="db_name" name="db_name" required
                           value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="db_user">数据库用户名：</label>
                    <input type="text" id="db_user" name="db_user" required
                           value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="db_pass">数据库密码：</label>
                    <input type="password" id="db_pass" name="db_pass">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button primary">保存数据库配置</button>
                </div>
            </form>
            
        <?php else: ?>
            <p class="setup-info">请设置管理员账号</p>
            
            <?php if (isset($error)): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" class="form">
                <div class="form-group">
                    <label for="username">管理员用户名：</label>
                    <input type="text" id="username" name="username" required 
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
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
                    <button type="submit" class="button primary">初始化系统</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require '../templates/layout/base.php';
?> 