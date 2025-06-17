<?php
/**
 * 統一データベース名設定
 * パス: /config/database_config.php
 */

// 実際のサーバーIDに置き換えてください
define('SERVER_ID', 'your_server_id');
define('DB_NAME', SERVER_ID . '_cabakura');

function getEnvironment() {
    // エックスサーバー判定
    if (strpos($_SERVER['HTTP_HOST'] ?? '', '.xsrv.jp') !== false) {
        return 'production';
    }
    
    // ローカル環境判定
    if (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'])) {
        return 'development';
    }
    
    return 'development';
}

function getDatabaseConfig() {
    $env = getEnvironment();
    $database_name = DB_NAME;
    
    // 環境別設定ファイル読み込み
    $config_file = __DIR__ . "/config.{$env}.php";
    if (file_exists($config_file)) {
        $env_config = include $config_file;
        $env_config['database'] = $database_name; // 統一データベース名を設定
        return $env_config;
    }
    
    // デフォルト設定
    return [
        'host' => 'localhost',
        'database' => $database_name,
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'port' => 3306,
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    ];
}

function getDatabaseConnection() {
    $config = getDatabaseConfig();
    
    try {
        $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']};port={$config['port']}";
        return new PDO($dsn, $config['username'], $config['password'], $config['options']);
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw new Exception("データベース接続に失敗しました");
    }
}
?>
