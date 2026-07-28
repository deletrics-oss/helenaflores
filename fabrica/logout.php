<?php
// catalogo/fabrica/logout.php
session_start();
unset($_SESSION['factory_user_id']);
unset($_SESSION['factory_user_name']);
session_destroy();
header("Location: login.php");
exit;
