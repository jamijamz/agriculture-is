<?php
session_start();

// If the user is already logged in, redirect them straight to the dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - AgriInfo</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="css/style.css?v=2.0" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">

<div class="container text-center">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 p-5 rounded-4 bg-white">
                <div class="mb-3">
                    <span class="display-4">🌱</span>
                </div>
                <h1 class="fw-bold text-custom-title mb-2">AgriInfo</h1>
                <p class="text-muted mb-4">Agricultural Information & Farm Management System</p>
                <hr class="text-muted opacity-25 mb-4">
                <div class="d-grid gap-2">
                    <a href="login.php" class="btn btn-custom rounded-pill py-2 fw-semibold">Login to System</a>
                </div>
                <div class="mt-4 text-muted small">
                    <span>Secure Database-Driven Platform</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>