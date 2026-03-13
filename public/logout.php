<?php
/**
 * public/logout.php
 * Destroys the session and redirects to login.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

auth_logout();

header('Location: /login.php');
exit;
