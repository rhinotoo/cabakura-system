<?php
/**
 * ダッシュボード画面
 * パス: /public/dashboard.php
 */

// アプリケーション初期化
require_once __DIR__ . '/../bootstrap.php';

// ログイン確認
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ユーザー情報取得
$user_info = null;
$stats = [];

try {
    $pdo = getDatabaseConnection();
    
    // ユーザー情報取得
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_info = $stmt->fetch();
    
    if (!$user_info) {
        // ユーザーが見つからない場合はログアウト
        session_destroy();
        header('Location: login.php');
        exit;
    }
    
    // 統計情報取得（テーブルが存在する場合）
    try {
        // ユーザー数
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE active = 1");
        $stats['users'] = $stmt->fetch()['count'];
        
        // キャスト数（テーブルが存在する場合）
        $stmt = $pdo->query("SHOW TABLES LIKE 'casts'");
        if ($stmt->fetch()) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM casts WHERE active = 1");
            $stats['casts'] = $stmt->fetch()['count'];
        } else {
            $stats['casts'] = 0;
        }
        
        // 今日のログイン数
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE DATE(last_login) = CURDATE()");
        $stats['today_logins'] = $stmt->fetch()['count'];
        
    } catch (Exception $e) {
        // テーブルが存在しない場合のデフォルト値
        $stats = [
            'users' => 1,
            'casts' => 0,
            'today_logins' => 1
        ];
    }
    
} catch (Exception $e) {
    logDatabaseError($e, 'Dashboard');
    $error_message = 'データの取得中にエラーが発生しました。';
}

// ログアウト処理
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php?message=logout');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ダッシュボード - Cabakura Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info span {
            background: rgba(255,255,255,0.2);
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 14px;
        }
        
        .btn-logout {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .btn-logout:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .welcome-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .welcome-section h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .welcome-section p {
            color: #666;
            font-size: 16px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-card .label {
            color: #666;
            font-size: 14px;
        }
        
        .actions-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .actions-section h2 {
            color: #333;
            margin-bottom: 20px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .action-btn {
            display: block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            font-weight: 500;
            transition: transform 0.2s;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
        }
        
        .system-info {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .system-info h3 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #666;
            font-weight: 500;
        }
        
        .info-value {
            color: #333;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 10px;
            }
            
            .user-info {
                flex-direction: column;
                gap: 10px;
            }
            
            .container {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">🏪 Cabakura Management</div>
            <div class="user-info">
                <span>👤 <?= htmlspecialchars($user_info['username'] ?? 'Unknown') ?></span>
                <span>🏷️ <?= htmlspecialchars($user_info['role'] ?? 'user') ?></span>
                <a href="?logout=1" class="btn-logout" onclick="return confirm('ログアウトしますか？')">
                    🚪 ログアウト
                </a>
            </div>
        </div>
    </header>
    
    <div class="container">
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                ❌ <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <div class="welcome-section">
            <h1>おかえりなさい、<?= htmlspecialchars($user_info['username'] ?? 'ユーザー') ?>さん！</h1>
            <p>
                最終ログイン: <?= $user_info['last_login'] ? date('Y年m月d日 H:i', strtotime($user_info['last_login'])) : '初回ログイン' ?> |
                環境: <?= getEnvironmentName() ?> |
                データベース: <?= DB_NAME ?>
            </p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">👥</div>
                <div class="number"><?= number_format($stats['users']) ?></div>
                <div class="label">登録ユーザー数</div>
            </div>
            
            <div class="stat-card">
                <div class="icon">💃</div>
                <div class="number"><?= number_format($stats['casts']) ?></div>
                <div class="label">キャスト数</div>
            </div>
            
            <div class="stat-card">
                <div class="icon">📊</div>
                <div class="number"><?= number_format($stats['today_logins']) ?></div>
                <div class="label">今日のログイン</div>
            </div>
        </div>
        
        <div class="actions-section">
            <h2>📋 管理メニュー</h2>
            <div class="actions-grid">
                <a href="users.php" class="action-btn">👥 ユーザー管理</a>
                <a href="casts.php" class="action-btn">💃 キャスト管理</a>
                <a href="schedule.php" class="action-btn">📅 スケジュール管理</a>
                <a href="sales.php" class="action-btn">💰 売上管理</a>
                <a href="reports.php" class="action-btn">📊 レポート</a>
                <a href="settings.php" class="action-btn">⚙️ 設定</a>
            </div>
        </div>
        
        <div class="system-info">
            <h3>🔧 システム情報</h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">環境:</span>
                    <span class="info-value"><?= getEnvironmentName() ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">データベース:</span>
                    <span class="info-value"><?= DB_NAME ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">PHPバージョン:</span>
                    <span class="info-value"><?= PHP_VERSION ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">サーバー時刻:</span>
                    <span class="info-value"><?= date('Y-m-d H:i:s') ?></span>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px; padding: 20px;">
            <a href="../config/check_database.php" style="color: #667eea; text-decoration: none;">
                🔧 データベース設定確認
            </a>
        </div>
    </div>
</body>
</html>
