<?php
require_once '../config/database.php';
require_once '../config/session.php';

checkLogin();
checkRole(['staff', 'admin']);

$database = new Database();
$db = $database->getConnection();

// フロアマップ用のテーブル情報取得
$query = "SELECT t.*, s.id as session_id, c.name as customer_name, u.name as cast_name 
          FROM tables t 
          LEFT JOIN sessions s ON t.id = s.table_id AND s.status = 'active'
          LEFT JOIN customers c ON s.customer_id = c.id
          LEFT JOIN users u ON s.cast_id = u.id
          ORDER BY t.table_number";
$stmt = $db->prepare($query);
$stmt->execute();
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 接客中キャスト一覧
$query = "SELECT u.name, COUNT(s.id) as customer_count 
          FROM users u 
          LEFT JOIN sessions s ON u.id = s.cast_id AND s.status = 'active'
          WHERE u.role = 'cast' 
          GROUP BY u.id, u.name";
$stmt = $db->prepare($query);
$stmt->execute();
$busy_casts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// フリーキャスト一覧
$query = "SELECT u.name FROM users u 
          WHERE u.role = 'cast' 
          AND u.id NOT IN (SELECT DISTINCT cast_id FROM sessions WHERE status = 'active' AND cast_id IS NOT NULL)";
$stmt = $db->prepare($query);
$stmt->execute();
$free_casts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>スタッフメイン - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>🍾 スタッフメイン画面</h1>
                <div class="user-info">
                    ログイン中: <?php echo htmlspecialchars($_SESSION['name']); ?> (スタッフ)
                    <a href="../config/session.php?action=logout" style="color: white; margin-left: 20px;">ログアウト</a>
                </div>
            </div>
            
            <!-- 機能ボタン -->
            <div class="grid">
                <div class="grid-item">
                    <h3>🆕 新規客</h3>
                    <a href="new_customer.php" class="btn btn-primary">新規客登録</a>
                </div>
                <div class="grid-item">
                    <h3>📝 注文</h3>
                    <a href="order.php" class="btn btn-success">注文入力</a>
                </div>
                <div class="grid-item">
                    <h3>💰 会計</h3>
                    <a href="checkout.php" class="btn btn-warning">会計処理</a>
                </div>
                <div class="grid-item">
                    <h3>🔄 席移動</h3>
                    <a href="table_move.php" class="btn btn-primary">席移動</a>
                </div>
                <div class="grid-item">
                    <h3>👥 キャストチェンジ</h3>
                    <a href="cast_change.php" class="btn btn-primary">チェンジ</a>
                </div>
                <div class="grid-item">
                    <h3>⚙️ 管理者機能</h3>
                    <a href="../admin/auth.php" class="btn btn-danger">管理画面</a>
                </div>
            </div>
            
            <!-- フロアマップ -->
            <h2>📍 フロアマップ</h2>
            <div class="floor-map">
                <?php foreach ($tables as $table): ?>
                    <div class="table-item status-<?php echo $table['status']; ?>">
                        <h3>テーブル <?php echo htmlspecialchars($table['table_number']); ?></h3>
                        <p>定員: <?php echo $table['capacity']; ?>名</p>
                        <?php if ($table['session_id']): ?>
                            <p><strong>お客様:</strong> <?php echo htmlspecialchars($table['customer_name']); ?></p>
                            <p><strong>キャスト:</strong> <?php echo htmlspecialchars($table['cast_name'] ?? '未配置'); ?></p>
                        <?php else: ?>
                            <p>空席</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px;">
                <!-- 接客中キャスト -->
                <div>
                    <h2>👩‍💼 接客中キャスト</h2>
                    <div class="table">
                        <table>
                            <thead>
                                <tr>
                                    <th>キャスト名</th>
                                    <th>接客数</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($busy_casts as $cast): ?>
                                    <?php if ($cast['customer_count'] > 0): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cast['name']); ?></td>
                                            <td><?php echo $cast['customer_count']; ?>組</td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- フリーキャスト -->
                <div>
                    <h2>🆓 フリーキャスト</h2>
                    <div class="table">
                        <table>
                            <thead>
                                <tr>
                                    <th>キャスト名</th>
                                    <th>状態</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($free_casts as $cast): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cast['name']); ?></td>
                                        <td><span style="color: green;">待機中</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
