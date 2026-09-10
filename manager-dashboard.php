<?php

require_once "config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Access Control
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    !in_array($_SESSION["role"], ["admin", "manager"], true)
) {

    header("Location: login.php");
    exit;

}


$manager_name = $_SESSION["name"] ?? "Manager";


/*
|--------------------------------------------------------------------------
| Dashboard Counters
|--------------------------------------------------------------------------
*/

$new_requests = 0;
$in_progress  = 0;
$overdue      = 0;
$tech_updates = 0;


/*
|--------------------------------------------------------------------------
| Technician Updates
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = 'technician_notes'
    ");

    $has_notes =
        (int)$stmt->fetchColumn() > 0;


    if ($has_notes) {

        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
            AND table_name = 'technician_notes'
            AND column_name = 'is_read'
        ");

        $has_is_read =
            (int)$stmt->fetchColumn() > 0;


        if ($has_is_read) {

            $stmt = $pdo->query("
                SELECT COUNT(*)
                FROM technician_notes
                WHERE is_read = 0
            ");

            $tech_updates =
                (int)$stmt->fetchColumn();

        } else {

            $stmt = $pdo->query("
                SELECT COUNT(*)
                FROM technician_notes
            ");

            $tech_updates =
                (int)$stmt->fetchColumn();

        }

    }

} catch (Throwable $e) {

    $tech_updates = 0;

}


/*
|--------------------------------------------------------------------------
| Request Statistics
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM client_requests
        WHERE LOWER(status) = 'submitted'
    ");

    $new_requests =
        (int)$stmt->fetchColumn();



    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM client_requests
        WHERE LOWER(status)
        IN ('assigned', 'processing', 'in progress')
    ");

    $in_progress =
        (int)$stmt->fetchColumn();



    $stmt = $pdo->query("
        SHOW TABLES LIKE 'maintenance_schedules'
    ");

    $has_maint =
        (bool)$stmt->fetchColumn();


    if ($has_maint) {

        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM maintenance_schedules
            WHERE next_date < CURDATE()
        ");

        $overdue =
            (int)$stmt->fetchColumn();

    }

} catch (Throwable $e) {

    // Keep defaults.

}


/*
|--------------------------------------------------------------------------
| Load Client Requests
|--------------------------------------------------------------------------
|
| We use SELECT * because your client_requests schema may still change
| during development.
|
*/

$requests = [];

