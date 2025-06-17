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

// メニュー追加
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_menu') {
    $name = $_POST['name'] ?? '';
    $category = $_POST['category'] ?? '';
    $price = $_POST['price'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($name) || empty($category) || empty($price)) {
        $error = '必須項目を入力してください。';
    } else {
        try {
            $query = "INSERT INTO menu_items (name, category, price, description) VALUES (?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$name, $category, $price, $description]);
            $message = 'メニューを追加しました。';
        } catch (Exception $e) {
            $error = 'エラーが発生しました: ' . $e->getMessage();
        }
    }
}

// メニュー更新
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_menu') {
    $menu_id = $_POST['menu_id'] ?? '';
    $name = $_POST['name'] ?? '';
    $category = $_POST['category'] ?? '';
    $price = $_POST['price'] ?? '';
    $description = $_POST['description'] ?? '';
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    
    if (empty($menu_id) || empty($name) || empty($category) || empty($price)) {
        $error = '必須項目を入力してください。';
    } else {
        try {
            $query = "UPDATE menu_items SET name = ?, category = ?, price = ?, description = ?, is_available = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$name, $category, $price, $description, $is_available, $menu_id]);
            $message = 'メニューを更新しました。';
        } catch (Exception $e) {
            $error = 'エラーが発生しました: ' . $e->getMessage();
        }
    }
}

// メニュー削除
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_menu') {
    $menu_id = $_POST['menu_id'] ?? '';
    
    try {
        $query = "DELETE FROM menu_items WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$menu_id]);
        $message = 'メニューを削除しました。';
    } catch (Exception $e) {
        $error = 'エラーが発生しました: ' . $e->getMessage();
    }
}

// メニュー一覧取得
$query = "SELECT * FROM menu_items ORDER BY category, name";
$stmt = $db->prepare($query);
$stmt->execute();
$menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$category_names = [
    'drink' => 'ドリンク',
    'food' => 'フード',
    'bottle' => 'ボトル',
    'service' => 'サービス'
];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メニュー管理 - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/style.css">
    <style>
        .add-menu-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 2fr auto;
            gap: 15px;
            align-items: end;
        }
        .menu-actions {
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
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>🍽️ メニュー管理</h1>
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
            
            <!-- メニュー追加フォーム -->
            <div class="add-menu-form">
                <h3>🆕 新規メニュー追加</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_menu">
                    <div class="form-row">
                        <div>
                            <label>メニュー名 *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div>
                            <label>カテゴリ *</label>
                            <select name="category" class="form-control" required>
                                <option value="">選択</option>
                                <option value="drink">ドリンク</option>
                                <option value="food">フード</option>
                                <option value="bottle">ボトル</option>
                                <option value="service">サービス</option>
                            </select>
                        </div>
                        <div>
                            <label>価格 *</label>
                            <input type="number" name="price" class="form-control" min="0" required>
                        </div>
                        <div>
                            <label>説明</label>
                            <input type="text" name="description" class="form-control">
                        </div>
                        <div>
                            <button type="submit" class="btn btn-success">追加</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- メニュー一覧 -->
            <h2>📋 メニュー一覧</h2>
            <div class="table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>メニュー名</th>
                            <th>カテゴリ</th>
                            <th>価格</th>
                            <th>説明</th>
                            <th>状態</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($menu_items as $item): ?>
                            <tr>
                                <td><?php echo $item['id']; ?></td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $item['category']; ?>">
                                        <?php echo $category_names[$item['category']] ?? $item['category']; ?>
                                    </span>
                                </td>
                                <td>¥<?php echo number_format($item['price']); ?></td>
                                <td><?php echo htmlspecialchars($item['description'] ?? ''); ?></td>
                                <td>
                                    <?php if ($item['is_available']): ?>
                                        <span style="color: #28a745;">提供中</span>
                                    <?php else: ?>
                                        <span style="color: #dc3545;">停止中</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="menu-actions">
                                        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($item)); ?>)" 
                                                class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">
                                            編集
                                        </button>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('本当に削除しますか？');">
                                            <input type="hidden" name="action" value="delete_menu">
                                            <input type="hidden" name="menu_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">
                                                削除
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- メニュー編集モーダル -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>✏️ メニュー編集</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_menu">
                <input type="hidden" id="edit_menu_id" name="menu_id">
                
                <div class="form-group">
                    <label>メニュー名 *</label>
                    <input type="text" id="edit_name" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>カテゴリ *</label>
                    <select id="edit_category" name="category" class="form-control" required>
                        <option value="drink">ドリンク</option>
                        <option value="food">フード</option>
                        <option value="bottle">ボトル</option>
                        <option value="service">サービス</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>価格 *</label>
                    <input type="number" id="edit_price" name="price" class="form-control" min="0" required>
                </div>
                
                <div class="form-group">
                    <label>説明</label>
                    <input type="text" id="edit_description" name="description" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="edit_is_available" name="is_available">
                        提供中
                    </label>
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">更新</button>
                    <button type="button" onclick="closeEditModal()" class="btn" style="background: #6c757d;">キャンセル</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openEditModal(item) {
            document.getElementById('edit_menu_id').value = item.id;
            document.getElementById('edit_name').value = item.name;
            document.getElementById('edit_category').value = item.category;
            document.getElementById('edit_price').value = item.price;
            document.getElementById('edit_description').value = item.description || '';
            document.getElementById('edit_is_available').checked = item.is_available == 1;
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
        .badge-drink { background: #3498db; color: white; }
        .badge-food { background: #e67e22; color: white; }
        .badge-bottle { background: #9b59b6; color: white; }
        .badge-service { background: #2ecc71; color: white; }
    </style>
</body>
</html>
