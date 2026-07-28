<?php
// OAuth2 Callback for Melhor Envio
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/melhorenvio.php';
isAdmin();

if (isset($_GET['code'])) {
    $me = new MelhorEnvioAPI($pdo);
    if ($me->exchangeCode($_GET['code'])) {
        header("Location: melhorenvio.php?msg=connected");
    } else {
        header("Location: melhorenvio.php?error=token_fail");
    }
    exit;
}

header("Location: melhorenvio.php?error=no_code");
exit;
