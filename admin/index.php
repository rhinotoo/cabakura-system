<?php
require_once '../config/database.php';
require_once '../config/session.php';

checkLogin();

// 管理者認証チェック
if (!isset($_SESSION['admin_authenticated']) && $_SESSION['role'] !== 'admin') {
    header('Location: auth.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// 本日の売上サマリー
$query = "SELECT COALESCE(SUM(total_amount), 0) as today_total FROM sales WHERE sale_date = CURDATE()";
$stmt = $db->prepare($query);
$stmt->execute();
$today_sales = $stmt->fetch(PDO::FETCH_ASSOC);

// 今月の売上サマリー
$query = "SELECT COALESCE(SUM(total_amount), 0) as month_total FROM sales 
          WHERE YEAR(sale_date) = YEAR(CURDATE()) AND MONTH(sale_date) = MONTH(CURDATE())";
$stmt = $db->prepare($query);
$stmt->execute();
$month_sales = $stmt->fetch(PDO::FETCH_ASSOC);

// 現在の接客状況
$query = "SELECT COUNT(*) as active_sessions FROM sessions WHERE status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute();
$active_sessions = $stmt->fetch(PDO::FETCH_ASSOC);

// 出勤中のスタッフ数
$query = "SELECT COUNT(*) as working_staff FROM attendance 
          WHERE work_date = CURDATE() AND check_in IS NOT NULL AND check_out IS NULL";
$stmt = $db->prepare($query);
$stmt->execute();
$working_staff = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者メニュー - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
        }
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .menu-item {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .menu-item:hover {
            transform: translateY(-5px);
        }
        .menu-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>⚙️ 管理者メニュー</h1>
                <div class="user-info">
                    ログイン中: <?php echo htmlspecialchars($_SESSION['name']); ?>
                    <a href="../config/session.php?action=logout" style="color: white; margin-left: 20px;">ログアウト</a>
                </div>
            </div>
            
            <!-- ダッシュボード統計 -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div style="font-size: 28px; font-weight: bold;">¥<?php echo number_format($today_sales['today_total']); ?></div>
                    <div>本日の売上</div>
                </div>
                <div class="stat-card">
                    <div style="font-size: 28px; font-weight: bold;">¥<?php echo number_format($month_sales['month_total']); ?></div>
                    <div>今月の売上</div>
                </div>
                <div class="stat-card">
                    <div style="font-size: 28px; font-weight: bold;"><?php echo $active_sessions['active_sessions']; ?>組</div>
                    <div>現在の接客数</div>
                </div>
                <div class="stat-card">
                    <div style="font-size: 28px; font-weight: bold;"><?php echo $working_staff['working_staff']; ?>名</div>
                    <div>出勤中スタッフ</div>
                </div>
            </div>
            
            <!-- 管理メニュー -->
            <div class="menu-grid">
                <div class="menu-item">
                    <div class="menu-icon">📊</div>
                    <h3>売上管理</h3>
                    <p>売上分析・キャスト別売上・分配計算</p>
                    <a href="sales/index.php" class="btn btn-primary">売上管理へ</a>
                </div>
                
                <div class="menu-item">
                    <div class="menu-icon">⏰</div>
                    <h3>勤怠管理</h3>
                    <p>出勤管理・勤怠履歴・シフト管理</p>
                    <a href="attendance/index.php" class="btn btn-primary">勤怠管理へ</a>
                </div>
                
                <div class="menu-item">
                    <div class="menu-icon">💳</div>
                    <h3>借金管理</h3>
                    <p>借金登録・返済記録・残高管理</p>
                    <a href="debt/index.php" class="btn btn-primary">借金管理へ</a>
                </div>
                
                <div class="menu-item">
                    <div class="menu-icon">🔧</div>
                    <h3>マスタ管理</h3>
                    <p>ユーザー・メニュー・席管理</p>
                    <a href="master/index.php" class="btn btn-primary">マスタ管理へ</a>
                </div>
                
                <div class="menu-item">
                    <div class="menu-icon">👥</div>
                    <h3>スタッフ画面</h3>
                    <p>フロア管理・注文・会計</p>
                    <a href="../staff/index.php" class="btn btn-success">スタッフ画面へ</a>
                </div>
                
                <div class="menu-item">
                    <div class="menu-icon">🍳</div>
                    <h3>キッチン画面</h3>
                    <p>注文確認・調理管理</p>
                    <a href="../kitchen/index.php" class="btn btn-warning">キッチン画面へ</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
