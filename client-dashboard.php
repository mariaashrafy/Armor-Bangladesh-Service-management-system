<?php

require_once "config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* ============================================================
   CLIENT ACCESS ONLY
============================================================ */

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "client"
) {
    header("Location: login.php");
    exit;
}


$user_id = (int)$_SESSION["user_id"];

$error   = "";
$success = "";


/* ============================================================
   HELPERS
============================================================ */

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = ?
        LIMIT 1
    ");

    $stmt->execute([$table]);

    return (bool)$stmt->fetchColumn();
}


function column_exists(
    PDO $pdo,
    string $table,
    string $column
): bool {

    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
        AND table_name = ?
        AND column_name = ?
        LIMIT 1
    ");

    $stmt->execute([
        $table,
        $column
    ]);

    return (bool)$stmt->fetchColumn();
}


function status_class(string $status): string
{
    $status = strtolower(trim($status));

    if (
        in_array(
            $status,
            ["completed", "closed", "resolved"],
            true
        )
    ) {
        return "status-success";
    }

    if (
        in_array(
            $status,
            ["assigned", "processing", "in progress"],
            true
        )
    ) {
        return "status-progress";
    }

    if (
        in_array(
            $status,
            ["rejected", "cancelled", "overdue"],
            true
        )
    ) {
        return "status-danger";
    }

    return "status-pending";
}


/* ============================================================
   CLIENT INFORMATION
============================================================ */

$client_name  = "Client";
$client_email = "";


try {

    $stmt = $pdo->prepare("
        SELECT name, email
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$user_id]);

    if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $client_name =
            $user["name"] ?? "Client";

        $client_email =
            $user["email"] ?? "";

    }

} catch (Throwable $e) {

    // Keep default values.

}


/* ============================================================
   CHECK CLIENT REQUEST TABLE
============================================================ */

if (!table_exists($pdo, "client_requests")) {

    $error =
        "The client_requests table was not found.";

}


/* ============================================================
   NEW SERVICE REQUEST
============================================================ */

if (
    $error === "" &&
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["submit_request"])
) {

    /*
    |--------------------------------------------------------------------------
    | IMPORTANT
    |--------------------------------------------------------------------------
    | Database still uses elevator_id.
    | Frontend calls it Terminal ID.
    */

    $elevator_id =
        trim($_POST["elevator_id"] ?? "");

    $category =
        trim($_POST["category"] ?? "");

    $priority =
        trim($_POST["priority"] ?? "");

    $description =
        trim($_POST["description"] ?? "");

    $contact_method =
        trim($_POST["contact_method"] ?? "Phone");


    if (
        $elevator_id === "" ||
        $category === "" ||
        $priority === "" ||
        $description === ""
    ) {

        $error =
            "Please fill in all required fields.";

    } elseif (
        !in_array(
            $priority,
            ["Low", "Medium", "High", "Critical"],
            true
        )
    ) {

        $error = "Invalid priority selected.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Save Client Problem
            |--------------------------------------------------------------------------
            |
            | The Manager Dashboard reads this same client_requests table.
            */

            $stmt = $pdo->prepare("
                INSERT INTO client_requests
                (
                    user_id,
                    elevator_id,
                    category,
                    priority,
                    description,
                    contact_method,
                    status
                )
                VALUES
                (
                    :user_id,
                    :elevator_id,
                    :category,
                    :priority,
                    :description,
                    :contact_method,
                    'Submitted'
                )
            ");


            $stmt->execute([

                ":user_id" =>
                    $user_id,

                ":elevator_id" =>
                    $elevator_id,

                ":category" =>
                    $category,

                ":priority" =>
                    $priority,

                ":description" =>
                    $description,

                ":contact_method" =>
                    $contact_method

            ]);


            $success =
                "Your service request has been submitted successfully. The management team can now review it.";

        } catch (PDOException $e) {

            /*
            | Don't expose database errors publicly.
            */

            $error =
                "Could not submit your request. Please try again.";

        }

    }

}


/* ============================================================
   DASHBOARD STATISTICS
============================================================ */

$total_requests     = 0;
$open_requests      = 0;
$completed_requests = 0;
$overdue_maint      = 0;

$recent_requests = [];


