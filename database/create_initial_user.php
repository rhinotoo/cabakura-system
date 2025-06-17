<?php
/**
 * 初期ユーザー作成スクリプト
 * パス: /database/create_initial_user.php
 * 一度だけ実行してデモユーザーを作成
 */

require_once __DIR__ . '/../bootstrap.php';

try {
    $pdo = getDatabaseConnection();
    
    // usersテーブルの作成
    $sql = "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin', 'manager', 'staff') DEFAULT 'staff',
        active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    $pdo->exec($sql);
    
    // デモユーザーの作成
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        $password_hash = password_hash('password123', PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password_hash, role) 
            VALUES ('admin', ?, 'admin')
        ");
        $stmt->execute([$password_hash]);
        
        echo "✅ 初期ユーザーを作成しました<br>";
        echo "ユーザー名: admin<br>";
        echo "パスワード: password123<br>";
    } else {
        echo "ℹ️ 初期ユーザーは既に存在します<br>";
    }
    
    echo "<br><a href='../public/login.php'>ログイン画面へ</a>";
    
} catch (Exception $e) {
    echo "❌ エラー: " . $e->getMessage();
}
?>
