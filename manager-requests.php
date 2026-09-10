<?php

require_once "config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* ============================================================
   MANAGER / ADMIN ACCESS ONLY
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

$error   = "";
$success = "";


/* ============================================================
   LOAD TECHNICIANS
============================================================ */

$technicians = [];

try {

    $stTech = $pdo->prepare("
        SELECT
            id,
            name
        FROM users
        WHERE LOWER(role) = 'technician'
        ORDER BY name ASC
    ");

    $stTech->execute();

    $technicians =
        $stTech->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $technicians = [];

}


/* ============================================================
   UPDATE REQUEST
   Status + Technician Assignment
============================================================ */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["update_status"])
) {

    $id =
        (int)($_POST["id"] ?? 0);

    $status =
        trim($_POST["status"] ?? "");

    $technician_id =
        trim($_POST["technician_id"] ?? "");


    $allowed_statuses = [
        "Submitted",
        "Assigned",
        "Processing",
        "Pending",
        "Completed",
        "Closed"
    ];


    if (
        $id <= 0 ||
        !in_array(
            $status,
            $allowed_statuses,
            true
        )
    ) {

        $error =
            "Invalid request update.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Technician Selected
            |--------------------------------------------------------------------------
            */

            if ($technician_id !== "") {

                /*
                | Verify selected user is actually a technician.
                */

                $checkTech = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE id = ?
                    AND LOWER(role) = 'technician'
                    LIMIT 1
                ");

                $checkTech->execute([
                    (int)$technician_id
                ]);


                if (!$checkTech->fetchColumn()) {

                    $error =
                        "Invalid technician selected.";

                } else {

                    /*
                    | Automatically change Submitted -> Assigned
                    | when a technician is assigned.
                    */

                    if ($status === "Submitted") {
                        $status = "Assigned";
                    }


                    $stmt = $pdo->prepare("
                        UPDATE client_requests
                        SET
                            status = ?,
                            technician_id = ?,
                            assigned_at = NOW()
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        $status,
                        (int)$technician_id,
                        $id
                    ]);


                    $success =
                        "Request updated and technician assigned successfully.";

                }

            } else {

                /*
                |--------------------------------------------------------------------------
                | No new technician selected
                |--------------------------------------------------------------------------
                | Keep the existing technician and update status only.
                */

                $stmt = $pdo->prepare("
                    UPDATE client_requests
                    SET status = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $status,
                    $id
                ]);


                $success =
                    "Request status updated successfully.";

            }

        } catch (Throwable $e) {

            $error =
                "Could not update the request. Please try again.";

        }

    }

}


/* ============================================================
   FILTERS
============================================================ */

$filter_status =
    trim($_GET["status"] ?? "");

$filter_priority =
    trim($_GET["priority"] ?? "");

$filter_search =
    trim($_GET["search"] ?? "");


/* ============================================================
   FETCH CLIENT REQUESTS
============================================================ */

$requests = [];

try {

    $sql = "
        SELECT
            cr.*,
            u.name AS client_name,
            u.email AS client_email,
            t.name AS technician_name
        FROM client_requests cr

        LEFT JOIN users u
            ON u.id = cr.user_id

        LEFT JOIN users t
            ON t.id = cr.technician_id

        WHERE 1 = 1
    ";


    $params = [];


    /*
    |--------------------------------------------------------------------------
    | Status Filter
    |--------------------------------------------------------------------------
    */

    if ($filter_status !== "") {

        $sql .= "
            AND LOWER(cr.status) = LOWER(?)
        ";

        $params[] =
            $filter_status;

    }


    /*
    |--------------------------------------------------------------------------
    | Priority Filter
    |--------------------------------------------------------------------------
    */

    if ($filter_priority !== "") {

        $sql .= "
            AND LOWER(cr.priority) = LOWER(?)
        ";

        $params[] =
            $filter_priority;

    }


    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */

    if ($filter_search !== "") {

        $sql .= "
            AND (
                CAST(cr.id AS CHAR) LIKE ?
                OR cr.elevator_id LIKE ?
                OR cr.category LIKE ?
                OR cr.description LIKE ?
                OR u.name LIKE ?
                OR u.email LIKE ?
            )
        ";


        $search_term =
            "%" . $filter_search . "%";


        for ($i = 0; $i < 6; $i++) {
            $params[] = $search_term;
        }

    }


    $sql .= "
        ORDER BY cr.id DESC
    ";


    $stmt =
        $pdo->prepare($sql);

    $stmt->execute($params);

    $requests =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $requests = [];

    $error =
        "Could not load service requests.";

}


