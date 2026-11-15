<?php
session_start();
require_once __DIR__ . '/config/Database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $database = new Database();
        $db = $database->getConnection();

        $stmt = $db->prepare("SELECT user_id, username, password_hash, role FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        // Direct plain-text comparison matching our database seed
        if ($user && $password === $user['password_hash']) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AgriInfo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #fff0f3 0%, #ffccd5 100%);
            height: 100vh;
        }
        .card {
            border: none;
            border-radius: 1rem;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
        }
        .card-header {
            background-color: #ff85a1 !important;
            border-top-left-radius: 1rem !important;
            border-top-right-radius: 1rem !important;
            padding: 1.5rem;
        }
        .btn-custom {
            background-color: #ff708f;
            border-color: #ff708f;
            color: #fff;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            background-color: #ff4d73;
            border-color: #ff4d73;
            color: #fff;
            transform: translateY(-1px);
        }
        .form-control:focus {
            border-color: #ff85a1;
            box-shadow: 0 0 0 0.25rem rgba(255, 133, 161, 0.25);
        }
        .form-label {
            font-weight: 500;
            color: #4a4a4a;
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card shadow-lg">
                <div class="card-header text-white text-center">
                    <h3 class="mb-0 fw-bold">🌱 AgriInfo</h3>
                    <p class="small text-white-50 mb-0 mt-1">Agriculture Information System</p>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small text-center"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" placeholder="Enter your username" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                        </div>
                        <button type="submit" class="btn btn-custom w-100 py-2 shadow-sm">Sign In</button>
                    </form>
                    <div class="text-center mt-3">
                        <small class="text-muted">Demo: <code>admin</code> / <code>admin123</code></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>