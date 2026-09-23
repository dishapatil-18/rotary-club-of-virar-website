<?php
/**
 * Shared helper functions — single source of truth.
 *
 * Included via require_once across frontend and admin pages.
 * All functions use function_exists() guards to prevent redeclaration.
 */

if (!function_exists('e')) {
    function e($s) {
        return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    }
}
