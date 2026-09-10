<?php

require_once "config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* ============================================================
   TECHNICIAN ONLY
============================================================ */

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "technician"
) {
    header("Location: login.php");
    exit;
}


$tech_id =
    (int)$_SESSION["user_id"];

$request_id =
    (int)($_GET["id"] ?? 0);

$error   = "";
$success = "";

$row = null;


/* ============================================================
   VALID REQUEST ID
============================================================ */

if ($request_id <= 0) {
    die("Invalid request ID.");
}


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
   REQUIRED TABLE CHECK
============================================================ */

if (!table_exists($pdo, "technician_notes")) {

    $error =
        "The technician_notes table was not found.";

}


/* ============================================================
   LOAD REQUEST
   Technician can only open their own assigned request.
============================================================ */

try {

    $stmt = $pdo->prepare("
        SELECT
            cr.*,

            u.name AS client_name,
            u.email AS client_email

        FROM client_requests cr

        LEFT JOIN users u
            ON u.id = cr.user_id

        WHERE cr.id = ?
        AND cr.technician_id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $request_id,
        $tech_id
    ]);

    $row =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$row) {

        die(
            "Request not found or this request is not assigned to you."
        );
    }

} catch (Throwable $e) {

    die(
        "Could not load the service request."
    );
}


/* ============================================================
   SEND TECHNICIAN UPDATE
============================================================ */

