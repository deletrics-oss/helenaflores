<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

echo "<h1>Migração de Endereços Antigos</h1>";

// 1. Fetch Users with addresses in the old table
// Only fetch if they don't already have an entry in user_addresses? 
// Or just migrate everything that has address info.

$users = $pdo->query("SELECT * FROM users WHERE address IS NOT NULL AND address != ''")->fetchAll(PDO::FETCH_ASSOC);

$count = 0;
foreach ($users as $u) {
    // Check if user already has addresses in new table
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ?");
    $stmtCheck->execute([$u['id']]);
    $hasNew = $stmtCheck->fetchColumn();

    if ($hasNew == 0) {
        // Migrate
        $stmtInsert = $pdo->prepare("INSERT INTO user_addresses 
        (user_id, name, recipient_name, zipcode, address, number, complement, neighborhood, city, state, is_default)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"); // Make it default

        $name = "Principal (Migrado)";
        // Use user's name as recipient if not stored separately
        $recipient = $u['name'];

        $stmtInsert->execute([
            $u['id'],
            $name,
            $recipient,
            $u['zipcode'] ?? '',
            $u['address'],
            $u['number'] ?? 'S/N',
            $u['complement'] ?? '',
            $u['neighborhood'] ?? '',
            $u['city'] ?? '',
            $u['state'] ?? '',
        ]);

        echo "Migrado: <b>{$u['name']}</b> ({$u['address']})<br>";
        $count++;
    } else {
        echo "Ignorado (já tem endereços novos): {$u['name']}<br>";
    }
}

echo "<hr><h3>Migração Concluída. $count endereços movidos.</h3>";
echo "<a href='my-addresses.php'>Ir para Meus Endereços</a>";
?>