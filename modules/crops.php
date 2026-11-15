<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../config/Database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Handle Form Submissions (Add / Update / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $cropName = trim($_POST['crop_name'] ?? '');
        $variety = trim($_POST['variety'] ?? '');
        $cropCategory = trim($_POST['crop_category'] ?? '');
        $growingPeriodDays = trim($_POST['growing_period_days'] ?? '');
        $recommendedSeason = trim($_POST['recommended_season'] ?? '');

        if (empty($cropName) || empty($cropCategory)) {
            $error = "Crop name and category are required.";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO crop (crop_name, variety, crop_category, growing_period_days, recommended_season) VALUES (:crop_name, :variety, :crop_category, :growing_period_days, :recommended_season)");
                $stmt->execute([
                    'crop_name' => $cropName,
                    'variety' => $variety,
                    'crop_category' => $cropCategory,
                    'growing_period_days' => $growingPeriodDays !== '' ? (int)$growingPeriodDays : null,
                    'recommended_season' => $recommendedSeason
                ]);

                // Realign sequence counter
                $db->query("SELECT setval('crop_crop_id_seq', COALESCE((SELECT MAX(crop_id) FROM crop), 0) + 1, false);");

                $message = "Crop added successfully!";
            } catch (PDOException $e) {
                $error = "Error adding crop: " . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        $cropId = $_POST['crop_id'] ?? '';
        $cropName = trim($_POST['crop_name'] ?? '');
        $variety = trim($_POST['variety'] ?? '');
        $cropCategory = trim($_POST['crop_category'] ?? '');
        $growingPeriodDays = trim($_POST['growing_period_days'] ?? '');
        $recommendedSeason = trim($_POST['recommended_season'] ?? '');

        if (empty($cropId) || empty($cropName) || empty($cropCategory)) {
            $error = "Invalid data for update. Crop name and category are required.";
        } else {
            try {
                $stmt = $db->prepare("UPDATE crop SET crop_name = :crop_name, variety = :variety, crop_category = :crop_category, growing_period_days = :growing_period_days, recommended_season = :recommended_season WHERE crop_id = :crop_id");
                $stmt->execute([
                    'crop_id' => $cropId,
                    'crop_name' => $cropName,
                    'variety' => $variety,
                    'crop_category' => $cropCategory,
                    'growing_period_days' => $growingPeriodDays !== '' ? (int)$growingPeriodDays : null,
                    'recommended_season' => $recommendedSeason
                ]);
                $message = "Crop updated successfully!";
            } catch (PDOException $e) {
                $error = "Error updating crop: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $cropId = $_POST['crop_id'] ?? '';
        if (!empty($cropId)) {
            try {
                $stmt = $db->prepare("DELETE FROM crop WHERE crop_id = :crop_id");
                $stmt->execute(['crop_id' => $cropId]);

                // Realign sequence counter
                $db->query("SELECT setval('crop_crop_id_seq', COALESCE((SELECT MAX(crop_id) FROM crop), 0) + 1, false);");

                $message = "Crop deleted successfully!";
            } catch (PDOException $e) {
                $error = "Cannot delete crop because it has linked planting or harvest records.";
            }
        }
    }
}

// Search, Sort, and Pagination Parameters
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'crop_id';
$order = $_GET['order'] ?? 'ASC';

// Allowed sorting columns
$allowedSorts = ['crop_id', 'crop_name', 'variety', 'crop_category', 'growing_period_days', 'recommended_season'];
if (!in_array($sort, $allowedSorts)) {
    $sort = 'crop_id';
}
$order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    $whereClause = "";
    $params = [];

    if (!empty($search)) {
        $whereClause = "WHERE crop_name ILIKE :search OR variety ILIKE :search OR crop_category ILIKE :search OR recommended_season ILIKE :search";
        $params['search'] = "%$search%";
    }

    // Get Total Count for Pagination
    $countStmt = $db->prepare("SELECT COUNT(*) FROM crop $whereClause");
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);
    if ($totalPages < 1) $totalPages = 1;

    // Fetch Paginated Records
    $query = "SELECT * FROM crop $whereClause ORDER BY $sort $order LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);

    foreach ($params as $key => $val) {
        $stmt->bindValue(":$key", $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $crops = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Error fetching crops: " . $e->getMessage();
    $crops = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crops Management - AgriInfo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../css/style.css?v=2.0" rel="stylesheet">
</head>
<body>

<!-- Navbar Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../dashboard.php">🌱 AgriInfo</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="../dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="farmers.php">Farmers</a></li>
                <li class="nav-item"><a class="nav-link" href="farms.php">Farms</a></li>
                <li class="nav-item"><a class="nav-link active fw-semibold" href="crops.php">Crops</a></li>
                <li class="nav-item"><a class="nav-link" href="plantings.php">Plantings</a></li>
                <li class="nav-item"><a class="nav-link" href="harvests.php">Harvests</a></li>
                <li class="nav-item"><a class="nav-link" href="../reports.php">Reports</a></li>
            </ul>
            <span class="navbar-text text-white me-3 small">
                👤 <?= htmlspecialchars($_SESSION['username'] ?? '') ?> (<?= htmlspecialchars($_SESSION['role'] ?? '') ?>)
            </span>
            <a href="../logout.php" class="btn btn-outline-light btn-sm px-3 rounded-pill">Logout</a>
        </div>
    </div>
</nav>

<div class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-custom-title mb-0">Crops Directory</h2>
        <button class="btn btn-custom px-4 shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#addCropModal">+ Add New Crop</button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success shadow-sm"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Search & Filter Bar -->
    <div class="card shadow-sm mb-4 p-3">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-md-8">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search by crop name, variety, category, or season..." value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-custom px-4" type="submit">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="crops.php" class="btn btn-outline-secondary">Reset</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4 text-md-end text-muted small">
                Showing <?= count($crops) ?> of <?= $totalRecords ?> crops
            </div>
        </form>
    </div>

    <!-- Crops Table Card -->
    <div class="card shadow-sm">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">
                            <a href="?search=<?= urlencode($search) ?>&sort=crop_id&order=<?= ($sort === 'crop_id' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                ID <?= $sort === 'crop_id' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th>
                            <a href="?search=<?= urlencode($search) ?>&sort=crop_name&order=<?= ($sort === 'crop_name' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                Crop Name <?= $sort === 'crop_name' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th>
                            <a href="?search=<?= urlencode($search) ?>&sort=variety&order=<?= ($sort === 'variety' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                Variety <?= $sort === 'variety' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th>
                            <a href="?search=<?= urlencode($search) ?>&sort=crop_category&order=<?= ($sort === 'crop_category' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                Category <?= $sort === 'crop_category' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th class="text-center">
                            <a href="?search=<?= urlencode($search) ?>&sort=growing_period_days&order=<?= ($sort === 'growing_period_days' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                Growing Period (Days) <?= $sort === 'growing_period_days' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th>
                            <a href="?search=<?= urlencode($search) ?>&sort=recommended_season&order=<?= ($sort === 'recommended_season' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                Recommended Season <?= $sort === 'recommended_season' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($crops)): ?>
                        <?php foreach ($crops as $crop): ?>
                            <tr>
                                <td class="ps-4 fw-semibold text-muted">#<?= $crop['crop_id'] ?></td>
                                <td class="fw-medium"><?= htmlspecialchars($crop['crop_name']) ?></td>
                                <td><?= htmlspecialchars($crop['variety'] ?: 'N/A') ?></td>
                                <td><?= htmlspecialchars($crop['crop_category']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($crop['growing_period_days'] ?: 'N/A') ?></td>
                                <td><?= htmlspecialchars($crop['recommended_season'] ?: 'N/A') ?></td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <button class="btn btn-sm btn-outline-primary px-3 rounded-pill" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editCropModal<?= $crop['crop_id'] ?>">Edit</button>
                                        <button class="btn btn-sm btn-outline-danger px-3 rounded-pill" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteCropModal<?= $crop['crop_id'] ?>">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No registered crops found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white py-3 d-flex justify-content-center">
                <nav>
                    <ul class="pagination mb-0">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
                                <a class="page-link" href="?search=<?= urlencode($search) ?>&sort=<?= $sort ?>&order=<?= $order ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Render Modals OUTSIDE the table loop to prevent backdrop and DOM nesting conflicts -->
<?php if (!empty($crops)): ?>
    <?php foreach ($crops as $crop): ?>

        <!-- Edit Modal -->
        <div class="modal fade" id="editCropModal<?= $crop['crop_id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content border-0 shadow">
                    <form method="POST">
                        <div class="modal-header" style="background-color: #fff0f3;">
                            <h5 class="modal-title fw-bold text-custom-title">Edit Crop</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-start">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="crop_id" value="<?= $crop['crop_id'] ?>">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Crop Name</label>
                                <input type="text" name="crop_name" class="form-control" value="<?= htmlspecialchars($crop['crop_name']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Variety</label>
                                <input type="text" name="variety" class="form-control" value="<?= htmlspecialchars($crop['variety']) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Category</label>
                                <input type="text" name="crop_category" class="form-control" value="<?= htmlspecialchars($crop['crop_category']) ?>" placeholder="e.g., Cereal, Vegetable, Fruit" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Growing Period (Days)</label>
                                <input type="number" name="growing_period_days" class="form-control" value="<?= htmlspecialchars($crop['growing_period_days']) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Recommended Season</label>
                                <input type="text" name="recommended_season" class="form-control" value="<?= htmlspecialchars($crop['recommended_season']) ?>">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-custom rounded-pill px-4">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div class="modal fade" id="deleteCropModal<?= $crop['crop_id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <form method="POST">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title fw-bold">Confirm Delete</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-start py-4">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="crop_id" value="<?= $crop['crop_id'] ?>">
                            <p class="mb-0">Are you sure you want to delete crop <strong><?= htmlspecialchars($crop['crop_name'] . ($crop['variety'] ? ' (' . $crop['variety'] . ')' : '')) ?></strong>?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger rounded-pill px-4">Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <?php endforeach; ?>
<?php endif; ?>

<!-- Add Crop Modal -->
<div class="modal fade" id="addCropModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <div class="modal-header" style="background-color: #fff0f3;">
                    <h5 class="modal-title fw-bold text-custom-title">Add New Crop</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Crop Name</label>
                        <input type="text" name="crop_name" class="form-control" placeholder="e.g., Rice, Corn, Tomato" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Variety</label>
                        <input type="text" name="variety" class="form-control" placeholder="e.g., NSIC Rc222">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category</label>
                        <input type="text" name="crop_category" class="form-control" placeholder="e.g., Cereal, Vegetable, Fruit" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Growing Period (Days)</label>
                        <input type="number" name="growing_period_days" class="form-control" placeholder="e.g., 120">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Recommended Season</label>
                        <input type="text" name="recommended_season" class="form-control" placeholder="e.g., Dry Season / Wet Season">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-custom rounded-pill px-4">Add Crop</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/main.js"></script>
</body>
</html>