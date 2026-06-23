<?php
require_once __DIR__ . '/../includes/session_security.php';
secureSessionStart();
destroySession();
header("Location: ../index.php");
exit;
