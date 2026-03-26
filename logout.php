<?php
require_once 'auth.php';
sessionStart();
session_destroy();
header('Location: login.php');
exit;
