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

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// レポートデータ取得
$report_data = [];

// 売上統計
$query = "SELECT 
            COUNT(DISTINCT s.id) as total_sessions,
            COALESCE(SUM(s.total_amount), 0) as total_revenue,
            COALESCE(AVG(s.total_amount), 0) as avg_revenue_per_session,
            COUNT(DISTINCT s.customer_id) as unique_customers
          FROM sessions s
          WHERE DATE(s.created_at) BETWEEN ? AND ?
          AND s.status = 'completed'";
$stmt = $db->prepare($query);
$stmt->execute([$date_from, $date_to]);
$revenue_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// 詳細データ取得
$query = "SELECT 
            s.id as session_id,
            c.name as customer_name,
            u.name as cast_name,
            t.table_number,
            s.created_at,
            s.total_amount,
            s.status
          FROM sessions s
          LEFT JOIN customers c ON s.customer_id = c.id
          LEFT JOIN users u ON s.cast_id = u.id
          LEFT JOIN tables t ON s.table_id = t.id
          WHERE DATE(s.created_at) BETWEEN ? AND ?
          ORDER BY s.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$date_from, $date_to]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CSVヘッダー設定
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="sales_report_' . $date_from . '_to_' . $date_to . '.csv"');

// BOM追加（Excel対応）
echo "\xEF\xBB\xBF";

// CSV出力
$output = fopen('php://output', 'w');

// サマリー情報
fputcsv($output, ['売上レポート']);
fputcsv($output, ['期間', $date_from . ' ～ ' . $date_to]);
fputcsv($output, ['']);
fputcsv($output, ['統計情報']);
fputcsv($output, ['総売上', '¥' . number_format($revenue_stats['total_revenue'])]);
fputcsv($output, ['総セッション数', number_format($revenue_stats['total_sessions'])]);
fputcsv($output, ['平均客単価', '¥' . number_format($revenue_stats['avg_revenue_per_session'])]);
fputcsv($output, ['ユニーク顧客数', number_format($revenue_stats['unique_customers'])]);
fputcsv($output, ['']);

// 詳細データ
fputcsv($output, ['詳細データ']);
fputcsv($output, ['セッションID', '顧客名', 'キャスト名', 'テーブル', '日時', '金額', 'ステータス']);

foreach ($sessions as $session) {
    fputcsv($output, [
        $session['session_id'],
        $session['customer_name'] ?? '',
        $session['cast_name'] ?? '',
        $session['table_number'] ?? '',
        $session['created_at'],
        $session['total_amount'],
        $session['status']
    ]);
}

fclose($output);
exit();
?>
