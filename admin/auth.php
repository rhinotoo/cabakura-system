<?php
require_once '../config/database.php';
require_once '../config/session.php';

checkLogin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $admin_password = $_POST['admin_password'] ?? '';
    
    // 管理者パスワード（実際の運用では環境変数等で管理）
    $correct_password = 'admin123';
    
    if ($admin_password === $correct_password) {
        $_SESSION['admin_authenticated'] = true;
        header('Location: index.php');
        exit();
    } else {
        $error = '管理者パスワードが間違っています。';
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者認証 - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container" style="max-width: 400px; margin-top: 100px;">
        <div class="card">
            <div class="header">
                <h1>🔐 管理者認証</h1>
                <div class="user-info">
                    管理機能にアクセスするには管理者パスワードが必要です
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="admin_password">管理者パスワード</label>
                    <input type="password" id="admin_password" name="admin_password" class="form-control" required>
                </div>
                
                <div style="text-align: center;">
                    <button type="submit" class="btn btn-danger">認証</button>
                    <a href="../staff/index.php" class="btn" style="background: #6c757d;">戻る</a>
                </div>
            </form>
            
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px; font-size: 14px;">
                <strong>デモ用管理者パスワード:</strong> admin123
            </div>
        </div>
    </div>
</body>
</html>
