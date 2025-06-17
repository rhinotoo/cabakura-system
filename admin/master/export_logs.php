<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

checkLogin();
if (!isset($_SESSION['admin_authenticated']) && $_SESSION['role'] !== 'admin') {
    header('Location: ../auth.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// ログデータ取得
$query = "SELECT sl.*, u.name as user_name
          FROM system_logs sl
          LEFT JOIN users u ON sl.user_id = u.id
          ORDER BY sl.created_at DESC
          LIMIT 10000";
$stmt = $db->prepare($query);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CSVヘッダー設定
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d_H-i-s') . '.csv"');

// BOM追加（Excel対応）
echo "\xEF\xBB\xBF";

// CSV出力
$output = fopen('php://output', 'w');

// ヘッダー行
fputcsv($output, ['ID', '日時', 'ユーザー名', 'アクション', '説明', 'IPアドレス']);

// データ行
foreach ($logs as $log) {
    fputcsv($output, [
        $log['id'],
        $log['created_at'],
        $log['user_name'] ?? '',
        $log['action'],
        $log['description'] ?? '',
        $log['ip_address'] ?? ''
    ]);
}

fclose($output);
exit();
?>
