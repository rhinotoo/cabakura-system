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

// 検索条件
$search_user = $_GET['user_id'] ?? '';
$search_date_from = $_GET['date_from'] ?? date('Y-m-01');
$search_date_to = $_GET['date_to'] ?? date('Y-m-t');

// ユーザー一覧取得
$query = "SELECT id, name, role FROM users WHERE role IN ('staff', 'cast', 'kitchen') ORDER BY role, name";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 勤怠履歴取得
$where_conditions = [];
$params = [];

if ($search_user) {
    $where_conditions[] = "u.id = ?";
    $params[] = $search_user;
}

if ($search_date_from) {
    $where_conditions[] = "a.work_date >= ?";
    $params[] = $search_date_from;
}

if ($search_date_to) {
    $where_conditions[] = "a.work_date <= ?";
    $params[] = $search_date_to;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$query = "SELECT u.name, u.role, a.work_date, a.check_in, a.check_out,
                 CASE 
                   WHEN a.check_in IS NOT NULL AND a.check_out IS NOT NULL 
                   THEN TIMESTAMPDIFF(HOUR, a.check_in, a.check_out)
                   ELSE NULL
                 END as work_hours
          FROM users u
          LEFT JOIN attendance a ON u.id = a.user_id
          $where_clause
          ORDER BY a.work_date DESC, u.name";

$stmt = $db->prepare($query);
$stmt->execute($params);
$attendance_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>勤怠履歴 - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/style.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>📋 勤怠履歴</h1>
                <div class="user-info">
                    <a href="index.php" style="color: white;">← 勤怠管理に戻る</a>
                </div>
            </div>
            
            <!-- 検索フォーム -->
            <form method="GET" style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr auto; gap: 15px; align-items: end;">
                    <div>
                        <label>スタッフ</label>
                        <select name="user_id" class="form-control">
                            <option value="">全員</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo $search_user == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['name']); ?> (<?php echo $user['role']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>開始日</label>
                        <input type="date" name="date_from" value="<?php echo $search_date_from; ?>" class="form-control">
                    </div>
                    <div>
                        <label>終了日</label>
                        <input type="date" name="date_to" value="<?php echo $search_date_to; ?>" class="form-control">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">検索</button>
                    </div>
                </div>
            </form>
            
            <!-- 履歴一覧 -->
            <div class="table">
                <table>
                    <thead>
                        <tr>
                            <th>日付</th>
                            <th>名前</th>
                            <th>役割</th>
                            <th>出勤時刻</th>
                            <th>退勤時刻</th>
                            <th>労働時間</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attendance_history)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #6c757d;">該当する履歴がありません</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($attendance_history as $record): ?>
                                <tr>
                                    <td><?php echo $record['work_date'] ? date('Y/m/d', strtotime($record['work_date'])) : '-'; ?></td>
                                    <td><?php echo htmlspecialchars($record['name']); ?></td>
                                    <td><?php echo $record['role']; ?></td>
                                    <td><?php echo $record['check_in'] ? date('H:i', strtotime($record['check_in'])) : '-'; ?></td>
                                    <td><?php echo $record['check_out'] ? date('H:i', strtotime($record['check_out'])) : '-'; ?></td>
                                    <td><?php echo $record['work_hours'] ? $record['work_hours'] . '時間' : '-'; ?></td>
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
