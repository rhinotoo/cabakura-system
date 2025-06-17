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

// テーブル追加
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_table') {
    $table_number = $_POST['table_number'] ?? '';
    $capacity = $_POST['capacity'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($table_number) || empty($capacity)) {
        $error = '必須項目を入力してください。';
    } else {
        try {
            // テーブル番号重複チェック
            $query = "SELECT id FROM tables WHERE table_number = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$table_number]);
            
            if ($stmt->fetch()) {
                $error = 'このテーブル番号は既に使用されています。';
            } else {
                $query = "INSERT INTO tables (table_number, capacity, description) VALUES (?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$table_number, $capacity, $description]);
                $message = 'テーブルを追加しました。';
            }
        } catch (Exception $e) {
            $error = 'エラーが発生しました: ' . $e->getMessage();
        }
    }
}

// テーブル更新
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_table') {
    $table_id = $_POST['table_id'] ?? '';
    $table_number = $_POST['table_number'] ?? '';
    $capacity = $_POST['capacity'] ?? '';
    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? '';
    
    if (empty($table_id) || empty($table_number) || empty($capacity) || empty($status)) {
        $error = '必須項目を入力してください。';
    } else {
        try {
            $query = "UPDATE tables SET table_number = ?, capacity = ?, description = ?, status = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$table_number, $capacity, $description, $status, $table_id]);
            $message = 'テーブルを更新しました。';
        } catch (Exception $e) {
            $error = 'エラーが発生しました: ' . $e->getMessage();
        }
    }
}

// テーブル削除
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_table') {
    $table_id = $_POST['table_id'] ?? '';
    
    try {
        $query = "DELETE FROM tables WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$table_id]);
        $message = 'テーブルを削除しました。';
    } catch (Exception $e) {
        $error = 'エラーが発生しました: ' . $e->getMessage();
    }
}

// テーブル一覧取得
$query = "SELECT t.*, 
                 CASE 
                   WHEN s.id IS NOT NULL THEN CONCAT(c.name, ' (', u.name, ')')
                   ELSE NULL
                 END as current_customer
          FROM tables t
          LEFT JOIN sessions s ON t.id = s.table_id AND s.status = 'active'
          LEFT JOIN customers c ON s.customer_id = c.id
          LEFT JOIN users u ON s.cast_id = u.id
          ORDER BY t.table_number";
$stmt = $db->prepare($query);
$stmt->execute();
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

$status_names = [
    'available' => '空席',
    'occupied' => '使用中',
    'reserved' => '予約済み',
    'maintenance' => 'メンテナンス'
];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>テーブル管理 - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/style.css">
    <style>
        .add-table-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 2fr auto;
            gap: 15px;
            align-items: end;
        }
        .table-actions {
            display: flex;
            gap: 10px;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 10px;
            width: 500px;
        }
        .table-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .table-card {
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: transform 0.2s;
        }
        .table-card:hover {
            transform: translateY(-2px);
        }
        .table-card.available { border-color: #28a745; background: #f8fff9; }
        .table-card.occupied { border-color: #dc3545; background: #fff8f8; }
        .table-card.reserved { border-color: #ffc107; background: #fffdf5; }
        .table-card.maintenance { border-color: #6c757d; background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>🪑 テーブル管理</h1>
                <div class="user-info">
                    <a href="index.php" style="color: white;">← マスタ管理に戻る</a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- テーブル追加フォーム -->
            <div class="add-table-form">
                <h3>🆕 新規テーブル追加</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_table">
                    <div class="form-row">
                        <div>
                            <label>テーブル番号 *</label>
                            <input type="text" name="table_number" class="form-control" required>
                        </div>
                        <div>
                            <label>定員 *</label>
                            <input type="number" name="capacity" class="form-control" min="1" required>
                        </div>
                        <div>
                            <label>説明</label>
                            <input type="text" name="description" class="form-control" placeholder="VIP席、窓際など">
                        </div>
                        <div>
                            <button type="submit" class="btn btn-success">追加</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- テーブル状況表示 -->
            <h2>📊 テーブル状況</h2>
            <div class="table-grid">
                <?php foreach ($tables as $table): ?>
                    <div class="table-card <?php echo $table['status']; ?>">
                        <h3>テーブル <?php echo htmlspecialchars($table['table_number']); ?></h3>
                        <p><strong>定員:</strong> <?php echo $table['capacity']; ?>名</p>
                        <p><strong>状態:</strong> 
                            <span class="badge badge-<?php echo $table['status']; ?>">
                                <?php echo $status_names[$table['status']] ?? $table['status']; ?>
                            </span>
                        </p>
                        <?php if ($table['current_customer']): ?>
                            <p><strong>利用中:</strong> <?php echo htmlspecialchars($table['current_customer']); ?></p>
                        <?php endif; ?>
                        <?php if ($table['description']): ?>
                            <p><small><?php echo htmlspecialchars($table['description']); ?></small></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- テーブル一覧 -->
            <h2>📋 テーブル一覧</h2>
            <div class="table">
                <table>
                    <thead>
                        <tr>
                            <th>テーブル番号</th>
                            <th>定員</th>
                            <th>状態</th>
                            <th>利用中</th>
                            <th>説明</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tables as $table): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($table['table_number']); ?></strong></td>
                                <td><?php echo $table['capacity']; ?>名</td>
                                <td>
                                    <span class="badge badge-<?php echo $table['status']; ?>">
                                        <?php echo $status_names[$table['status']] ?? $table['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($table['current_customer'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($table['description'] ?? '-'); ?></td>
                                <td>
                                    <div class="table-actions">
                                        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($table)); ?>)" 
                                                class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">
                                            編集
                                        </button>
                                        <?php if ($table['status'] !== 'occupied'): ?>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('本当に削除しますか？');">
                                                <input type="hidden" name="action" value="delete_table">
                                                <input type="hidden" name="table_id" value="<?php echo $table['id']; ?>">
                                                <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">
                                                    削除
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- テーブル編集モーダル -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>✏️ テーブル編集</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_table">
                <input type="hidden" id="edit_table_id" name="table_id">
                
                <div class="form-group">
                    <label>テーブル番号 *</label>
                    <input type="text" id="edit_table_number" name="table_number" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>定員 *</label>
                    <input type="number" id="edit_capacity" name="capacity" class="form-control" min="1" required>
                </div>
                
                <div class="form-group">
                    <label>状態 *</label>
                    <select id="edit_status" name="status" class="form-control" required>
                        <option value="available">空席</option>
                        <option value="occupied">使用中</option>
                        <option value="reserved">予約済み</option>
                        <option value="maintenance">メンテナンス</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>説明</label>
                    <input type="text" id="edit_description" name="description" class="form-control">
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">更新</button>
                    <button type="button" onclick="closeEditModal()" class="btn" style="background: #6c757d;">キャンセル</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openEditModal(table) {
            document.getElementById('edit_table_id').value = table.id;
            document.getElementById('edit_table_number').value = table.table_number;
            document.getElementById('edit_capacity').value = table.capacity;
            document.getElementById('edit_status').value = table.status;
            document.getElementById('edit_description').value = table.description || '';
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // モーダル外クリックで閉じる
        window.onclick = function(event) {
            var modal = document.getElementById('editModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
    
    <style>
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-available { background: #28a745; color: white; }
        .badge-occupied { background: #dc3545; color: white; }
        .badge-reserved { background: #ffc107; color: black; }
        .badge-maintenance { background: #6c757d; color: white; }
    </style>
</body>
</html>
