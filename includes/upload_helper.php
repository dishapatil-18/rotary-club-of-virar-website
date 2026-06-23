<?php

function validateUpload($file, $allowedExtensions = [], $maxSize = 2097152) {
    $errors = [];

    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['valid' => true, 'errors' => []];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'errors' => ['Upload failed with error code ' . $file['error']]];
    }

    if ($file['size'] > $maxSize) {
        $maxMb = $maxSize / 1048576;
        return ['valid' => false, 'errors' => ["File exceeds maximum size of {$maxMb}MB"]];
    }

    if (!empty($allowedExtensions)) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions)) {
            return ['valid' => false, 'errors' => ['File type not allowed. Allowed: ' . implode(', ', $allowedExtensions)]];
        }
    }

    return ['valid' => true, 'errors' => []];
}

function secureUploadPath($relativeDir) {
    $base = __DIR__ . '/../' . ltrim($relativeDir, '/');
    $base = realpath($base) ?: realpath(__DIR__ . '/../') . '/' . ltrim($relativeDir, '/');

    // Ensure directory exists
    if (!is_dir($base)) {
        mkdir($base, 0755, true);
    }

    // Prevent path traversal
    $base = str_replace('\\', '/', $base);

    return $base;
}