/* ============================================================
   COUNTERS
============================================================ */

$total_requests     = 0;
$submitted_requests = 0;
$active_requests    = 0;
$completed_requests = 0;


try {

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM client_requests
    ");

    $total_requests =
        (int)$stmt->fetchColumn();


    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM client_requests
        WHERE LOWER(status) = 'submitted'
    ");

    $submitted_requests =
        (int)$stmt->fetchColumn();


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

} catch (Throwable $e) {

    // keep defaults

}


/* ============================================================
   STATUS CLASS
============================================================ */

function request_status_class(string $status): string
{

    $status =
        strtolower(trim($status));


    if (
        in_array(
            $status,
            ["completed", "closed"],
            true
        )
    ) {
        return "status-success";
    }


    if (
        in_array(
            $status,
            ["assigned", "processing"],
            true
        )
    ) {
        return "status-progress";
    }


    if ($status === "pending") {

        return "status-pending";

    }


    return "status-new";

}


/* ============================================================
   PRIORITY CLASS
============================================================ */

function request_priority_class(string $priority): string
{

    $priority =
        strtolower(trim($priority));


    return match ($priority) {

        "critical" =>
            "priority-critical",

        "high" =>
            "priority-high",

        "medium" =>
            "priority-medium",

        default =>
            "priority-low"

    };

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
        Service Requests | Armor Bangladesh Ltd.
    </title>


    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


    <!-- Font Awesome -->
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        rel="stylesheet">


    <style>

        :root {

            --armor: #F58220;

            --sidebar: #123D36;
            --sidebar-dark: #0E312C;
            --sidebar-hover: #1A5148;

            --table-head: #58A89A;

            --page-bg: #F5F7F8;

            --text-dark: #25333A;

            --muted: #7A858B;

            --border: #E4E9EB;

        }


        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            background:
                var(--page-bg);

            color:
                var(--text-dark);

            font-family:
                Arial,
                Helvetica,
                sans-serif;

        }


        /* =====================================================
           SIDEBAR
        ===================================================== */

        .sidebar {

            position: fixed;

            left: 0;
            top: 0;

            width: 245px;

            height: 100vh;

            background:
                linear-gradient(
                    180deg,
                    var(--sidebar),
                    var(--sidebar-dark)
                );

            color: white;

            z-index: 1000;

            overflow-y: auto;

            transition: .25s ease;

        }


        .sidebar-logo {

            min-height: 78px;

            display: flex;

            align-items: center;

            padding:
                13px 20px;

            border-bottom:
                1px solid rgba(255,255,255,.09);

        }


        .sidebar-logo img {

            max-width: 150px;

            max-height: 55px;

            object-fit: contain;

            background: white;

            padding: 5px 8px;

            border-radius: 4px;

        }


        .sidebar-menu {

            padding:
                16px 10px;

        }


        .menu-title {

            color:
                rgba(255,255,255,.45);

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: 1.3px;

            padding:
                16px 14px 7px;

        }


        .sidebar-link {

            display: flex;

            align-items: center;

            gap: 13px;

            min-height: 44px;

            padding:
                10px 14px;

            margin-bottom: 3px;

            border-radius: 5px;

            text-decoration: none;

            color:
                rgba(255,255,255,.78);

            font-size: 14px;

            transition: .2s;

        }


        .sidebar-link i {

            width: 18px;

            text-align: center;

        }


        .sidebar-link:hover {

            background:
                rgba(255,255,255,.08);

            color: white;

        }


        .sidebar-link.active {

            background:
                var(--sidebar-hover);

            color: white;

            border-left:
                3px solid var(--armor);

        }


        /* =====================================================
           MAIN
        ===================================================== */

        .main-content {

            margin-left: 245px;

            min-height: 100vh;

        }


        .top-header {

            height: 67px;

            background: white;

            border-bottom:
                1px solid var(--border);

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                0 28px;

            position: sticky;

            top: 0;

            z-index: 900;

        }


        .menu-toggle {

            border: 0;

            background: transparent;

            font-size: 20px;

            color: #657279;

            display: none;

        }


        .top-title {

            font-size: 14px;

            color:
                var(--muted);

        }


        .top-user {

            display: flex;

            align-items: center;

            gap: 12px;

        }


        .user-avatar {

            width: 36px;

            height: 36px;

            border-radius: 50%;

            background:
                rgba(245,130,32,.13);

            color:
                var(--armor);

            display: grid;

            place-items: center;

        }


        .user-info {

            line-height: 1.2;

        }


        .user-info strong {

            display: block;

            font-size: 13px;

        }


        .user-info small {

            color:
                var(--muted);

            font-size: 11px;

        }


        .content-wrapper {

            padding:
                26px 28px 50px;

        }


        .breadcrumb-text {

            font-size: 13px;

            color:
                #7D898E;

            margin-bottom: 23px;

        }


        /* =====================================================
           SUMMARY
        ===================================================== */

        .summary-card {

            background: white;

            border:
                1px solid var(--border);

            border-radius: 6px;

            padding:
                15px 17px;

            height: 100%;

        }


        .summary-label {

            color:
                var(--muted);

            font-size: 12px;

            margin-bottom: 5px;

        }


        .summary-value {

            font-size: 24px;

            font-weight: 700;

        }


        .summary-icon {

            width: 38px;

            height: 38px;

            border-radius: 5px;

            background:
                rgba(88,168,154,.12);

            color:
                var(--table-head);

            display: grid;

            place-items: center;

        }


        /* =====================================================
           PANEL
        ===================================================== */

        .work-panel {

            background: white;

            border:
                1px solid var(--border);

            border-radius: 5px;

            margin-top: 22px;

            box-shadow:
                0 1px 3px
                rgba(0,0,0,.03);

        }


        .panel-heading {

            padding:
                18px 20px;

            border-bottom:
                1px solid #EDF0F2;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

        }


        /* =====================================================
           FILTERS
        ===================================================== */

        .filter-area {

            padding:
                15px 20px 20px;

        }


        .filter-control {

            height: 39px;

            border-radius: 3px;

            border:
                1px solid #D9DFE2;

            font-size: 12px;

        }


        .btn-filter {

            height: 39px;

            background:
                var(--table-head);

            border:
                1px solid var(--table-head);

            color: white;

            border-radius: 3px;

            font-size: 12px;

            font-weight: 600;

        }


        /* =====================================================
           TABLE
        ===================================================== */

        .table-container {

            overflow-x: auto;

        }


        .request-table {

            min-width: 1450px;

            margin: 0;

            font-size: 12px;

        }


        .request-table thead th {

            background:
                var(--table-head);

            color: white;

            border: 0;

            padding:
                12px 11px;

            font-size: 11px;

            font-weight: 600;

            text-transform: uppercase;

            white-space: nowrap;

        }


        .request-table tbody td {

            padding:
                13px 11px;

            vertical-align: middle;

            border-color:
                #EDF0F2;

            color:
                #47555C;

        }


        .request-table tbody tr:hover {

            background:
                #F9FCFB;

        }


        .problem-description {

            max-width: 320px;

            min-width: 230px;

            white-space: normal;

            line-height: 1.45;

        }


        /* =====================================================
           STATUS
        ===================================================== */

        .status-pill,
        .priority-pill {

            display: inline-flex;

            align-items: center;

            padding:
                4px 9px;

            border-radius: 20px;

            font-size: 10px;

            font-weight: 700;

            white-space: nowrap;

        }


        .status-success {

            background: #E4F6EE;
            color: #21875A;

        }


        .status-progress {

            background: #E6F1FB;
            color: #3478B8;

        }


        .status-pending {

            background: #EEEAF8;
            color: #67509B;

        }


        .status-new {

            background: #FFF4D9;
            color: #9A7617;

        }


        .priority-low {

            background: #EEF2F4;
            color: #637078;

        }


        .priority-medium {

            background: #E6F1FB;
            color: #3478B8;

        }


        .priority-high {

            background: #FFF4D9;
            color: #9A7617;

        }


        .priority-critical {

            background: #FDE9E8;
            color: #C04B45;

        }


        /* =====================================================
           UPDATE FORM
        ===================================================== */

        .update-form {

            display: flex;

            gap: 6px;

            align-items: center;

            min-width: 430px;

        }


        .update-form .form-select {

            font-size: 11px;

            border-radius: 3px;

        }


        .btn-update {

            background:
                var(--table-head);

            border:
                1px solid var(--table-head);

            color: white;

            border-radius: 3px;

        }


        .btn-update:hover {

            background: #478F83;

            color: white;

        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 991px) {

            .sidebar {

                transform:
                    translateX(-100%);

            }


            .sidebar.open {

                transform:
                    translateX(0);

            }


            .main-content {

                margin-left: 0;

            }


            .menu-toggle {

                display:
                    inline-block;

            }


            .content-wrapper {

                padding:
                    20px 15px 40px;

            }


            .top-header {

                padding:
                    0 15px;

            }

        }


        @media (max-width: 575px) {

            .user-info {

                display: none;

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
            class="sidebar-link active">

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
     MAIN CONTENT
============================================================ -->

<main class="main-content">


    <!-- TOP HEADER -->

    <header class="top-header">


        <div
            class="d-flex
                   align-items-center
                   gap-3">


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

            Client Service Requests

        </div>



        <!-- =====================================================
             MESSAGES
        ====================================================== -->

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


        <?php if ($success !== ""): ?>

            <div class="alert alert-success">

                <?php
                echo htmlspecialchars(
                    $success,
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>

            </div>

        <?php endif; ?>



        <!-- =====================================================
             SUMMARY
        ====================================================== -->

        <div class="row g-3">


            <div class="col-xl-3 col-md-6">

                <div class="summary-card">

                    <div
                        class="d-flex
                               justify-content-between
                               align-items-center">

                        <div>

                            <div class="summary-label">
                                Total Requests
                            </div>

                            <div class="summary-value">

                                <?php
                                echo $total_requests;
                                ?>

                            </div>

                        </div>


                        <div class="summary-icon">

                            <i class="fa-solid fa-list-check"></i>

                        </div>

                    </div>

                </div>

            </div>



            <div class="col-xl-3 col-md-6">

                <div class="summary-card">

                    <div
                        class="d-flex
                               justify-content-between
                               align-items-center">

                        <div>

                            <div class="summary-label">
                                New / Submitted
                            </div>

                            <div class="summary-value">

                                <?php
                                echo $submitted_requests;
                                ?>

                            </div>

                        </div>


                        <div class="summary-icon">

                            <i class="fa-solid fa-bell"></i>

                        </div>

                    </div>

                </div>

            </div>



            <div class="col-xl-3 col-md-6">

                <div class="summary-card">

                    <div
                        class="d-flex
                               justify-content-between
                               align-items-center">

                        <div>

                            <div class="summary-label">
                                Active Work
                            </div>

                            <div class="summary-value">

                                <?php
                                echo $active_requests;
                                ?>

                            </div>

                        </div>


                        <div class="summary-icon">

                            <i class="fa-solid fa-spinner"></i>

                        </div>

                    </div>

                </div>

            </div>



            <div class="col-xl-3 col-md-6">

                <div class="summary-card">

                    <div
                        class="d-flex
                               justify-content-between
                               align-items-center">

                        <div>

                            <div class="summary-label">
                                Completed
                            </div>

                            <div class="summary-value">

                                <?php
                                echo $completed_requests;
                                ?>

                            </div>

                        </div>


                        <div class="summary-icon">

                            <i class="fa-solid fa-circle-check"></i>

                        </div>

                    </div>

                </div>

            </div>


        </div>



        <!-- =====================================================
             REQUEST PANEL
        ====================================================== -->

        <section class="work-panel">


            <div class="panel-heading">


                <div>

                    <h5 class="mb-1">

                        Client Service Requests

                    </h5>


                    <small class="text-muted">

                        Review client problems,
                        assign technicians and update work status.

                    </small>

                </div>


                <span class="small text-muted">

                    <?php
                    echo count($requests);
                    ?>

                    result(s)

                </span>


            </div>



            <!-- FILTERS -->

            <div class="filter-area">


                <form
                    method="GET"
                    action="manager-requests.php">


                    <div class="row g-2">


                        <div class="col-xl-2 col-md-4">

                            <select
                                name="status"
                                class="form-select filter-control">

                                <option value="">
                                    All Status
                                </option>

                                <option
                                    value="Submitted"
                                    <?= $filter_status === "Submitted" ? "selected" : "" ?>>

                                    Submitted

                                </option>

                                <option
                                    value="Assigned"
                                    <?= $filter_status === "Assigned" ? "selected" : "" ?>>

                                    Assigned

                                </option>

                                <option
                                    value="Processing"
                                    <?= $filter_status === "Processing" ? "selected" : "" ?>>

                                    Processing

                                </option>

                                <option
                                    value="Pending"
                                    <?= $filter_status === "Pending" ? "selected" : "" ?>>

                                    Pending

                                </option>

                                <option
                                    value="Completed"
                                    <?= $filter_status === "Completed" ? "selected" : "" ?>>

                                    Completed

                                </option>

                                <option
                                    value="Closed"
                                    <?= $filter_status === "Closed" ? "selected" : "" ?>>

                                    Closed

                                </option>

                            </select>

                        </div>



                        <div class="col-xl-2 col-md-4">

                            <select
                                name="priority"
                                class="form-select filter-control">

                                <option value="">
                                    All Priority
                                </option>

                                <option
                                    value="Critical"
                                    <?= $filter_priority === "Critical" ? "selected" : "" ?>>

                                    Critical

                                </option>

                                <option
                                    value="High"
                                    <?= $filter_priority === "High" ? "selected" : "" ?>>

                                    High

                                </option>

                                <option
                                    value="Medium"
                                    <?= $filter_priority === "Medium" ? "selected" : "" ?>>

                                    Medium

                                </option>

                                <option
                                    value="Low"
                                    <?= $filter_priority === "Low" ? "selected" : "" ?>>

                                    Low

                                </option>

                            </select>

                        </div>



                        <div class="col-xl-5 col-md-4">

                            <input
                                type="text"
                                name="search"
                                value="<?=
                                    htmlspecialchars(
                                        $filter_search,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                ?>"
                                class="form-control filter-control"
                                placeholder="Request ID, client, terminal, problem or description">

                        </div>



                        <div class="col-xl-1 col-md-6">

                            <button
                                type="submit"
                                class="btn btn-filter w-100">

                                <i class="fa-solid fa-search"></i>

                            </button>

                        </div>



                        <div class="col-xl-2 col-md-6">

                            <a
                                href="manager-requests.php"
                                class="btn
                                       btn-outline-secondary
                                       w-100
                                       d-flex
                                       align-items-center
                                       justify-content-center"
                                style="height:39px; border-radius:3px; font-size:12px;">

                                Reset

                            </a>

                        </div>


                    </div>


                </form>


            </div>



            <!-- =================================================
                 TABLE
            ================================================== -->

            <div class="table-container">


                <table
                    class="table
                           request-table
                           align-middle">


                    <thead>

                        <tr>

                            <th>
                                Request ID
                            </th>

                            <th>
                                Client
                            </th>

                            <th>
                                Terminal ID
                            </th>

                            <th>
                                Problem
                            </th>

                            <th>
                                Description
                            </th>

                            <th>
                                Priority
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Assigned Technician
                            </th>

                            <th>
                                Manage Request
                            </th>

                            <th>
                                Assigned At
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (empty($requests)): ?>

                        <tr>

                            <td
                                colspan="10"
                                class="text-center
                                       py-5
                                       text-muted">

                                <i
                                    class="fa-regular
                                           fa-folder-open
                                           fs-3
                                           d-block
                                           mb-2">
                                </i>

                                No service requests found.

                            </td>

                        </tr>

                    <?php endif; ?>



                    <?php foreach ($requests as $r): ?>


                        <?php

                        $status =
                            $r["status"]
                            ?? "Submitted";

                        $priority =
                            $r["priority"]
                            ?? "Low";

                        ?>


                        <tr>


                            <!-- REQUEST -->

                            <td>

                                <strong>

                                    SR-<?=
                                    (int)$r["id"]
                                    ?>

                                </strong>

                            </td>



                            <!-- CLIENT -->

                            <td>

                                <strong>

                                    <?=
                                    htmlspecialchars(
                                        $r["client_name"]
                                        ?? "Client",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                    ?>

                                </strong>


                                <?php if (!empty($r["client_email"])): ?>

                                    <small
                                        class="d-block
                                               text-muted">

                                        <?=
                                        htmlspecialchars(
                                            $r["client_email"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        )
                                        ?>

                                    </small>

                                <?php endif; ?>

                            </td>



                            <!-- TERMINAL -->

                            <td>

                                <?=
                                htmlspecialchars(
                                    $r["elevator_id"]
                                    ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                )
                                ?>

                            </td>



                            <!-- PROBLEM -->

                            <td>

                                <?=
                                htmlspecialchars(
                                    $r["category"]
                                    ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                )
                                ?>

                            </td>



                            <!-- DESCRIPTION -->

                            <td class="problem-description">

                                <?=
                                nl2br(
                                    htmlspecialchars(
                                        $r["description"]
                                        ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                )
                                ?>

                            </td>



                            <!-- PRIORITY -->

                            <td>

                                <span
                                    class="priority-pill
                                    <?=
                                    request_priority_class(
                                        $priority
                                    )
                                    ?>">

                                    <?=
                                    htmlspecialchars(
                                        $priority,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                    ?>

                                </span>

                            </td>



                            <!-- STATUS -->

                            <td>

                                <span
                                    class="status-pill
                                    <?=
                                    request_status_class(
                                        $status
                                    )
                                    ?>">

                                    <?=
                                    htmlspecialchars(
                                        $status,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                    ?>

                                </span>

                            </td>



                            <!-- CURRENT TECHNICIAN -->

                            <td>

                                <?php
                                if (
                                    !empty(
                                        $r["technician_name"]
                                    )
                                ):
                                ?>

                                    <i
                                        class="fa-solid
                                               fa-user-gear
                                               me-1"
                                        style="color:#58A89A;">
                                    </i>

                                    <?=
                                    htmlspecialchars(
                                        $r["technician_name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                    ?>

                                <?php else: ?>

                                    <span class="text-muted">

                                        Not Assigned

                                    </span>

                                <?php endif; ?>

                            </td>



                            <!-- UPDATE -->

                            <td>


                                <form
                                    method="POST"
                                    action="manager-requests.php"
                                    class="update-form">


                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?=
                                        (int)$r["id"]
                                        ?>">



                                    <!-- STATUS -->

                                    <select
                                        name="status"
                                        class="form-select
                                               form-select-sm">


                                        <?php

                                        $statuses = [
                                            "Submitted",
                                            "Assigned",
                                            "Processing",
                                            "Pending",
                                            "Completed",
                                            "Closed"
                                        ];


                                        foreach ($statuses as $s):

                                            $selected =
                                                $s === $status
                                                ? "selected"
                                                : "";

                                        ?>

                                            <option
                                                value="<?= $s ?>"
                                                <?= $selected ?>>

                                                <?= $s ?>

                                            </option>

                                        <?php endforeach; ?>


                                    </select>



                                    <!-- TECHNICIAN -->

                                    <select
                                        name="technician_id"
                                        class="form-select
                                               form-select-sm">


                                        <option value="">

                                            Keep Current Technician

                                        </option>


                                        <?php
                                        foreach (
                                            $technicians
                                            as $technician
                                        ):
                                        ?>


                                            <option
                                                value="<?=
                                                (int)$technician["id"]
                                                ?>"
                                                <?php
                                                echo (
                                                    (int)(
                                                        $r["technician_id"]
                                                        ?? 0
                                                    )
                                                    ===
                                                    (int)$technician["id"]
                                                )
                                                    ? "selected"
                                                    : "";
                                                ?>>


                                                <?=
                                                htmlspecialchars(
                                                    $technician["name"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                )
                                                ?>


                                            </option>


                                        <?php endforeach; ?>


                                    </select>



                                    <button
                                        type="submit"
                                        name="update_status"
                                        class="btn
                                               btn-sm
                                               btn-update"
                                        title="Save Changes">


                                        <i
                                            class="fa-solid
                                                   fa-check">
                                        </i>


                                    </button>


                                </form>


                            </td>



                            <!-- ASSIGNED DATE -->

                            <td>

                                <?php

                                echo !empty(
                                    $r["assigned_at"]
                                )
                                    ? htmlspecialchars(
                                        $r["assigned_at"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                    : "-";

                                ?>

                            </td>


                        </tr>


                    <?php endforeach; ?>


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
        document.getElementById(
            "menuToggle"
        );

    const sidebar =
        document.getElementById(
            "sidebar"
        );


    if (menuToggle) {

        menuToggle.addEventListener(
            "click",
            function () {

                sidebar.classList.toggle(
                    "open"
                );

            }
        );

    }

</script>


</body>

</html>