if ($error === "") {

    try {

        /*
        |--------------------------------------------------------------------------
        | Total
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM client_requests
            WHERE user_id = ?
        ");

        $stmt->execute([$user_id]);

        $total_requests =
            (int)$stmt->fetchColumn();


        /*
        |--------------------------------------------------------------------------
        | Open
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM client_requests
            WHERE user_id = ?
            AND LOWER(status)
            IN (
                'submitted',
                'assigned',
                'processing',
                'pending',
                'in progress'
            )
        ");

        $stmt->execute([$user_id]);

        $open_requests =
            (int)$stmt->fetchColumn();


        /*
        |--------------------------------------------------------------------------
        | Completed
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM client_requests
            WHERE user_id = ?
            AND LOWER(status)
            IN (
                'completed',
                'closed',
                'resolved'
            )
        ");

        $stmt->execute([$user_id]);

        $completed_requests =
            (int)$stmt->fetchColumn();


        /*
        |--------------------------------------------------------------------------
        | Recent requests
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                elevator_id,
                category,
                priority,
                description,
                contact_method,
                status,
                created_at
            FROM client_requests
            WHERE user_id = ?
            ORDER BY id DESC
            LIMIT 20
        ");

        $stmt->execute([$user_id]);

        $recent_requests =
            $stmt->fetchAll(PDO::FETCH_ASSOC);


        /*
        |--------------------------------------------------------------------------
        | Maintenance
        |--------------------------------------------------------------------------
        */

        if (
            table_exists(
                $pdo,
                "maintenance_schedules"
            )
        ) {

            if (
                column_exists(
                    $pdo,
                    "maintenance_schedules",
                    "user_id"
                )
            ) {

                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM maintenance_schedules
                    WHERE user_id = ?
                    AND next_date < CURDATE()
                ");

                $stmt->execute([$user_id]);

                $overdue_maint =
                    (int)$stmt->fetchColumn();

            }

        }

    } catch (Throwable $e) {

        // Keep default counters.

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
        Client Dashboard | Armor Bangladesh Ltd.
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
            --armor-hover: #D96C13;

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

            top: 0;
            left: 0;

            width: 245px;

            height: 100vh;

            background:
                linear-gradient(
                    180deg,
                    var(--sidebar),
                    var(--sidebar-dark)
                );

            color: white;

            overflow-y: auto;

            z-index: 1000;

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

            background: white;

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
           MAIN AREA
        ===================================================== */

        .main-content {

            margin-left: 245px;

            min-height: 100vh;

        }


        /* =====================================================
           TOP HEADER
        ===================================================== */

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

            border: none;

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

            display: block;

            font-size: 13px;

        }


        .user-info small {

            color: var(--muted);

            font-size: 11px;

        }


        /* =====================================================
           PAGE CONTENT
        ===================================================== */

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


        /* =====================================================
           KPI CARDS
        ===================================================== */

        .mini-stat {

            background: white;

            border:
                1px solid var(--border);

            border-radius: 6px;

            padding:
                15px 17px;

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

        }


        .panel-heading h5 {

            margin: 0;

            font-weight: 600;

            font-size: 16px;

        }


        /* =====================================================
           FORM
        ===================================================== */

        .request-form {

            padding:
                22px 20px;

        }


        .form-label {

            font-size: 12px;

            font-weight: 600;

            color: #47565D;

        }


        .form-control,
        .form-select {

            min-height: 42px;

            border-radius: 3px;

            border:
                1px solid #D9DFE2;

            font-size: 13px;

        }


        textarea.form-control {

            min-height: 110px;

        }


        .form-control:focus,
        .form-select:focus {

            border-color:
                var(--table-head);

            box-shadow:
                0 0 0 .15rem
                rgba(88,168,154,.15);

        }


        .btn-submit {

            background:
                var(--table-head);

            border:
                1px solid var(--table-head);

            color: white;

            min-height: 42px;

            padding:
                0 25px;

            border-radius: 3px;

            font-size: 13px;

            font-weight: 600;

        }


        .btn-submit:hover {

            background: #478F83;

            color: white;

        }


        /* =====================================================
           TABLE
        ===================================================== */

        .table-container {

            overflow-x: auto;

        }


        .request-table {

            min-width: 1150px;

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

            color: #47555C;

        }


        .request-table tbody tr:hover {

            background: #F9FCFB;

        }


        .problem-text {

            max-width: 280px;

            white-space: normal;

            line-height: 1.45;

        }


        /* =====================================================
           STATUS
        ===================================================== */

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


        /* =====================================================
           ALERTS
        ===================================================== */

        .alert {

            border-radius: 4px;

            font-size: 13px;

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

        <a href="client-dashboard.php">

            <img
                src="img/armor.jpg"
                alt="Armor Bangladesh Ltd.">

        </a>

    </div>


    <div class="sidebar-menu">


        <a
            href="client-dashboard.php"
            class="sidebar-link active">

            <i class="fa-solid fa-house"></i>

            Dashboard

        </a>


        <div class="menu-title">

            Service

        </div>


        <a
            href="#newRequest"
            class="sidebar-link">

            <i class="fa-solid fa-circle-plus"></i>

            New Request

        </a>


        <a
            href="#myRequests"
            class="sidebar-link">

            <i class="fa-solid fa-list-check"></i>

            My Requests

        </a>


        <a
            href="client-requests.php"
            class="sidebar-link">

            <i class="fa-solid fa-folder-open"></i>

            Request History

        </a>


        <div class="menu-title">

            Maintenance

        </div>


        <a
            href="client-maintenance.php"
            class="sidebar-link">

            <i class="fa-solid fa-screwdriver-wrench"></i>

            Maintenance

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

            <strong>Home</strong>

            &nbsp;›&nbsp;

            Client Dashboard

        </div>



        <!-- =====================================================
             MESSAGES
        ====================================================== -->

        <?php if ($error !== ""): ?>

            <div class="alert alert-danger">

                <i
                    class="fa-solid
                           fa-circle-exclamation
                           me-2">
                </i>

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

                <i
                    class="fa-solid
                           fa-circle-check
                           me-2">
                </i>

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
             KPI CARDS
        ====================================================== -->

        <div class="row g-3">


            <!-- TOTAL -->

            <div class="col-xl-3 col-md-6">

                <div class="mini-stat">

                    <div
                        class="d-flex
                               justify-content-between
                               align-items-center">

                        <div>

                            <div class="mini-stat-label">

                                Total Requests

                            </div>

                            <div class="mini-stat-value">

                                <?php
                                echo $total_requests;
                                ?>

                            </div>

                        </div>


                        <div class="mini-stat-icon">

                            <i
                                class="fa-solid
                                       fa-clipboard-list">
                            </i>

                        </div>

                    </div>

                </div>

            </div>



            <!-- OPEN -->

            <div class="col-xl-3 col-md-6">

                <div class="mini-stat">

                    <div
                        class="d-flex
                               justify-content-between
                               align-items-center">

                        <div>

                            <div class="mini-stat-label">

                                Open Requests

                            </div>

                            <div class="mini-stat-value">

                                <?php
                                echo $open_requests;
                                ?>

                            </div>

                        </div>


                        <div class="mini-stat-icon">

                            <i
                                class="fa-solid
                                       fa-clock">
                            </i>

                        </div>

                    </div>

                </div>

            </div>



            <!-- COMPLETED -->

            <div class="col-xl-3 col-md-6">

                <div class="mini-stat">

                    <div
                        class="d-flex
                               justify-content-between
                               align-items-center">

                        <div>

                            <div class="mini-stat-label">

                                Completed

                            </div>

                            <div class="mini-stat-value">

                                <?php
                                echo $completed_requests;
                                ?>

                            </div>

                        </div>


                        <div class="mini-stat-icon">

                            <i
                                class="fa-solid
                                       fa-circle-check">
                            </i>

                        </div>

                    </div>

                </div>

            </div>



            <!-- MAINTENANCE -->

            <div class="col-xl-3 col-md-6">

                <div class="mini-stat">

                    <div
                        class="d-flex
                               justify-content-between
                               align-items-center">

                        <div>

                            <div class="mini-stat-label">

                                Overdue Maintenance

                            </div>

                            <div class="mini-stat-value">

                                <?php
                                echo $overdue_maint;
                                ?>

                            </div>

                        </div>


                        <div class="mini-stat-icon">

                            <i
                                class="fa-solid
                                       fa-triangle-exclamation">
                            </i>

                        </div>

                    </div>

                </div>

            </div>


        </div>



        <!-- =====================================================
             NEW SERVICE REQUEST
        ====================================================== -->

        <section
            class="work-panel"
            id="newRequest">


            <div class="panel-heading">


                <h5>

                    <i
                        class="fa-solid
                               fa-paper-plane
                               me-2"
                        style="color:#F58220;">
                    </i>

                    Submit a Service Request

                </h5>


            </div>



            <form
                method="POST"
                action="client-dashboard.php"
                class="request-form">


                <div class="row g-3">


                    <!-- Terminal ID -->

                    <div class="col-lg-4">


                        <label
                            for="elevator_id"
                            class="form-label">

                            Terminal ID *

                        </label>


                        <input
                            type="text"
                            id="elevator_id"
                            name="elevator_id"
                            class="form-control"
                            placeholder="Example: ATM-DHK-001"
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST["elevator_id"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                            ?>"
                            required>


                    </div>



                    <!-- Category -->

                    <div class="col-lg-4">


                        <label
                            for="category"
                            class="form-label">

                            Problem Category *

                        </label>


                        <select
                            id="category"
                            name="category"
                            class="form-select"
                            required>


                            <option value="">

                                Select Problem

                            </option>


                            <option value="Cash Dispensing Problem">

                                Cash Dispensing Problem

                            </option>


                            <option value="Card Reader Problem">

                                Card Reader Problem

                            </option>


                            <option value="Cash Deposit Problem">

                                Cash Deposit Problem

                            </option>


                            <option value="Receipt Printer Problem">

                                Receipt Printer Problem

                            </option>


                            <option value="Network / Communication Problem">

                                Network / Communication Problem

                            </option>


                            <option value="Power Problem">

                                Power Problem

                            </option>


                            <option value="Software Problem">

                                Software Problem

                            </option>


                            <option value="Hardware Problem">

                                Hardware Problem

                            </option>


                            <option value="Preventive Maintenance">

                                Preventive Maintenance

                            </option>


                            <option value="Other">

                                Other

                            </option>


                        </select>


                    </div>



                    <!-- Priority -->

                    <div class="col-lg-4">


                        <label
                            for="priority"
                            class="form-label">

                            Priority *

                        </label>


                        <select
                            id="priority"
                            name="priority"
                            class="form-select"
                            required>


                            <option value="">

                                Select Priority

                            </option>


                            <option value="Low">

                                Low

                            </option>


                            <option value="Medium">

                                Medium

                            </option>


                            <option value="High">

                                High

                            </option>


                            <option value="Critical">

                                Critical

                            </option>


                        </select>


                    </div>



                    <!-- Description -->

                    <div class="col-12">


                        <label
                            for="description"
                            class="form-label">

                            Describe the Problem *

                        </label>


                        <textarea
                            id="description"
                            name="description"
                            class="form-control"
                            placeholder="Explain what problem you are experiencing with the terminal..."
                            required><?php
                            echo htmlspecialchars(
                                $_POST["description"] ?? "",
                                ENT_QUOTES,
                                "UTF-8"
                            );
                        ?></textarea>


                        <small class="text-muted">

                            Please provide enough information for
                            the manager and technician to understand
                            the problem.

                        </small>


                    </div>



                    <!-- Contact -->

                    <div class="col-lg-4">


                        <label
                            for="contact_method"
                            class="form-label">

                            Preferred Contact

                        </label>


                        <select
                            id="contact_method"
                            name="contact_method"
                            class="form-select">


                            <option value="Phone">

                                Phone

                            </option>


                            <option value="Email">

                                Email

                            </option>


                            <option value="WhatsApp">

                                WhatsApp

                            </option>


                        </select>


                    </div>



                    <div
                        class="col-lg-8
                               d-flex
                               align-items-end">


                        <button
                            type="submit"
                            name="submit_request"
                            class="btn btn-submit">


                            <i
                                class="fa-solid
                                       fa-paper-plane
                                       me-2">
                            </i>

                            Submit Request

                        </button>


                    </div>


                </div>


            </form>


        </section>



        <!-- =====================================================
             CLIENT REQUEST TABLE
        ====================================================== -->

        <section
            class="work-panel"
            id="myRequests">


            <div
                class="panel-heading
                       d-flex
                       align-items-center
                       justify-content-between">


                <h5>

                    My Service Requests

                </h5>


                <span class="text-muted small">

                    <?php
                    echo count($recent_requests);
                    ?>

                    record(s)

                </span>


            </div>



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

                                Contact

                            </th>


                            <th>

                                Submitted

                            </th>


                        </tr>


                    </thead>


                    <tbody>


                    <?php if (empty($recent_requests)): ?>


                        <tr>


                            <td
                                colspan="8"
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


                                You have not submitted
                                any service requests yet.


                            </td>


                        </tr>


                    <?php else: ?>


                        <?php foreach ($recent_requests as $request): ?>


                            <tr>


                                <td>


                                    <strong>

                                        #<?php
                                        echo (int)$request["id"];
                                        ?>

                                    </strong>


                                </td>



                                <td>


                                    <?php
                                    echo htmlspecialchars(
                                        $request["elevator_id"] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>


                                </td>



                                <td>


                                    <?php
                                    echo htmlspecialchars(
                                        $request["category"] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>


                                </td>



                                <td class="problem-text">


                                    <?php
                                    echo nl2br(
                                        htmlspecialchars(
                                            $request["description"] ?? "-",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        )
                                    );
                                    ?>


                                </td>



                                <td>


                                    <?php
                                    echo htmlspecialchars(
                                        $request["priority"] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>


                                </td>



                                <td>


                                    <?php

                                    $status =
                                        $request["status"]
                                        ?? "Submitted";

                                    ?>


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
                                        $request["contact_method"] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>


                                </td>



                                <td>


                                    <?php
                                    echo htmlspecialchars(
                                        $request["created_at"] ?? "-",
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



            <div
                class="p-3
                       text-end
                       border-top">


                <a
                    href="client-requests.php"
                    class="btn
                           btn-outline-secondary
                           btn-sm">


                    View All Requests

                    <i
                        class="fa-solid
                               fa-arrow-right
                               ms-1">
                    </i>


                </a>


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