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
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $contactNumber = trim($_POST['contact_number'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($firstName) || empty($lastName)) {
            $error = "First name and last name are required.";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO farmer (first_name, last_name, contact_number, address) VALUES (:first_name, :last_name, :contact_number, :address)");
                $stmt->execute([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'contact_number' => $contactNumber,
                    'address' => $address
                ]);

                // Realign sequence counter so it fills gaps or increments cleanly without jumping
                $db->query("SELECT setval('farmer_farmer_id_seq', COALESCE((SELECT MAX(farmer_id) FROM farmer), 0) + 1, false);");

                $message = "Farmer added successfully!";
            } catch (PDOException $e) {
                $error = "Error adding farmer: " . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        $farmerId = $_POST['farmer_id'] ?? '';
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $contactNumber = trim($_POST['contact_number'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($farmerId) || empty($firstName) || empty($lastName)) {
            $error = "Invalid data for update.";
        } else {
            try {
                $stmt = $db->prepare("UPDATE farmer SET first_name = :first_name, last_name = :last_name, contact_number = :contact_number, address = :address WHERE farmer_id = :farmer_id");
                $stmt->execute([
                    'farmer_id' => $farmerId,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'contact_number' => $contactNumber,
                    'address' => $address
                ]);
                $message = "Farmer updated successfully!";
            } catch (PDOException $e) {
                $error = "Error updating farmer: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $farmerId = $_POST['farmer_id'] ?? '';
        if (!empty($farmerId)) {
            try {
                $stmt = $db->prepare("DELETE FROM farmer WHERE farmer_id = :farmer_id");
                $stmt->execute(['farmer_id' => $farmerId]);

                // Realign sequence counter after deletion so IDs fill gaps properly
                $db->query("SELECT setval('farmer_farmer_id_seq', COALESCE((SELECT MAX(farmer_id) FROM farmer), 0) + 1, false);");

                $message = "Farmer deleted successfully!";
            } catch (PDOException $e) {
                $error = "Cannot delete farmer because they have linked farm or planting records.";
            }
        }
    }
}

// Search, Sort, and Pagination Parameters
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'farmer_id';
$order = $_GET['order'] ?? 'ASC';

// Allowed sorting columns to prevent SQL injection
$allowedSorts = ['farmer_id', 'first_name', 'last_name', 'date_registered'];
if (!in_array($sort, $allowedSorts)) {
    $sort = 'farmer_id';
}
$order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    // Build Base Query with Search filter
    $whereClause = "";
    $params = [];

    if (!empty($search)) {
        $whereClause = "WHERE first_name ILIKE :search OR last_name ILIKE :search OR contact_number ILIKE :search OR address ILIKE :search";
        $params['search'] = "%$search%";
    }

    // Get Total Count for Pagination
    $countStmt = $db->prepare("SELECT COUNT(*) FROM farmer $whereClause");
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);
    if ($totalPages < 1) $totalPages = 1;

    // Fetch Paginated Records
    $query = "SELECT * FROM farmer $whereClause ORDER BY $sort $order LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);

    foreach ($params as $key => $val) {
        $stmt->bindValue(":$key", $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $farmers = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Error fetching farmers: " . $e->getMessage();
    $farmers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmers Management - AgriInfo</title>
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
                <li class="nav-item"><a class="nav-link active fw-semibold" href="farmers.php">Farmers</a></li>
                <li class="nav-item"><a class="nav-link" href="farms.php">Farms</a></li>
                <li class="nav-item"><a class="nav-link" href="crops.php">Crops</a></li>
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
        <h2 class="fw-bold text-custom-title mb-0">Farmers Directory</h2>
        <button class="btn btn-custom px-4 shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#addFarmerModal">+ Add New Farmer</button>
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
                    <input type="text" name="search" class="form-control" placeholder="Search by name, contact, or address..." value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-custom px-4" type="submit">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="farmers.php" class="btn btn-outline-secondary">Reset</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4 text-md-end text-muted small">
                Showing <?= count($farmers) ?> of <?= $totalRecords ?> farmers
            </div>
        </form>
    </div>

    <!-- Farmers Table Card -->
    <div class="card shadow-sm">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">
                            <a href="?search=<?= urlencode($search) ?>&sort=farmer_id&order=<?= ($sort === 'farmer_id' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                ID <?= $sort === 'farmer_id' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th>
                            <a href="?search=<?= urlencode($search) ?>&sort=first_name&order=<?= ($sort === 'first_name' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                First Name <?= $sort === 'first_name' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th>
                            <a href="?search=<?= urlencode($search) ?>&sort=last_name&order=<?= ($sort === 'last_name' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                Last Name <?= $sort === 'last_name' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th>Contact Number</th>
                        <th>Address</th>
                        <th>
                            <a href="?search=<?= urlencode($search) ?>&sort=date_registered&order=<?= ($sort === 'date_registered' && $order === 'ASC') ? 'DESC' : 'ASC' ?>">
                                Registered <?= $sort === 'date_registered' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                            </a>
                        </th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($farmers)): ?>
                        <?php foreach ($farmers as $farmer): ?>
                            <tr>
                                <td class="ps-4 fw-semibold text-muted">#<?= $farmer['farmer_id'] ?></td>
                                <td class="fw-medium"><?= htmlspecialchars($farmer['first_name']) ?></td>
                                <td class="fw-medium"><?= htmlspecialchars($farmer['last_name']) ?></td>
                                <td><?= htmlspecialchars($farmer['contact_number'] ?: 'N/A') ?></td>
                                <td><?= htmlspecialchars($farmer['address'] ?: 'N/A') ?></td>
                                <td><?= htmlspecialchars($farmer['date_registered']) ?></td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <button class="btn btn-sm btn-outline-primary px-3 rounded-pill" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editFarmerModal<?= $farmer['farmer_id'] ?>">Edit</button>
                                        <button class="btn btn-sm btn-outline-danger px-3 rounded-pill" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteFarmerModal<?= $farmer['farmer_id'] ?>">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No registered farmers found.</td></tr>
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
<?php if (!empty($farmers)): ?>
    <?php foreach ($farmers as $farmer): ?>

        <!-- Edit Modal -->
        <div class="modal fade" id="editFarmerModal<?= $farmer['farmer_id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content border-0 shadow">
                    <form method="POST">
                        <div class="modal-header" style="background-color: #fff0f3;">
                            <h5 class="modal-title fw-bold text-custom-title">Edit Farmer</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-start">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="farmer_id" value="<?= $farmer['farmer_id'] ?>">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">First Name</label>
                                <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($farmer['first_name']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Last Name</label>
                                <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($farmer['last_name']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Contact Number</label>
                                <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($farmer['contact_number']) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Address</label>
                                <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($farmer['address']) ?></textarea>
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
        <div class="modal fade" id="deleteFarmerModal<?= $farmer['farmer_id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <form method="POST">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title fw-bold">Confirm Delete</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-start py-4">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="farmer_id" value="<?= $farmer['farmer_id'] ?>">
                            <p class="mb-0">Are you sure you want to delete farmer <strong><?= htmlspecialchars($farmer['first_name'] . ' ' . $farmer['last_name']) ?></strong>?</p>
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

<!-- Add Farmer Modal -->
<div class="modal fade" id="addFarmerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <div class="modal-header" style="background-color: #fff0f3;">
                    <h5 class="modal-title fw-bold text-custom-title">Add New Farmer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-start">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">First Name</label>
                        <input type="text" name="first_name" class="form-control" placeholder="Enter first name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Last Name</label>
                        <input type="text" name="last_name" class="form-control" placeholder="Enter last name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Contact Number</label><input type="text" name="contact_number" class="form-control" placeholder="e.g., 09123456789">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Enter address"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-custom rounded-pill px-4">Add Farmer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/main.js"></script>
</body>
</html>