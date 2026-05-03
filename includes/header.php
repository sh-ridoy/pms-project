<?php
require_once __DIR__ . '/auth.php';
$user = getCurrentUser();
$flash = getFlash();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaCare PMS - <?= ucfirst($currentPage) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="bi bi-capsule-pill"></i></div>
        <div>
            <div class="brand-name">PharmaCare</div>
            <div class="brand-sub">PMS v1.0</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">MAIN</div>
        <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
        </a>
        <a href="pos.php" class="nav-link <?= $currentPage === 'pos' ? 'active' : '' ?>">
            <i class="bi bi-cart3"></i><span>Point of Sale</span>
        </a>

        <div class="nav-section-label">INVENTORY</div>
        <a href="medicines.php" class="nav-link <?= $currentPage === 'medicines' ? 'active' : '' ?>">
            <i class="bi bi-capsule"></i><span>Medicines</span>
        </a>
        <a href="categories.php" class="nav-link <?= $currentPage === 'categories' ? 'active' : '' ?>">
            <i class="bi bi-tags-fill"></i><span>Categories</span>
        </a>
        <a href="suppliers.php" class="nav-link <?= $currentPage === 'suppliers' ? 'active' : '' ?>">
            <i class="bi bi-truck"></i><span>Suppliers</span>
        </a>
        <a href="purchases.php" class="nav-link <?= $currentPage === 'purchases' ? 'active' : '' ?>">
            <i class="bi bi-bag-check-fill"></i><span>Purchases</span>
        </a>

        <div class="nav-section-label">SALES</div>
        <a href="sales.php" class="nav-link <?= $currentPage === 'sales' ? 'active' : '' ?>">
            <i class="bi bi-receipt-cutoff"></i><span>Sales History</span>
        </a>
        <a href="customers.php" class="nav-link <?= $currentPage === 'customers' ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i><span>Customers</span>
        </a>

        <div class="nav-section-label">REPORTS</div>
        <a href="reports.php" class="nav-link <?= $currentPage === 'reports' ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-fill"></i><span>Reports</span>
        </a>
        <a href="expiry.php" class="nav-link <?= $currentPage === 'expiry' ? 'active' : '' ?>">
            <i class="bi bi-exclamation-triangle-fill"></i><span>Expiry Alert</span>
        </a>

        <?php if (hasRole('admin')): ?>
        <div class="nav-section-label">ADMIN</div>
        <a href="users.php" class="nav-link <?= $currentPage === 'users' ? 'active' : '' ?>">
            <i class="bi bi-person-badge-fill"></i><span>Users</span>
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
            <div>
                <div class="user-name"><?= htmlspecialchars($user['full_name']) ?></div>
                <div class="user-role"><?= ucfirst($user['role']) ?></div>
            </div>
        </div>
        <a href="../logout.php" class="btn-logout" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</div>

<!-- Main Content -->
<div class="main-content" id="mainContent">

    <!-- Top Bar -->
    <div class="topbar">
        <button class="btn-toggle-sidebar" id="toggleSidebar">
            <i class="bi bi-list"></i>
        </button>
        <div class="topbar-title"><?= ucfirst(str_replace('_', ' ', $currentPage)) ?></div>
        <div class="topbar-right">
            <span class="badge-role"><?= ucfirst($user['role']) ?></span>
            <span class="topbar-time" id="liveClock"></span>
        </div>
    </div>

    <div class="page-body">
        <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'danger' : 'info') ?> alert-dismissible fade show custom-alert" role="alert">
            <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill' ?> me-2"></i>
            <?= htmlspecialchars($flash['msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
