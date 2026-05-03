<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: ../pages/dashboard.php');
        exit;
    }
}

function getCurrentUser() {
    return [
        'id'        => $_SESSION['user_id'] ?? null,
        'username'  => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role'      => $_SESSION['role'] ?? '',
    ];
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function canAccess($roles = []) {
    return in_array($_SESSION['role'] ?? '', $roles);
}

function generateInvoice($prefix = 'INV') {
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function flashMessage($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function formatCurrency($amount) {
    return '৳ ' . number_format($amount, 2);
}

function isExpiringSoon($date, $days = 90) {
    if (!$date) return false;
    return strtotime($date) <= strtotime("+{$days} days");
}

function isExpired($date) {
    if (!$date) return false;
    return strtotime($date) < time();
}
?>
