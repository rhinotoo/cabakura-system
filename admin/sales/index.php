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

// 売上サマリー
$query = "SELECT 
            COALESCE(SUM(total_amount), 0) as total_sales,
            COALESCE(SUM(cast_commission), 0) as total_cast_commission,
            COALESCE(SUM(staff_commission), 0) as total_staff_commission,
            (COALESCE(SUM(total_amount), 0) - COALESCE(SUM(cast_commission), 0) - COALESCE(SUM(staff_commission), 0)) as store_profit
          FROM sales 
          WHERE sale_date = CURDATE()";
$stmt = $db->prepare($query);
$stmt->execute();
$today_summary = $stmt->fetch(PDO::FETCH_ASSOC);

$query = "SELECT 
            COALESCE(SUM(total_amount), 0) as total_sales,
            COALESCE(SUM(cast_commission), 0) as total_cast_commission,
            COALESCE(SUM(staff_commission), 0) as total_staff_commission,
            (COALESCE(SUM(total_amount), 0) - COALESCE(SUM(cast_commission), 0) - COALESCE(SUM(staff_commission), 0)) as store_profit
          FROM sales 
          WHERE YEAR(sale_date) = YEAR(CURDATE()) AND MONTH(sale_date) = MONTH(CURDATE())";
$stmt = $db->prepare($query);
$stmt->execute();
$month_summary = $stmt->fetch(PDO::FETCH_ASSOC);

// キャスト別売上（今月）
$query = "SELECT u.name, COALESCE(SUM(s.total_amount), 0) as total_sales, COUNT(s.id) as sales_count
          FROM users u
          LEFT JOIN sales s ON u.id = s.cast_id AND YEAR(s.sale_date) = YEAR(CURDATE()) AND MONTH(s.sale_date) = MONTH(CURDATE())
          WHERE u.role = 'cast'
          GROUP BY u.id, u.name
          ORDER BY total_sales DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$cast_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// スタッフ別売上（今月）
$query = "SELECT u.name, COALESCE(SUM(s.total_amount), 0) as total_sales, COUNT(s.id) as sales_count
          FROM users u
          LEFT JOIN sales s ON u.id = s.staff_id AND YEAR(s.sale_date) = YEAR(CURDATE()) AND MONTH(s.sale_date) = MONTH(CURDATE())
          WHERE u.role = 'staff'
          GROUP BY u.id, u.name
          ORDER BY total_sales DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$staff_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>売上管理 - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/style.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>📊 売上管理</h1>
                <div class="user-info">
                    <a href="../index.php" style="color: white;">← 管理者メニューに戻る</a>
                </div>
            </div>
            
            <!-- 売上サマリー -->
            <h2>💰 売上サマリー</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                <div>
                    <h3>本日の売上</h3>
                    <table class="table">
                        <tr>
                            <td>総売上</td>
                            <td>¥<?php echo number_format($today_summary['total_sales']); ?></td>
                        </tr>
                        <tr>
                            <td>キャスト分配</td>
                            <td>¥<?php echo number_format($today_summary['total_cast_commission']); ?></td>
                        </tr>
                        <tr>
                            <td>スタッフ分配</td>
                            <td>¥<?php echo number_format($today_summary['total_staff_commission']); ?></td>
                        </tr>
                        <tr style="font-weight: bold; background: #f8f9fa;">
                            <td>店舗利益</td>
                            <td>¥<?php echo number_format($today_summary['store_profit']); ?></td>
                        </tr>
                    </table>
                </div>
                
                <div>
                    <h3>今月の売上</h3>
                    <table class="table">
                        <tr>
                            <td>総売上</td>
                            <td>¥<?php echo number_format($month_summary['total_sales']); ?></td>
                        </tr>
                        <tr>
                            <td>キャスト分配</td>
                            <td>¥<?php echo number_format($month_summary['total_cast_commission']); ?></td>
                        </tr>
                        <tr>
                            <td>スタッフ分配</td>
                            <td>¥<?php echo number_format($month_summary['total_staff_commission']); ?></td>
                        </tr>
                        <tr style="font-weight: bold; background: #f8f9fa;">
                            <td>店舗利益</td>
                            <td>¥<?php echo number_format($month_summary['store_profit']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <!-- キャスト別売上 -->
                <div>
                    <h2>👩‍💼 キャスト別売上（今月）</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>順位</th>
                                <th>キャスト名</th>
                                <th>売上金額</th>
                                <th>接客数</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cast_sales as $index => $cast): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?>位</td>
                                    <td><?php echo htmlspecialchars($cast['name']); ?></td>
                                    <td>¥<?php echo number_format($cast['total_sales']); ?></td>
                                    <td><?php echo $cast['sales_count']; ?>組</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- スタッフ別売上 -->
                <div>
                    <h2>👨‍💼 スタッフ別売上（今月）</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>順位</th>
                                <th>スタッフ名</th>
                                <th>売上金額</th>
                                <th>接客数</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staff_sales as $index => $staff): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?>位</td>
                                    <td><?php echo htmlspecialchars($staff['name']); ?></td>
                                    <td>¥<?php echo number_format($staff['total_sales']); ?></td>
                                    <td><?php echo $staff['sales_count']; ?>組</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
