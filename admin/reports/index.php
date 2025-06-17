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

// 日付範囲設定
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // 月初
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // 今日

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

// 人気メニュー
$query = "SELECT 
            mi.name,
            mi.category,
            SUM(o.quantity) as total_quantity,
            SUM(o.quantity * o.price) as total_amount
          FROM orders o
          JOIN menu_items mi ON o.menu_item_id = mi.id
          JOIN sessions s ON o.session_id = s.id
          WHERE DATE(s.created_at) BETWEEN ? AND ?
          GROUP BY mi.id
          ORDER BY total_amount DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$date_from, $date_to]);
$popular_menu = $stmt->fetchAll(PDO::FETCH_ASSOC);

// キャスト別売上
$query = "SELECT 
            u.name as cast_name,
            COUNT(DISTINCT s.id) as session_count,
            COALESCE(SUM(s.total_amount), 0) as total_revenue,
            COALESCE(AVG(s.total_amount), 0) as avg_revenue
          FROM sessions s
          JOIN users u ON s.cast_id = u.id
          WHERE DATE(s.created_at) BETWEEN ? AND ?
          AND s.status = 'completed'
          AND u.role = 'cast'
          GROUP BY u.id
          ORDER BY total_revenue DESC";
$stmt = $db->prepare($query);
$stmt->execute([$date_from, $date_to]);
$cast_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 日別売上推移
$query = "SELECT 
            DATE(s.created_at) as date,
            COUNT(DISTINCT s.id) as session_count,
            COALESCE(SUM(s.total_amount), 0) as daily_revenue
          FROM sessions s
          WHERE DATE(s.created_at) BETWEEN ? AND ?
          AND s.status = 'completed'
          GROUP BY DATE(s.created_at)
          ORDER BY date";
$stmt = $db->prepare($query);
$stmt->execute([$date_from, $date_to]);
$daily_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

// テーブル利用率
$query = "SELECT 
            t.table_number,
            COUNT(s.id) as usage_count,
            COALESCE(SUM(s.total_amount), 0) as table_revenue
          FROM tables t
          LEFT JOIN sessions s ON t.id = s.table_id 
          AND DATE(s.created_at) BETWEEN ? AND ?
          AND s.status = 'completed'
          GROUP BY t.id
          ORDER BY usage_count DESC";
$stmt = $db->prepare($query);
$stmt->execute([$date_from, $date_to]);
$table_usage = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 勤怠統計
$query = "SELECT 
            u.name,
            u.role,
            COUNT(a.id) as attendance_days,
            COALESCE(SUM(TIMESTAMPDIFF(MINUTE, a.clock_in, a.clock_out)), 0) as total_minutes
          FROM users u
          LEFT JOIN attendance a ON u.id = a.user_id 
          AND DATE(a.clock_in) BETWEEN ? AND ?
          WHERE u.role IN ('cast', 'staff')
          GROUP BY u.id
          ORDER BY total_minutes DESC";
