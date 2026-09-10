<?php

require_once "config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* ============================================================
   CLIENT ONLY
============================================================ */

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "client"
) {
    header("Location: login.php");
    exit;
}


$user_id =
    (int)$_SESSION["user_id"];

$client_name =
    $_SESSION["name"] ?? "Client";

$error = "";

$rows = [];


/* ============================================================
   HELPERS
============================================================ */

function due_status(?string $next_date): string
{
    if (empty($next_date)) {
        return "Unknown";
    }

    try {

        $today =
            new DateTime(date("Y-m-d"));

        $next =
            new DateTime($next_date);


        if ($next < $today) {
            return "Overdue";
        }


        $days =
            (int)$today
                ->diff($next)
                ->format("%a");


        if ($days <= 7) {
            return "Due Soon";
        }


        return "On Schedule";

    } catch (Throwable $e) {

        return "Unknown";
    }
}


function due_class(string $status): string
{
    $status =
        strtolower(trim($status));


    if ($status === "overdue") {
        return "status-danger";
    }


    if ($status === "due soon") {
        return "status-warning";
    }


    if ($status === "on schedule") {
        return "status-success";
    }


    return "status-info";
}


function maintenance_class(string $status): string
{
    $status =
        strtolower(trim($status));


    if ($status === "completed") {
        return "status-success";
    }


    if ($status === "overdue") {
        return "status-danger";
    }


    if ($status === "due soon") {
        return "status-warning";
    }


    return "status-info";
}


/* ============================================================
   LOAD ONLY THIS CLIENT'S MAINTENANCE
============================================================ */

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            user_id,
            elevator_id,
            last_service_date,
            next_date,
            maintenance_type,
            status,
            created_at

        FROM maintenance_schedules

        WHERE user_id = ?

        ORDER BY
            next_date ASC,
            id DESC
    ");

    $stmt->execute([
        $user_id
    ]);


    $rows =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $error =
        "Could not load your maintenance schedules.";
}


/* ============================================================
   COUNTERS
============================================================ */

$total = count($rows);

$upcoming  = 0;
$due_soon  = 0;
$overdue   = 0;
$completed = 0;


foreach ($rows as $row) {

    $status =
        strtolower(
            trim(
                $row["status"]
                ?? ""
            )
        );


    if ($status === "completed") {

        $completed++;

        continue;
    }


    $due =
        due_status(
            $row["next_date"]
            ?? null
        );


    if ($due === "Overdue") {

        $overdue++;

    } elseif ($due === "Due Soon") {

        $due_soon++;

    } elseif ($due === "On Schedule") {

        $upcoming++;
    }
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
    My Maintenance | Armor Bangladesh Ltd.
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

    --table-head:#58A89A;

    --bg:#F5F7F8;

    --text:#25333A;

    --muted:#7A858B;

    --border:#E4E9EB;
}


* {
    box-sizing:border-box;
}


body {

    margin:0;

    background:var(--bg);

    color:var(--text);

    font-family:
        Arial,
        Helvetica,
        sans-serif;
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

    transition:.25s;
}


.sidebar-logo {

    min-height:78px;

    display:flex;

    align-items:center;

    padding:
        13px 20px;

    border-bottom:
        1px solid rgba(255,255,255,.09);
}


.sidebar-logo img {

    max-width:150px;

    max-height:55px;

    object-fit:contain;

    background:white;

    padding:
        5px 8px;

    border-radius:4px;
}


.sidebar-menu {

    padding:
        16px 10px;
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

    color:var(--muted);

    font-size:14px;
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

    font-size:11px;

    color:var(--muted);
}


.content-wrapper {

    padding:
        26px 28px 50px;
}


.breadcrumb-text {

    color:#7D898E;

    font-size:13px;

    margin-bottom:23px;
}


/* ============================================================
   KPI
============================================================ */

.summary-card {

    background:white;

    border:
        1px solid var(--border);

    border-radius:6px;

    padding:
        15px 17px;

    height:100%;
}


.summary-label {

    color:var(--muted);

    font-size:12px;

    margin-bottom:5px;
}


.summary-value {

    font-size:24px;

    font-weight:700;
}


