<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

checkLogin();
if (!isset($_SESSION['admin_authenticated']) && $_SESSION['role'] !== 'admin') {
    header('Location: ../auth.php');
    exit();
}

$message = '';
$error = '';

// 自動バックアップ設定
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'set_auto_backup') {
    $auto_backup_enabled = isset($_POST['auto_backup_enabled']) ? 1 : 0;
    $backup_frequency = $_POST['backup_frequency'] ?? 'daily';
    $backup_retention_days = $_POST['backup_retention_days'] ?? 30;
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $settings = [
            'auto_backup_enabled' => $auto_backup_enabled,
            'backup_frequency' => $backup_frequency,
            'backup_retention_days' => $backup_retention_days
        ];
        
        foreach ($settings as $key => $value) {
            $query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                      ON DUPLICATE KEY UPDATE setting_value = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$key, $value, $value]);
        }
        
        $message = '自動バックアップ設定を更新しました。';
    } catch (Exception $e) {
        $error = 'エラーが発生しました: ' . $e->getMessage();
    }
}

// バックアップファイル削除
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_backup') {
    $filename = $_POST['filename'] ?? '';
    $filepath = '../../backups/' . $filename;
    
    if (file_exists($filepath) && strpos($filename, 'backup_') === 0) {
        if (unlink($filepath)) {
            $message = 'バックアップファイルを削除しました。';
        } else {
            $error = 'ファイルの削除に失敗しました。';
        }
    } else {
        $error = '指定されたファイルが見つかりません。';
    }
}

// 現在の設定取得
$database = new Database();
$db = $database->getConnection();

$query = "SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'backup_%'";
$stmt = $db->prepare($query);
$stmt->execute();
$backup_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$auto_backup_enabled = $backup_settings['auto_backup_enabled'] ?? 0;
$backup_frequency = $backup_settings['backup_frequency'] ?? 'daily';
$backup_retention_days = $backup_settings['backup_retention_days'] ?? 30;

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
                'date' => filemtime($filepath),
                'path' => $filepath
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
    <title>バックアップ管理 - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/style.css">
    <style>
        .backup-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .file-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .backup-file {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
            background: white;
        }
        .backup-actions {
            display: flex;
            gap: 10px;
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-active { background-color: #28a745; }
        .status-inactive { background-color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>💾 バックアップ管理</h1>
                <div class="user-info">
                    <a href="../index.php" style="color: white;">← 管理画面に戻る</a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="settings-grid">
                <!-- 自動バックアップ設定 -->
                <div class="backup-section">
                    <h2>⚙️ 自動バックアップ設定</h2>
                    <div style="margin-bottom: 20px;">
                        <span class="status-indicator <?php echo $auto_backup_enabled ? 'status-active' : 'status-inactive'; ?>"></span>
                        <strong>現在の状態: <?php echo $auto_backup_enabled ? '有効' : '無効'; ?></strong>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="set_auto_backup">
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="auto_backup_enabled" 
                                       <?php echo $auto_backup_enabled ? 'checked' : ''; ?>>
                                自動バックアップを有効にする
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>バックアップ頻度</label>
                            <select name="backup_frequency" class="form-control">
                                <option value="daily" <?php echo $backup_frequency === 'daily' ? 'selected' : ''; ?>>毎日</option>
                                <option value="weekly" <?php echo $backup_frequency === 'weekly' ? 'selected' : ''; ?>>毎週</option>
                                <option value="monthly" <?php echo $backup_frequency === 'monthly' ? 'selected' : ''; ?>>毎月</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>保存期間（日）</label>
                            <input type="number" name="backup_retention_days" 
                                   value="<?php echo $backup_retention_days; ?>" 
                                   class="form-control" min="1" max="365">
                            <small>指定した日数を過ぎた古いバックアップは自動削除されます</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">設定保存</button>
                    </form>
                </div>
                
                <!-- 手動バックアップ -->
                <div class="backup-section">
                    <h2>🔧 手動バックアップ</h2>
                    <p>今すぐバックアップを作成します。</p>
                    
                    <form method="POST" action="../master/database.php" 
                          onsubmit="return confirm('バックアップを作成しますか？');">
                        <input type="hidden" name="action" value="backup">
                        <button type="submit" class="btn btn-success">今すぐバックアップ</button>
                    </form>
                    
                    <div style="margin-top: 20px; padding: 15px; background: #e8f4f8; border-radius: 5px;">
                        <h3>📊 バックアップ統計</h3>
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            <li>総バックアップ数: <strong><?php echo count($backup_files); ?>個</strong></li>
                            <li>最新バックアップ: 
                                <strong>
                                <?php if (!empty($backup_files)): ?>
                                    <?php echo date('Y/m/d H:i', $backup_files[0]['date']); ?>
                                <?php else: ?>
                                    なし
                                <?php endif; ?>
                                </strong>
                            </li>
                            <li>総サイズ: 
                                <strong>
                                <?php 
                                $total_size = array_sum(array_column($backup_files, 'size'));
                                echo number_format($total_size / 1024 / 1024, 2) . ' MB';
                                ?>
                                </strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- バックアップファイル一覧 -->
            <div class="backup-section">
                <h2>📁 バックアップファイル一覧</h2>
                <div class="file-list">
                    <?php if (empty($backup_files)): ?>
                        <p style="text-align: center; color: #6c757d; padding: 50px;">
                            バックアップファイルがありません
                        </p>
                    <?php else: ?>
                        <?php foreach ($backup_files as $file): ?>
                            <div class="backup-file">
                                <div>
                                    <strong><?php echo htmlspecialchars($file['name']); ?></strong>
                                    <div style="font-size: 14px; color: #6c757d;">
                                        📅 <?php echo date('Y/m/d H:i:s', $file['date']); ?> - 
                                        📦 <?php echo number_format($file['size'] / 1024, 1); ?> KB
                                    </div>
                                </div>
                                <div class="backup-actions">
                                    <a href="../../backups/<?php echo urlencode($file['name']); ?>" 
                                       class="btn btn-info" style="padding: 5px 10px; font-size: 12px;" download>
                                        ダウンロード
                                    </a>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('本当に削除しますか？');">
                                        <input type="hidden" name="action" value="delete_backup">
                                        <input type="hidden" name="filename" value="<?php echo htmlspecialchars($file['name']); ?>">
                                        <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">
                                            削除
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 注意事項 -->
            <div style="background: #fff3cd; padding: 20px; border-radius: 10px; border-left: 4px solid #ffc107;">
                <h3>⚠️ 重要な注意事項</h3>
                <ul>
                    <li>バックアップファイルは定期的に外部ストレージにも保存することをお勧めします</li>
                    <li>復元作業は必ず技術者が行ってください</li>
                    <li>バックアップファイルには機密情報が含まれているため、適切に管理してください</li>
                    <li>自動バックアップはサーバーの負荷を考慮して実行時間を調整してください</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
