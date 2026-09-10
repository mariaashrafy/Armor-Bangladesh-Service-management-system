   <?php
require_once "config.php";

/* 🔐 Admin protection */
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}

/* Handle status update */
if (isset($_POST["update_status"])) {
    $id = $_POST["request_id"];
    $status = $_POST["status"];

    $stmt = $pdo->prepare(
        "UPDATE service_requests SET status = ? WHERE id = ?"
    );
    $stmt->execute([$status, $id]);
}

/* Fetch requests */
$stmt = $pdo->query(
    "SELECT * FROM service_requests ORDER BY created_at DESC"
);
$requests = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | Sonic Elevator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container my-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Admin Dashboard</h2>
        <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>

    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            Service Appointments
        </div>

        <div class="card-body table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Service</th>
                        <th>Building</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (!$requests): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted">
                            No service requests found
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $i => $row): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($row["service_type"]) ?></td>
                            <td><?= htmlspecialchars($row["building_type"]) ?></td>
                            <td><?= htmlspecialchars($row["customer_name"]) ?></td>
                            <td><?= htmlspecialchars($row["phone"]) ?></td>
                            <td><?= $row["preferred_date"] ?></td>
                            <td><?= $row["preferred_time"] ?></td>

                            <!-- Status Badge -->
                            <td>
                                <?php
                                $badge = match ($row["status"]) {
                                    "Pending"   => "warning",
                                    "Assigned"  => "info",
                                    "Completed" => "success",
                                    default     => "secondary"
                                };
                                ?>
                                <span class="badge bg-<?= $badge ?>">
                                    <?= $row["status"] ?>
                                </span>
                            </td>

                            <!-- Update Status -->
                            <td>
                                <form method="post" class="d-flex gap-2">
                                    <input type="hidden" name="request_id" value="<?= $row["id"] ?>">
                                    <select name="status" class="form-select form-select-sm">
                                        <option <?= $row["status"]=="Pending"?"selected":"" ?>>Pending</option>
                                        <option <?= $row["status"]=="Assigned"?"selected":"" ?>>Assigned</option>
                                        <option <?= $row["status"]=="Completed"?"selected":"" ?>>Completed</option>
                                    </select>
                                    <button class="btn btn-sm btn-primary" name="update_status">
                                        Update
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>

            </table>
        </div>
    </div>

</div>

</body>
</html>
