<?php

require_once "config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* ============================================================
   MANAGER / ADMIN ONLY
============================================================ */

if (
    !isset($_SESSION["user_id"]) ||
    !in_array(
        $_SESSION["role"] ?? "",
        ["admin", "manager"],
        true
    )
) {
    header("Location: login.php");
    exit;
}


$manager_name =
    $_SESSION["name"] ?? "Manager";


/* ============================================================
   HELPERS
============================================================ */

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = ?
    ");

    $stmt->execute([$table]);

    return (int)$stmt->fetchColumn() > 0;
}


function column_exists(
    PDO $pdo,
    string $table,
    string $column
): bool {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
        AND table_name = ?
        AND column_name = ?
    ");

    $stmt->execute([
        $table,
        $column
    ]);

    return (int)$stmt->fetchColumn() > 0;
}


/* ============================================================
   DEFAULT METRICS
============================================================ */

$total_requests     = 0;
$submitted_requests = 0;
$active_requests    = 0;
$completed_requests = 0;

$critical_requests  = 0;
$high_requests      = 0;

$total_clients      = 0;
$total_technicians  = 0;

$overdue_maintenance = 0;

$avg_resolution_days = null;

$top_categories = [];

$error = "";


/* ============================================================
   REQUEST METRICS
============================================================ */

