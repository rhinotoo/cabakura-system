<?php
require_once '../config/database.php';
require_once '../config/session.php';

checkLogin();
checkRole(['staff', 'admin']);

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// 空席テーブル取得
$query = "SELECT * FROM tables WHERE status = 'available' ORDER BY table_number";
$stmt = $db->prepare($query);
$stmt->execute();
$available_tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// フリーキャスト取得
$query = "SELECT u.* FROM users u 
          WHERE u.role = 'cast' 
          AND u.id NOT IN (SELECT DISTINCT cast_id FROM sessions WHERE status = 'active' AND cast_id IS NOT NULL)";
$stmt = $db->prepare($query);
$stmt->execute();
$free_casts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_name = $_POST['customer_name'] ?? '';
    $party_size = $_POST['party_size'] ?? '';
    $table_id = $_POST['table_id'] ?? '';
    $cast_id = $_POST['cast_id'] ?? '';
    
    if (empty($customer_name) || empty($party_size) || empty($table_id)) {
        $error = '必須項目を入力してください。';
    } else {
        try {
            $db->beginTransaction();
            
            // お客様登録
            $query = "INSERT INTO customers (name) VALUES (?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$customer_name]);
            $customer_id = $db->lastInsertId();
            
            // セッション開始
            $query = "INSERT INTO sessions (customer_id, table_id, cast_id, staff_id, party_size) VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$customer_id, $table_id, $cast_id ?: null, $_SESSION['user_id'], $party_size]);
            
            // テーブル状態更新
            $query = "UPDATE tables SET status = 'occupied' WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$table_id]);
            
            $db->commit();
            $message = 'お客様の登録が完了しました。';
            
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
    <title>新規客登録 - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>🆕 新規客登録</h1>
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
                    <label for="customer_name">お客様名 *</label>
                    <input type="text" id="customer_name" name="customer_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="party_size">人数 *</label>
                    <select id="party_size" name="party_size" class="form-control" required>
                        <option value="">選択してください</option>
                        <option value="1">1名</option>
                        <option value="2">2名</option>
                        <option value="3">3名</option>
                        <option value="4">4名</option>
                        <option value="5">5名</option>
                        <option value="6">6名</option>
                        <option value="7">7名</option>
                        <option value="8">8名</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="table_id">席選択 *</label>
                    <select id="table_id" name="table_id" class="form-control" required>
                        <option value="">選択してください</option>
                        <?php foreach ($available_tables as $table): ?>
                            <option value="<?php echo $table['id']; ?>">
                                テーブル <?php echo htmlspecialchars($table['table_number']); ?> 
                                (定員<?php echo $table['capacity']; ?>名)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="cast_id">キャスト選択</label>
                    <select id="cast_id" name="cast_id" class="form-control">
                        <option value="">後で選択</option>
                        <?php foreach ($free_casts as $cast): ?>
                            <option value="<?php echo $cast['id']; ?>">
                                <?php echo htmlspecialchars($cast['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">登録する</button>
                    <a href="index.php" class="btn" style="background: #6c757d;">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
