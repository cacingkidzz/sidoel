<?php
require_once __DIR__ . '/_init.php';
portal_logout();
header('Location: login.php?logout=1');
exit;
