<?php

require_once "config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* ============================================================
   MANAGER / ADMIN ACCESS
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


function col_exists(
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


function status_class(string $status): string
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


function priority_class(string $priority): string
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


/* ============================================================
   DATABASE CHECK
============================================================ */

if (!table_exists($pdo, "technician_notes")) {

    die(
        "Table <b>technician_notes</b> not found."
    );

}


$has_is_read =
    col_exists(
        $pdo,
        "technician_notes",
        "is_read"
    );


$has_suggest =
    col_exists(
        $pdo,
        "technician_notes",
        "suggested_status"
    );


/* ============================================================
   MESSAGES
============================================================ */

$success = "";
$error   = "";


/* ============================================================
   MARK NOTE AS READ
============================================================ */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["mark_read"])
) {

    $note_id =
        (int)($_POST["note_id"] ?? 0);


    if (
        $note_id > 0 &&
        $has_is_read
    ) {

        try {

            $stmt = $pdo->prepare("
                UPDATE technician_notes
                SET is_read = 1
                WHERE id = ?
            ");

            $stmt->execute([
                $note_id
            ]);


            $success =
                "Technician update marked as read.";

        } catch (Throwable $e) {

            $error =
                "Could not mark the update as read.";

        }

    } else {

        $error =
            "The is_read column is not available.";

    }

}


/* ============================================================
   UPDATE FINAL CLIENT STATUS
============================================================ */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["update_final_status"])
) {

    $request_id =
        (int)($_POST["request_id"] ?? 0);

    $final_status =
        trim($_POST["final_status"] ?? "");


    $allowed_statuses = [
        "Submitted",
        "Assigned",
        "Processing",
        "Pending",
        "Completed",
        "Closed"
    ];


    if (
        $request_id <= 0 ||
        !in_array(
            $final_status,
            $allowed_statuses,
            true
        )
    ) {

        $error =
            "Invalid request or status.";

    } else {

        try {

            $stmt = $pdo->prepare("
                UPDATE client_requests
                SET status = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $final_status,
                $request_id
            ]);


            $success =
                "Client request status updated successfully.";

        } catch (Throwable $e) {

            $error =
                "Could not update the client request.";

        }

    }

}


/* ============================================================
   FETCH TECHNICIAN UPDATES
============================================================ */

$notes = [];


try {

    $sql = "
        SELECT

            tn.id AS note_id,
            tn.request_id,
            tn.technician_id,
            tn.note,
            tn.created_at,

            " .
            (
                $has_is_read
                ? "tn.is_read"
                : "0 AS is_read"
            )
            . ",

            " .
            (
                $has_suggest
                ? "tn.suggested_status"
                : "NULL AS suggested_status"
            )
            . ",

            cr.elevator_id,
            cr.category,
            cr.description,
            cr.priority,
            cr.status AS client_status,

            client.name AS client_name,
            client.email AS client_email,

            tech.name AS technician_name

        FROM technician_notes tn

        LEFT JOIN client_requests cr
            ON cr.id = tn.request_id

        LEFT JOIN users tech
            ON tech.id = tn.technician_id

        LEFT JOIN users client
            ON client.id = cr.user_id

        ORDER BY
            " .
            (
                $has_is_read
                ? "tn.is_read ASC,"
                : ""
            )
            . "
            tn.id DESC

        LIMIT 100
    ";


    $notes =
        $pdo->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $notes = [];

    $error =
        "Could not load technician updates.";

}


/* ============================================================
   COUNTERS
============================================================ */

$total_updates = count($notes);

$unread_updates     = 0;
$processing_updates = 0;
$completed_updates  = 0;


