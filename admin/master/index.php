<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

checkLogin();
if (!isset($_SESSION['admin_authenticated']) && $_SESSION['role'] !== 'admin') {
    header('Location: ../auth.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マスタ管理 - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/style.css">
    <style>
        .master-menu {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        .menu-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .menu-card:hover {
            transform: translateY(-5px);
        }
        .menu-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .menu-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        .menu-description {
            color: #6c757d;
            margin-bottom: 20px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>🔧 マスタ管理</h1>
                <div class="user-info">
                    <a href="../index.php" style="color: white;">← 管理者メニューに戻る</a>
                </div>
            </div>
            
            <div class="master-menu">
                <!-- ユーザー管理 -->
                <div class="menu-card">
                    <div class="menu-icon">👥</div>
                    <div class="menu-title">ユーザー管理</div>
                    <div class="menu-description">
                        スタッフ・キャスト・キッチンスタッフの<br>
                        登録・編集・削除を行います
                    </div>
                    <a href="users.php" class="btn btn-primary">ユーザー管理へ</a>
                </div>
                
                <!-- メニュー管理 -->
                <div class="menu-card">
                    <div class="menu-icon">🍽️</div>
                    <div class="menu-title">メニュー管理</div>
                    <div class="menu-description">
                        ドリンク・フード・その他メニューの<br>
                        登録・編集・価格設定を行います
                    </div>
                    <a href="menu.php" class="btn btn-primary">メニュー管理へ</a>
                </div>
                
                <!-- テーブル管理 -->
                <div class="menu-card">
                    <div class="menu-icon">🪑</div>
                    <div class="menu-title">テーブル管理</div>
                    <div class="menu-description">
                        席の登録・編集・定員設定・<br>
                        状態管理を行います
                    </div>
                    <a href="tables.php" class="btn btn-primary">テーブル管理へ</a>
                </div>
                
                <!-- システム設定 -->
                <div class="menu-card">
                    <div class="menu-icon">⚙️</div>
                    <div class="menu-title">システム設定</div>
                    <div class="menu-description">
                        席料・延長料金・分配率などの<br>
                        システム設定を行います
                    </div>
                    <a href="settings.php" class="btn btn-primary">システム設定へ</a>
                </div>
                
                <!-- データベース管理 -->
                <div class="menu-card">
                    <div class="menu-icon">💾</div>
                    <div class="menu-title">データベース管理</div>
                    <div class="menu-description">
                        データのバックアップ・復元・<br>
                        初期化を行います
                    </div>
                    <a href="database.php" class="btn btn-warning">データベース管理へ</a>
                </div>
                
                <!-- ログ管理 -->
                <div class="menu-card">
                    <div class="menu-icon">📋</div>
                    <div class="menu-title">ログ管理</div>
                    <div class="menu-description">
                        システムログ・エラーログ・<br>
                        操作履歴の確認を行います
                    </div>
                    <a href="logs.php" class="btn btn-info">ログ管理へ</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
