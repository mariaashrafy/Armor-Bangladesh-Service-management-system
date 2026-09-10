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

$client_name = $_SESSION["name"] ?? "Client";


/* ============================================================
   STATUS CLASS
============================================================ */

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
   LOAD CLIENT REQUESTS
============================================================ */

$requests = [];

try {

    $stmt = $pdo->prepare("
        SELECT *
        FROM client_requests
        WHERE user_id = ?
        ORDER BY id DESC
    ");

    $stmt->execute([$user_id]);

    $requests =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $requests = [];

}


/* ============================================================
   COUNTERS
============================================================ */

$total_requests = count($requests);

$open_requests      = 0;
$completed_requests = 0;
$critical_requests  = 0;


foreach ($requests as $request) {

    $status =
        strtolower(
            trim(
                $request["status"] ?? ""
            )
        );


    if (
        in_array(
            $status,
            [
                "submitted",
                "assigned",
                "processing",
                "pending",
                "in progress"
            ],
            true
        )
    ) {
        $open_requests++;
    }


    if (
        in_array(
            $status,
            [
                "completed",
                "closed",
                "resolved"
            ],
            true
        )
    ) {
        $completed_requests++;
    }


    if (
        strtolower(
            trim(
                $request["priority"] ?? ""
            )
        ) === "critical"
    ) {
        $critical_requests++;
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
        My Requests | Armor Bangladesh Ltd.
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

            color: #7D898E;

            margin-bottom: 23px;

        }


        .breadcrumb-text strong {

            color: #36464D;

        }


        /* =====================================================
           SUMMARY CARDS
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
           WORK PANEL
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


        .panel-heading h5 {

            margin: 0;

            font-size: 16px;

            font-weight: 600;

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


        .description-cell {

            max-width: 320px;

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
           PRIORITY
        ===================================================== */

        .priority {

            display: inline-flex;

            align-items: center;

            padding:
                4px 9px;

            border-radius: 20px;

            font-size: 10px;

            font-weight: 700;

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
           BUTTON
        ===================================================== */

        .btn-new {

            background:
                var(--table-head);

            border:
                1px solid var(--table-head);

            color: white;

            border-radius: 3px;

            font-size: 12px;

            font-weight: 600;

            padding:
                8px 15px;

            text-decoration: none;

        }


        .btn-new:hover {

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
            class="sidebar-link active">

            <i class="fa-solid fa-list-check"></i>

            My Requests

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
     MAIN
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


        <!-- Breadcrumb -->

        <div class="breadcrumb-text">

            <strong>
                Client Dashboard
            </strong>

            &nbsp;›&nbsp;

            My Requests

        </div>



        <!-- =====================================================
             HEADER
        ====================================================== -->

        <div
            class="d-flex
                   flex-column
                   flex-md-row
                   align-items-md-center
                   justify-content-between
                   gap-3
                   mb-4">


            <div>

                <h4 class="fw-bold mb-1">

                    My Service Requests

                </h4>


                <div class="text-muted small">

                    View the problems you have submitted
                    and follow their current status.

                </div>

            </div>


            <a
                href="client-dashboard.php#newRequest"
                class="btn-new">


                <i
                    class="fa-solid
                           fa-circle-plus
                           me-1">
                </i>

                New Request

            </a>


        </div>



        <!-- =====================================================
             SUMMARY
        ====================================================== -->

        <div class="row g-3">


            <!-- TOTAL -->

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

                <div class="summary-card">

                    <div
                        class="d-flex
                               justify-content-between
                               align-items-center">


                        <div>

                            <div class="summary-label">

                                Open

                            </div>

                            <div class="summary-value">

                                <?php
                                echo $open_requests;
                                ?>

                            </div>

                        </div>


                        <div class="summary-icon">

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

                            <i
                                class="fa-solid
                                       fa-circle-check">
                            </i>

                        </div>


                    </div>

                </div>

            </div>



            <!-- CRITICAL -->

            <div class="col-xl-3 col-md-6">

                <div class="summary-card">

                    <div
                        class="d-flex
                               justify-content-between
                               align-items-center">


                        <div>

                            <div class="summary-label">

                                Critical

                            </div>

                            <div class="summary-value">

                                <?php
                                echo $critical_requests;
                                ?>

                            </div>

                        </div>


                        <div class="summary-icon">

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
             REQUEST TABLE
        ====================================================== -->

        <section class="work-panel">


            <div class="panel-heading">


                <div>

                    <h5>

                        Request History

                    </h5>


                    <small class="text-muted">

                        All service requests submitted
                        from your account.

                    </small>

                </div>


                <span class="small text-muted">

                    <?php
                    echo count($requests);
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


                    <?php if (empty($requests)): ?>


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


                                No service requests
                                have been submitted yet.


                            </td>


                        </tr>


                    <?php else: ?>


                        <?php foreach ($requests as $request): ?>


                            <?php

                            $status =
                                $request["status"]
                                ?? "Submitted";


                            $priority =
                                strtolower(
                                    trim(
                                        $request["priority"]
                                        ?? "Low"
                                    )
                                );


                            $priority_class =
                                match ($priority) {

                                    "critical" =>
                                        "priority-critical",

                                    "high" =>
                                        "priority-high",

                                    "medium" =>
                                        "priority-medium",

                                    default =>
                                        "priority-low"

                                };

                            ?>


                            <tr>


                                <!-- Request ID -->

                                <td>

                                    <strong>

                                        #<?php
                                        echo (int)$request["id"];
                                        ?>

                                    </strong>

                                </td>



                                <!-- Terminal ID -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $request["elevator_id"] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </td>



                                <!-- Category -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $request["category"] ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </td>



                                <!-- Description -->

                                <td class="description-cell">

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



                                <!-- Priority -->

                                <td>

                                    <span
                                        class="priority
                                        <?php
                                        echo $priority_class;
                                        ?>">


                                        <?php
                                        echo htmlspecialchars(
                                            ucfirst($priority),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );
                                        ?>


                                    </span>

                                </td>



                                <!-- Status -->

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



                                <!-- Contact -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $request["contact_method"]
                                        ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </td>



                                <!-- Created -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $request["created_at"]
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



        <div class="mt-3">

            <a
                href="client-dashboard.php"
                class="btn
                       btn-outline-secondary
                       btn-sm">


                <i
                    class="fa-solid
                           fa-arrow-left
                           me-1">
                </i>

                Back to Dashboard

            </a>

        </div>


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