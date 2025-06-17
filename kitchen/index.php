<?php
require_once '../config/database.php';
require_once '../config/session.php';

checkLogin();
checkRole(['kitchen', 'admin']);

$database = new Database();
$db = $database->getConnection();

$message = '';

// 注文完了処理
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['complete_order'])) {
    $order_id = $_POST['order_id'];
    
    try {
        $query = "UPDATE orders SET status = 'completed' WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$order_id]);
        $message = '注文を完了にしました。';
    } catch (Exception $e) {
        $message = 'エラーが発生しました: ' . $e->getMessage();
    }
}

// 調理開始処理
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['start_cooking'])) {
    $order_id = $_POST['order_id'];
    
    try {
        $query = "UPDATE orders SET status = 'preparing' WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$order_id]);
        $message = '調理を開始しました。';
    } catch (Exception $e) {
        $message = 'エラーが発生しました: ' . $e->getMessage();
    }
}

// 新規注文取得
$query = "SELECT o.*, m.name as menu_name, t.table_number, c.name as customer_name
          FROM orders o
          JOIN menu_items m ON o.menu_item_id = m.id
          JOIN sessions s ON o.session_id = s.id
          JOIN tables t ON s.table_id = t.id
          JOIN customers c ON s.customer_id = c.id
          WHERE o.status = 'pending' AND m.category IN ('food', 'drink')
          ORDER BY o.ordered_at ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$pending_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 調理中注文取得
$query = "SELECT o.*, m.name as menu_name, t.table_number, c.name as customer_name
          FROM orders o
          JOIN menu_items m ON o.menu_item_id = m.id
          JOIN sessions s ON o.session_id = s.id
          JOIN tables t ON s.table_id = t.id
          JOIN customers c ON s.customer_id = c.id
          WHERE o.status = 'preparing' AND m.category IN ('food', 'drink')
          ORDER BY o.ordered_at ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$preparing_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>キッチンディスプレイ - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .order-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 5px solid #007bff;
        }
        .order-card.preparing {
            border-left-color: #ffc107;
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .table-info {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }
        .order-time {
            font-size: 14px;
            color: #6c757d;
        }
        .menu-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .quantity {
            font-size: 24px;
            font-weight: bold;
            color: #e74c3c;
        }
        .auto-refresh {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            font-size: 14px;
        }
    </style>
    <script>
        // 30秒ごとに自動更新
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</head>
<body>
    <div class="auto-refresh">
        🔄 30秒ごとに自動更新
    </div>
    
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>🍳 キッチンディスプレイ</h1>
                <div class="user-info">
                    ログイン中: <?php echo htmlspecialchars($_SESSION['name']); ?>
                    <a href="../config/session.php?action=logout" style="color: white; margin-left: 20px;">ログアウト</a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <!-- 新規注文 -->
                <div>
                    <h2>🆕 新規注文 (<?php echo count($pending_orders); ?>件)</h2>
                    <?php if (empty($pending_orders)): ?>
                        <p style="text-align: center; color: #6c757d; padding: 40px;">新規注文はありません</p>
                    <?php else: ?>
                        <?php foreach ($pending_orders as $order): ?>
                            <div class="order-card">
                                <div class="order-header">
                                    <div class="table-info">
                                        テーブル <?php echo htmlspecialchars($order['table_number']); ?>
                                    </div>
                                    <div class="order-time">
                                        <?php echo date('H:i', strtotime($order['ordered_at'])); ?>
                                    </div>
                                </div>
                                
                                <div class="menu-item">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <strong><?php echo htmlspecialchars($order['menu_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($order['customer_name']); ?></small>
                                        </div>
                                        <div class="quantity">×<?php echo $order['quantity']; ?></div>
                                    </div>
                                </div>
                                
                                <form method="POST" style="text-align: center;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <button type="submit" name="start_cooking" class="btn btn-warning">調理開始</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- 調理中 -->
                <div>
                    <h2>🔥 調理中 (<?php echo count($preparing_orders); ?>件)</h2>
                    <?php if (empty($preparing_orders)): ?>
                        <p style="text-align: center; color: #6c757d; padding: 40px;">調理中の注文はありません</p>
                    <?php else: ?>
                        <?php foreach ($preparing_orders as $order): ?>
                            <div class="order-card preparing">
                                <div class="order-header">
                                    <div class="table-info">
                                        テーブル <?php echo htmlspecialchars($order['table_number']); ?>
                                    </div>
                                    <div class="order-time">
                                        <?php echo date('H:i', strtotime($order['ordered_at'])); ?>
                                    </div>
                                </div>
                                
                                <div class="menu-item">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <strong><?php echo htmlspecialchars($order['menu_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($order['customer_name']); ?></small>
                                        </div>
                                        <div class="quantity">×<?php echo $order['quantity']; ?></div>
                                    </div>
                                </div>
                                
                                <form method="POST" style="text-align: center;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <button type="submit" name="complete_order" class="btn btn-success">完了</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
