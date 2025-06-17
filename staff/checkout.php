<?php
require_once '../config/database.php';
require_once '../config/session.php';

checkLogin();
checkRole(['staff', 'admin']);

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';
$session_details = null;

// 使用中のテーブル取得
$query = "SELECT t.id, t.table_number, c.name as customer_name, s.id as session_id
          FROM tables t 
          JOIN sessions s ON t.id = s.table_id AND s.status = 'active'
          JOIN customers c ON s.customer_id = c.id
          ORDER BY t.table_number";
$stmt = $db->prepare($query);
$stmt->execute();
$occupied_tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// セッション詳細取得
if (isset($_GET['session_id']) || isset($_POST['session_id'])) {
    $session_id = $_GET['session_id'] ?? $_POST['session_id'];
    
    // セッション情報取得
    $query = "SELECT s.*, c.name as customer_name, t.table_number, u.name as cast_name,
                     TIMESTAMPDIFF(HOUR, s.start_time, NOW()) as hours
              FROM sessions s
              JOIN customers c ON s.customer_id = c.id
              JOIN tables t ON s.table_id = t.id
              LEFT JOIN users u ON s.cast_id = u.id
              WHERE s.id = ? AND s.status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute([$session_id]);
    $session_details = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session_details) {
        // 注文明細取得
        $query = "SELECT o.*, m.name as menu_name 
                  FROM orders o
                  JOIN menu_items m ON o.menu_item_id = m.id
                  WHERE o.session_id = ?
                  ORDER BY o.ordered_at";
        $stmt = $db->prepare($query);
        $stmt->execute([$session_id]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 合計金額計算
        $subtotal = array_sum(array_column($orders, 'total_price'));
        $seat_charge = 3000; // 席料
        $extension_charge = max(0, ($session_details['hours'] - 1)) * 1000; // 延長料金
        $total_amount = $subtotal + $seat_charge + $extension_charge;
        
        $session_details['orders'] = $orders;
        $session_details['subtotal'] = $subtotal;
        $session_details['seat_charge'] = $seat_charge;
        $session_details['extension_charge'] = $extension_charge;
        $session_details['total_amount'] = $total_amount;
    }
}

// 会計処理
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'checkout') {
    $session_id = $_POST['session_id'];
    $total_amount = $_POST['total_amount'];
    
    try {
        $db->beginTransaction();
        
        // セッション終了
        $query = "UPDATE sessions SET status = 'completed', end_time = NOW(), total_amount = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$total_amount, $session_id]);
        
        // テーブル状態更新
        $query = "UPDATE tables t 
                  JOIN sessions s ON t.id = s.table_id 
                  SET t.status = 'available' 
                  WHERE s.id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$session_id]);
        
        // 売上記録
        $query = "INSERT INTO sales (session_id, cast_id, staff_id, total_amount, sale_date)
                  SELECT id, cast_id, staff_id, total_amount, CURDATE()
                  FROM sessions WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$session_id]);
        
        $db->commit();
        $message = '会計処理が完了しました。';
        $session_details = null;
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'エラーが発生しました: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>会計処理 - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .checkout-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .amount-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 5px 0;
        }
        .total-row {
            border-top: 2px solid #dee2e6;
            padding-top: 10px;
            font-weight: bold;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>💰 会計処理</h1>
                <div class="user-info">
                    ログイン中: <?php echo htmlspecialchars($_SESSION['name']); ?>
                    <a href="index.php" style="color: white; margin-left: 20px;">← メイン画面に戻る</a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (!$session_details): ?>
                <form method="GET">
                    <div class="form-group">
                        <label for="session_id">テーブル選択</label>
                        <select id="session_id" name="session_id" class="form-control" required>
                            <option value="">選択してください</option>
                            <?php foreach ($occupied_tables as $table): ?>
                                <option value="<?php echo $table['session_id']; ?>">
                                    テーブル <?php echo htmlspecialchars($table['table_number']); ?> 
                                    - <?php echo htmlspecialchars($table['customer_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">明細表示</button>
                </form>
            <?php else: ?>
                <div class="checkout-summary">
                    <h2>会計明細</h2>
                    <p><strong>お客様:</strong> <?php echo htmlspecialchars($session_details['customer_name']); ?></p>
                    <p><strong>テーブル:</strong> <?php echo htmlspecialchars($session_details['table_number']); ?></p>
                    <p><strong>キャスト:</strong> <?php echo htmlspecialchars($session_details['cast_name'] ?? '未配置'); ?></p>
                    <p><strong>利用時間:</strong> <?php echo $session_details['hours']; ?>時間</p>
                    
                    <h3>注文明細</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>商品名</th>
                                <th>単価</th>
                                <th>数量</th>
                                <th>金額</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($session_details['orders'] as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['menu_name']); ?></td>
                                    <td>¥<?php echo number_format($order['unit_price']); ?></td>
                                    <td><?php echo $order['quantity']; ?></td>
                                    <td>¥<?php echo number_format($order['total_price']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="amount-row">
                        <span>小計:</span>
                        <span>¥<?php echo number_format($session_details['subtotal']); ?></span>
                    </div>
                    <div class="amount-row">
                        <span>席料:</span>
                        <span>¥<?php echo number_format($session_details['seat_charge']); ?></span>
                    </div>
                    <div class="amount-row">
                        <span>延長料金 (<?php echo max(0, $session_details['hours'] - 1); ?>時間):</span>
                        <span>¥<?php echo number_format($session_details['extension_charge']); ?></span>
                    </div>
                    <div class="amount-row total-row">
                        <span>合計金額:</span>
                        <span>¥<?php echo number_format($session_details['total_amount']); ?></span>
                    </div>
                </div>
                
                <form method="POST" onsubmit="return confirm('会計処理を実行しますか？');">
                    <input type="hidden" name="action" value="checkout">
                    <input type="hidden" name="session_id" value="<?php echo $session_details['id']; ?>">
                    <input type="hidden" name="total_amount" value="<?php echo $session_details['total_amount']; ?>">
                    
                    <div style="text-align: center; margin-top: 30px;">
                        <button type="submit" class="btn btn-success">会計確定</button>
                        <a href="checkout.php" class="btn" style="background: #6c757d;">戻る</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
