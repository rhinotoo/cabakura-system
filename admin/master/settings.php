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

// 設定更新
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $settings = [
            'seat_charge' => $_POST['seat_charge'] ?? 0,
            'extension_fee' => $_POST['extension_fee'] ?? 0,
            'cast_commission_rate' => $_POST['cast_commission_rate'] ?? 0,
            'staff_commission_rate' => $_POST['staff_commission_rate'] ?? 0,
            'tax_rate' => $_POST['tax_rate'] ?? 0,
            'service_charge_rate' => $_POST['service_charge_rate'] ?? 0,
            'store_name' => $_POST['store_name'] ?? '',
            'store_phone' => $_POST['store_phone'] ?? '',
            'store_address' => $_POST['store_address'] ?? '',
            'opening_time' => $_POST['opening_time'] ?? '',
            'closing_time' => $_POST['closing_time'] ?? ''
        ];
        
        foreach ($settings as $key => $value) {
            $query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                      ON DUPLICATE KEY UPDATE setting_value = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$key, $value, $value]);
        }
        
        $message = '設定を更新しました。';
    } catch (Exception $e) {
        $error = 'エラーが発生しました: ' . $e->getMessage();
    }
}

// 現在の設定取得
$query = "SELECT setting_key, setting_value FROM settings";
$stmt = $db->prepare($query);
$stmt->execute();
$settings_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// デフォルト値設定
$default_settings = [
    'seat_charge' => 3000,
    'extension_fee' => 1000,
    'cast_commission_rate' => 40,
    'staff_commission_rate' => 10,
    'tax_rate' => 10,
    'service_charge_rate' => 10,
    'store_name' => 'キャバクラ管理システム',
    'store_phone' => '',
    'store_address' => '',
    'opening_time' => '20:00',
    'closing_time' => '02:00'
];

$settings = array_merge($default_settings, $settings_data);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>システム設定 - キャバクラ管理システム</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/style.css">
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .settings-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
        }
        .settings-section h3 {
            margin-bottom: 20px;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>⚙️ システム設定</h1>
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
            
            <form method="POST">
                <div class="settings-grid">
                    <!-- 料金設定 -->
                    <div class="settings-section">
                        <h3>💰 料金設定</h3>
                        
                        <div class="form-group">
                            <label for="seat_charge">席料 (円)</label>
                            <input type="number" id="seat_charge" name="seat_charge" 
                                   value="<?php echo htmlspecialchars($settings['seat_charge']); ?>" 
                                   class="form-control" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="extension_fee">延長料金 (円/時間)</label>
                            <input type="number" id="extension_fee" name="extension_fee" 
                                   value="<?php echo htmlspecialchars($settings['extension_fee']); ?>" 
                                   class="form-control" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="tax_rate">消費税率 (%)</label>
                            <input type="number" id="tax_rate" name="tax_rate" 
                                   value="<?php echo htmlspecialchars($settings['tax_rate']); ?>" 
                                   class="form-control" min="0" max="100" step="0.1">
                        </div>
                        
                        <div class="form-group">
                            <label for="service_charge_rate">サービス料率 (%)</label>
                            <input type="number" id="service_charge_rate" name="service_charge_rate" 
                                   value="<?php echo htmlspecialchars($settings['service_charge_rate']); ?>" 
                                   class="form-control" min="0" max="100" step="0.1">
                        </div>
                    </div>
                    
                    <!-- 分配設定 -->
                    <div class="settings-section">
                        <h3>📊 分配設定</h3>
                        
                        <div class="form-group">
                            <label for="cast_commission_rate">キャスト分配率 (%)</label>
                            <input type="number" id="cast_commission_rate" name="cast_commission_rate" 
                                   value="<?php echo htmlspecialchars($settings['cast_commission_rate']); ?>" 
                                   class="form-control" min="0" max="100" step="0.1">
                        </div>
                        
                        <div class="form-group">
                            <label for="staff_commission_rate">スタッフ分配率 (%)</label>
                            <input type="number" id="staff_commission_rate" name="staff_commission_rate" 
                                   value="<?php echo htmlspecialchars($settings['staff_commission_rate']); ?>" 
                                   class="form-control" min="0" max="100" step="0.1">
                        </div>
                        
                        <div style="background: #e8f4f8; padding: 15px; border-radius: 5px; margin-top: 15px;">
                            <h4>分配計算例</h4>
                            <p>売上: ¥10,000の場合</p>
                            <ul>
                                <li>キャスト: ¥<?php echo number_format(10000 * ($settings['cast_commission_rate'] / 100)); ?></li>
                                <li>スタッフ: ¥<?php echo number_format(10000 * ($settings['staff_commission_rate'] / 100)); ?></li>
                                <li>店舗: ¥<?php echo number_format(10000 * (1 - ($settings['cast_commission_rate'] + $settings['staff_commission_rate']) / 100)); ?></li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- 店舗情報 -->
                    <div class="settings-section">
                        <h3>🏪 店舗情報</h3>
                        
                        <div class="form-group">
                            <label for="store_name">店舗名</label>
                            <input type="text" id="store_name" name="store_name" 
                                   value="<?php echo htmlspecialchars($settings['store_name']); ?>" 
                                   class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="store_phone">電話番号</label>
                            <input type="text" id="store_phone" name="store_phone" 
                                   value="<?php echo htmlspecialchars($settings['store_phone']); ?>" 
                                   class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="store_address">住所</label>
                            <textarea id="store_address" name="store_address" 
                                      class="form-control" rows="3"><?php echo htmlspecialchars($settings['store_address']); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- 営業時間 -->
                    <div class="settings-section">
                        <h3>🕐 営業時間</h3>
                        
                        <div class="form-group">
                            <label for="opening_time">開店時刻</label>
                            <input type="time" id="opening_time" name="opening_time" 
                                   value="<?php echo htmlspecialchars($settings['opening_time']); ?>" 
                                   class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="closing_time">閉店時刻</label>
                            <input type="time" id="closing_time" name="closing_time" 
                                   value="<?php echo htmlspecialchars($settings['closing_time']); ?>" 
                                   class="form-control">
                        </div>
                        
                        <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-top: 15px;">
                            <strong>注意:</strong> 深夜営業の場合、閉店時刻が開店時刻より早い時間になります。
                        </div>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary" style="padding: 15px 30px; font-size: 16px;">
                        💾 設定を保存
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
