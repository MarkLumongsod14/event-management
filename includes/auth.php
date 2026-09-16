<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Authentication required']);
        } else {
            header('Location: login.php');
        }
        exit;
    }
}

function currentUser() {
    return $_SESSION['user'] ?? null;
}

function hasRole(...$roles) {
    return isLoggedIn()
        && isset($_SESSION['user']['role'])
        && in_array($_SESSION['user']['role'], $roles, true);
}

function requireRole(...$roles) {
    requireLogin();
    if (!hasRole(...$roles)) {
        http_response_code(403);
        die(json_encode(['error' => 'Forbidden: insufficient permissions']));
    }
}

// Permission matrix
function canCreate() { return hasRole('superadmin', 'admin'); }
function canApprove() { return hasRole('superadmin', 'admin'); }
function canEdit()   { return hasRole('superadmin', 'admin', 'staff'); }
function canDelete() { return hasRole('superadmin', 'admin'); }
function canView()   { return isLoggedIn(); }