$stmt = $db->prepare($query);
$stmt->execute([$date_from, $date_to]);
$attendance_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>レポート・分析 - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
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
            border-left: 4px solid #3498db;
        }
        .stat-card h3 {
            font-size: 2em;
            margin: 0;
            color: #2c3e50;
        }
        .report-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .chart-container {
            position: relative;
            height: 400px;
            margin: 20px 0;
        }
        .filter-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="report-header">
            <h1>📊 レポート・分析</h1>
            <p>期間: <?php echo $date_from; ?> ～ <?php echo $date_to; ?></p>
            <a href="../index.php" style="color: white; text-decoration: underline;">← 管理画面に戻る</a>
        </div>
        
        <!-- 期間選択フォーム -->
        <div class="filter-form">
            <form method="GET" style="display: flex; gap: 15px; align-items: end;">
                <div>
                    <label>開始日</label>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>" class="form-control">
                </div>
                <div>
                    <label>終了日</label>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>" class="form-control">
                </div>
                <button type="submit" class="btn btn-primary">更新</button>
                <a href="export_report.php?date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
                   class="btn btn-success">レポートエクスポート</a>
            </form>
        </div>
        
        <!-- 売上統計 -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>¥<?php echo number_format($revenue_stats['total_revenue']); ?></h3>
                <p>総売上</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($revenue_stats['total_sessions']); ?></h3>
                <p>総セッション数</p>
            </div>
            <div class="stat-card">
                <h3>¥<?php echo number_format($revenue_stats['avg_revenue_per_session']); ?></h3>
                <p>平均客単価</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($revenue_stats['unique_customers']); ?></h3>
                <p>ユニーク顧客数</p>
            </div>
        </div>
        
        <!-- 日別売上推移グラフ -->
        <div class="report-section">
            <h2>📈 日別売上推移</h2>
            <div class="chart-container">
                <canvas id="dailyRevenueChart"></canvas>
            </div>
        </div>
        
        <!-- 人気メニューとキャスト別売上 -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <!-- 人気メニュー -->
            <div class="report-section">
                <h2>🍽️ 人気メニュー TOP10</h2>
                <div class="table">
                    <table>
                        <thead>
                            <tr>
                                <th>順位</th>
                                <th>メニュー名</th>
                                <th>数量</th>
                                <th>売上</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($popular_menu as $index => $menu): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($menu['name']); ?></td>
                                    <td><?php echo number_format($menu['total_quantity']); ?></td>
                                    <td>¥<?php echo number_format($menu['total_amount']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- キャスト別売上 -->
            <div class="report-section">
                <h2>👩 キャスト別売上</h2>
                <div class="table">
                    <table>
                        <thead>
                            <tr>
                                <th>キャスト名</th>
                                <th>セッション数</th>
                                <th>売上</th>
                                <th>平均</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cast_revenue as $cast): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cast['cast_name']); ?></td>
                                    <td><?php echo number_format($cast['session_count']); ?></td>
                                    <td>¥<?php echo number_format($cast['total_revenue']); ?></td>
                                    <td>¥<?php echo number_format($cast['avg_revenue']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- テーブル利用率と勤怠統計 -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <!-- テーブル利用率 -->
            <div class="report-section">
                <h2>🪑 テーブル利用率</h2>
                <div class="chart-container">
                    <canvas id="tableUsageChart"></canvas>
                </div>
            </div>
            
            <!-- 勤怠統計 -->
            <div class="report-section">
                <h2>⏰ 勤怠統計</h2>
                <div class="table">
                    <table>
                        <thead>
                            <tr>
                                <th>名前</th>
                                <th>役割</th>
                                <th>出勤日数</th>
                                <th>総労働時間</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_stats as $attendance): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($attendance['name']); ?></td>
                                    <td><?php echo htmlspecialchars($attendance['role']); ?></td>
                                    <td><?php echo number_format($attendance['attendance_days']); ?>日</td>
                                    <td><?php echo number_format($attendance['total_minutes'] / 60, 1); ?>時間</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // 日別売上推移グラフ
        const dailyCtx = document.getElementById('dailyRevenueChart').getContext('2d');
        const dailyData = <?php echo json_encode($daily_revenue); ?>;
        
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyData.map(d => d.date),
                datasets: [{
                    label: '売上 (¥)',
                    data: dailyData.map(d => d.daily_revenue),
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4
                }, {
                    label: 'セッション数',
                    data: dailyData.map(d => d.session_count),
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    yAxisID: 'y1',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
        
        // テーブル利用率グラフ
        const tableCtx = document.getElementById('tableUsageChart').getContext('2d');
        const tableData = <?php echo json_encode($table_usage); ?>;
        
        new Chart(tableCtx, {
            type: 'bar',
            data: {
                labels: tableData.map(t => 'テーブル ' + t.table_number),
                datasets: [{
                    label: '利用回数',
                    data: tableData.map(t => t.usage_count),
                    backgroundColor: '#3498db',
                }, {
                    label: '売上 (¥)',
                    data: tableData.map(t => t.table_revenue),
                    backgroundColor: '#2ecc71',
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    </script>
</body>
</html>
