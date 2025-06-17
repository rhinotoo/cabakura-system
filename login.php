<?php
require_once 'config/database.php';
session_start();

if (isset($_SESSION['user_id'])) {
    // header('Location: dashboard.php');
    // exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'ユーザーIDとパスワードを入力してください。';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT id, username, password, role, name FROM users WHERE username = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$username]);
        
        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];
                
                // ロール別リダイレクト
                switch ($user['role']) {
                    case 'staff':
                        header('Location: staff/index.php');
                        break;
                    case 'kitchen':
                        header('Location: kitchen/index.php');
                        break;
                    case 'cast':
                        header('Location: cast/index.php');
                        break;
                    case 'admin':
                        header('Location: admin/index.php');
                        break;
                }
                exit();
            } else {
                $error = 'パスワードが間違っています。';
            }
        } else {
            $error = 'ユーザーが見つかりません。';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #7f8c8d;
            font-size: 16px;
        }
        
        .demo-accounts {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .demo-accounts h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .demo-account {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px;
            background: white;
            border-radius: 5px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>🍾 キャバクラ管理システム</h1>
                <p>ログインしてください</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">ユーザーID</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="password">パスワード</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">ログイン</button>
            </form>
            
            <div class="demo-accounts">
                <h3>デモアカウント</h3>
                <div class="demo-account">
                    <span><strong>管理者:</strong> admin</span>
                    <span>password</span>
                </div>
                <div class="demo-account">
                    <span><strong>スタッフ:</strong> staff1</span>
                    <span>password</span>
                </div>
                <div class="demo-account">
                    <span><strong>キッチン:</strong> kitchen1</span>
                    <span>password</span>
                </div>
                <div class="demo-account">
                    <span><strong>キャスト:</strong> cast1</span>
                    <span>password</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
