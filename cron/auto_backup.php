<?php
// 自動バックアップ実行スクリプト
// crontabで定期実行: 0 2 * * * /usr/bin/php /path/to/cron/auto_backup.php

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // 自動バックアップ設定確認
    $query = "SELECT setting_value FROM settings WHERE setting_key = 'auto_backup_enabled'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $auto_backup_enabled = $stmt->fetchColumn();
    
    if (!$auto_backup_enabled) {
        echo "自動バックアップは無効です。\n";
        exit();
    }
    
    // バックアップ実行
    $backup_dir = '../backups/';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    $filename = 'backup_auto_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backup_dir . $filename;
    
    // データベース情報取得
    $config = require '../config/config.php';
    
    $command = sprintf(
        'mysqldump --user=%s --password=%s --host=%s %s > %s 2>&1',
        escapeshellarg($config['db_user']),
        escapeshellarg($config['db_pass']),
        escapeshellarg($config['db_host']),
        escapeshellarg($config['db_name']),
        escapeshellarg($filepath)
    );
    
    exec($command, $output, $return_code);
    
    if ($return_code === 0) {
        echo "バックアップが正常に作成されました: $filename\n";
        
        // 古いバックアップファイルの削除
        $query = "SELECT setting_value FROM settings WHERE setting_key = 'backup_retention_days'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $retention_days = $stmt->fetchColumn() ?: 30;
        
        $cutoff_time = time() - ($retention_days * 24 * 60 * 60);
        $files = glob($backup_dir . 'backup_*.sql');
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
                echo "古いバックアップファイルを削除しました: " . basename($file) . "\n";
            }
        }
        
    } else {
        echo "バックアップの作成に失敗しました。\n";
        echo "エラー出力: " . implode("\n", $output) . "\n";
    }
    
} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
}
?>
