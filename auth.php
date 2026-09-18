<?php
/**
 * Shared session and role guard for every protected page.
 *
 * The project has two customer-facing entry points:
 *   /test/index.php        customer marketplace
 *   /test/portal/index.php staff portal selector
 *
 * Redirects are built from the current script location so this works from
 * the project root as well as from the customer and staff subdirectories.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

function swapy_project_root_url(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $directory = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

    foreach (['/customer', '/AdminCM', '/delivery', '/SystemAdmin', '/CustSupport', '/portal'] as $segment) {
        if (substr($directory, -strlen($segment)) === $segment) {
            $directory = substr($directory, 0, -strlen($segment));
            break;
        }
    }

    return $directory === '/' ? '' : $directory;
}

function swapy_redirect_for_role(int $role): void
{
    $root = swapy_project_root_url();
    $destination = $role === 0 ? 'index.php' : 'portal/index.php';
    header('Location: ' . $root . '/' . $destination);
    exit();
}

function protect_page(int $requiredRole): void
{
    if (empty($_SESSION['swapy_session']) || !isset($_SESSION['role'])) {
        $root = swapy_project_root_url();
        header('Location: ' . $root . '/login.php');
        exit();
    }

    $currentRole = (int) $_SESSION['role'];
    if ($currentRole !== $requiredRole) {
        swapy_redirect_for_role($currentRole);
    }
}