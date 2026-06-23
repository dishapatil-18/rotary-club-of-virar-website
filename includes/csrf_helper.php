<?php

function generateCsrfToken() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrfField() {
    $token = generateCsrfToken();
    return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
}

function validateCsrfToken() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    if (!isset($_SESSION['_csrf_token']) || !isset($_POST['_csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['_csrf_token'], $_POST['_csrf_token']);
}
