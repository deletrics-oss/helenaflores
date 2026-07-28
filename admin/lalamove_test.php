<?php
require '../includes/db.php';
require '../includes/lalamove.php';

$llm = new LalamoveAPI($pdo);

// Hardcoded store and delivery coords
$from = ['lat' => '-23.543598', 'lng' => '-46.574902'];
$to   = ['lat' => '-23.518155', 'lng' => '-46.537526'];

$quotes = $llm->getAllQuotations($to, 'Rua Teste');

print_r($quotes);
