<?php
// 数据库配置
define('DB_HOST', 'localhost');
define('DB_USER', 'kuake');
define('DB_PASS', '8PNGeSkaJxhsBPAD');
define('DB_NAME', 'kuake');

// 应用配置
define('APP_NAME', '数据分析平台');
define('APP_URL', 'http://your-domain.com');
define('SESSION_LIFETIME', 7 * 24 * 60 * 60); // 7天

// 错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 会话配置
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.cookie_lifetime', SESSION_LIFETIME);
session_set_cookie_params(SESSION_LIFETIME); 