<?php
$ch = curl_init("http://localhost/catalogo/admin/api_chat.php?action=list_chats");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// bypass auth for local test by passing a fake session?
// Actually I can't easily bypass auth if it has isAdmin().
// Let me just read php_error_log.
