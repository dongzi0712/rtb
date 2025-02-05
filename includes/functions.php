<?php
/**
 * 通用辅助函数文件
 */

/**
 * 安全的输出HTML
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * 格式化日期
 */
function format_date($date, $format = 'Y-m-d H:i') {
    return date($format, strtotime($date));
}

/**
 * 格式化数字（添加千位分隔符）
 */
function format_number($number, $decimals = 0) {
    return number_format($number, $decimals, '.', ',');
}

/**
 * 生成随机字符串
 */
function random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $string = '';
    for ($i = 0; $i < $length; $i++) {
        $string .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $string;
}

/**
 * 检查是否为AJAX请求
 */
function is_ajax_request() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * 获取当前URL
 */
function current_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * 重定向并退出
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * 设置闪存消息
 */
function set_flash_message($message, $type = 'success') {
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * 获取并清除闪存消息
 */
function get_flash_message() {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * 验证日期格式
 */
function validate_date($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * 检查字符串是否为空
 */
function is_empty($value) {
    return !isset($value) || trim($value) === '';
}

/**
 * 获取配置值
 */
function config($key, $default = null) {
    global $config;
    return $config[$key] ?? $default;
}

/**
 * 检查用户是否有权限
 */
function has_permission($permission) {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
}

/**
 * 记录日志
 */
function log_message($message, $type = 'info') {
    $log_file = dirname(__DIR__) . '/storage/logs/' . date('Y-m-d') . '.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $time = date('Y-m-d H:i:s');
    $message = "[$time] [$type] $message" . PHP_EOL;
    file_put_contents($log_file, $message, FILE_APPEND);
} 