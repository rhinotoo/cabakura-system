<?php
/**
 * データベース接続確認
 * パス: /config/check_database.php
 * アクセス: http://localhost/cabakura-management/config/check_database.php
 */

require_once __DIR__ . '/database_config.php';

define('DEBUG_MODE', true);

try {
    echo "<h2>🗄️ データベース設定確認</h2>";
    
    $config = getDatabaseConfig();
    $env = getEnvironment();
    
    echo "<div style='background:#e8f4fd;border:1px solid #bee5eb;padding:15px;margin:10px 0;border-radius:5px;'>";
    echo "<strong>📊 Current Configuration:</strong><br>";
    echo "Environment: <strong>{$env}</strong><br>";
    echo "Host: {$config['host']}<br>";
    echo "Database: <strong>{$config['database']}</strong><br>";
    echo "Username: {$config['username']}<br>";
    echo "Port: {$config['port']}<br>";
    echo "Charset: {$config['charset']}<br>";
    echo "</div>";
    
    $pdo = getDatabaseConnection();
    
    echo "<div style='background:#d4edda;border:1px solid #c3e6cb;padding:15px;margin:10px 0;color:#155724;border-radius:5px;'>";
    echo "<strong>✅ データベース接続成功!</strong><br>";
    
    $stmt = $pdo->query("SELECT DATABASE() as current_db");
    $result = $stmt->fetch();
    echo "接続中のデータベース: <strong>{$result['current_db']}</strong><br>";
    
    $stmt = $pdo->query("SELECT VERSION() as version");
    $result = $stmt->fetch();
    echo "MySQLバージョン: {$result['version']}<br>";
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background:#f8d7da;border:1px solid #f5c6cb;padding:15px;margin:10px 0;color:#721c24;border-radius:5px;'>";
    echo "<strong>❌ エラー:</strong><br>";
    echo $e->getMessage();
    echo "</div>";
}
?>
