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

$message = '';
$error = '';

// バックアップ作成
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'backup') {
    try {
        $backup_dir = '../../backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . $filename;
        
        // データベース情報取得
        $config = require '../../config/config.php';
        
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s %s > %s',
            escapeshellarg($config['db_user']),
            escapeshellarg($config['db_pass']),
            escapeshellarg($config['db_host']),
            escapeshellarg($config['db_name']),
            escapeshellarg($filepath)
        );
        
        exec($command, $output, $return_code);
        
        if ($return_code === 0) {
            $message = 'バックアップを作成しました: ' . $filename;
        } else {
            $error = 'バックアップの作成に失敗しました。';
        }
    } catch (Exception $e) {
        $error = 'エラーが発生しました: ' . $e->getMessage();
    }
}

// データベース統計取得
$stats = [];
$tables = ['users', 'customers', 'sessions', 'orders', 'menu_items', 'tables', 'debts', 'debt_payments', 'attendance'];

foreach ($tables as $table) {
    try {
        $query = "SELECT COUNT(*) as count FROM $table";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats[$table] = $result['count'];
    } catch (Exception $e) {
        $stats[$table] = 'エラー';
    }
}

// バックアップファイル一覧
$backup_files = [];
$backup_dir = '../../backups/';
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (strpos($file, 'backup_') === 0 && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $filepath = $backup_dir . $file;
            $backup_files[] = [
                'name' => $file,
                'size' => filesize($filepath),
                'date' => filemtime($filepath)
            ];
        }
    }
    // 日付順でソート
    usort($backup_files, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>データベース管理 - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/style.css">
    <style>
        .db-stats {
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
            border-left: 4px solid #3498db;
        }
        .backup-section {
            background: #fff3cd;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #ffc107;
        }
        .danger-section {
            background: #f8d7da;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>💾 データベース管理</h1>
                <div class="user-info">
                    <a href="index.php" style="color: white;">← マスタ管理に戻る</a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- データベース統計 -->
            <h2>📊 データベース統計</h2>
            <div class="db-stats">
                <div class="stat-card">
                    <h3><?php echo $stats['users']; ?></h3>
                    <p>ユーザー数</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['customers']; ?></h3>
                    <p>顧客数</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['sessions']; ?></h3>
                    <p>セッション数</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['orders']; ?></h3>
                    <p>注文数</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['menu_items']; ?></h3>
                    <p>メニュー数</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['tables']; ?></h3>
                    <p>テーブル数</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['debts']; ?></h3>
                    <p>借金記録数</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['attendance']; ?></h3>
                    <p>勤怠記録数</p>
                </div>
            </div>
            
            <!-- バックアップ -->
            <div class="backup-section">
                <h2>💾 データベースバックアップ</h2>
                <p>重要なデータを保護するため、定期的にバックアップを作成してください。</p>
                <form method="POST" onsubmit="return confirm('バックアップを作成しますか？');">
                    <input type="hidden" name="action" value="backup">
                    <button type="submit" class="btn btn-warning">バックアップ作成</button>
                </form>
            </div>
            
            <!-- バックアップファイル一覧 -->
            <h2>📁 バックアップファイル一覧</h2>
            <div class="table">
                <table>
                    <thead>
                        <tr>
                            <th>ファイル名</th>
                            <th>作成日時</th>
                            <th>サイズ</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($backup_files)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #6c757d;">バックアップファイルがありません</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($backup_files as $file): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($file['name']); ?></td>
                                    <td><?php echo date('Y/m/d H:i:s', $file['date']); ?></td>
                                    <td><?php echo number_format($file['size'] / 1024, 1); ?> KB</td>
                                    <td>
                                        <a href="../../backups/<?php echo urlencode($file['name']); ?>" 
                                           class="btn btn-info" style="padding: 5px 10px; font-size: 12px;" download>
                                            ダウンロード
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- 危険な操作 -->
            <div class="danger-section">
                <h2>⚠️ 危険な操作</h2>
                <p><strong>注意:</strong> 以下の操作は取り消すことができません。必ずバックアップを作成してから実行してください。</p>
                
                <div style="margin-top: 20px;">
                    <h3>🗑️ 古いデータの削除</h3>
                    <p>指定した期間より古いデータを削除します。</p>
                    <form method="POST" onsubmit="return confirm('本当に古いデータを削除しますか？この操作は取り消せません。');" style="display: inline;">
                        <input type="hidden" name="action" value="cleanup_old_data">
                        <select name="cleanup_days" class="form-control" style="width: 200px; display: inline-block;">
                            <option value="90">90日前</option>
                            <option value="180">180日前</option>
                            <option value="365">1年前</option>
                        </select>
                        <button type="submit" class="btn btn-danger">古いデータ削除</button>
                    </form>
                </div>
                
                <div style="margin-top: 20px;">
                    <h3>🔄 データベース初期化</h3>
                    <p>全てのデータを削除し、初期状態に戻します。</p>
                    <button onclick="confirmReset()" class="btn btn-danger">データベース初期化</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function confirmReset() {
            if (confirm('本当にデータベースを初期化しますか？\n全てのデータが削除され、復元できません。')) {
                if (confirm('最終確認: 本当に実行しますか？')) {
                    // 実際の初期化処理をここに実装
                    alert('この機能は安全のため無効化されています。\n必要な場合は開発者にお問い合わせください。');
                }
            }
        }
    </script>
</body>
</html>
