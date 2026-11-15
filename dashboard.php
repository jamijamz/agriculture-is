<?php
require_once __DIR__ . '/includes/auth.php';
require_login(); // Protects the dashboard page using the auth helper

require_once __DIR__ . '/config/Database.php';

$database = new Database();
$db = $database->getConnection();

$totalFarmers = 0;
$totalFarms = 0;
$totalCrops = 0;
$totalPlantings = 0;
$totalHarvests = 0;
$totalHarvestedQuantity = 0;
$statusCounts = ['Growing' => 0, 'Harvested' => 0];

$recentPlantings = [];
$recentHarvests = [];

try {
    // Summary Counts
    $totalFarmers = $db->query("SELECT COUNT(*) FROM farmer")->fetchColumn();
    $totalFarms = $db->query("SELECT COUNT(*) FROM farm")->fetchColumn();
    $totalCrops = $db->query("SELECT COUNT(*) FROM crop")->fetchColumn();
    $totalPlantings = $db->query("SELECT COUNT(*) FROM planting")->fetchColumn();
    $totalHarvests = $db->query("SELECT COUNT(*) FROM harvest")->fetchColumn();

    // Total Harvested Quantity
    $totalHarvestedQuantity = $db->query("SELECT COALESCE(SUM(quantity_kg), 0) FROM harvest")->fetchColumn();

    // Planting Status Breakdown
    $statusStmt = $db->query("SELECT status, COUNT(*) as cnt FROM planting GROUP BY status");
    while ($row = $statusStmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($statusCounts[$row['status']])) {
            $statusCounts[$row['status']] = (int)$row['cnt'];
        }
    }

    // Recent Plantings
    $recentPlantingsStmt = $db->query("SELECT p.*, f.farm_name, c.crop_name, c.variety FROM planting p LEFT JOIN farm f ON p.farm_id = f.farm_id LEFT JOIN crop c ON p.crop_id = c.crop_id ORDER BY p.planting_id DESC LIMIT 5");
    $recentPlantings = $recentPlantingsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent Harvests
    $recentHarvestsStmt = $db->query("SELECT h.*, f.farm_name, c.crop_name, c.variety FROM harvest h LEFT JOIN planting p ON h.planting_id = p.planting_id LEFT JOIN farm f ON p.farm_id = f.farm_id LEFT JOIN crop c ON p.crop_id = c.crop_id ORDER BY h.harvest_id DESC LIMIT 5");
    $recentHarvests = $recentHarvestsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error loading dashboard metrics: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AgriInfo</title>
    <!-- Using stable Bootstrap CDN to guarantee proper layout rendering -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="css/style.css?v=2.0" rel="stylesheet">
</head>
<body>

<!-- Navbar Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">🌱 AgriInfo</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link active fw-semibold" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="modules/farmers.php">Farmers</a></li>
                <li class="nav-item"><a class="nav-link" href="modules/farms.php">Farms</a></li>
                <li class="nav-item"><a class="nav-link" href="modules/crops.php">Crops</a></li>
                <li class="nav-item"><a class="nav-link" href="modules/plantings.php">Plantings</a></li>
                <li class="nav-item"><a class="nav-link" href="modules/harvests.php">Harvests</a></li>
                <li class="nav-item"><a class="nav-link" href="reports.php">Reports</a></li>
            </ul>
            <span class="navbar-text text-white me-3 small">
                👤 <?= htmlspecialchars($_SESSION['username'] ?? '') ?> (<?= htmlspecialchars($_SESSION['role'] ?? '') ?>)
            </span>
            <a href="logout.php" class="btn btn-outline-light btn-sm px-3 rounded-pill">Logout</a>
        </div>
    </div>
</nav>