.summary-icon {

    width:38px;

    height:38px;

    border-radius:5px;

    display:grid;

    place-items:center;

    background:
        rgba(88,168,154,.12);

    color:var(--table-head);
}


/* ============================================================
   PANEL
============================================================ */

.work-panel {

    background:white;

    border:
        1px solid var(--border);

    border-radius:5px;

    margin-top:22px;

    box-shadow:
        0 1px 3px rgba(0,0,0,.03);
}


.panel-heading {

    padding:
        18px 20px;

    border-bottom:
        1px solid #EDF0F2;

    display:flex;

    justify-content:space-between;

    align-items:center;
}


.panel-heading h5 {

    margin:0;

    font-size:16px;

    font-weight:600;
}


/* ============================================================
   TABLE
============================================================ */

.table-container {

    overflow-x:auto;
}


.maintenance-table {

    min-width:1000px;

    margin:0;

    font-size:12px;
}


.maintenance-table thead th {

    background:var(--table-head);

    color:white;

    border:0;

    padding:
        12px 11px;

    font-size:11px;

    text-transform:uppercase;

    white-space:nowrap;
}


.maintenance-table tbody td {

    padding:
        13px 11px;

    vertical-align:middle;

    border-color:#EDF0F2;

    color:#47555C;
}


.maintenance-table tbody tr:hover {

    background:#F9FCFB;
}


/* ============================================================
   STATUS
============================================================ */

.status-pill {

    display:inline-flex;

    align-items:center;

    padding:
        4px 9px;

    border-radius:20px;

    font-size:10px;

    font-weight:700;

    white-space:nowrap;
}


.status-success {

    background:#E4F6EE;

    color:#21875A;
}


.status-danger {

    background:#FDE9E8;

    color:#C04B45;
}


.status-warning {

    background:#FFF4D9;

    color:#9A7617;
}


