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

// 出勤・退勤処理
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_POST['user_id'] ?? '';
    
    if ($action === 'check_in' && $user_id) {
        try {
            // 既に出勤済みかチェック
            $query = "SELECT id FROM attendance WHERE user_id = ? AND work_date = CURDATE() AND check_in IS NOT NULL";
            $stmt = $db->prepare($query);
            $stmt->execute([$user_id]);
            
            if ($stmt->fetch()) {
                $error = '既に出勤済みです。';
            } else {
                $query = "INSERT INTO attendance (user_id, check_in, work_date) VALUES (?, NOW(), CURDATE())";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id]);
                $message = '出勤を記録しました。';
            }
        } catch (Exception $e) {
            $error = 'エラーが発生しました: ' . $e->getMessage();
        }
    } elseif ($action === 'check_out' && $user_id) {
        try {
            $query = "UPDATE attendance SET check_out = NOW() 
                      WHERE user_id = ? AND work_date = CURDATE() AND check_out IS NULL";
            $stmt = $db->prepare($query);
            $stmt->execute([$user_id]);
            
            if ($stmt->rowCount() > 0) {
                $message = '退勤を記録しました。';
            } else {
                $error = '出勤記録が見つかりません。';
            }
        } catch (Exception $e) {
            $error = 'エラーが発生しました: ' . $e->getMessage();
        }
    }
}

// 本日の出勤状況
$query = "SELECT u.id, u.name, u.role, a.check_in, a.check_out,
                 CASE 
                   WHEN a.check_in IS NOT NULL AND a.check_out IS NULL THEN '出勤中'
                   WHEN a.check_in IS NOT NULL AND a.check_out IS NOT NULL THEN '退勤済み'
                   ELSE '未出勤'
                 END as status
          FROM users u
          LEFT JOIN attendance a ON u.id = a.user_id AND a.work_date = CURDATE()
          WHERE u.role IN ('staff', 'cast', 'kitchen')
          ORDER BY u.role, u.name";
$stmt = $db->prepare($query);
$stmt->execute();
$today_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 今月の勤怠サマリー
$query = "SELECT u.name, u.role, COUNT(a.id) as work_days,
                 COALESCE(SUM(TIMESTAMPDIFF(HOUR, a.check_in, COALESCE(a.check_out, NOW()))), 0) as total_hours
          FROM users u
          LEFT JOIN attendance a ON u.id = a.user_id 
                                   AND YEAR(a.work_date) = YEAR(CURDATE()) 
                                   AND MONTH(a.work_date) = MONTH(CURDATE())
                                   AND a.check_in IS NOT NULL
          WHERE u.role IN ('staff', 'cast', 'kitchen')
          GROUP BY u.id, u.name, u.role
          ORDER BY u.role, work_days DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$month_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>勤怠管理 - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/style.css">
    <style>
        .status-working { color: #28a745; font-weight: bold; }
        .status-finished { color: #6c757d; }
        .status-absent { color: #dc3545; }
        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .role-section {
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>⏰ 勤怠管理</h1>
                <div class="user-info">
                    <a href="../index.php" style="color: white;">← 管理者メニューに戻る</a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- 本日の出勤状況 -->
            <h2>📅 本日の出勤状況 (<?php echo date('Y年m月d日'); ?>)</h2>
            <div class="table">
                <table>
                    <thead>
                        <tr>
                            <th>名前</th>
                            <th>役割</th>
                            <th>出勤時刻</th>
                            <th>退勤時刻</th>
                            <th>状態</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $role_names = [
                            'staff' => 'スタッフ',
                            'cast' => 'キャスト',
                            'kitchen' => 'キッチン'
                        ];
                        ?>
                        <?php foreach ($today_attendance as $attendance): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($attendance['name']); ?></td>
                                <td><?php echo $role_names[$attendance['role']]; ?></td>
                                <td><?php echo $attendance['check_in'] ? date('H:i', strtotime($attendance['check_in'])) : '-'; ?></td>
                                <td><?php echo $attendance['check_out'] ? date('H:i', strtotime($attendance['check_out'])) : '-'; ?></td>
                                <td>
                                    <span class="status-<?php echo $attendance['status'] === '出勤中' ? 'working' : ($attendance['status'] === '退勤済み' ? 'finished' : 'absent'); ?>">
                                        <?php echo $attendance['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="quick-actions">
                                        <?php if ($attendance['status'] === '未出勤'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="check_in">
                                                <input type="hidden" name="user_id" value="<?php echo $attendance['id']; ?>">
                                                <button type="submit" class="btn btn-success" style="padding: 5px 10px; font-size: 12px;">出勤</button>
                                            </form>
                                        <?php elseif ($attendance['status'] === '出勤中'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="check_out">
                                                <input type="hidden" name="user_id" value="<?php echo $attendance['id']; ?>">
                                                <button type="submit" class="btn btn-warning" style="padding: 5px 10px; font-size: 12px;">退勤</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- 今月の勤怠サマリー -->
            <h2>📊 今月の勤怠サマリー (<?php echo date('Y年m月'); ?>)</h2>
            <div class="table">
                <table>
                    <thead>
                        <tr>
                            <th>名前</th>
                            <th>役割</th>
                            <th>出勤日数</th>
                            <th>総労働時間</th>
                            <th>平均労働時間</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($month_summary as $summary): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($summary['name']); ?></td>
                                <td><?php echo $role_names[$summary['role']]; ?></td>
                                <td><?php echo $summary['work_days']; ?>日</td>
                                <td><?php echo $summary['total_hours']; ?>時間</td>
                                <td>
                                    <?php 
                                    $avg_hours = $summary['work_days'] > 0 ? round($summary['total_hours'] / $summary['work_days'], 1) : 0;
                                    echo $avg_hours . '時間';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- 機能メニュー -->
            <div style="margin-top: 30px;">
                <h2>🔧 機能メニュー</h2>
                <div class="grid">
                    <div class="grid-item">
                        <h3>📋 勤怠履歴</h3>
                        <a href="history.php" class="btn btn-primary">履歴確認</a>
                    </div>
                    <div class="grid-item">
                        <h3>📝 勤怠修正</h3>
                        <a href="edit.php" class="btn btn-warning">修正入力</a>
                    </div>
                    <div class="grid-item">
                        <h3>📊 レポート</h3>
                        <a href="report.php" class="btn btn-success">レポート出力</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
