<?php
require_once __DIR__ . '/../includes/db.php';
try {
    \ = \->query("SHOW CREATE TABLE users");
    print_r(\->fetch());
} catch(Exception \) {
    echo \->getMessage();
}
