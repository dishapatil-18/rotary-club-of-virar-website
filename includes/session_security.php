<?php

function secureSessionStart() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure cookie parameters before starting session
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }
}

function regenerateSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function checkSessionTimeout($timeoutMinutes = 30) {
    if (session_status() !== PHP_SESSION_ACTIVE) return;

    $timeout = $timeoutMinutes * 60;

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        $_SESSION = [];
        session_destroy();
        header("Location: " . (isset($_GET['admin']) ? '../login.php' : 'login.php'));
        exit;
    }

    $_SESSION['last_activity'] = time();
}

function destroySession() {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
}
