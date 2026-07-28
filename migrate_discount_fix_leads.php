<?php
// Setup completo: migração + credenciais Melhor Envio
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Setup</title></head><body style='background:#111;color:#0f0;font-family:monospace;padding:30px'>";
echo "<h2>🔧 Setup Fight Arcade - Migração + Melhor Envio</h2>";

// ===== 1. MIGRATIONS =====
$migrations = [
    "CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    "ALTER TABLE orders ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0",
    "ALTER TABLE orders ADD COLUMN updated_at DATETIME DEFAULT NULL",
    "ALTER TABLE orders ADD COLUMN me_order_id VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE orders ADD COLUMN me_tracking VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE order_items ADD COLUMN variation_id INT NULL",
    "ALTER TABLE users ADD COLUMN complement VARCHAR(255) DEFAULT NULL",
];

foreach ($migrations as $sql) {
    try {
        $pdo->exec($sql);
        echo "<p>✅ " . htmlspecialchars(substr($sql, 0, 80)) . "</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            echo "<p style='color:orange'>⚠️ Já existe</p>";
        } else {
            echo "<p style='color:red'>❌ " . $e->getMessage() . "</p>";
        }
    }
}

// Fix leads
$fixed = $pdo->exec("UPDATE users SET is_lead = 0 WHERE role = 'customer' AND is_lead = 1");
echo "<p>✅ Corrigidos <strong>$fixed</strong> leads → clientes</p>";

// ===== 2. MELHOR ENVIO CREDENTIALS =====
echo "<h2>📦 Salvando Credenciais Melhor Envio</h2>";

$meSettings = [
    'me_client_id' => '24424',
    'me_client_secret' => 'HQ3Zz6cWsOpDRrbOIZ6bFZ2OZl33cqPxB1983aGF',
    'me_redirect_uri' => 'https://www.fightarcade.com.br/catalogo/admin/melhorenvio_callback.php',
    'me_sandbox' => '0', // Production
    'me_from_zipcode' => '79002000',
    'me_access_token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxIiwianRpIjoiMDc5NjgyNDM3M2RkNTQ1YzY0ODdhYTg5Y2E2OGJkYzQ1YjZmZTVkNjIwMWQyNWNmNTg5NmI0YWRlMzNiOTQ2M2I0MzMwMGJjY2M3MWM0NWMiLCJpYXQiOjE3Nzc0NDEwODUuMzY0MjU0LCJuYmYiOjE3Nzc0NDEwODUuMzY0MjU2LCJleHAiOjE4MDg5NzcwODUuMzUxNjYzLCJzdWIiOiI5ZDE4YmJhNi0yMzJhLTQ3NjgtYTcyMi05YmRkZThmOTM0NzgiLCJzY29wZXMiOlsiY2FydC1yZWFkIiwiY2FydC13cml0ZSIsImNvbXBhbmllcy1yZWFkIiwiY29tcGFuaWVzLXdyaXRlIiwiY291cG9ucy1yZWFkIiwiY291cG9ucy13cml0ZSIsIm5vdGlmaWNhdGlvbnMtcmVhZCIsIm9yZGVycy1yZWFkIiwicHJvZHVjdHMtcmVhZCIsInByb2R1Y3RzLWRlc3Ryb3kiLCJwcm9kdWN0cy13cml0ZSIsInB1cmNoYXNlcy1yZWFkIiwic2hpcHBpbmctY2FsY3VsYXRlIiwic2hpcHBpbmctY2FuY2VsIiwic2hpcHBpbmctY2hlY2tvdXQiLCJzaGlwcGluZy1jb21wYW5pZXMiLCJzaGlwcGluZy1nZW5lcmF0ZSIsInNoaXBwaW5nLXByZXZpZXciLCJzaGlwcGluZy1wcmludCIsInNoaXBwaW5nLXNoYXJlIiwic2hpcHBpbmctdHJhY2tpbmciLCJlY29tbWVyY2Utc2hpcHBpbmciLCJ0cmFuc2FjdGlvbnMtcmVhZCIsInVzZXJzLXJlYWQiLCJ1c2Vycy13cml0ZSIsIndlYmhvb2tzLXJlYWQiLCJ3ZWJob29rcy13cml0ZSIsIndlYmhvb2tzLWRlbGV0ZSIsInRkZWFsZXItd2ViaG9vayJdfQ.4Ii6jtN5hCG3pn28NYTyBfJKrV5DspwdgH4HCrnIipLc9TicexKNisBF84-JbF02HNGszrnSTJSAorSoQV6uVKTMqzGCZxGcQDckSH_lNo-Bt3-iKTJ8hme0t9carT5fuQy6A_TWYizoxYlDf6_yroSpHE1RWNd07kSTluYJht8ArhbbgBjG9h2TiPE5AhVKGFou5VuEvdwedTBiFBz8p_5hvQ19GigX0dk4PqIVg9h83JymtAXHh2hSKEfZggwKcmuueltwpbK1u-mLYMQ-cmtWUuFkkfHsNpK4k8hHFBoIYPSXs8gyJRiRvuGwcaXgooJNSi5GKvaHqmoC-iMQiIZiB8JgN_sYM_cModsC80NH52oKD_tgivNa4aYv2tcyLqOAtiWM5RPe0kLbUV0l1DFUN4YOyKSgX629HVvvPjEUE4XDqsoKPD4p1rQFuyjVd6jxgQgyT4_gzYAMYsNpy3JLK9_1fOnhkLesay1pHanicWCD9-pCILP2Srgpzwqjx6s7moSNHvE3yrGDEQbnGHoARv-GcEtDS4i6SdMzWv-PGGYViaVMBpxUUU05PJFQC92Nx9dzkUzjY4bMAB0SR0IWR0GBoMsUyipTC9pkpah65aVotCWIVNappG5kkzLvNDI7cwoOXWaUpcHa-2tYimWwnwXZIdehnuIGaqRz_9g',
    'me_token_expires' => '2027-06-25 00:00:00',
];

foreach ($meSettings as $key => $val) {
    try {
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$key, $val, $val]);
        $display = ($key === 'me_access_token') ? substr($val, 0, 30) . '...' : $val;
        echo "<p>✅ $key = <strong>$display</strong></p>";
    } catch (Exception $e) {
        echo "<p style='color:red'>❌ $key: " . $e->getMessage() . "</p>";
    }
}

echo "<br><br>";
echo "<a href='admin/melhorenvio.php' style='background:#e74c3c;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:bold'>📦 Ir para Melhor Envio</a> ";
echo "<a href='admin/dashboard.php' style='background:#f1c40f;color:#000;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:bold'>📊 Dashboard</a>";
echo "</body></html>";
?>
