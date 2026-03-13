<?php
/**
 * includes/csrf.php
 * Minimal CSRF token helpers (requires an active session).
 */

declare(strict_types=1);

/** Returns the current CSRF token, generating it on first call. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** Renders a hidden CSRF input field. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
         . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Verifies the submitted CSRF token.
 * Terminates with 403 on mismatch.
 */
function csrf_verify(): void
{
    $submitted = (string) ($_POST['csrf_token'] ?? '');
    $stored    = (string) ($_SESSION['csrf_token'] ?? '');

    if ($stored === '' || !hash_equals($stored, $submitted)) {
        http_response_code(403);
        exit('Ungültiger CSRF-Token. Bitte die Seite neu laden.');
    }
}
