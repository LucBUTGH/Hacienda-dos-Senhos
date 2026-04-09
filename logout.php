<?php
require_once 'auth.php';
sessionStart();
$_SESSION = [];
session_destroy();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Location: login.php');
exit;
