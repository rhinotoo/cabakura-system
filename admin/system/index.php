<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

checkLogin();
if (!isset($_SESSION['admin_authenticated']) && $_SESSION['role'] !== 'admin') {
    header('Location: ../auth.php');
    exit();
}

// システム情報取得
$system_info = [
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'timezone' => date_default_timezone_get(),
    'current_time' => date('Y-m-d H:i:s')
];

// データベース情報
$database = new Database();
$db = $database->getConnection();

try {
    $query = "SELECT VERSION() as version";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $db_version = $stmt->fetchColumn();
} catch (Exception $e) {
    $db_version = 'Unknown';
}

// ディスク使用量
$disk_free = disk_free_space('.');
$disk_total = disk_total_space('.');
$disk_used = $disk_total - $disk_free;
$disk_usage_percent = ($disk_used / $disk_total) * 100;

// 拡張モジュール確認
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'];
$extension_status = [];
foreach ($required_extensions as $ext) {
    $extension_status[$ext] = extension_loaded($ext);
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>システム情報 - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/style.css">
    <style>
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .info-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .info-table td:first-child {
            font-weight: bold;
            width: 40%;
        }
        .status-ok { color: #28a745; }
        .status-error { color: #dc3545; }
        .status-warning { color: #ffc107; }
        .progress-bar {
            width: 100%;
            height: 20px;
            background-color: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background-color: #007bff;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>🖥️ システム情報</h1>
                <div class="user-info">
                    <a href="../index.php" style="color: white;">← 管理画面に戻る</a>
                </div>
            </div>
            
            <div class="info-grid">
                <!-- サーバー情報 -->
                <div class="info-section">
                    <h2>🖥️ サーバー情報</h2>
                    <table class="info-table">
                        <tr>
                            <td>PHP バージョン</td>
                            <td><?php echo $system_info['php_version']; ?></td>
                        </tr>
                        <tr>
                            <td>サーバーソフトウェア</td>
                            <td><?php echo $system_info['server_software']; ?></td>
                        </tr>
                        <tr>
                            <td>サーバー名</td>
                            <td><?php echo $system_info['server_name']; ?></td>
                        </tr>
                        <tr>
                            <td>ドキュメントルート</td>
                            <td><?php echo $system_info['document_root']; ?></td>
                        </tr>
                        <tr>
                            <td>タイムゾーン</td>
                            <td><?php echo $system_info['timezone']; ?></td>
                        </tr>
                        <tr>
                            <td>現在時刻</td>
                            <td><?php echo $system_info['current_time']; ?></td>
                        </tr>
                    </table>
                </div>
                
                <!-- PHP設定 -->
                <div class="info-section">
                    <h2>🐘 PHP設定</h2>
                    <table class="info-table">
                        <tr>
                            <td>メモリ制限</td>
                            <td><?php echo $system_info['memory_limit']; ?></td>
                        </tr>
                        <tr>
                            <td>最大実行時間</td>
                            <td><?php echo $system_info['max_execution_time']; ?>秒</td>
                        </tr>
                        <tr>
                            <td>アップロード最大サイズ</td>
                            <td><?php echo $system_info['upload_max_filesize']; ?></td>
                        </tr>
                        <tr>
                            <td>POST最大サイズ</td>
                            <td><?php echo $system_info['post_max_size']; ?></td>
                        </tr>
                    </table>
                </div>
                
                <!-- データベース情報 -->
                <div class="info-section">
                    <h2>🗄️ データベース情報</h2>
                    <table class="info-table">
                        <tr>
                            <td>MySQL バージョン</td>
                            <td><?php echo $db_version; ?></td>
                        </tr>
                        <tr>
                            <td>接続状態</td>
                            <td><span class="status-ok">✓ 接続中</span></td>
                        </tr>
                    </table>
                </div>
                
                <!-- ディスク使用量 -->
                <div class="info-section">
                    <h2>💾 ディスク使用量</h2>
                    <table class="info-table">
                        <tr>
                            <td>使用量</td>
                            <td><?php echo number_format($disk_used / 1024 / 1024 / 1024, 2); ?> GB</td>
                        </tr>
                        <tr>
                            <td>空き容量</td>
                            <td><?php echo number_format($disk_free / 1024 / 1024 / 1024, 2); ?> GB</td>
                        </tr>
                        <tr>
                            <td>総容量</td>
                            <td><?php echo number_format($disk_total / 1024 / 1024 / 1024, 2); ?> GB</td>
                        </tr>
                        <tr>
                            <td>使用率</td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $disk_usage_percent; ?>%"></div>
                                </div>
                                <?php echo number_format($disk_usage_percent, 1); ?>%
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- PHP拡張モジュール -->
            <div class="info-section" style="margin-top: 30px;">
                <h2>🔧 PHP拡張モジュール</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <?php foreach ($extension_status as $ext => $loaded): ?>
                        <div style="padding: 10px; background: white; border-radius: 5px; text-align: center;">
                            <strong><?php echo $ext; ?></strong><br>
                            <span class="<?php echo $loaded ? 'status-ok' : 'status-error'; ?>">
                                <?php echo $loaded ? '✓ 有効' : '✗ 無効'; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- システム診断 -->
            <div class="info-section" style="margin-top: 30px;">
                <h2>🔍 システム診断</h2>
                <div style="background: white; padding: 20px; border-radius: 5px;">
                    <?php
                    $issues = [];
                    
                    // PHP バージョンチェック
                    if (version_compare(PHP_VERSION, '7.4.0', '<')) {
                        $issues[] = 'PHP バージョンが古い可能性があります (推奨: 7.4以上)';
                    }
                    
                    // メモリ制限チェック
                    $memory_limit = ini_get('memory_limit');
                    if ($memory_limit !== '-1' && (int)$memory_limit < 128) {
                        $issues[] = 'メモリ制限が低い可能性があります (推奨: 128M以上)';
                    }
                    
                    // ディスク使用量チェック
                    if ($disk_usage_percent > 90) {
                        $issues[] = 'ディスク使用量が90%を超えています';
                    } elseif ($disk_usage_percent > 80) {
                        $issues[] = 'ディスク使用量が80%を超えています';
                    }
                    
                    // 拡張モジュールチェック
                    foreach ($extension_status as $ext => $loaded) {
                        if (!$loaded) {
                            $issues[] = "必要な拡張モジュール '{$ext}' が無効です";
                        }
                    }
                    
                    if (empty($issues)) {
                        echo '<p class="status-ok">✓ システムは正常に動作しています</p>';
                    } else {
                        echo '<p class="status-warning">⚠️ 以下の問題が検出されました:</p>';
                        echo '<ul>';
                        foreach ($issues as $issue) {
                            echo '<li class="status-warning">' . htmlspecialchars($issue) . '</li>';
                        }
                        echo '</ul>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