if (
    $error === "" &&
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["submit_note"])
) {

    $note =
        trim($_POST["note"] ?? "");

    $suggested_status =
        trim($_POST["suggested_status"] ?? "");


    $allowed_statuses = [
        "Processing",
        "Pending",
        "Completed"
    ];


    if ($note === "") {

        $error =
            "Work performed note is required.";

    } elseif (
        !in_array(
            $suggested_status,
            $allowed_statuses,
            true
        )
    ) {

        $error =
            "Invalid suggested status.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Security check
            |--------------------------------------------------------------------------
            | Make sure the request still belongs to this technician.
            */

            $check = $pdo->prepare("
                SELECT id
                FROM client_requests
                WHERE id = ?
                AND technician_id = ?
                LIMIT 1
            ");

            $check->execute([
                $request_id,
                $tech_id
            ]);


            if (!$check->fetchColumn()) {

                $error =
                    "You cannot send an update for this request.";

            } else {

                $has_is_read =
                    col_exists(
                        $pdo,
                        "technician_notes",
                        "is_read"
                    );


                $has_suggested_status =
                    col_exists(
                        $pdo,
                        "technician_notes",
                        "suggested_status"
                    );


                /*
                |--------------------------------------------------------------------------
                | Both optional columns exist
                |--------------------------------------------------------------------------
                */

                if (
                    $has_is_read &&
                    $has_suggested_status
                ) {

                    $stmt = $pdo->prepare("
                        INSERT INTO technician_notes
                        (
                            request_id,
                            technician_id,
                            note,
                            suggested_status,
                            is_read
                        )
                        VALUES (?, ?, ?, ?, 0)
                    ");

                    $stmt->execute([
                        $request_id,
                        $tech_id,
                        $note,
                        $suggested_status
                    ]);

                }


                /*
                |--------------------------------------------------------------------------
                | is_read exists, suggested_status does not
                |--------------------------------------------------------------------------
                */

                elseif (
                    $has_is_read &&
                    !$has_suggested_status
                ) {

                    $final_note =
                        $note
                        .
                        "\n\nSuggested Status: "
                        .
                        $suggested_status;


                    $stmt = $pdo->prepare("
                        INSERT INTO technician_notes
                        (
                            request_id,
                            technician_id,
                            note,
                            is_read
                        )
                        VALUES (?, ?, ?, 0)
                    ");

                    $stmt->execute([
                        $request_id,
                        $tech_id,
                        $final_note
                    ]);

                }


                /*
                |--------------------------------------------------------------------------
                | suggested_status exists, is_read does not
                |--------------------------------------------------------------------------
                */

                elseif (
                    !$has_is_read &&
                    $has_suggested_status
                ) {

                    $stmt = $pdo->prepare("
                        INSERT INTO technician_notes
                        (
                            request_id,
                            technician_id,
                            note,
                            suggested_status
                        )
                        VALUES (?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $request_id,
                        $tech_id,
                        $note,
                        $suggested_status
                    ]);

                }


                /*
                |--------------------------------------------------------------------------
                | Neither optional column exists
                |--------------------------------------------------------------------------
                */

                else {

                    $final_note =
                        $note
                        .
                        "\n\nSuggested Status: "
                        .
                        $suggested_status;


                    $stmt = $pdo->prepare("
                        INSERT INTO technician_notes
                        (
                            request_id,
                            technician_id,
                            note
                        )
                        VALUES (?, ?, ?)
                    ");

                    $stmt->execute([
                        $request_id,
                        $tech_id,
                        $final_note
                    ]);

                }


                $success =
                    "Work update sent to the manager successfully.";

            }

        } catch (Throwable $e) {

            $error =
                "Could not send the technician update.";

        }

    }

}


/* ============================================================
   LOAD PREVIOUS TECHNICIAN NOTES
============================================================ */

$previous_notes = [];


if (
    $error === "" &&
    table_exists($pdo, "technician_notes")
) {

    try {

        $has_suggested_status =
            col_exists(
                $pdo,
                "technician_notes",
                "suggested_status"
            );


        $sql = "
            SELECT
                id,
                note,
                created_at,
                "
                .
                (
                    $has_suggested_status
                    ? "suggested_status"
                    : "NULL AS suggested_status"
                )
                .
            "
            FROM technician_notes
            WHERE request_id = ?
            AND technician_id = ?
            ORDER BY id DESC
            LIMIT 20
        ";


        $stmt =
            $pdo->prepare($sql);

        $stmt->execute([
            $request_id,
            $tech_id
        ]);

        $previous_notes =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {

        $previous_notes = [];
    }

}


$client_status =
    $row["status"] ?? "Assigned";

$priority =
    $row["priority"] ?? "Low";

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1">

<title>
    Service Request SR-<?php echo (int)$request_id; ?>
    | Armor Bangladesh Ltd.
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

    background:var(--sidebar-hover);

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
   TITLE
============================================================ */

.request-title {

    font-size:22px;

    font-weight:700;
}


.request-subtitle {

    font-size:12px;

    color:var(--muted);

    margin-top:4px;
}


/* ============================================================
   PANELS
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
}


.panel-heading h5 {

    margin:0;

    font-size:16px;

    font-weight:600;
}


.panel-body {

    padding:20px;
}


/* ============================================================
   REQUEST INFO
============================================================ */

.info-card {

    border:
        1px solid #E5EAEC;

    border-radius:4px;

    padding:14px;

    height:100%;

    background:#FAFCFC;
}


.info-label {

    font-size:10px;

    color:var(--muted);

    text-transform:uppercase;

    letter-spacing:.5px;

    margin-bottom:4px;
}


.info-value {

    font-size:13px;

    font-weight:600;

    color:#34464D;
}


.description-box {

    background:#FFF8F1;

    border-left:
        3px solid var(--armor);

    padding:14px;

    line-height:1.55;

    font-size:13px;

    border-radius:3px;
}


/* ============================================================
   STATUS
============================================================ */

.status-pill,
.priority-pill {

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


.status-progress {

    background:#E6F1FB;

    color:#3478B8;
}


.status-pending {

    background:#EEEAF8;

    color:#67509B;
}


.status-new {

    background:#FFF4D9;

    color:#9A7617;
}


.priority-low {

    background:#EEF2F4;

    color:#637078;
}


.priority-medium {

    background:#E6F1FB;

    color:#3478B8;
}


.priority-high {

    background:#FFF4D9;

    color:#9A7617;
}


.priority-critical {

    background:#FDE9E8;

    color:#C04B45;
}


/* ============================================================
   FORM
============================================================ */

.form-label {

    font-size:12px;

    font-weight:600;

    color:#43535A;
}


.form-control,
.form-select {

    border-radius:3px;

    border:
        1px solid #D9DFE2;

    font-size:12px;
}


.form-control:focus,
.form-select:focus {

    border-color:var(--teal);

    box-shadow:
        0 0 0 .15rem rgba(88,168,154,.15);
}


.btn-send {

    background:var(--teal);

    border:
        1px solid var(--teal);

    color:white;

    border-radius:3px;

    font-size:12px;

    font-weight:600;

    min-height:41px;
}


.btn-send:hover {

    background:#478F83;

    color:white;
}


/* ============================================================
   PREVIOUS NOTES
============================================================ */

.note-item {

    border-bottom:
        1px solid #EDF0F2;

    padding:
        16px 20px;
}


.note-item:last-child {

    border-bottom:0;
}


.note-date {

    font-size:10px;

    color:var(--muted);
}


.note-text {

    font-size:13px;

    line-height:1.55;

    margin-top:8px;

    color:#42525A;
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

    <a href="technician-dashboard.php">

        <img
            src="img/armor.jpg"
            alt="Armor Bangladesh Ltd.">

    </a>

</div>


<div class="sidebar-menu">


    <a
        href="technician-dashboard.php"
        class="sidebar-link">

        <i class="fa-solid fa-house"></i>

        Dashboard

    </a>


    <div class="menu-title">
        Service Work
    </div>


    <a
        href="technician-requests.php"
        class="sidebar-link">

        <i class="fa-solid fa-list-check"></i>

        My Requests

    </a>


    <a
        href="technician-request-details.php?id=<?php echo (int)$request_id; ?>"
        class="sidebar-link active">

        <i class="fa-solid fa-folder-open"></i>

        Request Details

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
            Technician Portal

        </div>


    </div>



    <div class="top-user">


        <div class="user-avatar">

            <i class="fa-solid fa-user-gear"></i>

        </div>


        <div class="user-info">

            <strong>

                <?php
                echo htmlspecialchars(
                    $_SESSION["name"]
                    ?? "Technician",
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>

            </strong>

            <small>
                Technician
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
            Technician Dashboard
        </strong>

        &nbsp;›&nbsp;

        My Requests

        &nbsp;›&nbsp;

        SR-<?php echo (int)$request_id; ?>

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


    <?php if ($success !== ""): ?>

        <div class="alert alert-success">

            <i
                class="fa-solid
                       fa-circle-check
                       me-1">
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



    <!-- ========================================================
         PAGE TITLE
    ========================================================= -->

    <div
        class="d-flex
               flex-column
               flex-md-row
               justify-content-between
               align-items-md-center
               gap-3">


        <div>


            <div class="request-title">

                Service Request
                SR-<?php echo (int)$request_id; ?>

            </div>


            <div class="request-subtitle">

                Review the client problem and
                submit your work update to the manager.

            </div>


        </div>



        <a
            href="technician-requests.php"
            class="btn
                   btn-outline-secondary
                   btn-sm">

            <i class="fa-solid fa-arrow-left me-1"></i>

            Back to My Requests

        </a>


    </div>



    <!-- ========================================================
         REQUEST INFORMATION
    ========================================================= -->

    <section class="work-panel">


        <div
            class="panel-heading
                   d-flex
                   justify-content-between
                   align-items-center">


            <h5>

                <i
                    class="fa-solid
                           fa-clipboard-list
                           me-2"
                    style="color:#F58220;">
                </i>

                Request Information

            </h5>


            <div class="d-flex gap-2">


                <span
                    class="priority-pill
                    <?php
                    echo priority_class($priority);
                    ?>">

                    <?php
                    echo htmlspecialchars(
                        $priority,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>

                </span>


                <span
                    class="status-pill
                    <?php
                    echo status_class($client_status);
                    ?>">

                    <?php
                    echo htmlspecialchars(
                        $client_status,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>

                </span>


            </div>


        </div>



        <div class="panel-body">


            <div class="row g-3">


                <!-- CLIENT -->

                <div class="col-xl-4 col-md-6">

                    <div class="info-card">

                        <div class="info-label">
                            Client
                        </div>


                        <div class="info-value">

                            <?php
                            echo htmlspecialchars(
                                $row["client_name"]
                                ?? "Client",
                                ENT_QUOTES,
                                "UTF-8"
                            );
                            ?>

                        </div>


                        <?php if (!empty($row["client_email"])): ?>

                            <small class="text-muted">

                                <?php
                                echo htmlspecialchars(
                                    $row["client_email"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>

                            </small>

                        <?php endif; ?>

                    </div>

                </div>



                <!-- TERMINAL -->

                <div class="col-xl-4 col-md-6">

                    <div class="info-card">

                        <div class="info-label">
                            Terminal ID
                        </div>


                        <div class="info-value">

                            <?php
                            echo htmlspecialchars(
                                $row["elevator_id"]
                                ?? "-",
                                ENT_QUOTES,
                                "UTF-8"
                            );
                            ?>

                        </div>

                    </div>

                </div>



                <!-- PROBLEM -->

                <div class="col-xl-4 col-md-6">

                    <div class="info-card">

                        <div class="info-label">
                            Problem Category
                        </div>


                        <div class="info-value">

                            <?php
                            echo htmlspecialchars(
                                $row["category"]
                                ?? "-",
                                ENT_QUOTES,
                                "UTF-8"
                            );
                            ?>

                        </div>

                    </div>

                </div>



                <!-- PRIORITY -->

                <div class="col-xl-3 col-md-6">

                    <div class="info-card">

                        <div class="info-label">
                            Priority
                        </div>

                        <div class="info-value">

                            <?php
                            echo htmlspecialchars(
                                $priority,
                                ENT_QUOTES,
                                "UTF-8"
                            );
                            ?>

                        </div>

                    </div>

                </div>



                <!-- STATUS -->

                <div class="col-xl-3 col-md-6">

                    <div class="info-card">

                        <div class="info-label">
                            Current Status
                        </div>

                        <div class="info-value">

                            <?php
                            echo htmlspecialchars(
                                $client_status,
                                ENT_QUOTES,
                                "UTF-8"
                            );
                            ?>

                        </div>

                    </div>

                </div>



                <!-- CONTACT -->

                <div class="col-xl-3 col-md-6">

                    <div class="info-card">

                        <div class="info-label">
                            Preferred Contact
                        </div>

                        <div class="info-value">

                            <?php
                            echo htmlspecialchars(
                                $row["contact_method"]
                                ?? "-",
                                ENT_QUOTES,
                                "UTF-8"
                            );
                            ?>

                        </div>

                    </div>

                </div>



                <!-- CREATED -->

                <div class="col-xl-3 col-md-6">

                    <div class="info-card">

                        <div class="info-label">
                            Submitted
                        </div>

                        <div class="info-value">

                            <?php
                            echo htmlspecialchars(
                                $row["created_at"]
                                ?? "-",
                                ENT_QUOTES,
                                "UTF-8"
                            );
                            ?>

                        </div>

                    </div>

                </div>


                <!-- DESCRIPTION -->

                <div class="col-12">


                    <div class="info-label mb-2">

                        Client Problem Description

                    </div>


                    <div class="description-box">

                        <?php
                        echo nl2br(
                            htmlspecialchars(
                                $row["description"]
                                ?? "-",
                                ENT_QUOTES,
                                "UTF-8"
                            )
                        );
                        ?>

                    </div>


                </div>


            </div>


        </div>


    </section>



    <!-- ========================================================
         SEND UPDATE
    ========================================================= -->

    <section class="work-panel">


        <div class="panel-heading">

            <h5>

                <i
                    class="fa-solid
                           fa-paper-plane
                           me-2"
                    style="color:#F58220;">
                </i>

                Send Work Update to Manager

            </h5>

        </div>


        <div class="panel-body">


            <form
                method="POST"
                class="row g-3">


                <div class="col-12">


                    <label class="form-label">

                        Work Performed *

                    </label>


                    <textarea
                        name="note"
                        class="form-control"
                        rows="5"
                        placeholder="Describe troubleshooting, work performed, observations, parts replaced, test results, or any follow-up required."
                        required><?php
                            echo htmlspecialchars(
                                $_POST["note"]
                                ?? "",
                                ENT_QUOTES,
                                "UTF-8"
                            );
                        ?></textarea>


                </div>



                <div class="col-xl-6">


                    <label class="form-label">

                        Suggested Status

                    </label>


                    <select
                        name="suggested_status"
                        class="form-select">


                        <option
                            value="Processing"
                            <?=
                            ($_POST["suggested_status"] ?? "")
                            === "Processing"
                                ? "selected"
                                : ""
                            ?>>

                            Processing

                        </option>


                        <option
                            value="Pending"
                            <?=
                            ($_POST["suggested_status"] ?? "")
                            === "Pending"
                                ? "selected"
                                : ""
                            ?>>

                            Pending

                        </option>


                        <option
                            value="Completed"
                            <?=
                            ($_POST["suggested_status"] ?? "")
                            === "Completed"
                                ? "selected"
                                : ""
                            ?>>

                            Completed

                        </option>


                    </select>


                    <small class="text-muted">

                        This is only your recommendation.
                        The manager controls the final client-visible status.

                    </small>


                </div>



                <div
                    class="col-xl-6
                           d-flex
                           align-items-end">


                    <button
                        type="submit"
                        name="submit_note"
                        class="btn
                               btn-send
                               w-100">


                        <i
                            class="fa-solid
                                   fa-paper-plane
                                   me-1">
                        </i>


                        Send Update to Manager


                    </button>


                </div>


            </form>


        </div>


    </section>



    <!-- ========================================================
         PREVIOUS UPDATES
    ========================================================= -->

    <section class="work-panel">


        <div
            class="panel-heading
                   d-flex
                   justify-content-between
                   align-items-center">


            <h5>

                Previous Work Updates

            </h5>


            <span class="small text-muted">

                <?php echo count($previous_notes); ?>

                update(s)

            </span>


        </div>



        <?php if (empty($previous_notes)): ?>


            <div
                class="text-center
                       py-5
                       text-muted">

                <i
                    class="fa-regular
                           fa-comment-dots
                           fs-3
                           d-block
                           mb-2">
                </i>

                No work updates have been submitted yet.

            </div>


        <?php else: ?>


            <?php foreach ($previous_notes as $previous): ?>


                <div class="note-item">


                    <div
                        class="d-flex
                               justify-content-between
                               gap-3">


                        <strong style="font-size:12px;">

                            Technician Update

                        </strong>


                        <span class="note-date">

                            <?php
                            echo htmlspecialchars(
                                $previous["created_at"]
                                ?? "-",
                                ENT_QUOTES,
                                "UTF-8"
                            );
                            ?>

                        </span>


                    </div>



                    <div class="note-text">

                        <?php
                        echo nl2br(
                            htmlspecialchars(
                                $previous["note"]
                                ?? "-",
                                ENT_QUOTES,
                                "UTF-8"
                            )
                        );
                        ?>

                    </div>



                    <?php if (!empty($previous["suggested_status"])): ?>


                        <div class="mt-2">

                            <small class="text-muted">

                                Suggested Status:

                            </small>


                            <strong
                                style="font-size:11px;">

                                <?php
                                echo htmlspecialchars(
                                    $previous["suggested_status"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>

                            </strong>

                        </div>


                    <?php endif; ?>


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