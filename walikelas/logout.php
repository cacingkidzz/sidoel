<?php
require_once __DIR__ . '/_init.php';

unset($_SESSION['walikelas']);
session_regenerate_id(true);
header('Location: login.php');
exit;
