<?php
/**
 * セッション管理統合ファイル
 * パス: /config/session.php
 */

// bootstrap.phpが読み込まれていない場合のみ基本設定を行う
if (!defined('BOOTSTRAP_LOADED')) {
    // セッション設定
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_lifetime' => 3600,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Strict',
            'use_strict_mode' => true,
            'use_only_cookies' => true
        ]);
    }
    
    // 基本的な環境判定
    if (!defined('ENVIRONMENT')) {
        define('ENVIRONMENT', 'development');
    }
}

/**
 * 改良版ログアウト処理
 */
function logout() {
    // 出力バッファをクリア
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // セッション開始（まだ開始されていない場合）
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // ログアウトログ記録（開発環境）
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        error_log("[Session] User logout: " . ($_SESSION['username'] ?? 'unknown'));
    }
    
    // セッション変数をすべて削除
    $_SESSION = array();
    
    // セッションクッキーも削除
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // セッション破棄
    session_destroy();
    
    // キャッシュ無効化ヘッダー
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // ログアウト後のリダイレクト
    header('Location: login.php?message=logout');
    exit();
}

/**
 * ログイン確認
 */
function checkLogin() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header('Location: login.php?message=access_denied');
        exit();
    }
    
    // セッションタイムアウトチェック
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 3600) {
        logout();
    }
}

/**
 * 権限確認
 */
function checkRole($allowedRoles) {
    checkLogin(); // まずログイン確認
    
    $userRole = $_SESSION['role'] ?? '';
    if (!in_array($userRole, $allowedRoles)) {
        header('Location: unauthorized.php?message=insufficient_privileges');
        exit();
    }
}

/**
 * 現在のユーザー情報取得
 */
function getCurrentSessionUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'role' => $_SESSION['role'] ?? 'user'
    ];
}

/**
 * ログイン状態確認
 */
function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * セッション情報デバッグ出力
 */
function debugSession() {
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        echo "<div style='background:#e3f2fd;border:1px solid #bbdefb;padding:10px;margin:10px;border-radius:5px;'>";
        echo "<strong>🔧 セッション情報:</strong><br>";
        echo "セッションID: " . session_id() . "<br>";
        echo "ユーザーID: " . ($_SESSION['user_id'] ?? 'なし') . "<br>";
        echo "ユーザー名: " . ($_SESSION['username'] ?? 'なし') . "<br>";
        echo "権限: " . ($_SESSION['role'] ?? 'なし') . "<br>";
        echo "ログイン時刻: " . (isset($_SESSION['login_time']) ? date('Y-m-d H:i:s', $_SESSION['login_time']) : 'なし') . "<br>";
        echo "</div>";
    }
}

// 後方互換性のための関数エイリアス
if (!function_exists('doLogout')) {
    function doLogout() {
        logout();
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin() {
        checkLogin();
    }
}
?>
