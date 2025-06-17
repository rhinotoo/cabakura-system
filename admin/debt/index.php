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

// 新規借金登録
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_debt') {
    $customer_name = $_POST['customer_name'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($customer_name) || empty($amount)) {
        $error = '必須項目を入力してください。';
    } else {
        try {
            $db->beginTransaction();
            
            // お客様が存在するかチェック
            $query = "SELECT id FROM customers WHERE name = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$customer_name]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customer) {
                // 新規お客様登録
                $query = "INSERT INTO customers (name) VALUES (?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$customer_name]);
                $customer_id = $db->lastInsertId();
            } else {
                $customer_id = $customer['id'];
            }
            
            // 借金登録
            $query = "INSERT INTO debts (customer_id, amount, remaining_amount, description) VALUES (?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$customer_id, $amount, $amount, $description]);
            
            $db->commit();
            $message = '借金を登録しました。';
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'エラーが発生しました: ' . $e->getMessage();
        }
    }
}

// 返済処理
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_payment') {
    $debt_id = $_POST['debt_id'] ?? '';
    $payment_amount = $_POST['payment_amount'] ?? '';
    
    if (empty($debt_id) || empty($payment_amount)) {
        $error = '必須項目を入力してください。';
    } else {
        try {
            $db->beginTransaction();
            
            // 借金情報取得
            $query = "SELECT remaining_amount FROM debts WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$debt_id]);
            $debt = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$debt) {
                throw new Exception('借金情報が見つかりません。');
            }
            
            if ($payment_amount > $debt['remaining_amount']) {
                throw new Exception('返済額が残高を超えています。');
            }
            
            // 返済記録
            $query = "INSERT INTO debt_payments (debt_id, amount) VALUES (?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$debt_id, $payment_amount]);
            
            // 借金残高更新
            $new_remaining = $debt['remaining_amount'] - $payment_amount;
            $query = "UPDATE debts SET remaining_amount = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$new_remaining, $debt_id]);
            
            $db->commit();
            $message = '返済を記録しました。';
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'エラーが発生しました: ' . $e->getMessage();
        }
    }
}

// 借金一覧取得
$query = "SELECT d.*, c.name as customer_name,
                 COALESCE(SUM(dp.amount), 0) as total_payments
          FROM debts d
          JOIN customers c ON d.customer_id = c.id
          LEFT JOIN debt_payments dp ON d.id = dp.debt_id
          GROUP BY d.id
          HAVING d.remaining_amount > 0
          ORDER BY d.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$active_debts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 借金統計
$query = "SELECT 
            COUNT(*) as total_debts,
            COALESCE(SUM(amount), 0) as total_debt_amount,
            COALESCE(SUM(remaining_amount), 0) as total_remaining
          FROM debts 
          WHERE remaining_amount > 0";
$stmt = $db->prepare($query);
$stmt->execute();
$debt_stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>借金管理 - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/style.css">
    <style>
        .debt-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>💳 借金管理</h1>
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
            
            <!-- 借金統計 -->
            <div class="debt-stats">
                <div class="stat-card">
                    <div style="font-size: 24px; font-weight: bold;"><?php echo $debt_stats['total_debts']; ?>件</div>
                    <div>未回収借金数</div>
                </div>
                <div class="stat-card">
                    <div style="font-size: 24px; font-weight: bold;">¥<?php echo number_format($debt_stats['total_debt_amount']); ?></div>
                    <div>総借金額</div>
                </div>
                <div class="stat-card">
                    <div style="font-size: 24px; font-weight: bold;">¥<?php echo number_format($debt_stats['total_remaining']); ?></div>
                    <div>未回収残高</div>
                </div>
            </div>
            
            <!-- 新規登録・返済フォーム -->
            <div class="form-grid">
                <!-- 新規借金登録 -->
                <div class="form-section">
                    <h3>🆕 新規借金登録</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_debt">
                        
                        <div class="form-group">
                            <label for="customer_name">お客様名 *</label>
                            <input type="text" id="customer_name" name="customer_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="amount">借金額 *</label>
                            <input type="number" id="amount" name="amount" class="form-control" min="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">備考</label>
                            <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-danger">借金登録</button>
                    </form>
                </div>
                
                <!-- 返済記録 -->
                <div class="form-section">
                    <h3>💰 返済記録</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_payment">
                        
                        <div class="form-group">
                            <label for="debt_id">借金選択 *</label>
                            <select id="debt_id" name="debt_id" class="form-control" required>
                                <option value="">選択してください</option>
                                <?php foreach ($active_debts as $debt): ?>
                                    <option value="<?php echo $debt['id']; ?>">
                                        <?php echo htmlspecialchars($debt['customer_name']); ?> 
                                        - 残高¥<?php echo number_format($debt['remaining_amount']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_amount">返済額 *</label>
                            <input type="number" id="payment_amount" name="payment_amount" class="form-control" min="1" required>
                        </div>
                        
                        <button type="submit" class="btn btn-success">返済記録</button>
                    </form>
                </div>
            </div>
            
            <!-- 借金一覧 -->
            <h2>📋 未回収借金一覧</h2>
            <div class="table">
                <table>
                    <thead>
                        <tr>
                            <th>お客様名</th>
                            <th>借金額</th>
                            <th>返済済み</th>
                            <th>残高</th>
                            <th>登録日</th>
                            <th>備考</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($active_debts)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #6c757d;">未回収の借金はありません</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($active_debts as $debt): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($debt['customer_name']); ?></td>
                                    <td>¥<?php echo number_format($debt['amount']); ?></td>
                                    <td>¥<?php echo number_format($debt['total_payments']); ?></td>
                                    <td><strong>¥<?php echo number_format($debt['remaining_amount']); ?></strong></td>
                                    <td><?php echo date('Y/m/d', strtotime($debt['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($debt['description'] ?? ''); ?></td>
                                    <td>
                                        <a href="detail.php?id=<?php echo $debt['id']; ?>" class="btn btn-info" style="padding: 5px 10px; font-size: 12px;">詳細</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- 機能メニュー -->
            <div style="margin-top: 30px;">
                <h2>🔧 機能メニュー</h2>
                <div class="grid">
                    <div class="grid-item">
                        <h3>📋 返済履歴</h3>
                        <a href="history.php" class="btn btn-primary">履歴確認</a>
                    </div>
                    <div class="grid-item">
                        <h3>📊 借金レポート</h3>
                        <a href="report.php" class="btn btn-success">レポート出力</a>
                    </div>
                    <div class="grid-item">
                        <h3>🔍 完済済み借金</h3>
                        <a href="completed.php" class="btn btn-secondary">完済済み一覧</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