if (table_exists($pdo, "client_requests")) {

    try {

        /* Total */

        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM client_requests
        ");

        $total_requests =
            (int)$stmt->fetchColumn();


        /* Submitted */

        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM client_requests
            WHERE LOWER(status) = 'submitted'
        ");

        $submitted_requests =
            (int)$stmt->fetchColumn();


        /* Active */

        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM client_requests
            WHERE LOWER(status)
            IN (
                'assigned',
                'processing',
                'pending'
            )
        ");

        $active_requests =
            (int)$stmt->fetchColumn();


        /* Completed / Closed */

        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM client_requests
            WHERE LOWER(status)
            IN (
                'completed',
                'closed'
            )
        ");

        $completed_requests =
            (int)$stmt->fetchColumn();


        /* Critical */

        if (
            column_exists(
                $pdo,
                "client_requests",
                "priority"
            )
        ) {

            $stmt = $pdo->query("
                SELECT COUNT(*)
                FROM client_requests
                WHERE LOWER(priority) = 'critical'
            ");

            $critical_requests =
                (int)$stmt->fetchColumn();


            $stmt = $pdo->query("
                SELECT COUNT(*)
                FROM client_requests
                WHERE LOWER(priority) = 'high'
            ");

            $high_requests =
                (int)$stmt->fetchColumn();

        }


        /* Problem categories */

        if (
            column_exists(
                $pdo,
                "client_requests",
                "category"
            )
        ) {

            $stmt = $pdo->query("
                SELECT
                    category,
                    COUNT(*) AS total
                FROM client_requests
                WHERE category IS NOT NULL
                AND category <> ''
                GROUP BY category
                ORDER BY total DESC
                LIMIT 5
            ");

            $top_categories =
                $stmt->fetchAll(PDO::FETCH_ASSOC);

        }


        /* Average resolution time
           Only calculate if completed_at exists.
        */

        if (
            column_exists(
                $pdo,
                "client_requests",
                "completed_at"
            )
            &&
            column_exists(
                $pdo,
                "client_requests",
                "created_at"
            )
        ) {

            $stmt = $pdo->query("
                SELECT AVG(
                    TIMESTAMPDIFF(
                        HOUR,
                        created_at,
                        completed_at
                    )
                )
                FROM client_requests
                WHERE completed_at IS NOT NULL
            ");

            $hours =
                $stmt->fetchColumn();


            if ($hours !== null) {

                $avg_resolution_days =
                    round(
                        ((float)$hours / 24),
                        1
                    );

            }

        }

    } catch (Throwable $e) {

        $error =
            "Some request metrics could not be loaded.";

    }

}


/* ============================================================
   USERS METRICS
============================================================ */

if (table_exists($pdo, "users")) {

    try {

        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM users
            WHERE LOWER(role) = 'client'
        ");

        $total_clients =
            (int)$stmt->fetchColumn();


        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM users
            WHERE LOWER(role) = 'technician'
        ");

        $total_technicians =
            (int)$stmt->fetchColumn();

    } catch (Throwable $e) {

        /* keep defaults */

    }

}


/* ============================================================
   MAINTENANCE METRICS
============================================================ */

if (
    table_exists($pdo, "maintenance_schedules")
    &&
    column_exists(
        $pdo,
        "maintenance_schedules",
        "next_date"
    )
) {

    try {

        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM maintenance_schedules
            WHERE next_date < CURDATE()
            AND LOWER(status) <> 'completed'
        ");

        $overdue_maintenance =
            (int)$stmt->fetchColumn();

    } catch (Throwable $e) {

        /* keep default */

    }

}


/* ============================================================
   COMPLETION RATE
============================================================ */

$completion_rate = 0;

if ($total_requests > 0) {

    $completion_rate =
        round(
            ($completed_requests / $total_requests) * 100,
            1
        );

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1">

<title>
    Reports & Metrics | Armor Bangladesh Ltd.
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet">


<link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    rel="stylesheet">


<style>

:root {

    --armor:#F58220;

    --sidebar:#123D36;
    --sidebar-dark:#0E312C;
    --sidebar-hover:#1A5148;

    --teal:#58A89A;

    --page-bg:#F5F7F8;

    --text:#25333A;

    --muted:#7A858B;

    --border:#E4E9EB;
}


* {
    box-sizing:border-box;
}


body {

    margin:0;

    background:var(--page-bg);

    color:var(--text);

    font-family:Arial, Helvetica, sans-serif;
}


/* ============================================================
   SIDEBAR
============================================================ */

.sidebar {

    position:fixed;

    top:0;
    left:0;

    width:245px;
    height:100vh;

    background:
        linear-gradient(
            180deg,
            var(--sidebar),
            var(--sidebar-dark)
        );

    color:white;

    overflow-y:auto;

    z-index:1000;

    transition:.25s ease;
}


.sidebar-logo {

    min-height:78px;

    display:flex;

    align-items:center;

    padding:13px 20px;

    border-bottom:
        1px solid rgba(255,255,255,.09);
}


.sidebar-logo img {

    max-width:150px;

    max-height:55px;

    object-fit:contain;

    background:white;

    padding:5px 8px;

    border-radius:4px;
}


.sidebar-menu {

    padding:16px 10px;
}


.menu-title {

    color:
        rgba(255,255,255,.45);

    font-size:11px;

    text-transform:uppercase;

    letter-spacing:1.3px;

    padding:
        16px 14px 7px;
}


.sidebar-link {

    display:flex;

    align-items:center;

    gap:13px;

    min-height:44px;

    padding:
        10px 14px;

    margin-bottom:3px;

    border-radius:5px;

    text-decoration:none;

    color:
        rgba(255,255,255,.78);

    font-size:14px;

    transition:.2s;
}


.sidebar-link i {

    width:18px;

    text-align:center;
}


.sidebar-link:hover {

    background:
        rgba(255,255,255,.08);

    color:white;
}


.sidebar-link.active {

    background:
        var(--sidebar-hover);

    color:white;

    border-left:
        3px solid var(--armor);
}


/* ============================================================
   MAIN
============================================================ */

.main-content {

    margin-left:245px;

    min-height:100vh;
}


.top-header {

    height:67px;

    background:white;

    border-bottom:
        1px solid var(--border);

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:
        0 28px;

    position:sticky;

    top:0;

    z-index:900;
}


.menu-toggle {

    border:0;

    background:transparent;

    font-size:20px;

    color:#657279;

    display:none;
}


.top-title {

    font-size:14px;

    color:var(--muted);
}


.top-user {

    display:flex;

    align-items:center;

    gap:12px;
}


.user-avatar {

    width:36px;

    height:36px;

    border-radius:50%;

    background:
        rgba(245,130,32,.13);

    color:var(--armor);

    display:grid;

    place-items:center;
}


.user-info {

    line-height:1.2;
}


.user-info strong {

    display:block;

    font-size:13px;
}


.user-info small {

    color:var(--muted);

    font-size:11px;
}


.content-wrapper {

    padding:
        26px 28px 50px;
}


.breadcrumb-text {

    font-size:13px;

    color:#7D898E;

    margin-bottom:23px;
}


/* ============================================================
   KPI
============================================================ */

.metric-card {

    background:white;

    border:
        1px solid var(--border);

    border-radius:6px;

    padding:18px;

    height:100%;
}


.metric-label {

    color:var(--muted);

    font-size:12px;

    margin-bottom:5px;
}


.metric-value {

    font-size:26px;

    font-weight:700;

    line-height:1.2;
}


.metric-icon {

    width:42px;

    height:42px;

    border-radius:5px;

    display:grid;

    place-items:center;

    background:
        rgba(88,168,154,.12);

    color:var(--teal);

    font-size:17px;
}


/* ============================================================
   PANELS
============================================================ */

.work-panel {

    background:white;

    border:
        1px solid var(--border);

    border-radius:5px;

    box-shadow:
        0 1px 3px rgba(0,0,0,.03);

    margin-top:22px;
}


.panel-heading {

    padding:
        18px 20px;

    border-bottom:
        1px solid #EDF0F2;

    display:flex;

    align-items:center;

    justify-content:space-between;
}


.panel-heading h5 {

    margin:0;

    font-size:16px;

    font-weight:600;
}


/* ============================================================
   PERFORMANCE
============================================================ */

.performance-row {

    padding:
        16px 20px;

    border-bottom:
        1px solid #EDF0F2;
}


.performance-row:last-child {

    border-bottom:0;
}


.performance-title {

    font-size:13px;

    font-weight:600;
}


.performance-value {

    font-size:13px;

    font-weight:700;

    color:var(--teal);
}


.progress {

    height:7px;

    background:#EDF1F2;
}


.progress-bar {

    background:var(--teal);
}


/* ============================================================
   CATEGORY TABLE
============================================================ */

.report-table {

    margin:0;

    font-size:12px;
}


.report-table thead th {

    background:var(--teal);

    color:white;

    border:0;

    padding:
        12px 15px;

    font-size:11px;

    text-transform:uppercase;
}


.report-table tbody td {

    padding:
        13px 15px;

    vertical-align:middle;

    border-color:#EDF0F2;
}


/* ============================================================
   EXPORT
============================================================ */

.btn-export {

    background:var(--teal);

    border:
        1px solid var(--teal);

    color:white;

    border-radius:3px;

    font-size:12px;

    font-weight:600;

    padding:
        9px 16px;
}


.btn-export:hover {

    background:#478F83;

    color:white;
}


/* ============================================================
   RESPONSIVE
============================================================ */

@media(max-width:991px){

    .sidebar {

        transform:
            translateX(-100%);
    }


    .sidebar.open {

        transform:
            translateX(0);
    }


    .main-content {

        margin-left:0;
    }


    .menu-toggle {

        display:inline-block;
    }


    .top-header {

        padding:0 15px;
    }


    .content-wrapper {

        padding:
            20px 15px 40px;
    }
}


@media(max-width:575px){

    .user-info {

        display:none;
    }
}

</style>

</head>


<body>


<!-- ============================================================
     SIDEBAR
============================================================ -->

<aside
    class="sidebar"
    id="sidebar">


<div class="sidebar-logo">

    <a href="manager-dashboard.php">

        <img
            src="img/armor.jpg"
            alt="Armor Bangladesh Ltd.">

    </a>

</div>


<div class="sidebar-menu">


    <a
        href="manager-dashboard.php"
        class="sidebar-link">

        <i class="fa-solid fa-house"></i>

        Dashboard

    </a>


    <div class="menu-title">
        Task Management
    </div>


    <a
        href="manager-requests.php"
        class="sidebar-link">

        <i class="fa-solid fa-list-check"></i>

        Service Requests

    </a>


    <a
        href="manager-tech-updates.php"
        class="sidebar-link">

        <i class="fa-solid fa-bell"></i>

        Technician Updates

    </a>


    <a
        href="maintenance-overview.php"
        class="sidebar-link">

        <i class="fa-solid fa-screwdriver-wrench"></i>

        Maintenance

    </a>


    <div class="menu-title">
        Reconciliation
    </div>


    <a
        href="manager-dashboard.php"
        class="sidebar-link">

        <i class="fa-solid fa-scale-balanced"></i>

        Reconciliation

    </a>


    <div class="menu-title">
        Reporting
    </div>


    <a
        href="reports-overview.php"
        class="sidebar-link active">

        <i class="fa-solid fa-chart-column"></i>

        Reports

    </a>


    <div class="menu-title">
        Account
    </div>


    <a
        href="logout.php"
        class="sidebar-link">

        <i class="fa-solid fa-right-from-bracket"></i>

        Logout

    </a>


</div>

</aside>



<!-- ============================================================
     MAIN
============================================================ -->

<main class="main-content">


<header class="top-header">


    <div class="d-flex align-items-center gap-3">


        <button
            type="button"
            class="menu-toggle"
            id="menuToggle">

            <i class="fa-solid fa-bars"></i>

        </button>


        <div class="top-title">

            Armor Bangladesh Ltd.
            &nbsp;/&nbsp;
            Manager Portal

        </div>


    </div>



    <div class="top-user">


        <div class="user-avatar">

            <i class="fa-solid fa-user-tie"></i>

        </div>


        <div class="user-info">

            <strong>

                <?php
                echo htmlspecialchars(
                    $manager_name,
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>

            </strong>


            <small>

                <?php
                echo htmlspecialchars(
                    ucfirst(
                        $_SESSION["role"]
                        ?? "manager"
                    ),
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>

            </small>

        </div>


        <a
            href="logout.php"
            class="text-secondary"
            title="Logout">

            <i class="fa-solid fa-power-off"></i>

        </a>


    </div>


</header>



<div class="content-wrapper">


    <div class="breadcrumb-text">

        <strong>
            Manager Dashboard
        </strong>

        &nbsp;›&nbsp;

        Reports & Performance Metrics

    </div>


    <?php if ($error !== ""): ?>

        <div class="alert alert-warning">

            <?php
            echo htmlspecialchars(
                $error,
                ENT_QUOTES,
                "UTF-8"
            );
            ?>

        </div>

    <?php endif; ?>



    <!-- ========================================================
         MAIN METRICS
    ========================================================= -->

    <div class="row g-3">


        <div class="col-xl-3 col-md-6">

            <div class="metric-card">

                <div class="d-flex justify-content-between">

                    <div>

                        <div class="metric-label">
                            Total Requests
                        </div>

                        <div class="metric-value">
                            <?php echo $total_requests; ?>
                        </div>

                    </div>


                    <div class="metric-icon">

                        <i class="fa-solid fa-clipboard-list"></i>

                    </div>

                </div>

            </div>

        </div>



        <div class="col-xl-3 col-md-6">

            <div class="metric-card">

                <div class="d-flex justify-content-between">

                    <div>

                        <div class="metric-label">
                            Active Requests
                        </div>

                        <div class="metric-value">
                            <?php echo $active_requests; ?>
                        </div>

                    </div>


                    <div class="metric-icon">

                        <i class="fa-solid fa-spinner"></i>

                    </div>

                </div>

            </div>

        </div>



        <div class="col-xl-3 col-md-6">

            <div class="metric-card">

                <div class="d-flex justify-content-between">

                    <div>

                        <div class="metric-label">
                            Completed
                        </div>

                        <div class="metric-value">
                            <?php echo $completed_requests; ?>
                        </div>

                    </div>


                    <div class="metric-icon">

                        <i class="fa-solid fa-circle-check"></i>

                    </div>

                </div>

            </div>

        </div>



        <div class="col-xl-3 col-md-6">

            <div class="metric-card">

                <div class="d-flex justify-content-between">

                    <div>

                        <div class="metric-label">
                            Completion Rate
                        </div>

                        <div class="metric-value">

                            <?php
                            echo $completion_rate;
                            ?>%

                        </div>

                    </div>


                    <div class="metric-icon">

                        <i class="fa-solid fa-chart-line"></i>

                    </div>

                </div>

            </div>

        </div>


    </div>



    <div class="row g-3 mt-1">


        <div class="col-xl-3 col-md-6">

            <div class="metric-card">

                <div class="d-flex justify-content-between">

                    <div>

                        <div class="metric-label">
                            Critical Requests
                        </div>

                        <div class="metric-value">
                            <?php echo $critical_requests; ?>
                        </div>

                    </div>


                    <div class="metric-icon">

                        <i class="fa-solid fa-triangle-exclamation"></i>

                    </div>

                </div>

            </div>

        </div>



        <div class="col-xl-3 col-md-6">

            <div class="metric-card">

                <div class="d-flex justify-content-between">

                    <div>

                        <div class="metric-label">
                            Overdue Maintenance
                        </div>

                        <div class="metric-value">
                            <?php echo $overdue_maintenance; ?>
                        </div>

                    </div>


                    <div class="metric-icon">

                        <i class="fa-solid fa-calendar-xmark"></i>

                    </div>

                </div>

            </div>

        </div>



        <div class="col-xl-3 col-md-6">

            <div class="metric-card">

                <div class="d-flex justify-content-between">

                    <div>

                        <div class="metric-label">
                            Total Clients
                        </div>

                        <div class="metric-value">
                            <?php echo $total_clients; ?>
                        </div>

                    </div>


                    <div class="metric-icon">

                        <i class="fa-solid fa-building"></i>

                    </div>

                </div>

            </div>

        </div>



        <div class="col-xl-3 col-md-6">

            <div class="metric-card">

                <div class="d-flex justify-content-between">

                    <div>

                        <div class="metric-label">
                            Technicians
                        </div>

                        <div class="metric-value">
                            <?php echo $total_technicians; ?>
                        </div>

                    </div>


                    <div class="metric-icon">

                        <i class="fa-solid fa-user-gear"></i>

                    </div>

                </div>

            </div>

        </div>


    </div>



    <!-- ========================================================
         PERFORMANCE
    ========================================================= -->

    <div class="row g-3">


        <div class="col-xl-6">


            <section class="work-panel">


                <div class="panel-heading">

                    <h5>
                        Request Performance
                    </h5>

                </div>



                <div class="performance-row">


                    <div
                        class="d-flex
                               justify-content-between
                               mb-2">

                        <span class="performance-title">
                            Completion Rate
                        </span>

                        <span class="performance-value">
                            <?php echo $completion_rate; ?>%
                        </span>

                    </div>


                    <div class="progress">

                        <div
                            class="progress-bar"
                            style="width:
                            <?php
                            echo min(
                                100,
                                $completion_rate
                            );
                            ?>%">
                        </div>

                    </div>


                </div>



                <div class="performance-row">


                    <div
                        class="d-flex
                               justify-content-between
                               mb-2">

                        <span class="performance-title">
                            Submitted Requests
                        </span>

                        <span class="performance-value">
                            <?php echo $submitted_requests; ?>
                        </span>

                    </div>


                    <div class="text-muted small">

                        Requests currently waiting for manager action.

                    </div>


                </div>



                <div class="performance-row">


                    <div
                        class="d-flex
                               justify-content-between
                               mb-2">

                        <span class="performance-title">
                            High + Critical Priority
                        </span>

                        <span class="performance-value">

                            <?php
                            echo
                                $high_requests +
                                $critical_requests;
                            ?>

                        </span>

                    </div>


                    <div class="text-muted small">

                        Requests requiring greater operational attention.

                    </div>


                </div>



                <div class="performance-row">


                    <div
                        class="d-flex
                               justify-content-between
                               align-items-center">

                        <span class="performance-title">
                            Average Resolution Time
                        </span>


                        <span class="performance-value">

                            <?php if ($avg_resolution_days !== null): ?>

                                <?php
                                echo $avg_resolution_days;
                                ?>

                                Days

                            <?php else: ?>

                                Not Available

                            <?php endif; ?>

                        </span>

                    </div>


                    <?php if ($avg_resolution_days === null): ?>

                        <div class="text-muted small mt-1">

                            Add a completed_at field to client_requests
                            if you want exact resolution-time reporting.

                        </div>

                    <?php endif; ?>


                </div>


            </section>


        </div>



        <!-- =====================================================
             TOP PROBLEMS
        ====================================================== -->

        <div class="col-xl-6">


            <section class="work-panel">


                <div class="panel-heading">

                    <h5>
                        Most Reported Problems
                    </h5>

                </div>


                <div class="table-responsive">


                    <table
                        class="table
                               report-table
                               align-middle">


                        <thead>

                            <tr>

                                <th>
                                    Problem Category
                                </th>

                                <th>
                                    Requests
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php if (empty($top_categories)): ?>


                            <tr>

                                <td
                                    colspan="2"
                                    class="text-center
                                           py-4
                                           text-muted">

                                    No category data available.

                                </td>

                            </tr>


                        <?php else: ?>


                            <?php foreach ($top_categories as $category): ?>


                                <tr>

                                    <td>

                                        <?php
                                        echo htmlspecialchars(
                                            $category["category"]
                                            ?? "-",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );
                                        ?>

                                    </td>


                                    <td>

                                        <strong>

                                            <?php
                                            echo (int)(
                                                $category["total"]
                                                ?? 0
                                            );
                                            ?>

                                        </strong>

                                    </td>

                                </tr>


                            <?php endforeach; ?>


                        <?php endif; ?>


                        </tbody>


                    </table>


                </div>


            </section>


        </div>


    </div>



    <!-- ========================================================
         EXPORT
    ========================================================= -->

    <section class="work-panel">


        <div class="panel-heading">

            <div>

                <h5 class="mb-1">
                    Report Export
                </h5>

                <small class="text-muted">

                    Export operational data for further reporting.

                </small>

            </div>

        </div>


        <div class="p-3">


            <a
                href="report-export.php?type=csv"
                class="btn btn-export me-2">

                <i class="fa-solid fa-file-csv me-1"></i>

                Export CSV

            </a>


            <button
                type="button"
                class="btn
                       btn-outline-secondary
                       btn-sm"
                disabled>

                <i class="fa-solid fa-file-pdf me-1"></i>

                PDF Export

            </button>


            <small
                class="d-block
                       text-muted
                       mt-2">

                CSV can be implemented with plain PHP and is suitable
                for InfinityFree. PDF export should be added separately
                only after choosing a compatible PDF method.

            </small>


        </div>


    </section>


</div>


</main>



<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


<script>

const menuToggle =
    document.getElementById("menuToggle");

const sidebar =
    document.getElementById("sidebar");


if (menuToggle) {

    menuToggle.addEventListener(
        "click",
        function () {

            sidebar.classList.toggle("open");

        }
    );

}

</script>


</body>
</html>