<?php
require_once '../config/database.php';
require_once '../config/session.php';

checkLogin();
checkRole(['staff', 'admin']);

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// 使用中のテーブル取得
$query = "SELECT t.id, t.table_number, c.name as customer_name 
          FROM tables t 
          JOIN sessions s ON t.id = s.table_id AND s.status = 'active'
          JOIN customers c ON s.customer_id = c.id
          ORDER BY t.table_number";
$stmt = $db->prepare($query);
$stmt->execute();
$occupied_tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// メニュー取得
$query = "SELECT * FROM menu_items WHERE is_active = 1 ORDER BY category, name";
$stmt = $db->prepare($query);
$stmt->execute();
$menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// カテゴリ別にメニューを整理
$menu_by_category = [];
foreach ($menu_items as $item) {
    $menu_by_category[$item['category']][] = $item;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $table_id = $_POST['table_id'] ?? '';
    $orders = $_POST['orders'] ?? [];
    
    if (empty($table_id)) {
        $error = 'テーブルを選択してください。';
    } elseif (empty($orders)) {
        $error = '注文を選択してください。';
    } else {
        try {
            $db->beginTransaction();
            
            // セッションID取得
            $query = "SELECT id FROM sessions WHERE table_id = ? AND status = 'active'";
            $stmt = $db->prepare($query);
            $stmt->execute([$table_id]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                throw new Exception('有効なセッションが見つかりません。');
            }
            
            $session_id = $session['id'];
            
            // 注文登録
            foreach ($orders as $menu_item_id => $quantity) {
                if ($quantity > 0) {
                    // メニュー価格取得
                    $query = "SELECT price FROM menu_items WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$menu_item_id]);
                    $menu_item = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $unit_price = $menu_item['price'];
                    $total_price = $unit_price * $quantity;
                    
                    // 注文登録
                    $query = "INSERT INTO orders (session_id, menu_item_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$session_id, $menu_item_id, $quantity, $unit_price, $total_price]);
                }
            }
            
            $db->commit();
            $message = '注文が登録されました。';
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'エラーが発生しました: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注文入力 - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .menu-category {
            margin-bottom: 30px;
        }
        .menu-items {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .menu-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .menu-item h4 {
            margin-bottom: 10px;
            color: #2c3e50;
        }
        .price {
            font-weight: bold;
            color: #e74c3c;
            margin-bottom: 10px;
        }
        .quantity-input {
            width: 80px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>📝 注文入力</h1>
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
            
            <form method="POST">
                <div class="form-group">
                    <label for="table_id">テーブル選択 *</label>
                    <select id="table_id" name="table_id" class="form-control" required>
                        <option value="">選択してください</option>
                        <?php foreach ($occupied_tables as $table): ?>
                            <option value="<?php echo $table['id']; ?>">
                                テーブル <?php echo htmlspecialchars($table['table_number']); ?> 
                                - <?php echo htmlspecialchars($table['customer_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php 
                $category_names = [
                    'drink' => '🍺 ドリンク',
                    'food' => '🍽️ フード',
                    'other' => '🎯 その他'
                ];
                ?>
                
                <?php foreach ($menu_by_category as $category => $items): ?>
                    <div class="menu-category">
                        <h2><?php echo $category_names[$category] ?? $category; ?></h2>
                        <div class="menu-items">
                            <?php foreach ($items as $item): ?>
                                <div class="menu-item">
                                    <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                    <div class="price">¥<?php echo number_format($item['price']); ?></div>
                                    <div>
                                        <label>数量:</label>
                                        <input type="number" 
                                               name="orders[<?php echo $item['id']; ?>]" 
                                               class="form-control quantity-input" 
                                               min="0" 
                                               max="99" 
                                               value="0">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-success">注文確定</button>
                    <a href="index.php" class="btn" style="background: #6c757d;">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
