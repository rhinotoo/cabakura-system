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

// ユーザー追加
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_user') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $name = $_POST['name'] ?? '';
    $role = $_POST['role'] ?? '';
    
    if (empty($username) || empty($password) || empty($name) || empty($role)) {
        $error = '必須項目を入力してください。';
    } else {
        try {
            // ユーザー名重複チェック
            $query = "SELECT id FROM users WHERE username = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$username]);
            
            if ($stmt->fetch()) {
                $error = 'このユーザー名は既に使用されています。';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$username, $hashed_password, $name, $role]);
                $message = 'ユーザーを追加しました。';
            }
        } catch (Exception $e) {
            $error = 'エラーが発生しました: ' . $e->getMessage();
        }
    }
}

// ユーザー削除
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_user') {
    $user_id = $_POST['user_id'] ?? '';
    
    if ($user_id == $_SESSION['user_id']) {
        $error = '自分自身は削除できません。';
    } else {
        try {
            $query = "DELETE FROM users WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$user_id]);
            $message = 'ユーザーを削除しました。';
        } catch (Exception $e) {
            $error = 'エラーが発生しました: ' . $e->getMessage();
        }
    }
}

// パスワードリセット
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'reset_password') {
    $user_id = $_POST['user_id'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    if (empty($user_id) || empty($new_password)) {
        $error = '必須項目を入力してください。';
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $query = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$hashed_password, $user_id]);
            $message = 'パスワードをリセットしました。';
        } catch (Exception $e) {
            $error = 'エラーが発生しました: ' . $e->getMessage();
        }
    }
}

// ユーザー一覧取得
$query = "SELECT * FROM users ORDER BY role, name";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$role_names = [
    'admin' => '管理者',
    'staff' => 'スタッフ',
    'cast' => 'キャスト',
    'kitchen' => 'キッチン'
];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ユーザー管理 - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/style.css">
    <style>
        .add-user-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        .user-actions {
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
            margin: 15% auto;
            padding: 20px;
            border-radius: 10px;
            width: 400px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>👥 ユーザー管理</h1>
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
            
            <!-- ユーザー追加フォーム -->
            <div class="add-user-form">
                <h3>🆕 新規ユーザー追加</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_user">
                    <div class="form-row">
                        <div>
                            <label>ユーザー名 *</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div>
                            <label>パスワード *</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div>
                            <label>表示名 *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div>
                            <label>役割 *</label>
                            <select name="role" class="form-control" required>
                                <option value="">選択</option>
                                <option value="admin">管理者</option>
                                <option value="staff">スタッフ</option>
                                <option value="cast">キャスト</option>
                                <option value="kitchen">キッチン</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-success">追加</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- ユーザー一覧 -->
            <h2>📋 ユーザー一覧</h2>
            <div class="table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ユーザー名</th>
                            <th>表示名</th>
                            <th>役割</th>
                            <th>登録日</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $user['role']; ?>">
                                        <?php echo $role_names[$user['role']] ?? $user['role']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y/m/d', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="user-actions">
                                        <button onclick="openPasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')" 
                                                class="btn btn-warning" style="padding: 5px 10px; font-size: 12px;">
                                            パスワード変更
                                        </button>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('本当に削除しますか？');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
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
    
    <!-- パスワード変更モーダル -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <h3>🔑 パスワード変更</h3>
            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" id="modal_user_id" name="user_id">
                
                <div class="form-group">
                    <label>ユーザー名</label>
                    <input type="text" id="modal_user_name" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label>新しいパスワード *</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">変更</button>
                    <button type="button" onclick="closePasswordModal()" class="btn" style="background: #6c757d;">キャンセル</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openPasswordModal(userId, userName) {
            document.getElementById('modal_user_id').value = userId;
            document.getElementById('modal_user_name').value = userName;
            document.getElementById('passwordModal').style.display = 'block';
        }
        
        function closePasswordModal() {
            document.getElementById('passwordModal').style.display = 'none';
        }
        
        // モーダル外クリックで閉じる
        window.onclick = function(event) {
            var modal = document.getElementById('passwordModal');
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
        .badge-admin { background: #e74c3c; color: white; }
        .badge-staff { background: #3498db; color: white; }
        .badge-cast { background: #e91e63; color: white; }
        .badge-kitchen { background: #ff9800; color: white; }
    </style>
</body>
</html>
