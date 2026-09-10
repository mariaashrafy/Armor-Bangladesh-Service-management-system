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
    !in_array($_SESSION["role"] ?? "", ["admin", "manager"], true)
) {
    header("Location: login.php");
    exit;
}

$manager_name = $_SESSION["name"] ?? "Manager";

$error = "";
$success = "";


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


function col_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
        AND table_name = ?
        AND column_name = ?
    ");

    $stmt->execute([$table, $column]);

    return (int)$stmt->fetchColumn() > 0;
}


function due_status(?string $next_date): string
{
    if (empty($next_date)) {
        return "Unknown";
    }

    try {

        $today = new DateTime(date("Y-m-d"));
        $next  = new DateTime($next_date);

        if ($next < $today) {
            return "Overdue";
        }

        $days = (int)$today->diff($next)->format("%a");

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
    $status = strtolower(trim($status));

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
    $status = strtolower(trim($status));

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
   CHECK TABLE
============================================================ */

$table = "maintenance_schedules";

$required_columns = [
    "user_id",
    "elevator_id",
    "last_service_date",
    "next_date",
    "maintenance_type",
    "status",
    "created_at"
];


if (!table_exists($pdo, $table)) {

    $error = "Maintenance table maintenance_schedules was not found.";

} else {

    foreach ($required_columns as $column) {

        if (!col_exists($pdo, $table, $column)) {

            $error =
                "Required column " .
                htmlspecialchars($column) .
                " is missing from maintenance_schedules.";

            break;
        }
    }
}


/* ============================================================
   ADD MAINTENANCE SCHEDULE
============================================================ */

if (
    $error === "" &&
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["add_schedule"])
) {

    $client_id =
        (int)($_POST["user_id"] ?? 0);

    $elevator_id =
        trim($_POST["elevator_id"] ?? "");

    $last_service_date =
        trim($_POST["last_service_date"] ?? "");

    $next_date =
        trim($_POST["next_date"] ?? "");

    $maintenance_type =
        trim($_POST["maintenance_type"] ?? "");

    $status =
        trim($_POST["status"] ?? "On Schedule");


    $allowed_statuses = [
        "On Schedule",
        "Due Soon",
        "Overdue",
        "Completed"
    ];


    if (
        $client_id <= 0 ||
        $elevator_id === "" ||
        $next_date === "" ||
        $maintenance_type === ""
    ) {

        $error =
            "Please complete Client, Terminal ID, Maintenance Type and Next Due Date.";

    } elseif (
        !in_array($status, $allowed_statuses, true)
    ) {

        $error = "Invalid maintenance status.";

    } else {

        try {

            /* Make sure selected user is actually a client */

            $check = $pdo->prepare("
                SELECT id
                FROM users
                WHERE id = ?
                AND LOWER(role) = 'client'
                LIMIT 1
            ");

            $check->execute([$client_id]);


            if (!$check->fetchColumn()) {

                $error = "Invalid client selected.";

            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO maintenance_schedules
                    (
                        user_id,
                        elevator_id,
                        last_service_date,
                        next_date,
                        maintenance_type,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $client_id,
                    $elevator_id,
                    $last_service_date !== ""
                        ? $last_service_date
                        : null,
                    $next_date,
                    $maintenance_type,
                    $status
                ]);


                $success =
                    "Maintenance schedule added successfully.";
            }

        } catch (Throwable $e) {

            $error =
                "Could not add maintenance schedule.";
        }
    }
}


/* ============================================================
   LOAD CLIENTS
============================================================ */

$clients = [];

try {

    $stmt = $pdo->query("
        SELECT
            id,
            name,
            email
        FROM users
        WHERE LOWER(role) = 'client'
        ORDER BY name ASC
    ");

    $clients =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $clients = [];
}


/* ============================================================
   LOAD MAINTENANCE
============================================================ */

$rows = [];

$total = 0;
$overdue = 0;
$due_soon = 0;
$completed = 0;


if ($error === "") {

    try {

        /*
        | Join users so manager sees client name/email
        | instead of only user_id.
        */

        $stmt = $pdo->query("
            SELECT
                ms.id,
                ms.user_id,
                ms.elevator_id,
                ms.last_service_date,
                ms.next_date,
                ms.maintenance_type,
                ms.status,
                ms.created_at,

                u.name AS client_name,
                u.email AS client_email

            FROM maintenance_schedules ms

            LEFT JOIN users u
                ON u.id = ms.user_id

            ORDER BY
                ms.next_date ASC,
                ms.id DESC
        ");

        $rows =
            $stmt->fetchAll(PDO::FETCH_ASSOC);


        $total = count($rows);


        foreach ($rows as $row) {

            $calculated_due =
                due_status($row["next_date"] ?? null);


            if ($calculated_due === "Overdue") {
                $overdue++;
            }


            if ($calculated_due === "Due Soon") {
                $due_soon++;
            }


            if (
                strtolower(
                    trim($row["status"] ?? "")
                ) === "completed"
            ) {
                $completed++;
            }
        }

    } catch (Throwable $e) {

        $rows = [];

        $error =
            "Something went wrong while loading maintenance schedules.";
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
    Maintenance Overview | Armor Bangladesh Ltd.
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

    transition:.25s;
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

    color:rgba(255,255,255,.45);

    font-size:11px;

    text-transform:uppercase;

    letter-spacing:1.3px;

    padding:16px 14px 7px;
}


.sidebar-link {

    display:flex;

    align-items:center;

    gap:13px;

    min-height:44px;

    padding:10px 14px;

    margin-bottom:3px;

    border-radius:5px;

    text-decoration:none;

    color:rgba(255,255,255,.78);

    font-size:14px;

    transition:.2s;
}


.sidebar-link i {

    width:18px;

    text-align:center;
}


.sidebar-link:hover {

    background:rgba(255,255,255,.08);

    color:white;
}


.sidebar-link.active {

    background:var(--sidebar-hover);

    color:white;

    border-left:3px solid var(--armor);
}


/* ============================================================
   MAIN AREA
============================================================ */

.main-content {

    margin-left:245px;

    min-height:100vh;
}


.top-header {

    height:67px;

    background:white;

    border-bottom:1px solid var(--border);

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:0 28px;

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

    background:rgba(245,130,32,.13);

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

    padding:26px 28px 50px;
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

    border:1px solid var(--border);

    border-radius:6px;

    padding:15px 17px;

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

    background:rgba(88,168,154,.12);

    color:var(--table-head);
}


/* ============================================================
   PANELS
============================================================ */

.work-panel {

    background:white;

    border:1px solid var(--border);

    border-radius:5px;

    margin-top:22px;

    box-shadow:
        0 1px 3px rgba(0,0,0,.03);
}


.panel-heading {

    padding:18px 20px;

    border-bottom:1px solid #EDF0F2;
}


.panel-heading h5 {

    margin:0;

    font-size:16px;

    font-weight:600;
}


/* ============================================================
   FORM
============================================================ */

.schedule-form {

    padding:20px;
}


.form-label {

    font-size:12px;

    font-weight:600;

    color:#47565D;
}


.form-control,
.form-select {

    min-height:41px;

    border-radius:3px;

    border:1px solid #D9DFE2;

    font-size:12px;
}


.form-control:focus,
.form-select:focus {

    border-color:var(--table-head);

    box-shadow:
        0 0 0 .15rem rgba(88,168,154,.15);
}


.btn-save {

    background:var(--table-head);

    border:1px solid var(--table-head);

    color:white;

    border-radius:3px;

    min-height:41px;

    padding:0 22px;

    font-size:12px;

    font-weight:600;
}


.btn-save:hover {

    background:#478F83;

    color:white;
}


/* ============================================================
   TABLE
============================================================ */

.table-container {
    overflow-x:auto;
}


.maintenance-table {

    min-width:1100px;

    margin:0;

    font-size:12px;
}


.maintenance-table thead th {

    background:var(--table-head);

    color:white;

    border:0;

    padding:12px 11px;

    font-size:11px;

    text-transform:uppercase;

    white-space:nowrap;
}


.maintenance-table tbody td {

    padding:13px 11px;

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

    padding:4px 9px;

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

        transform:translateX(-100%);
    }


    .sidebar.open {

        transform:translateX(0);
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

        padding:20px 15px 40px;
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
            class="sidebar-link active">

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
            class="sidebar-link">

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
                    ucfirst($_SESSION["role"] ?? "manager"),
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

        <strong>Manager Dashboard</strong>

        &nbsp;›&nbsp;

        Maintenance Overview

    </div>


    <?php if ($error !== ""): ?>

        <div class="alert alert-danger">

            <?php echo $error; ?>

        </div>

    <?php endif; ?>


    <?php if ($success !== ""): ?>

        <div class="alert alert-success">

            <i class="fa-solid fa-circle-check me-1"></i>

            <?php
            echo htmlspecialchars(
                $success,
                ENT_QUOTES,
                "UTF-8"
            );
            ?>

        </div>

    <?php endif; ?>



    <?php if ($error === ""): ?>


    <!-- ========================================================
         KPI CARDS
    ========================================================= -->

    <div class="row g-3">


        <div class="col-xl-3 col-md-6">

            <div class="summary-card">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <div class="summary-label">
                            Total Scheduled
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



        <div class="col-xl-3 col-md-6">

            <div class="summary-card">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <div class="summary-label">
                            Completed
                        </div>

                        <div class="summary-value">
                            <?php echo $completed; ?>
                        </div>

                    </div>


                    <div class="summary-icon">

                        <i class="fa-solid fa-circle-check"></i>

                    </div>

                </div>

            </div>

        </div>


    </div>



    <!-- ========================================================
         ADD SCHEDULE
    ========================================================= -->

    <section class="work-panel">


        <div class="panel-heading">

            <h5>

                <i
                    class="fa-solid fa-calendar-plus me-2"
                    style="color:#F58220;">
                </i>

                Add Maintenance Schedule

            </h5>

        </div>


        <form
            method="POST"
            class="schedule-form">


            <div class="row g-3">


                <!-- CLIENT -->

                <div class="col-xl-4 col-md-6">

                    <label class="form-label">
                        Client *
                    </label>


                    <select
                        name="user_id"
                        class="form-select"
                        required>


                        <option value="">
                            Select Client
                        </option>


                        <?php foreach ($clients as $client): ?>


                            <option
                                value="<?php echo (int)$client["id"]; ?>">


                                <?php
                                echo htmlspecialchars(
                                    $client["name"]
                                    .
                                    " (" .
                                    $client["email"]
                                    . ")",
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>


                            </option>


                        <?php endforeach; ?>


                    </select>

                </div>



                <!-- TERMINAL -->

                <div class="col-xl-4 col-md-6">

                    <label class="form-label">
                        Terminal ID *
                    </label>


                    <input
                        type="text"
                        name="elevator_id"
                        class="form-control"
                        placeholder="Example: ATM-DHK-001"
                        required>

                </div>



                <!-- TYPE -->

                <div class="col-xl-4 col-md-6">

                    <label class="form-label">
                        Maintenance Type *
                    </label>


                    <select
                        name="maintenance_type"
                        class="form-select"
                        required>


                        <option value="">
                            Select Type
                        </option>


                        <option value="Preventive Maintenance">
                            Preventive Maintenance
                        </option>


                        <option value="Monthly Maintenance">
                            Monthly Maintenance
                        </option>


                        <option value="Quarterly Maintenance">
                            Quarterly Maintenance
                        </option>


                        <option value="Annual Maintenance">
                            Annual Maintenance
                        </option>


                        <option value="Inspection">
                            Inspection
                        </option>


                        <option value="Emergency Maintenance">
                            Emergency Maintenance
                        </option>


                    </select>

                </div>



                <!-- LAST -->

                <div class="col-xl-3 col-md-6">

                    <label class="form-label">
                        Last Service Date
                    </label>


                    <input
                        type="date"
                        name="last_service_date"
                        class="form-control">

                </div>



                <!-- NEXT -->

                <div class="col-xl-3 col-md-6">

                    <label class="form-label">
                        Next Due Date *
                    </label>


                    <input
                        type="date"
                        name="next_date"
                        class="form-control"
                        required>

                </div>



                <!-- STATUS -->

                <div class="col-xl-3 col-md-6">

                    <label class="form-label">
                        Status
                    </label>


                    <select
                        name="status"
                        class="form-select">


                        <option value="On Schedule">
                            On Schedule
                        </option>


                        <option value="Due Soon">
                            Due Soon
                        </option>


                        <option value="Overdue">
                            Overdue
                        </option>


                        <option value="Completed">
                            Completed
                        </option>


                    </select>

                </div>



                <!-- SAVE -->

                <div class="col-xl-3 col-md-6 d-flex align-items-end">

                    <button
                        type="submit"
                        name="add_schedule"
                        class="btn btn-save w-100">

                        <i class="fa-solid fa-floppy-disk me-2"></i>

                        Save Schedule

                    </button>

                </div>


            </div>


        </form>


    </section>



    <!-- ========================================================
         MAINTENANCE TABLE
    ========================================================= -->

    <section class="work-panel">


        <div
            class="panel-heading
                   d-flex
                   justify-content-between
                   align-items-center">


            <div>

                <h5 class="mb-1">
                    Maintenance Overview
                </h5>

                <small class="text-muted">

                    Maintenance schedules assigned to Armor clients.

                </small>

            </div>


            <span class="small text-muted">

                <?php echo count($rows); ?>

                record(s)

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
                            Client
                        </th>

                        <th>
                            Terminal ID
                        </th>

                        <th>
                            Last Service
                        </th>

                        <th>
                            Next Due
                        </th>

                        <th>
                            Maintenance Type
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Due Check
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

                            No maintenance schedules found.

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach ($rows as $row): ?>


                        <?php

                        $schedule_status =
                            $row["status"] ?? "On Schedule";

                        $calculated_due =
                            due_status(
                                $row["next_date"] ?? null
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



                            <!-- CLIENT -->

                            <td>


                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $row["client_name"]
                                        ?? "Client",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </strong>


                                <?php
                                if (!empty($row["client_email"])):
                                ?>

                                    <small class="d-block text-muted">

                                        <?php
                                        echo htmlspecialchars(
                                            $row["client_email"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );
                                        ?>

                                    </small>

                                <?php endif; ?>


                            </td>



                            <!-- TERMINAL -->

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $row["elevator_id"]
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



                            <!-- CALCULATED DUE -->

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


                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>


                </tbody>


            </table>


        </div>


    </section>


    <?php endif; ?>


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