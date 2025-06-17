<?php
require_once '../config/database.php';
require_once '../config/session.php';

checkLogin();
checkRole(['cast', 'admin']);

$database = new Database();
$db = $database->getConnection();

$cast_id = $_SESSION['user_id'];

// 本日の売上
$query = "SELECT COALESCE(SUM(total_amount), 0) as today_sales, COUNT(*) as today_customers
          FROM sales 
          WHERE cast_id = ? AND sale_date = CURDATE()";
$stmt = $db->prepare($query);
$stmt->execute([$cast_id]);
$today_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// 今月の売上
$query = "SELECT COALESCE(SUM(total_amount), 0) as month_sales, COUNT(*) as month_customers
          FROM sales 
          WHERE cast_id = ? AND YEAR(sale_date) = YEAR(CURDATE()) AND MONTH(sale_date) = MONTH(CURDATE())";
$stmt = $db->prepare($query);
$stmt->execute([$cast_id]);
$month_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// 今月の出勤日数
$query = "SELECT COUNT(DISTINCT work_date) as work_days
          FROM attendance 
          WHERE user_id = ? AND YEAR(work_date) = YEAR(CURDATE()) AND MONTH(work_date) = MONTH(CURDATE())
          AND check_in IS NOT NULL";
$stmt = $db->prepare($query);
$stmt->execute([$cast_id]);
$attendance_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// 指名数（今月）
$query = "SELECT COUNT(*) as nominations
          FROM sales 
          WHERE cast_id = ? AND YEAR(sale_date) = YEAR(CURDATE()) AND MONTH(sale_date) = MONTH(CURDATE())";
$stmt = $db->prepare($query);
$stmt->execute([$cast_id]);
$nomination_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// 最近の売上履歴
$query = "SELECT s.*, c.name as customer_name, t.table_number
          FROM sales s
          JOIN sessions sess ON s.session_id = sess.id
          JOIN customers c ON sess.customer_id = c.id
          JOIN tables t ON sess.table_id = t.id
          WHERE s.cast_id = ?
          ORDER BY s.sale_date DESC, s.created_at DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$cast_id]);
$recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>キャスト売上確認 - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        .sales-history {
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>💎 キャスト売上確認</h1>
                <div class="user-info">
                    ログイン中: <?php echo htmlspecialchars($_SESSION['name']); ?>
                    <a href="../config/session.php?action=logout" style="color: white; margin-left: 20px;">ログアウト</a>
                </div>
            </div>
            
            <!-- 統計情報 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value">¥<?php echo number_format($today_stats['today_sales']); ?></div>
                    <div class="stat-label">本日の売上</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $today_stats['today_customers']; ?>組</div>
                    <div class="stat-label">本日の接客数</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $nomination_stats['nominations']; ?>回</div>
                    <div class="stat-label">今月の指名数</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value">¥<?php echo number_format($month_stats['month_sales']); ?></div>
                    <div class="stat-label">今月の累計売上</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $month_stats['month_customers']; ?>組</div>
                    <div class="stat-label">今月の接客数</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $attendance_stats['work_days']; ?>日</div>
                    <div class="stat-label">今月の出勤日数</div>
                </div>
            </div>
            
            <!-- 売上履歴 -->
            <h2>📊 最近の売上履歴</h2>
            <div class="sales-history">
                <table class="table">
                    <thead>
                        <tr>
                            <th>日付</th>
                            <th>お客様</th>
                            <th>テーブル</th>
                            <th>売上金額</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_sales)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #6c757d;">売上履歴がありません</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_sales as $sale): ?>
                                <tr>
                                    <td><?php echo date('Y/m/d', strtotime($sale['sale_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                                    <td>テーブル <?php echo htmlspecialchars($sale['table_number']); ?></td>
                                    <td>¥<?php echo number_format($sale['total_amount']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
