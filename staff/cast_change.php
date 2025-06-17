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
$query = "SELECT t.id, t.table_number, c.name as customer_name, s.id as session_id, 
                 u.name as current_cast_name, s.cast_id
          FROM tables t 
          JOIN sessions s ON t.id = s.table_id AND s.status = 'active'
          JOIN customers c ON s.customer_id = c.id
          LEFT JOIN users u ON s.cast_id = u.id
          ORDER BY t.table_number";
$stmt = $db->prepare($query);
$stmt->execute();
$occupied_tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// フリーキャスト取得
$query = "SELECT u.* FROM users u 
          WHERE u.role = 'cast' 
          AND u.id NOT IN (SELECT DISTINCT cast_id FROM sessions WHERE status = 'active' AND cast_id IS NOT NULL)";
$stmt = $db->prepare($query);
$stmt->execute();
$free_casts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $session_id = $_POST['session_id'] ?? '';
    $new_cast_id = $_POST['new_cast_id'] ?? '';
    
    if (empty($session_id) || empty($new_cast_id)) {
        $error = '必須項目を選択してください。';
    } else {
        try {
            $db->beginTransaction();
            
            // セッションのキャスト更新
            $query = "UPDATE sessions SET cast_id = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$new_cast_id, $session_id]);
            
            $db->commit();
            $message = 'キャストチェンジが完了しました。';
            
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
    <title>キャストチェンジ - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>👥 キャストチェンジ</h1>
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
                    <label for="session_id">テーブル選択 *</label>
                    <select id="session_id" name="session_id" class="form-control" required onchange="updateCurrentCast()">
                        <option value="">選択してください</option>
                        <?php foreach ($occupied_tables as $table): ?>
                            <option value="<?php echo $table['session_id']; ?>" 
                                    data-current-cast="<?php echo htmlspecialchars($table['current_cast_name'] ?? '未配置'); ?>">
                                テーブル <?php echo htmlspecialchars($table['table_number']); ?> 
                                - <?php echo htmlspecialchars($table['customer_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>現在のキャスト</label>
                    <div id="current-cast" class="form-control" style="background: #f8f9fa;">
                        テーブルを選択してください
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="new_cast_id">新しいキャスト *</label>
                    <select id="new_cast_id" name="new_cast_id" class="form-control" required>
                        <option value="">選択してください</option>
                        <?php foreach ($free_casts as $cast): ?>
                            <option value="<?php echo $cast['id']; ?>">
                                <?php echo htmlspecialchars($cast['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('キャストチェンジを実行しますか？');">チェンジ実行</button>
                    <a href="index.php" class="btn" style="background: #6c757d;">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function updateCurrentCast() {
            const select = document.getElementById('session_id');
            const currentCastDiv = document.getElementById('current-cast');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption.value) {
                currentCastDiv.textContent = selectedOption.getAttribute('data-current-cast');
            } else {
                currentCastDiv.textContent = 'テーブルを選択してください';
            }
        }
    </script>
</body>
</html>
