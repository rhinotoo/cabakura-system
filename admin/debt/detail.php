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

$debt_id = $_GET['id'] ?? '';

if (!$debt_id) {
    header('Location: index.php');
    exit();
}

// 借金詳細取得
$query = "SELECT d.*, c.name as customer_name
          FROM debts d
          JOIN customers c ON d.customer_id = c.id
          WHERE d.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$debt_id]);
$debt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$debt) {
    header('Location: index.php');
    exit();
}

// 返済履歴取得
$query = "SELECT * FROM debt_payments WHERE debt_id = ? ORDER BY payment_date DESC";
$stmt = $db->prepare($query);
$stmt->execute([$debt_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 返済統計
$total_payments = array_sum(array_column($payments, 'amount'));
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>借金詳細 - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/style.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>🔍 借金詳細</h1>
                <div class="user-info">
                    <a href="index.php" style="color: white;">← 借金管理に戻る</a>
                </div>
            </div>
            
            <!-- 借金情報 -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
                <h2>💳 借金情報</h2>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <p><strong>お客様名:</strong> <?php echo htmlspecialchars($debt['customer_name']); ?></p>
                        <p><strong>借金額:</strong> ¥<?php echo number_format($debt['amount']); ?></p>
                        <p><strong>返済済み:</strong> ¥<?php echo number_format($total_payments); ?></p>
                        <p><strong>残高:</strong> <span style="color: #e74c3c; font-weight: bold;">¥<?php echo number_format($debt['remaining_amount']); ?></span></p>
                    </div>
                    <div>
                        <p><strong>登録日:</strong> <?php echo date('Y年m月d日', strtotime($debt['created_at'])); ?></p>
                        <p><strong>備考:</strong> <?php echo htmlspecialchars($debt['description'] ?? ''); ?></p>
                        <p><strong>返済回数:</strong> <?php echo count($payments); ?>回</p>
                        <p><strong>返済率:</strong> <?php echo $debt['amount'] > 0 ? round(($total_payments / $debt['amount']) * 100, 1) : 0; ?>%</p>
                    </div>
                </div>
            </div>
            
            <!-- 返済履歴 -->
            <h2>💰 返済履歴</h2>
            <div class="table">
                <table>
                    <thead>
                        <tr>
                            <th>返済日</th>
                            <th>返済額</th>
                            <th>返済後残高</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: #6c757d;">返済履歴がありません</td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $running_balance = $debt['amount'];
                            foreach ($payments as $payment): 
                                $running_balance -= $payment['amount'];
                            ?>
                                <tr>
                                    <td><?php echo date('Y/m/d H:i', strtotime($payment['payment_date'])); ?></td>
                                    <td>¥<?php echo number_format($payment['amount']); ?></td>
                                    <td>¥<?php echo number_format($running_balance); ?></td>
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