<div class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-custom-title mb-1">System Dashboard</h2>
            <p class="text-muted small mb-0">Overview of agricultural data, operational records, and system summaries.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="reports.php" class="btn btn-outline-secondary rounded-pill px-4 shadow-sm">📊 View Reports</a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger shadow-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Summary Cards Row 1: Core Modules Count -->
    <div class="row g-3 mb-4">
        <div class="col-md">
            <div class="card shadow-sm border-0 p-3 text-center h-100" style="border-top: 4px solid #ff758c !important;">
                <span class="text-muted small fw-semibold text-uppercase">Total Farmers</span>
                <h2 class="fw-bold mb-0 text-dark mt-2"><?= number_format($totalFarmers) ?></h2>
                <a href="modules/farmers.php" class="stretched-link"></a>
            </div>
        </div>
        <div class="col-md">
            <div class="card shadow-sm border-0 p-3 text-center h-100" style="border-top: 4px solid #ff758c !important;">
                <span class="text-muted small fw-semibold text-uppercase">Total Farms</span>
                <h2 class="fw-bold mb-0 text-dark mt-2"><?= number_format($totalFarms) ?></h2>
                <a href="modules/farms.php" class="stretched-link"></a>
            </div>
        </div>
        <div class="col-md">
            <div class="card shadow-sm border-0 p-3 text-center h-100" style="border-top: 4px solid #ff758c !important;">
                <span class="text-muted small fw-semibold text-uppercase">Total Crops</span>
                <h2 class="fw-bold mb-0 text-dark mt-2"><?= number_format($totalCrops) ?></h2>
                <a href="modules/crops.php" class="stretched-link"></a>
            </div>
        </div>
        <div class="col-md">
            <div class="card shadow-sm border-0 p-3 text-center h-100" style="border-top: 4px solid #ff758c !important;">
                <span class="text-muted small fw-semibold text-uppercase">Planting Records</span>
                <h2 class="fw-bold mb-0 text-dark mt-2"><?= number_format($totalPlantings) ?></h2>
                <a href="modules/plantings.php" class="stretched-link"></a>
            </div>
        </div>
        <div class="col-md">
            <div class="card shadow-sm border-0 p-3 text-center h-100" style="border-top: 4px solid #ff758c !important;">
                <span class="text-muted small fw-semibold text-uppercase">Harvest Records</span>
                <h2 class="fw-bold mb-0 text-dark mt-2"><?= number_format($totalHarvests) ?></h2>
                <a href="modules/harvests.php" class="stretched-link"></a>
            </div>
        </div>
    </div>

    <!-- Summary Cards Row 2: Agricultural Insights -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 p-4 bg-white h-100">
                <span class="text-muted small fw-semibold text-uppercase mb-1">Total Harvested Quantity</span>
                <h3 class="fw-bold text-success mb-3"><?= number_format($totalHarvestedQuantity, 2) ?> kg</h3>
                <hr class="text-muted opacity-25">
                <span class="text-muted small fw-semibold text-uppercase mb-2">Planting Status Breakdown</span>
                <div class="d-flex gap-4 mt-1">
                    <div>
                        <span class="badge bg-warning text-dark px-3 py-2 fs-6">Growing: <?= $statusCounts['Growing'] ?></span>
                    </div>
                    <div>
                        <span class="badge bg-success px-3 py-2 fs-6">Harvested: <?= $statusCounts['Harvested'] ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0 p-4 bg-white h-100">
                <span class="text-muted small fw-semibold text-uppercase mb-2">Quick Navigation</span>
                <p class="text-muted small mb-3">Access and manage core database modules quickly.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="modules/farmers.php" class="btn btn-outline-dark rounded-pill px-3 btn-sm">Manage Farmers</a>
                    <a href="modules/farms.php" class="btn btn-outline-dark rounded-pill px-3 btn-sm">Manage Farms</a>
                    <a href="modules/crops.php" class="btn btn-outline-dark rounded-pill px-3 btn-sm">Manage Crops</a>
                    <a href="modules/plantings.php" class="btn btn-outline-dark rounded-pill px-3 btn-sm">Manage Plantings</a>
                    <a href="modules/harvests.php" class="btn btn-outline-dark rounded-pill px-3 btn-sm">Manage Harvests</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Tables Row -->
    <div class="row g-4">
        <!-- Recent Plantings -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-custom-title">🌱 Recent Planting Records</h6>
                    <a href="modules/plantings.php" class="small text-decoration-none">View All →</a>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">ID</th>
                                <th>Farm Name</th>
                                <th>Crop</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentPlantings)): ?>
                                <?php foreach ($recentPlantings as $p): ?>
                                    <tr>
                                        <td class="ps-3 text-muted">#<?= $p['planting_id'] ?></td>
                                        <td class="fw-medium"><?= htmlspecialchars($p['farm_name']) ?></td>
                                        <td><?= htmlspecialchars($p['crop_name'] . ' (' . $p['variety'] . ')') ?></td>
                                        <td><?= htmlspecialchars($p['planting_date']) ?></td>
                                        <td>
                                            <span class="badge <?= ($p['status'] === 'Harvested') ? 'bg-success' : 'bg-warning text-dark' ?>">
                                                <?= htmlspecialchars($p['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">No recent plantings found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Harvests -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-custom-title">🌾 Recent Harvest Records</h6>
                    <a href="modules/harvests.php" class="small text-decoration-none">View All →</a>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">ID</th>
                                <th>Farm & Crop</th>
                                <th>Date</th>
                                <th>Quantity</th>
                                <th>Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentHarvests)): ?>
                                <?php foreach ($recentHarvests as $h): ?>
                                    <tr>
                                        <td class="ps-3 text-muted">#<?= $h['harvest_id'] ?></td>
                                        <td>
                                            <div class="fw-medium"><?= htmlspecialchars($h['farm_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($h['crop_name']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($h['harvest_date']) ?></td>
                                        <td class="fw-semibold"><?= number_format((float)$h['quantity_kg'], 2) ?> kg</td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($h['quality_grade'] ?: 'N/A') ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">No recent harvests found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>