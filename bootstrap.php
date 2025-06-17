<?php
/**
 * アプリケーション初期化ファイル
 * パス: /bootstrap.php
 */

// 重複実行防止
if (defined('BOOTSTRAP_LOADED')) {
    return;
}
define('BOOTSTRAP_LOADED', true);

// エラーレポート設定
if (!defined('ENVIRONMENT')) {
    // 環境判定
    if (strpos($_SERVER['HTTP_HOST'] ?? '', '.xsrv.jp') !== false) {
        define('ENVIRONMENT', 'production');
    } else {
        define('ENVIRONMENT', 'development');
    }
}

// 開発環境でのエラー表示
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// 文字エンコーディング設定
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// セッション設定
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 3600, // 1時間
        'cookie_secure' => ENVIRONMENT === 'production',
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
        'use_only_cookies' => true
    ]);
}

// オートローダー（将来のクラス用）
spl_autoload_register(function ($class) {
    $directories = [
        __DIR__ . '/src/models/',
        __DIR__ . '/src/controllers/',
        __DIR__ . '/src/utils/'
    ];
    
    foreach ($directories as $directory) {
        $file = $directory . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// 設定ファイルの読み込み
$config_file = __DIR__ . '/config/database.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    // database.phpが存在しない場合の基本設定
    define('SERVER_ID', 'your_server_id');
    define('DB_NAME', SERVER_ID . '_cabakura');
    
    /**
     * 環境判定
     */
    function getEnvironment() {
        return ENVIRONMENT;
    }
    
    /**
     * 環境名取得
     */
    function getEnvironmentName() {
        return ENVIRONMENT === 'production' ? '本番環境' : '開発環境';
    }
    
    /**
     * データベース接続
     */
    function getDatabaseConnection() {
        static $pdo = null;
        
        if ($pdo !== null) {
            return $pdo;
        }
        
        $env = getEnvironment();
        
        if ($env === 'production') {
            // 本番環境設定
            $config = [
                'host' => 'mysql**.xserver.jp',
                'database' => DB_NAME,
                'username' => SERVER_ID . '_user',
                'password' => 'your_production_password',
            ];
        } else {
            // 開発環境設定
            $config = [
                'host' => 'localhost',
                'database' => DB_NAME,
                'username' => 'root',
                'password' => '',
            ];
        }
        
        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
            return $pdo;
            
        } catch (PDOException $e) {
            throw new Exception("データベース接続失敗: " . $e->getMessage());
        }
    }
    
    /**
     * データベースエラーログ
     */
    function logDatabaseError($e, $context = '') {
        $message = "[DB Error] {$context}: " . $e->getMessage();
        error_log($message);
        
        if (getEnvironment() === 'development') {
            echo "<div style='background:#f8d7da;color:#721c24;padding:10px;margin:10px;border-radius:5px;'>";
            echo "<strong>Database Error:</strong> " . htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    }
}

// ===== 共通関数の定義 =====

/**
 * リダイレクト処理
 * @param string $url リダイレクト先URL
 */
function redirect($url) {
    // 出力バッファをクリア
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // キャッシュ無効化ヘッダー
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // リダイレクト
    header("Location: {$url}");
    exit;
}

/**
 * ログイン状態確認
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * ログイン必須ページの認証
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php?message=access_denied');
    }
    
    // セッションタイムアウトチェック（1時間）
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 3600) {
        logout();
    }
}

/**
 * 現在のユーザー情報取得
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'role' => $_SESSION['role'] ?? 'user'
    ];
}

/**
 * 安全なログアウト処理
 */
function logout() {
    // セッション開始（まだ開始されていない場合）
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
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
    
    // ログアウト後のリダイレクト
    redirect('login.php?message=logout');
}

/**
 * ログアウトリンク用の関数
 */
function getLogoutUrl() {
    return $_SERVER['PHP_SELF'] . '?action=logout';
}

/**
 * CSRFトークン生成
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRFトークン検証
 * @param string $token
 * @return bool
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * HTMLエスケープ
 * @param string $string
 * @return string
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * 配列から安全に値を取得
 * @param array $array
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function getValue($array, $key, $default = '') {
    return isset($array[$key]) ? $array[$key] : $default;
}

/**
 * POSTデータから安全に値を取得
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function getPost($key, $default = '') {
    return getValue($_POST, $key, $default);
}

/**
 * GETデータから安全に値を取得
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function getGet($key, $default = '') {
    return getValue($_GET, $key, $default);
}

/**
 * ユーザーの権限確認
 * @param string $required_role
 * @return bool
 */
function hasRole($required_role) {
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }
    
    $roles = ['staff' => 1, 'manager' => 2, 'admin' => 3];
    $user_level = $roles[$user['role']] ?? 0;
    $required_level = $roles[$required_role] ?? 999;
    
    return $user_level >= $required_level;
}

/**
 * 管理者権限必須
 */
function requireAdmin() {
    if (!hasRole('admin')) {
        redirect('login.php?message=access_denied');
    }
}

/**
 * マネージャー権限必須
 */
function requireManager() {
    if (!hasRole('manager')) {
        redirect('login.php?message=access_denied');
    }
}

/**
 * フラッシュメッセージ設定
 * @param string $type
 * @param string $message
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * フラッシュメッセージ取得
 * @return array
 */
function getFlashMessages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * 成功メッセージ設定
 * @param string $message
 */
function setSuccessMessage($message) {
    setFlashMessage('success', $message);
}

/**
 * エラーメッセージ設定
 * @param string $message
 */
function setErrorMessage($message) {
    setFlashMessage('error', $message);
}

/**
 * 情報メッセージ設定
 * @param string $message
 */
function setInfoMessage($message) {
    setFlashMessage('info', $message);
}

/**
 * 警告メッセージ設定
 * @param string $message
 */
function setWarningMessage($message) {
    setFlashMessage('warning', $message);
}

/**
 * フラッシュメッセージHTML出力
 * @return string
 */
function displayFlashMessages() {
    $messages = getFlashMessages();
    $html = '';
    
    foreach ($messages as $flash) {
        $type = $flash['type'];
        $message = h($flash['message']);
        $icon = [
            'success' => '✅',
            'error' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️'
        ][$type] ?? 'ℹ️';
        
        $html .= "<div class='alert alert-{$type}'>{$icon} {$message}</div>";
    }
    
    return $html;
}

/**
 * ページネーション計算
 * @param int $total_records
 * @param int $records_per_page
 * @param int $current_page
 * @return array
 */
function calculatePagination($total_records, $records_per_page = 20, $current_page = 1) {
    $total_pages = ceil($total_records / $records_per_page);
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $records_per_page;
    
    return [
        'total_records' => $total_records,
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'records_per_page' => $records_per_page,
        'offset' => $offset,
        'has_previous' => $current_page > 1,
        'has_next' => $current_page < $total_pages,
        'previous_page' => $current_page - 1,
        'next_page' => $current_page + 1
    ];
}

/**
 * ファイルアップロード処理
 * @param array $file $_FILES配列の要素
 * @param string $upload_dir アップロード先ディレクトリ
 * @param array $allowed_types 許可するファイルタイプ
 * @param int $max_size 最大ファイルサイズ（バイト）
 * @return array
 */
function uploadFile($file, $upload_dir = 'uploads/', $allowed_types = ['jpg', 'jpeg', 'png', 'gif'], $max_size = 2097152) {
    $result = ['success' => false, 'message' => '', 'filename' => ''];
    
    // ファイルがアップロードされているかチェック
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        $result['message'] = 'ファイルのアップロードに失敗しました。';
        return $result;
    }
    
    // ファイルサイズチェック
    if ($file['size'] > $max_size) {
        $result['message'] = 'ファイルサイズが大きすぎます。';
        return $result;
    }
    
    // ファイル拡張子チェック
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_types)) {
        $result['message'] = '許可されていないファイル形式です。';
        return $result;
    }
    
    // アップロードディレクトリ作成
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // ファイル名生成（重複回避）
    $filename = uniqid() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // ファイル移動
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $result['success'] = true;
        $result['filename'] = $filename;
        $result['message'] = 'ファイルのアップロードが完了しました。';
    } else {
        $result['message'] = 'ファイルの保存に失敗しました。';
    }
    
    return $result;
}

/**
 * デバッグ情報出力（開発環境のみ）
 * @param mixed $data
 * @param string $label
 */
function debug($data, $label = 'Debug') {
    if (getEnvironment() === 'development') {
        echo "<div style='background:#f8f9fa;border:1px solid #dee2e6;padding:10px;margin:10px 0;border-radius:5px;'>";
        echo "<strong>{$label}:</strong><br>";
        echo "<pre>" . print_r($data, true) . "</pre>";
        echo "</div>";
    }
}

/**
 * アプリケーション情報取得
 * @return array
 */
function getAppInfo() {
    return [
        'name' => 'Cabakura Management System',
        'version' => '1.0.0',
        'environment' => getEnvironmentName(),
        'database' => DB_NAME,
        'php_version' => PHP_VERSION,
        'session_id' => session_id(),
        'server_time' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get()
    ];
}

// アプリケーション初期化完了ログ
if (ENVIRONMENT === 'development') {
    error_log("[Bootstrap] Application initialized successfully in " . ENVIRONMENT . " mode");
}

// 初期化完了フラグ
define('APP_INITIALIZED', true);
?>
