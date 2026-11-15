<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

require_once __DIR__ . '/config/Database.php';

$database = new Database();
$db = $database->getConnection();

// Active Report Type selection (default to 'farms')
$reportType = $_GET['type'] ?? 'farms';
$statusFilter = $_GET['status_filter'] ?? '';
$gradeFilter = $_GET['grade_filter'] ?? '';

$reportData = [];
$summaryStats = [];

try {
    if ($reportType === 'farms') {
        // Report 1: Farmers and their farms
        $query = "SELECT f.farm_id, f.farm_name, f.location, f.area_hectares, f.soil_type, f.irrigation_type, 
                         fm.farmer_id, fm.first_name, fm.last_name, fm.contact_number, fm.address 
                  FROM farm f 
                  JOIN farmer fm ON f.farmer_id = fm.farmer_id 
                  ORDER BY fm.last_name ASC, f.farm_name ASC";
        $stmt = $db->query($query);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($reportType === 'plantings_crop') {
        // Report 2: Crops planted per farm (Sorted Ascending by ID)
        $query = "SELECT p.planting_id, p.planting_date, p.area_planted, p.seed_quantity, p.status,
                         f.farm_name, f.location, 
                         c.crop_name, c.variety, c.crop_category 
                  FROM planting p 
                  JOIN farm f ON p.farm_id = f.farm_id 
                  JOIN crop c ON p.crop_id = c.crop_id";
        
        $params = [];
        if (!empty($statusFilter)) {
            $query .= " WHERE p.status = :status_filter";
            $params['status_filter'] = $statusFilter;
        }
        $query .= " ORDER BY p.planting_id ASC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($reportType === 'harvests_value') {
        // Report 4: Harvest records, total harvested quantity, and estimated harvest value (Sorted Ascending by ID)
        $query = "SELECT h.harvest_id, h.harvest_date, h.quantity_kg, h.selling_price_per_kg, h.quality_grade, h.remarks,
                         (h.quantity_kg * h.selling_price_per_kg) AS total_estimated_value,
                         p.planting_id, f.farm_name, c.crop_name, c.variety 
                  FROM harvest h 
                  JOIN planting p ON h.planting_id = p.planting_id 
                  JOIN farm f ON p.farm_id = f.farm_id 
                  JOIN crop c ON p.crop_id = c.crop_id";
        
        $params = [];
        if (!empty($gradeFilter)) {
            $query .= " WHERE h.quality_grade = :grade_filter";
            $params['grade_filter'] = $gradeFilter;
        }
        $query .= " ORDER BY h.harvest_id ASC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate summary totals dynamically from database query results
        $totalQty = 0;
        $totalVal = 0;
        foreach ($reportData as $row) {
            $totalQty += (float)($row['quantity_kg'] ?? 0);
            $totalVal += (float)($row['total_estimated_value'] ?? 0);
        }
        $summaryStats = [
            'total_quantity' => $totalQty,
            'total_value' => $totalVal
        ];
    }
} catch (PDOException $e) {
    $error = "Error generating report: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agricultural Reports - AgriInfo</title>
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
                <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="modules/farmers.php">Farmers</a></li>
                <li class="nav-item"><a class="nav-link" href="modules/farms.php">Farms</a></li>
                <li class="nav-item"><a class="nav-link" href="modules/crops.php">Crops</a></li>
                <li class="nav-item"><a class="nav-link" href="modules/plantings.php">Plantings</a></li>
                <li class="nav-item"><a class="nav-link" href="modules/harvests.php">Harvests</a></li>
                <li class="nav-item"><a class="nav-link active fw-semibold" href="reports.php">Reports</a></li>
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
            <h2 class="fw-bold text-custom-title mb-1">Agricultural Reports</h2>
            <p class="text-muted small mb-0">Generate analytical summaries and operational reports from your database[cite: 4].</p>
        </div>
        <button onclick="window.print()" class="btn btn-outline-secondary rounded-pill px-4 shadow-sm">🖨️ Print Report</button>
    </div>

    <!-- Report Type Selector Tabs -->
    <div class="card shadow-sm mb-4 p-3 bg-white">
        <div class="row g-3 align-items-center">
            <div class="col-md-8">
                <div class="btn-group w-100 shadow-sm" role="group">
                    <a href="reports.php?type=farms" class="btn <?= ($reportType === 'farms') ? 'btn-custom text-white fw-semibold' : 'btn-outline-light text-dark border' ?>">
                        Farmers & Their Farms[cite: 4]
                    </a>
                    <a href="reports.php?type=plantings_crop" class="btn <?= ($reportType === 'plantings_crop') ? 'btn-custom text-white fw-semibold' : 'btn-outline-light text-dark border' ?>">
                        Crops Planted per Farm[cite: 4]
                    </a>
                    <a href="reports.php?type=harvests_value" class="btn <?= ($reportType === 'harvests_value') ? 'btn-custom text-white fw-semibold' : 'btn-outline-light text-dark border' ?>">
                        Harvests & Estimated Value[cite: 4]
                    </a>
                </div>
            </div>

            <!-- Optional Sub-filters based on report type -->
            <div class="col-md-4">
                <?php if ($reportType === 'plantings_crop'): ?>
                    <form method="GET">
                        <input type="hidden" name="type" value="plantings_crop">
                        <select name="status_filter" class="form-select" onchange="this.form.submit()">
                            <option value="">Filter Status (All)</option>
                            <option value="Growing" <?= ($statusFilter === 'Growing') ? 'selected' : '' ?>>Growing</option>
                            <option value="Harvested" <?= ($statusFilter === 'Harvested') ? 'selected' : '' ?>>Harvested</option>
                        </select>
                    </form>
                <?php elseif ($reportType === 'harvests_value'): ?>
                    <form method="GET">
                        <input type="hidden" name="type" value="harvests_value">
                        <select name="grade_filter" class="form-select" onchange="this.form.submit()">
                            <option value="">Filter Grade (All)</option>
                            <option value="Grade A" <?= ($gradeFilter === 'Grade A') ? 'selected' : '' ?>>Grade A</option>
                            <option value="Grade B" <?= ($gradeFilter === 'Grade B') ? 'selected' : '' ?>>Grade B</option>
                            <option value="Grade C" <?= ($gradeFilter === 'Grade C') ? 'selected' : '' ?>>Grade C</option>
                        </select>
                    </form>
                <?php else: ?>
                    <div class="text-muted small text-md-end">Total Records: <strong><?= count($reportData) ?></strong></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($reportType === 'harvests_value' && !empty($summaryStats)): ?>
        <!-- Summary Cards for Harvest Value Report -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm border-0 p-3" style="border-left: 5px solid #ff758c !important;">
                    <span class="text-muted small fw-semibold text-uppercase">Total Harvested Quantity</span>
                    <h3 class="fw-bold mb-0 text-dark"><?= number_format($summaryStats['total_quantity'], 2) ?> kg</h3>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm border-0 p-3" style="border-left: 5px solid #28a745 !important;">
                    <span class="text-muted small fw-semibold text-uppercase">Total Estimated Harvest Value</span>
                    <h3 class="fw-bold mb-0 text-success">₱<?= number_format($summaryStats['total_value'], 2) ?></h3>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Report Table Results -->
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-custom-title">
                <?php if ($reportType === 'farms'): ?>
                    🌱 Farmers & Their Managed Farms Report[cite: 4]
                <?php elseif ($reportType === 'plantings_crop'): ?>
                    🌾 Crops Planted per Farm Report[cite: 4]
                <?php else: ?>
                    💰 Harvest Records & Estimated Value Report[cite: 4]
                <?php endif; ?>
            </h5>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <?php if ($reportType === 'farms'): ?>
                        <tr>
                            <th class="ps-4">Farmer Name</th>
                            <th>Contact</th>
                            <th>Farmer Address</th>
                            <th>Farm Name</th>
                            <th>Location</th>
                            <th>Area (ha)</th>
                            <th>Soil / Irrigation</th>
                        </tr>
                    <?php elseif ($reportType === 'plantings_crop'): ?>
                        <tr>
                            <th class="ps-4">Planting ID</th>
                            <th>Farm Name</th>
                            <th>Crop Name</th>
                            <th>Variety</th>
                            <th>Category</th>
                            <th>Planted Date</th>
                            <th>Status</th>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <th class="ps-4">Harvest ID</th>
                            <th>Farm & Crop</th>
                            <th>Harvest Date</th>
                            <th>Quantity (kg)</th>
                            <th>Price/kg</th>
                            <th>Estimated Value</th>
                            <th>Grade & Remarks</th>
                        </tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <?php if (!empty($reportData)): ?>
                        <?php foreach ($reportData as $row): ?>
                            <?php if ($reportType === 'farms'): ?>
                                <tr>
                                    <td class="ps-4 fw-medium"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                    <td><?= htmlspecialchars($row['contact_number'] ?: 'N/A') ?></td>
                                    <td class="text-muted"><?= htmlspecialchars($row['address'] ?: 'N/A') ?></td>
                                    <td class="fw-semibold text-custom-title"><?= htmlspecialchars($row['farm_name']) ?></td>
                                    <td><?= htmlspecialchars($row['location'] ?: 'N/A') ?></td>
                                    <td><?= htmlspecialchars($row['area_hectares'] !== null ? $row['area_hectares'] . ' ha' : 'N/A') ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['soil_type'] . ' / ' . $row['irrigation_type']) ?></span></td>
                                </tr>
                            <?php elseif ($reportType === 'plantings_crop'): ?>
                                <tr>
                                    <td class="ps-4 fw-semibold text-muted">#<?= $row['planting_id'] ?></td>
                                    <td class="fw-medium"><?= htmlspecialchars($row['farm_name']) ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($row['crop_name']) ?></td>
                                    <td><?= htmlspecialchars($row['variety'] ?: 'N/A') ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['crop_category'] ?: 'General') ?></span></td>
                                    <td><?= htmlspecialchars($row['planting_date']) ?></td>
                                    <td>
                                        <span class="badge <?= ($row['status'] === 'Harvested') ? 'bg-success' : 'bg-warning text-dark' ?> px-2 py-1">
                                            <?= htmlspecialchars($row['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td class="ps-4 fw-semibold text-muted">#<?= $row['harvest_id'] ?></td>
                                    <td>
                                        <div class="fw-medium"><?= htmlspecialchars($row['farm_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($row['crop_name'] . ' (' . $row['variety'] . ')') ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($row['harvest_date']) ?></td>
                                    <td class="fw-semibold"><?= number_format((float)$row['quantity_kg'], 2) ?> kg</td>
                                    <td>₱<?= number_format((float)$row['selling_price_per_kg'], 2) ?></td>
                                    <td class="text-success fw-bold">₱<?= number_format((float)$row['total_estimated_value'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border me-1"><?= htmlspecialchars($row['quality_grade'] ?: 'N/A') ?></span>
                                        <small class="text-muted"><?= htmlspecialchars($row['remarks']) ?></small>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No report records found matching criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>