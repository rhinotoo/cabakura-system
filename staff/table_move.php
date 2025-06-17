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
$query = "SELECT t.id, t.table_number, c.name as customer_name, s.id as session_id
          FROM tables t 
          JOIN sessions s ON t.id = s.table_id AND s.status = 'active'
          JOIN customers c ON s.customer_id = c.id
          ORDER BY t.table_number";
$stmt = $db->prepare($query);
$stmt->execute();
$occupied_tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 空席テーブル取得
$query = "SELECT * FROM tables WHERE status = 'available' ORDER BY table_number";
$stmt = $db->prepare($query);
$stmt->execute();
$available_tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $session_id = $_POST['session_id'] ?? '';
    $new_table_id = $_POST['new_table_id'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if (empty($session_id) || empty($new_table_id) || empty($reason)) {
        $error = '必須項目を入力してください。';
    } else {
        try {
            $db->beginTransaction();
            
            // 現在のテーブルID取得
            $query = "SELECT table_id FROM sessions WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$session_id]);
            $current_session = $stmt->fetch(PDO::FETCH_ASSOC);
            $old_table_id = $current_session['table_id'];
            
            // セッションのテーブル更新
            $query = "UPDATE sessions SET table_id = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$new_table_id, $session_id]);
            
            // 旧テーブルを空席に
            $query = "UPDATE tables SET status = 'available' WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$old_table_id]);
            
            // 新テーブルを使用中に
            $query = "UPDATE tables SET status = 'occupied' WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$new_table_id]);
            
            $db->commit();
            $message = '席移動が完了しました。';
            
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
    <title>席移動 - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>🔄 席移動</h1>
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
                    <label for="session_id">移動元テーブル *</label>
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
                
                <div class="form-group">
                    <label for="reason">移動理由 *</label>
                    <select id="reason" name="reason" class="form-control" required>
                        <option value="">選択してください</option>
                        <option value="お客様の要望">お客様の要望</option>
                        <option value="テーブル不具合">テーブル不具合</option>
                        <option value="人数変更">人数変更</option>
                        <option value="騒音対策">騒音対策</option>
                        <option value="その他">その他</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="new_table_id">移動先テーブル *</label>
                    <select id="new_table_id" name="new_table_id" class="form-control" required>
                        <option value="">選択してください</option>
                        <?php foreach ($available_tables as $table): ?>
                            <option value="<?php echo $table['id']; ?>">
                                テーブル <?php echo htmlspecialchars($table['table_number']); ?> 
                                (定員<?php echo $table['capacity']; ?>名)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('席移動を実行しますか？');">移動実行</button>
                    <a href="index.php" class="btn" style="background: #6c757d;">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
