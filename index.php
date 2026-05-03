<?php
session_start();
require_once 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: pages/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
            header('Location: pages/dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaCare PMS - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a6b3c;
            --accent: #f0a500;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0d3d22 0%, #1a6b3c 50%, #256d45 100%);
            display: flex; align-items: center; justify-content: center;
            position: relative; overflow: hidden;
        }
        body::before {
            content: '';
            position: absolute; inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .login-wrap {
            width: 420px; z-index: 1;
        }
        .login-card {
            background: #fff;
            border-radius: 20px;
            padding: 40px 36px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.3);
        }
        .logo-area { text-align: center; margin-bottom: 28px; }
        .logo-icon {
            width: 64px; height: 64px;
            background: var(--accent);
            border-radius: 16px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 30px; color: #fff; margin-bottom: 12px;
        }
        .logo-title {
            font-family: 'Space Mono', monospace;
            font-size: 22px; font-weight: 700;
            color: var(--primary);
        }
        .logo-sub { font-size: 13px; color: #6b7280; margin-top: 2px; }
        .form-label { font-size: 13px; font-weight: 600; color: #374151; }
        .form-control {
            border: 1.5px solid #e5e7eb;
            border-radius: 10px; padding: 10px 14px;
            font-size: 14px; transition: all .2s;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26,107,60,.12);
        }
        .input-group-text {
            border: 1.5px solid #e5e7eb; border-right: none;
            background: #f9fafb; color: #6b7280;
            border-radius: 10px 0 0 10px;
        }
        .input-group .form-control { border-radius: 0 10px 10px 0; border-left: none; }
        .btn-login {
            background: var(--primary); color: #fff;
            border: none; border-radius: 10px;
            padding: 11px; font-size: 15px; font-weight: 700;
            width: 100%; cursor: pointer; transition: all .2s;
            letter-spacing: .3px;
        }
        .btn-login:hover { background: #124d2c; transform: translateY(-1px); }
        .demo-creds {
            background: #f0f4f8; border-radius: 10px;
            padding: 12px 16px; margin-top: 20px;
            font-size: 12px; color: #4b5563;
        }
        .demo-creds strong { color: var(--primary); }
        .credit { text-align: center; margin-top: 20px; font-size: 12px; color: rgba(255,255,255,.6); }
        .credit a { color: rgba(255,255,255,.9); font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="logo-area">
            <div class="logo-icon"><i class="bi bi-capsule-pill"></i></div>
            <div class="logo-title">PharmaCare PMS</div>
            <div class="logo-sub">Pharmacy Management System</div>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger rounded-3 py-2 px-3 mb-3" style="font-size:13px;">
            <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="Enter username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                </div>
            </div>
            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
            </button>
        </form>

        <div class="demo-creds">
            <div class="mb-1"><i class="bi bi-info-circle me-1"></i> <strong>Demo Credentials</strong></div>
            <div>Admin: <strong>admin</strong> / <strong>password</strong></div>
            <div>Pharmacist: <strong>pharmacist1</strong> / <strong>password</strong></div>
            <div>Cashier: <strong>cashier1</strong> / <strong>password</strong></div>
        </div>
    </div>

    <div class="credit">
        Developed by <a href="#">Md Shamim Hossain Ridoy</a> &mdash; Developer Portfolio &copy; <?= date('Y') ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
