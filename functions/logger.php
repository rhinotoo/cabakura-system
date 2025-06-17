<?php
function logAction($user_id, $action, $description = null, $db = null) {
    if (!$db) {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
    }
    
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $query = "INSERT INTO system_logs (user_id, action, description, ip_address, user_agent) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id, $action, $description, $ip_address, $user_agent]);
    } catch (Exception $e) {
        // ログ記録エラーは無視（無限ループ防止）
        error_log("Logger error: " . $e->getMessage());
    }
}

// 使用例:
// logAction($_SESSION['user_id'], 'login', 'ユーザーがログインしました');
// logAction($_SESSION['user_id'], 'order_create', '注文を作成しました: テーブル1');
// logAction($_SESSION['user_id'], 'payment_complete', '支払いが完了しました: ¥5000');
?>
