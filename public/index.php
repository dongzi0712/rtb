<?php
// 检查配置文件是否存在
$config_file = dirname(__DIR__) . '/config/config.php';
$config_exists = file_exists($config_file);

// 如果配置文件不存在，直接跳转到初始化页面
if (!$config_exists && $_GET['route'] !== 'init_setup') {
    header('Location: ?route=init_setup');
    exit;
}

// 只有在配置文件存在时才加载配置和数据库
if ($config_exists) {
    require_once '../config/config.php';
    require_once '../includes/Database.php';
    require_once '../includes/Auth.php';
} else {
    // 初始化页面不需要数据库连接
    require_once '../includes/functions.php';
    $route = 'init_setup';
    require '../templates/init_setup.php';
    exit;
}

require_once '../includes/functions.php';
require_once '../includes/BaseController.php';

session_start();

$controller = new BaseController();
$db = $controller->db;
$auth = $controller->auth;

// 路由处理
$route = $_GET['route'] ?? 'home';

// 检查系统是否初始化（检查是否有管理员用户）
function isSystemInitialized($db) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE is_admin = 1");
        $stmt->execute();
        return $stmt->fetch()['count'] > 0;
    } catch (Exception $e) {
        return false;
    }
}

// 如果系统未初始化且不是在初始化页面，则重定向到初始化页面
if (!isSystemInitialized($db) && $route !== 'init_setup') {
    header('Location: ?route=init_setup');
    exit;
}

// 需要登录的路由
$protected_routes = ['home', 'data_collection', 'data_analysis', 'uid_list'];
if (in_array($route, $protected_routes) && !$auth->isLoggedIn()) {
    header('Location: ?route=login');
    exit;
}

// 需要管理员权限的路由
$admin_routes = ['admin_users'];
if (in_array($route, $admin_routes) && !$auth->isAdmin()) {
    header('Location: ?route=home');
    exit;
}

// 路由映射
switch ($route) {
    case 'login':
        require '../templates/login.php';
        break;
    case 'register':
        require '../templates/register.php';
        break;
    case 'home':
        require '../templates/home.php';
        break;
    case 'uid_list':
        require '../templates/uid_list.php';
        break;
    case 'update_nickname':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $uid = $_POST['uid'] ?? '';
            $nickname = $_POST['nickname'] ?? '';
            
            if ($uid && $nickname) {
                try {
                    $stmt = $db->prepare("
                        UPDATE uids 
                        SET nickname = ?
                        WHERE uid = ?" . 
                        (!$_SESSION['is_admin'] ? " AND created_by = ?" : "")
                    );
                    
                    $params = [$nickname, $uid];
                    if (!$_SESSION['is_admin']) {
                        $params[] = $_SESSION['user_id'];
                    }
                    
                    $stmt->execute($params);
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Missing uid or nickname']);
            }
        }
        exit;
    case 'admin_users':
        if (!$_SESSION['is_admin']) {
            header('Location: ?route=home');
            exit;
        }
        require '../templates/admin/users.php';
        break;
    case 'toggle_admin':
        if (!$_SESSION['is_admin']) {
            echo json_encode(['success' => false, 'error' => '需要管理员权限']);
            exit;
        }
        
        $user_id = $_POST['user_id'] ?? '';
        $is_admin = $_POST['is_admin'] === 'true';
        
        if ($user_id == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'error' => '不能修改自己的权限']);
            exit;
        }
        
        try {
            $stmt = $db->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
            $stmt->execute([$is_admin, $user_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    case 'reset_password':
        if (!$_SESSION['is_admin']) {
            echo json_encode(['success' => false, 'error' => '需要管理员权限']);
            exit;
        }
        
        $user_id = $_POST['user_id'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        
        if (!$user_id || !$new_password) {
            echo json_encode(['success' => false, 'error' => '缺少必要参数']);
            exit;
        }
        
        try {
            $stmt = $db->prepare("
                UPDATE users 
                SET password_hash = ? 
                WHERE id = ?
            ");
            $stmt->execute([password_hash($new_password, PASSWORD_DEFAULT), $user_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    case 'change_password':
        if (!isset($_SESSION['user_id'])) {
            header('Location: ?route=login');
            exit;
        }
        require '../templates/change_password.php';
        break;
    case 'init_setup':
        if ($config_exists && isSystemInitialized($db)) {
            header('Location: ?route=login');
            exit;
        }
        require '../templates/init_setup.php';
        break;
    case 'data_collection':
        require '../templates/data_collection.php';
        break;
    case 'data_analysis':
        require '../templates/data_analysis.php';
        break;
    case 'update_prices':
        if (!$_SESSION['is_admin']) {
            header('HTTP/1.0 403 Forbidden');
            exit;
        }
        
        try {
            $stmt = $db->prepare("
                INSERT INTO price_settings 
                (start_date, end_date, mobile_new_price, pc_new_price, save_price)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['start_date'],
                $_POST['end_date'],
                $_POST['mobile_new_price'],
                $_POST['pc_new_price'],
                $_POST['save_price']
            ]);
            
            $_SESSION['flash'] = '价格设置已添加';
        } catch (Exception $e) {
            $_SESSION['flash'] = '添加失败：' . $e->getMessage();
        }
        
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    case 'delete_price':
        if (!$_SESSION['is_admin']) {
            echo json_encode(['success' => false, 'error' => '无权限']);
            exit;
        }
        
        try {
            $stmt = $db->prepare("DELETE FROM price_settings WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    // ... 其他路由
    default:
        header('HTTP/1.0 404 Not Found');
        echo '页面未找到';
        break;
} 