try {

    $stmt = $pdo->query("
        SELECT *
        FROM client_requests
        ORDER BY id DESC
        LIMIT 100
    ");

    $requests =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $requests = [];

}


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
|
| Finds the first existing column from several possible column names.
| This helps while your database is still being converted from
| the Sonic Elevator project.
|
*/

function request_value(array $row, array $keys, string $default = "-"): string
{

    foreach ($keys as $key) {

        if (
            array_key_exists($key, $row) &&
            $row[$key] !== null &&
            $row[$key] !== ""
        ) {

            return (string)$row[$key];

        }

    }

    return $default;

}


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$filter_status =
    trim($_GET["status"] ?? "");

$filter_type =
    trim($_GET["service_type"] ?? "");

$filter_search =
    trim($_GET["search"] ?? "");


/*
|--------------------------------------------------------------------------
| Filter Records
|--------------------------------------------------------------------------
*/

if ($filter_status !== "") {

    $requests = array_filter(
        $requests,
        function ($row) use ($filter_status) {

            $status =
                request_value(
                    $row,
                    ["status"],
                    ""
                );

            return strtolower($status)
                === strtolower($filter_status);

        }
    );

}


if ($filter_type !== "") {

    $requests = array_filter(
        $requests,
        function ($row) use ($filter_type) {

            $type =
                request_value(
                    $row,
                    [
                        "service_type",
                        "request_type",
                        "type"
                    ],
                    ""
                );

            return strtolower($type)
                === strtolower($filter_type);

        }
    );

}


if ($filter_search !== "") {

    $requests = array_filter(
        $requests,
        function ($row) use ($filter_search) {

            $haystack =
                strtolower(
                    implode(
                        " ",
                        array_map(
                            "strval",
                            $row
                        )
                    )
                );

            return strpos(
                $haystack,
                strtolower($filter_search)
            ) !== false;

        }
    );

}


/*
|--------------------------------------------------------------------------
| Status Badge
|--------------------------------------------------------------------------
*/

function status_class(string $status): string
{

    $status = strtolower(trim($status));


    if (
        in_array(
            $status,
            [
                "completed",
                "resolved",
                "reconciled",
                "matched"
            ],
            true
        )
    ) {

        return "status-success";

    }


    if (
        in_array(
            $status,
            [
                "processing",
                "assigned",
                "in progress"
            ],
            true
        )
    ) {

        return "status-progress";

    }


    if (
        in_array(
            $status,
            [
                "rejected",
                "overdue",
                "mismatch",
                "failed"
            ],
            true
        )
    ) {

        return "status-danger";

    }


    return "status-pending";

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
        Manager Dashboard | Armor Bangladesh Ltd.
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

            --armor-hover: #DB6E12;

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

            background: var(--page-bg);

            color: var(--text-dark);

            font-family:
                Arial,
                Helvetica,
                sans-serif;

        }


        /*
        |--------------------------------------------------------------------------
        | Sidebar
        |--------------------------------------------------------------------------
        */

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

            color: #ffffff;

            z-index: 1000;

            overflow-y: auto;

            transition: .25s ease;

        }


        .sidebar-logo {

            min-height: 78px;

            display: flex;

            align-items: center;

            padding: 13px 20px;

            border-bottom:
                1px solid rgba(255,255,255,.09);

        }


        .sidebar-logo img {

            max-width: 150px;

            max-height: 55px;

            object-fit: contain;

            background: #ffffff;

            padding: 5px 8px;

            border-radius: 4px;

        }


        .sidebar-menu {

            padding: 16px 10px;

        }


        .menu-title {

            color:
                rgba(255,255,255,.45);

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: 1.4px;

            padding: 16px 14px 7px;

        }


        .sidebar-link {

            display: flex;

            align-items: center;

            gap: 13px;

            min-height: 44px;

            padding: 10px 14px;

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

            color: #ffffff;

        }


        .sidebar-link.active {

            background: var(--sidebar-hover);

            color: #ffffff;

            border-left:
                3px solid var(--armor);

        }


        .sidebar-badge {

            margin-left: auto;

            background: #E84D4D;

            color: #ffffff;

            min-width: 22px;

            height: 22px;

            padding: 0 6px;

            border-radius: 20px;

            font-size: 11px;

            display: grid;

            place-items: center;

        }


        /*
        |--------------------------------------------------------------------------
        | Main Layout
        |--------------------------------------------------------------------------
        */

        .main-content {

            margin-left: 245px;

            min-height: 100vh;

        }


        /*
        |--------------------------------------------------------------------------
        | Top Navigation
        |--------------------------------------------------------------------------
        */

        .top-header {

            height: 67px;

            background: #ffffff;

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

            color: var(--muted);

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

            color: var(--armor);

            display: grid;

            place-items: center;

        }


        .user-info {

            line-height: 1.2;

        }


        .user-info strong {

            font-size: 13px;

            display: block;

        }


        .user-info small {

            color: var(--muted);

            font-size: 11px;

        }


        /*
        |--------------------------------------------------------------------------
        | Page
        |--------------------------------------------------------------------------
        */

        .content-wrapper {

            padding:
                26px 28px 50px;

        }


        .breadcrumb-text {

            font-size: 13px;

            color: #7D898E;

            margin-bottom: 23px;

        }


        .breadcrumb-text strong {

            color: #36464D;

        }


        /*
        |--------------------------------------------------------------------------
        | KPI
        |--------------------------------------------------------------------------
        */

        .mini-stat {

            background: #ffffff;

            border:
                1px solid var(--border);

            border-radius: 6px;

            padding: 15px 17px;

            height: 100%;

        }


        .mini-stat-label {

            color: var(--muted);

            font-size: 12px;

            margin-bottom: 6px;

        }


        .mini-stat-value {

            font-size: 24px;

            font-weight: 700;

        }


        .mini-stat-icon {

            width: 38px;

            height: 38px;

            border-radius: 5px;

            background:
                rgba(88,168,154,.12);

            color: var(--table-head);

            display: grid;

            place-items: center;

        }


        /*
        |--------------------------------------------------------------------------
        | Main Work Panel
        |--------------------------------------------------------------------------
        */

        .work-panel {

            background: #ffffff;

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
                18px 20px 10px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

        }


        .panel-heading h5 {

            margin: 0;

            font-weight: 600;

            font-size: 16px;

        }


        /*
        |--------------------------------------------------------------------------
        | Filters
        |--------------------------------------------------------------------------
        */

        .filter-area {

            padding:
                10px 20px 20px;

        }


        .filter-control {

            height: 39px;

            border-radius: 3px;

            border:
                1px solid #D9DFE2;

            font-size: 12px;

            color: #536168;

            background-color: #ffffff;

        }


        .filter-control:focus {

            border-color:
                var(--table-head);

            box-shadow:
                0 0 0 .15rem
                rgba(88,168,154,.15);

        }


        .btn-filter {

            height: 39px;

            padding:
                0 20px;

            border-radius: 3px;

            background: var(--table-head);

            border:
                1px solid var(--table-head);

            color: #ffffff;

            font-size: 12px;

            font-weight: 600;

        }


        .btn-filter:hover {

            background: #478F83;

            color: #ffffff;

        }


        .btn-reset {

            height: 39px;

            border-radius: 3px;

            font-size: 12px;

        }


        /*
        |--------------------------------------------------------------------------
        | Table
        |--------------------------------------------------------------------------
        */

        .table-container {

            overflow-x: auto;

        }


        .reconciliation-table {

            min-width: 1050px;

            margin: 0;

            font-size: 12px;

        }


        .reconciliation-table thead th {

            background: var(--table-head);

            color: #ffffff;

            border: 0;

            padding:
                12px 11px;

            font-size: 11px;

            font-weight: 600;

            text-transform: uppercase;

            white-space: nowrap;

        }


        .reconciliation-table tbody td {

            padding:
                13px 11px;

            vertical-align: middle;

            border-color: #EDF0F2;

            color: #47555C;

        }


        .reconciliation-table tbody tr:hover {

            background: #F9FCFB;

        }


        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        .status-pill {

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


        .status-danger {

            background: #FDE9E8;

            color: #C04B45;

        }


        .status-pending {

            background: #FFF4D9;

            color: #9A7617;

        }


        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        .btn-view {

            background: #ffffff;

            border:
                1px solid #C9D2D6;

            color: #506068;

            border-radius: 3px;

            padding:
                5px 11px;

            font-size: 11px;

            text-decoration: none;

            white-space: nowrap;

        }


        .btn-view:hover {

            background: var(--table-head);

            border-color: var(--table-head);

            color: #ffffff;

        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

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

                display: inline-block;

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
            class="sidebar-link active">

            <i class="fa-solid fa-house"></i>

            Home

        </a>


        <div class="menu-title">

            Task Management

        </div>


        <a
            href="manager-requests.php"
            class="sidebar-link">

            <i class="fa-solid fa-list-check"></i>

            Service Requests

            <?php if ($new_requests > 0): ?>

                <span class="sidebar-badge">

                    <?php
                    echo (int)$new_requests;
                    ?>

                </span>

            <?php endif; ?>

        </a>


        <a
            href="manager-tech-updates.php"
            class="sidebar-link">

            <i class="fa-solid fa-bell"></i>

            Technician Updates

            <?php if ($tech_updates > 0): ?>

                <span class="sidebar-badge">

                    <?php
                    echo (int)$tech_updates;
                    ?>

                </span>

            <?php endif; ?>

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


        <a
            href="manager-dashboard.php?status=submitted"
            class="sidebar-link">

            <i class="fa-regular fa-clock"></i>

            Pending

        </a>


        <a
            href="manager-dashboard.php?status=completed"
            class="sidebar-link">

            <i class="fa-solid fa-circle-check"></i>

            Completed

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


        <div class="d-flex align-items-center gap-3">


            <button
                type="button"
                class="menu-toggle"
                id="menuToggle">

                <i class="fa-solid fa-bars"></i>

            </button>


            <div class="top-title">

                Armor Bangladesh Ltd.
                &nbsp; / &nbsp;
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


        <!-- Breadcrumb -->
        <div class="breadcrumb-text">

            <strong>Home</strong>

            &nbsp;›&nbsp;

            Select Request to View

        </div>



        <!-- =====================================================
             SMALL SUMMARY
        ====================================================== -->

        <div class="row g-3">


            <div class="col-xl-3 col-md-6">

                <div class="mini-stat">

                    <div
                        class="d-flex
                               justify-content-between
                               align-items-center">

                        <div>

                            <div class="mini-stat-label">

                                New Requests

                            </div>

                            <div class="mini-stat-value">

                                <?php
                                echo (int)$new_requests;
                                ?>

                            </div>

                        </div>


                        <div class="mini-stat-icon">

                            <i class="fa-solid fa-file-circle-plus"></i>

                        </div>

                    </div>

                </div>

            </div>



            <div class="col-xl-3 col-md-6">

                <div class="mini-stat">

                    <div
                        class="d-flex
                               justify-content-between
                               align-items-center">

                        <div>

                            <div class="mini-stat-label">

                                In Progress

                            </div>

                            <div class="mini-stat-value">

                                <?php
                                echo (int)$in_progress;
                                ?>

                            </div>

                        </div>


                        <div class="mini-stat-icon">

                            <i class="fa-solid fa-arrows-rotate"></i>

                        </div>

                    </div>

                </div>

            </div>



            <div class="col-xl-3 col-md-6">

                <div class="mini-stat">

                    <div
                        class="d-flex
                               justify-content-between
                               align-items-center">

                        <div>

                            <div class="mini-stat-label">

                                Technician Updates

                            </div>

                            <div class="mini-stat-value">

                                <?php
                                echo (int)$tech_updates;
                                ?>

                            </div>

                        </div>


                        <div class="mini-stat-icon">

                            <i class="fa-solid fa-bell"></i>

                        </div>

                    </div>

                </div>

            </div>



            <div class="col-xl-3 col-md-6">

                <div class="mini-stat">

                    <div
                        class="d-flex
                               justify-content-between
                               align-items-center">

                        <div>

                            <div class="mini-stat-label">

                                Overdue

                            </div>

                            <div class="mini-stat-value">

                                <?php
                                echo (int)$overdue;
                                ?>

                            </div>

                        </div>


                        <div class="mini-stat-icon">

                            <i class="fa-solid fa-triangle-exclamation"></i>

                        </div>

                    </div>

                </div>

            </div>


        </div>



        <!-- =====================================================
             MAIN REQUEST / RECONCILIATION PANEL
        ====================================================== -->

        <section class="work-panel">


            <div class="panel-heading">


                <h5>

                    Select Request to View

                </h5>


                <small class="text-muted">

                    <?php
                    echo count($requests);
                    ?>

                    record(s)

                </small>


            </div>



            <!-- FILTERS -->
            <div class="filter-area">


                <form
                    method="GET"
                    action="manager-dashboard.php">


                    <div class="row g-2">


                        <!-- Status -->
                        <div class="col-xl-2 col-md-4">

                            <select
                                name="status"
                                class="form-select filter-control">

                                <option value="">

                                    Select Request Status

                                </option>

                                <option
                                    value="submitted"
                                    <?php
                                    echo $filter_status === "submitted"
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Submitted

                                </option>

                                <option
                                    value="assigned"
                                    <?php
                                    echo $filter_status === "assigned"
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Assigned

                                </option>

                                <option
                                    value="processing"
                                    <?php
                                    echo $filter_status === "processing"
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Processing

                                </option>

                                <option
                                    value="completed"
                                    <?php
                                    echo $filter_status === "completed"
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Completed

                                </option>

                            </select>

                        </div>



                        <!-- Service Type -->
                        <div class="col-xl-2 col-md-4">

                            <select
                                name="service_type"
                                class="form-select filter-control">

                                <option value="">

                                    Select Task Type

                                </option>

                                <option
                                    value="ATM Installation">

                                    ATM Installation

                                </option>

                                <option
                                    value="Preventive Maintenance">

                                    Preventive Maintenance

                                </option>

                                <option
                                    value="Repair">

                                    Repair

                                </option>

                                <option
                                    value="Technical Support">

                                    Technical Support

                                </option>

                            </select>

                        </div>



                        <!-- Search -->
                        <div class="col-xl-4 col-md-4">

                            <input
                                type="text"
                                name="search"
                                value="<?php
                                    echo htmlspecialchars(
                                        $filter_search,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                ?>"
                                class="form-control filter-control"
                                placeholder="Request ID / Terminal ID / Client / Location">

                        </div>



                        <div class="col-xl-2 col-md-6">

                            <button
                                type="submit"
                                class="btn btn-filter w-100">

                                <i class="fa-solid fa-filter me-1"></i>

                                Filter

                            </button>

                        </div>



                        <div class="col-xl-2 col-md-6">

                            <a
                                href="manager-dashboard.php"
                                class="btn btn-outline-secondary
                                       btn-reset
                                       w-100
                                       d-flex
                                       justify-content-center
                                       align-items-center">

                                Reset

                            </a>

                        </div>


                    </div>


                </form>


            </div>



            <!-- TABLE -->
            <div class="table-container">


                <table
                    class="table
                           reconciliation-table
                           align-middle">


                    <thead>


                    <tr>

                        <th>
                            Request ID
                        </th>

                        <th>
                            Customer
                        </th>

                        <th>
                            Terminal ID
                        </th>

                        <th>
                            Service Type
                        </th>

                        <th>
                            Location
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Created
                        </th>

                        <th>
                            Assigned To
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>


                    </thead>


                    <tbody>


                    <?php if (empty($requests)): ?>


                        <tr>

                            <td
                                colspan="9"
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


                    <?php else: ?>


                        <?php foreach ($requests as $row): ?>


                            <?php

                            $request_id =
                                request_value(
                                    $row,
                                    ["id"],
                                    "-"
                                );


                            $customer =
                                request_value(
                                    $row,
                                    [
                                        "customer_name",
                                        "client_name",
                                        "name",
                                        "client"
                                    ]
                                );


                            $terminal =
                                request_value(
                                    $row,
                                    [
                                        "terminal_id",
                                        "elevator_id",
                                        "machine_id",
                                        "atm_id"
                                    ]
                                );


                            $service =
                                request_value(
                                    $row,
                                    [
                                        "service_type",
                                        "request_type",
                                        "type"
                                    ]
                                );


                            $location =
                                request_value(
                                    $row,
                                    [
                                        "location",
                                        "address",
                                        "branch",
                                        "branch_name"
                                    ]
                                );


                            $status =
                                request_value(
                                    $row,
                                    ["status"],
                                    "Submitted"
                                );


                            $created =
                                request_value(
                                    $row,
                                    [
                                        "created_at",
                                        "request_date",
                                        "date"
                                    ]
                                );


                            $technician =
                                request_value(
                                    $row,
                                    [
                                        "technician_name",
                                        "assigned_to",
                                        "technician"
                                    ],
                                    "Not Assigned"
                                );

                            ?>


                            <tr>


                                <td>

                                    <strong>

                                        #<?php
                                        echo htmlspecialchars(
                                            $request_id,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );
                                        ?>

                                    </strong>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $customer,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $terminal,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $service,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $location,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </td>


                                <td>

                                    <span
                                        class="status-pill
                                        <?php
                                        echo status_class(
                                            $status
                                        );
                                        ?>">

                                        <?php
                                        echo htmlspecialchars(
                                            ucfirst($status),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );
                                        ?>

                                    </span>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $created,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $technician,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </td>


                                <td>

                                    <a
                                        href="manager-requests.php?request_id=<?php
                                        echo urlencode(
                                            $request_id
                                        );
                                        ?>"
                                        class="btn-view">

                                        View

                                        <i
                                            class="fa-solid
                                                   fa-arrow-right
                                                   ms-1">
                                        </i>

                                    </a>

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



<!-- ============================================================
     JAVASCRIPT
============================================================ -->

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