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

// ログテーブルが存在しない場合は作成
try {
    $query = "CREATE TABLE IF NOT EXISTS system_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(100) NOT NULL,
        description TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )";
    $db->exec($query);
} catch (Exception $e) {
    // テーブル作成エラーは無視
}

// フィルタリング条件
$where_conditions = [];
$params = [];

$user_filter = $_GET['user_filter'] ?? '';
$action_filter = $_GET['action_filter'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

if (!empty($user_filter)) {
    $where_conditions[] = "u.name LIKE ?";
    $params[] = "%$user_filter%";
}

if (!empty($action_filter)) {
    $where_conditions[] = "sl.action LIKE ?";
    $params[] = "%$action_filter%";
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(sl.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(sl.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// ログ一覧取得
$query = "SELECT sl.*, u.name as user_name
          FROM system_logs sl
          LEFT JOIN users u ON sl.user_id = u.id
          $where_clause
          ORDER BY sl.created_at DESC
          LIMIT 1000";
$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ログ統計
$query = "SELECT 
            COUNT(*) as total_logs,
            COUNT(DISTINCT user_id) as unique_users,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_logs,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_logs
          FROM system_logs";
$stmt = $db->prepare($query);
$stmt->execute();
$log_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// よく使われるアクション
$query = "SELECT action, COUNT(*) as count 
          FROM system_logs 
          GROUP BY action 
          ORDER BY count DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$popular_actions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログ管理 - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/style.css">
    <style>
        .log-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border-left: 4px solid #17a2b8;
        }
        .filter-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .filter-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        .log-entry {
            border-left: 4px solid #6c757d;
            padding: 10px;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 0 5px 5px 0;
        }
        .log-entry.login { border-left-color: #28a745; }
        .log-entry.logout { border-left-color: #dc3545; }
        .log-entry.order { border-left-color: #007bff; }
        .log-entry.payment { border-left-color: #ffc107; }
        .log-entry.error { border-left-color: #e74c3c; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>📋 ログ管理</h1>
                <div class="user-info">
                    <a href="index.php" style="color: white;">← マスタ管理に戻る</a>
                </div>
            </div>
            
            <!-- ログ統計 -->
            <h2>📊 ログ統計</h2>
            <div class="log-stats">
                <div class="stat-card">
                    <h3><?php echo number_format($log_stats['total_logs']); ?></h3>
                    <p>総ログ数</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($log_stats['unique_users']); ?></h3>
                    <p>アクティブユーザー数</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($log_stats['today_logs']); ?></h3>
                    <p>今日のログ数</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($log_stats['week_logs']); ?></h3>
                    <p>今週のログ数</p>
                </div>
            </div>
            
            <!-- よく使われるアクション -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 30px;">
                <div>
                    <!-- フィルタフォーム -->
                    <div class="filter-form">
                        <h3>🔍 ログ検索・フィルタ</h3>
                        <form method="GET">
                            <div class="filter-row">
                                <div>
                                    <label>ユーザー名</label>
                                    <input type="text" name="user_filter" value="<?php echo htmlspecialchars($user_filter); ?>" 
                                           class="form-control" placeholder="ユーザー名で検索">
                                </div>
                                <div>
                                    <label>アクション</label>
                                    <input type="text" name="action_filter" value="<?php echo htmlspecialchars($action_filter); ?>" 
                                           class="form-control" placeholder="アクションで検索">
                                </div>
                                <div>
                                    <label>開始日</label>
                                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                                           class="form-control">
                                </div>
                                <div>
                                    <label>終了日</label>
                                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                                           class="form-control">
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-primary">検索</button>
                                    <a href="logs.php" class="btn" style="background: #6c757d; margin-left: 5px;">リセット</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div>
                    <h3>📈 人気アクション</h3>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 10px;">
                        <?php foreach ($popular_actions as $action): ?>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span><?php echo htmlspecialchars($action['action']); ?></span>
                                <strong><?php echo number_format($action['count']); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- ログ一覧 -->
            <h2>📝 ログ一覧 (最新1000件)</h2>
            <?php if (empty($logs)): ?>
                <div style="text-align: center; padding: 50px; color: #6c757d;">
                    <p>ログが見つかりませんでした。</p>
                </div>
            <?php else: ?>
                <div style="max-height: 600px; overflow-y: auto;">
                    <?php foreach ($logs as $log): ?>
                        <div class="log-entry <?php echo strtolower($log['action']); ?>">
                            <div style="display: flex; justify-content: between; align-items: center;">
                                <div style="flex: 1;">
                                    <strong><?php echo htmlspecialchars($log['action']); ?></strong>
                                    <?php if ($log['user_name']): ?>
                                        - <?php echo htmlspecialchars($log['user_name']); ?>
                                    <?php endif; ?>
                                    <div style="font-size: 14px; color: #6c757d; margin-top: 5px;">
                                        <?php echo htmlspecialchars($log['description'] ?? ''); ?>
                                    </div>
                                    <?php if ($log['ip_address']): ?>
                                        <div style="font-size: 12px; color: #6c757d;">
                                            IP: <?php echo htmlspecialchars($log['ip_address']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div style="text-align: right; font-size: 12px; color: #6c757d;">
                                    <?php echo date('Y/m/d H:i:s', strtotime($log['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- ログ管理機能 -->
            <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-radius: 10px;">
                <h3>🔧 ログ管理機能</h3>
                <div style="display: flex; gap: 15px; margin-top: 15px;">
                    <button onclick="exportLogs()" class="btn btn-info">ログエクスポート</button>
                    <button onclick="clearOldLogs()" class="btn btn-warning">古いログ削除</button>
                    <button onclick="downloadErrorLogs()" class="btn btn-danger">エラーログダウンロード</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function exportLogs() {
            if (confirm('ログをCSVファイルでエクスポートしますか？')) {
                window.location.href = 'export_logs.php';
            }
        }
        
        function clearOldLogs() {
            if (confirm('30日以上古いログを削除しますか？\nこの操作は取り消せません。')) {
                // 実際の削除処理
                alert('この機能は実装中です。');
            }
        }
        
        function downloadErrorLogs() {
            if (confirm('エラーログをダウンロードしますか？')) {
                // エラーログダウンロード処理
                alert('この機能は実装中です。');
            }
        }
    </script>
</body>
</html>
