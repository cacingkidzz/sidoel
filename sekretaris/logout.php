<?php
require_once __DIR__ . '/_init.php';
unset($_SESSION['sekretaris']);
session_destroy();
header('Location: login.php');
exit;