foreach ($notes as $note) {

    if (
        (int)($note["is_read"] ?? 0) === 0
    ) {
        $unread_updates++;
    }


    $status =
        strtolower(
            trim(
                $note["client_status"] ?? ""
            )
        );


    if (
        in_array(
            $status,
            ["assigned", "processing", "pending"],
            true
        )
    ) {
        $processing_updates++;
    }


    if (
        in_array(
            $status,
            ["completed", "closed"],
            true
        )
    ) {
        $completed_updates++;
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
        Technician Updates | Armor Bangladesh Ltd.
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


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

            justify-content: space-between;

            align-items: center;

        }


        .panel-heading h5 {

            margin: 0;

            font-size: 16px;

            font-weight: 600;

        }


        /* =====================================================
           UPDATE CARD
        ===================================================== */

        .update-card {

            border-bottom:
                1px solid #EDF0F2;

            padding:
                20px;

            background: white;

        }


        .update-card:hover {

            background:
                #F9FCFB;

        }


        .update-card.unread {

            border-left:
                4px solid var(--armor);

        }


        .request-title {

            font-size: 14px;

            font-weight: 700;

            color:
                #35464D;

        }


        .meta-text {

            font-size: 11px;

            color:
                var(--muted);

        }


        .note-box {

            margin-top: 14px;

            background:
                #F7FAFA;

            border:
                1px solid #E3E9E9;

            border-radius: 4px;

            padding:
                14px;

            font-size: 13px;

            line-height: 1.55;

            color:
                #44535A;

        }


        .problem-box {

            background:
                #FFF8F1;

            border-left:
                3px solid var(--armor);

            padding:
                10px 12px;

            margin-top: 12px;

            font-size: 12px;

            line-height: 1.5;

        }


        /* =====================================================
           BADGES
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


        .unread-label {

            display: inline-flex;

            align-items: center;

            padding:
                3px 8px;

            border-radius: 20px;

            background:
                #FDE9E8;

            color:
                #C04B45;

            font-size: 10px;

            font-weight: 700;

        }


        /* =====================================================
           BUTTONS / FORM
        ===================================================== */

        .btn-action {

            background:
                var(--table-head);

            border:
                1px solid var(--table-head);

            color: white;

            border-radius: 3px;

            font-size: 11px;

            font-weight: 600;

        }


        .btn-action:hover {

            background:
                #478F83;

            color: white;

        }


        .form-select-sm {

            font-size: 11px;

            border-radius: 3px;

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
            class="sidebar-link active">

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
     MAIN
============================================================ -->

<main class="main-content">


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

                    <?=
                    htmlspecialchars(
                        $manager_name,
                        ENT_QUOTES,
                        "UTF-8"
                    )
                    ?>

                </strong>

                <small>

                    <?=
                    htmlspecialchars(
                        ucfirst(
                            $_SESSION["role"]
                            ?? "manager"
                        ),
                        ENT_QUOTES,
                        "UTF-8"
                    )
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

            Technician Updates

        </div>



        <?php if ($error !== ""): ?>

            <div class="alert alert-danger">

                <?=
                htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                )
                ?>

            </div>

        <?php endif; ?>


        <?php if ($success !== ""): ?>

            <div class="alert alert-success">

                <?=
                htmlspecialchars(
                    $success,
                    ENT_QUOTES,
                    "UTF-8"
                )
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

                                Total Updates

                            </div>

                            <div class="summary-value">

                                <?= $total_updates ?>

                            </div>

                        </div>


                        <div class="summary-icon">

                            <i class="fa-solid fa-comments"></i>

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

                                Unread Updates

                            </div>

                            <div class="summary-value">

                                <?= $unread_updates ?>

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

                                Active Requests

                            </div>

                            <div class="summary-value">

                                <?= $processing_updates ?>

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

                                <?= $completed_updates ?>

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
             UPDATES PANEL
        ====================================================== -->

        <section class="work-panel">


            <div class="panel-heading">


                <div>

                    <h5 class="mb-1">

                        Technician Updates

                    </h5>


                    <small class="text-muted">

                        Review technician notes and approve the
                        final service request status.

                    </small>

                </div>


                <span class="small text-muted">

                    <?= count($notes) ?>

                    update(s)

                </span>


            </div>



            <?php if (empty($notes)): ?>


                <div
                    class="text-center
                           text-muted
                           py-5">


                    <i
                        class="fa-regular
                               fa-bell-slash
                               fs-2
                               d-block
                               mb-2">
                    </i>


                    No technician updates found.


                </div>


            <?php else: ?>


                <?php foreach ($notes as $note): ?>


                    <?php

                    $is_unread =
                        (int)(
                            $note["is_read"]
                            ?? 0
                        ) === 0;


                    $request_id =
                        (int)(
                            $note["request_id"]
                            ?? 0
                        );


                    $client_status =
                        $note["client_status"]
                        ?? "Submitted";


                    $priority =
                        $note["priority"]
                        ?? "Low";

                    ?>


                    <div
                        class="update-card
                        <?= $is_unread ? "unread" : "" ?>">


                        <!-- ================================
                             TOP
                        ================================= -->

                        <div
                            class="d-flex
                                   flex-column
                                   flex-xl-row
                                   justify-content-between
                                   gap-3">


                            <div>


                                <div class="request-title">


                                    SR-<?= $request_id ?>


                                    <span
                                        class="text-muted
                                               fw-normal
                                               ms-2">


                                        Terminal ID:


                                    </span>


                                    <?=
                                    htmlspecialchars(
                                        $note["elevator_id"]
                                        ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                    ?>


                                </div>



                                <div class="meta-text mt-1">


                                    Client:


                                    <strong>

                                        <?=
                                        htmlspecialchars(
                                            $note["client_name"]
                                            ?? "Client",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        )
                                        ?>

                                    </strong>


                                    &nbsp; • &nbsp;


                                    Technician:


                                    <strong>

                                        <?=
                                        htmlspecialchars(
                                            $note["technician_name"]
                                            ?? (
                                                "#"
                                                .
                                                (
                                                    $note["technician_id"]
                                                    ?? "-"
                                                )
                                            ),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        )
                                        ?>

                                    </strong>


                                    &nbsp; • &nbsp;


                                    <?=
                                    htmlspecialchars(
                                        $note["created_at"]
                                        ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                    ?>


                                </div>


                            </div>



                            <div
                                class="d-flex
                                       flex-wrap
                                       gap-2
                                       align-items-start">


                                <span
                                    class="priority-pill
                                    <?=
                                    priority_class(
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



                                <span
                                    class="status-pill
                                    <?=
                                    status_class(
                                        $client_status
                                    )
                                    ?>">


                                    <?=
                                    htmlspecialchars(
                                        $client_status,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                    ?>


                                </span>



                                <?php if ($is_unread): ?>

                                    <span class="unread-label">

                                        Unread

                                    </span>

                                <?php endif; ?>


                            </div>


                        </div>



                        <!-- ================================
                             CLIENT PROBLEM
                        ================================= -->

                        <div class="problem-box">


                            <strong>

                                Client Problem:

                            </strong>


                            <?=
                            htmlspecialchars(
                                $note["category"]
                                ?? "-",
                                ENT_QUOTES,
                                "UTF-8"
                            )
                            ?>


                            <?php
                            if (
                                !empty(
                                    $note["description"]
                                )
                            ):
                            ?>


                                <div class="mt-1">


                                    <?=
                                    nl2br(
                                        htmlspecialchars(
                                            $note["description"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        )
                                    )
                                    ?>


                                </div>


                            <?php endif; ?>


                        </div>



                        <!-- ================================
                             TECHNICIAN NOTE
                        ================================= -->

                        <div class="mt-3">


                            <div
                                class="fw-semibold
                                       mb-1"
                                style="font-size:12px;">


                                Technician Note


                            </div>


                            <div class="note-box">


                                <?=
                                nl2br(
                                    htmlspecialchars(
                                        $note["note"]
                                        ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                )
                                ?>


                            </div>


                        </div>



                        <!-- ================================
                             ACTIONS
                        ================================= -->

                        <div
                            class="d-flex
                                   flex-column
                                   flex-xl-row
                                   justify-content-between
                                   gap-3
                                   align-items-xl-center
                                   mt-3">


                            <div class="meta-text">


                                Suggested Status:


                                <strong>

                                    <?=
                                    htmlspecialchars(
                                        $note["suggested_status"]
                                        ?? "—",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                    ?>

                                </strong>


                            </div>



                            <div
                                class="d-flex
                                       flex-wrap
                                       gap-2
                                       align-items-center">


                                <!-- MARK READ -->

                                <?php
                                if (
                                    $has_is_read &&
                                    $is_unread
                                ):
                                ?>


                                    <form
                                        method="POST"
                                        class="m-0">


                                        <input
                                            type="hidden"
                                            name="note_id"
                                            value="<?=
                                                (int)$note["note_id"]
                                            ?>">


                                        <button
                                            type="submit"
                                            name="mark_read"
                                            class="btn
                                                   btn-outline-secondary
                                                   btn-sm">


                                            <i
                                                class="fa-solid
                                                       fa-check
                                                       me-1">
                                            </i>


                                            Mark Read


                                        </button>


                                    </form>


                                <?php endif; ?>



                                <!-- FINAL STATUS -->

                                <form
                                    method="POST"
                                    class="m-0
                                           d-flex
                                           gap-2
                                           align-items-center">


                                    <input
                                        type="hidden"
                                        name="request_id"
                                        value="<?= $request_id ?>">


                                    <select
                                        name="final_status"
                                        class="form-select
                                               form-select-sm"
                                        style="width:170px;"
                                        required>


                                        <?php

                                        $statuses = [
                                            "Submitted",
                                            "Assigned",
                                            "Processing",
                                            "Pending",
                                            "Completed",
                                            "Closed"
                                        ];


                                        foreach ($statuses as $status):

                                            $selected =
                                                strtolower($status)
                                                ===
                                                strtolower(
                                                    $client_status
                                                )
                                                ? "selected"
                                                : "";

                                        ?>


                                            <option
                                                value="<?= $status ?>"
                                                <?= $selected ?>>


                                                <?= $status ?>


                                            </option>


                                        <?php endforeach; ?>


                                    </select>



                                    <button
                                        type="submit"
                                        name="update_final_status"
                                        class="btn
                                               btn-action
                                               btn-sm">


                                        <i
                                            class="fa-solid
                                                   fa-floppy-disk
                                                   me-1">
                                        </i>


                                        Update Client


                                    </button>


                                </form>


                            </div>


                        </div>


                    </div>


                <?php endforeach; ?>


            <?php endif; ?>


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