.status-info {

    background:#E6F1FB;

    color:#3478B8;
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

        padding:
            0 15px;
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

    <a href="client-dashboard.php">

        <img
            src="img/armor.jpg"
            alt="Armor Bangladesh Ltd.">

    </a>

</div>


<div class="sidebar-menu">


    <a
        href="client-dashboard.php"
        class="sidebar-link">

        <i class="fa-solid fa-house"></i>

        Dashboard

    </a>


    <div class="menu-title">
        Service
    </div>


    <a
        href="client-dashboard.php#newRequest"
        class="sidebar-link">

        <i class="fa-solid fa-circle-plus"></i>

        New Request

    </a>


    <a
        href="client-requests.php"
        class="sidebar-link">

        <i class="fa-solid fa-list-check"></i>

        My Requests

    </a>


    <div class="menu-title">
        Maintenance
    </div>


    <a
        href="client-maintenance.php"
        class="sidebar-link active">

        <i class="fa-solid fa-screwdriver-wrench"></i>

        My Maintenance

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
            Client Portal

        </div>


    </div>



    <div class="top-user">


        <div class="user-avatar">

            <i class="fa-solid fa-user"></i>

        </div>


        <div class="user-info">

            <strong>

                <?php
                echo htmlspecialchars(
                    $client_name,
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>

            </strong>


            <small>
                Client
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
            Client Dashboard
        </strong>

        &nbsp;›&nbsp;

        My Maintenance

    </div>



    <?php if ($error !== ""): ?>

        <div class="alert alert-danger">

            <?php
            echo htmlspecialchars(
                $error,
                ENT_QUOTES,
                "UTF-8"
            );
            ?>

        </div>

    <?php endif; ?>



    <div class="mb-4">

        <h4 class="fw-bold mb-1">

            My Maintenance Schedule

        </h4>

        <div class="text-muted small">

            Maintenance schedules assigned to your terminals
            by Armor Bangladesh.

        </div>

    </div>



    <!-- ========================================================
         KPI
    ========================================================= -->

    <div class="row g-3">


        <div class="col-xl-3 col-md-6">

            <div class="summary-card">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <div class="summary-label">
                            Total Maintenance
                        </div>

                        <div class="summary-value">

                            <?php echo $total; ?>

                        </div>

                    </div>


                    <div class="summary-icon">

                        <i class="fa-solid fa-calendar-check"></i>

                    </div>

                </div>

            </div>

        </div>



        <div class="col-xl-3 col-md-6">

            <div class="summary-card">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <div class="summary-label">
                            Upcoming
                        </div>

                        <div class="summary-value">

                            <?php echo $upcoming; ?>

                        </div>

                    </div>


                    <div class="summary-icon">

                        <i class="fa-solid fa-calendar-days"></i>

                    </div>

                </div>

            </div>

        </div>



        <div class="col-xl-3 col-md-6">

            <div class="summary-card">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <div class="summary-label">
                            Due Soon
                        </div>

                        <div class="summary-value">

                            <?php echo $due_soon; ?>

                        </div>

                    </div>


                    <div class="summary-icon">

                        <i class="fa-solid fa-clock"></i>

                    </div>

                </div>

            </div>

        </div>



        <div class="col-xl-3 col-md-6">

            <div class="summary-card">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <div class="summary-label">
                            Overdue
                        </div>

                        <div class="summary-value">

                            <?php echo $overdue; ?>

                        </div>

                    </div>


                    <div class="summary-icon">

                        <i class="fa-solid fa-triangle-exclamation"></i>

                    </div>

                </div>

            </div>

        </div>


    </div>



    <!-- ========================================================
         MAINTENANCE TABLE
    ========================================================= -->

    <section class="work-panel">


        <div class="panel-heading">


            <div>

                <h5 class="mb-1">

                    Maintenance Schedule

                </h5>


                <small class="text-muted">

                    Only maintenance assigned to your account is shown.

                </small>

            </div>


            <span class="small text-muted">

                <?php echo count($rows); ?>

                schedule(s)

            </span>


        </div>



        <div class="table-container">


            <table
                class="table
                       maintenance-table
                       align-middle">


                <thead>


                    <tr>

                        <th>
                            Schedule ID
                        </th>

                        <th>
                            Terminal ID
                        </th>

                        <th>
                            Maintenance Type
                        </th>

                        <th>
                            Last Service
                        </th>

                        <th>
                            Next Due
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Due Check
                        </th>

                        <th>
                            Created
                        </th>

                    </tr>


                </thead>


                <tbody>


                <?php if (empty($rows)): ?>


                    <tr>

                        <td
                            colspan="8"
                            class="text-center py-5 text-muted">

                            <i
                                class="fa-regular
                                       fa-calendar-xmark
                                       fs-3
                                       d-block
                                       mb-2">
                            </i>

                            No maintenance schedules have
                            been assigned to your account.

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach ($rows as $row): ?>


                        <?php

                        $schedule_status =
                            $row["status"]
                            ?? "On Schedule";


                        $calculated_due =
                            due_status(
                                $row["next_date"]
                                ?? null
                            );

                        ?>


                        <tr>


                            <!-- ID -->

                            <td>

                                <strong>

                                    MT-<?php
                                    echo (int)$row["id"];
                                    ?>

                                </strong>

                            </td>



                            <!-- TERMINAL -->

                            <td>

                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $row["elevator_id"]
                                        ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </strong>

                            </td>



                            <!-- TYPE -->

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $row["maintenance_type"]
                                    ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>

                            </td>



                            <!-- LAST -->

                            <td>

                                <?php

                                echo !empty(
                                    $row["last_service_date"]
                                )
                                    ? htmlspecialchars(
                                        $row["last_service_date"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                    : "-";

                                ?>

                            </td>



                            <!-- NEXT -->

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $row["next_date"]
                                    ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>

                            </td>



                            <!-- DATABASE STATUS -->

                            <td>

                                <span
                                    class="status-pill
                                    <?php
                                    echo maintenance_class(
                                        $schedule_status
                                    );
                                    ?>">

                                    <?php
                                    echo htmlspecialchars(
                                        $schedule_status,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </span>

                            </td>



                            <!-- DUE -->

                            <td>

                                <span
                                    class="status-pill
                                    <?php
                                    echo due_class(
                                        $calculated_due
                                    );
                                    ?>">

                                    <?php
                                    echo htmlspecialchars(
                                        $calculated_due,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </span>

                            </td>



                            <!-- CREATED -->

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $row["created_at"]
                                    ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>


                </tbody>


            </table>


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