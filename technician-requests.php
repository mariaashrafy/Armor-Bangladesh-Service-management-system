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

$tech_name =
    $_SESSION["name"] ?? "Technician";

$error = "";
$rows = [];


/* ============================================================
   HELPERS
============================================================ */

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
   LOAD TECHNICIAN NAME
============================================================ */

try {

    $stmt = $pdo->prepare("
        SELECT name
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$tech_id]);

    $name =
        $stmt->fetchColumn();

    if ($name) {
        $tech_name = $name;
    }

} catch (Throwable $e) {
    /* keep default */
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
   LOAD ASSIGNED REQUESTS
============================================================ */

try {

    $sql = "
        SELECT
            cr.id,
            cr.elevator_id,
            cr.category,
            cr.priority,
            cr.description,
            cr.status,
            cr.created_at,

            u.name AS client_name,
            u.email AS client_email

        FROM client_requests cr

        LEFT JOIN users u
            ON u.id = cr.user_id

        WHERE cr.technician_id = ?
    ";

    $params = [$tech_id];


    if ($filter_status !== "") {

        $sql .= "
            AND LOWER(cr.status) = LOWER(?)
        ";

        $params[] =
            $filter_status;
    }


    if ($filter_priority !== "") {

        $sql .= "
            AND LOWER(cr.priority) = LOWER(?)
        ";

        $params[] =
            $filter_priority;
    }


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

        $search =
            "%" . $filter_search . "%";

        for ($i = 0; $i < 6; $i++) {
            $params[] = $search;
        }
    }


    $sql .= "
        ORDER BY cr.id DESC
    ";


    $stmt =
        $pdo->prepare($sql);

    $stmt->execute($params);

    $rows =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $error =
        "Failed to load your assigned requests.";
}


/* ============================================================
   COUNTERS
============================================================ */

$total_requests     = 0;
$active_requests    = 0;
$pending_requests   = 0;
$completed_requests = 0;


try {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM client_requests
        WHERE technician_id = ?
    ");

    $stmt->execute([$tech_id]);

    $total_requests =
        (int)$stmt->fetchColumn();


    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM client_requests
        WHERE technician_id = ?
        AND LOWER(status)
        IN ('assigned','processing')
    ");

    $stmt->execute([$tech_id]);

    $active_requests =
        (int)$stmt->fetchColumn();


    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM client_requests
        WHERE technician_id = ?
        AND LOWER(status) = 'pending'
    ");

    $stmt->execute([$tech_id]);

    $pending_requests =
        (int)$stmt->fetchColumn();


    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM client_requests
        WHERE technician_id = ?
        AND LOWER(status)
        IN ('completed','closed')
    ");

    $stmt->execute([$tech_id]);

    $completed_requests =
        (int)$stmt->fetchColumn();

} catch (Throwable $e) {
    /* keep defaults */
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
   KPI
============================================================ */

.summary-card {

    background:white;

    border:
        1px solid var(--border);

    border-radius:6px;

    padding:16px 17px;

    height:100%;
}


.summary-label {

    color:var(--muted);

    font-size:12px;

    margin-bottom:5px;
}


.summary-value {

    font-size:25px;

    font-weight:700;
}


.summary-icon {

    width:39px;

    height:39px;

    border-radius:5px;

    background:
        rgba(88,168,154,.12);

    color:var(--teal);

    display:grid;

    place-items:center;
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

    align-items:center;

    justify-content:space-between;

    gap:15px;
}


.panel-heading h5 {

    margin:0;

    font-size:16px;

    font-weight:600;
}


/* ============================================================
   FILTERS
============================================================ */

.filter-area {

    padding:
        15px 20px 20px;
}


.filter-control {

    height:39px;

    border-radius:3px;

    border:
        1px solid #D9DFE2;

    font-size:12px;
}


.btn-filter {

    height:39px;

    background:var(--teal);

    border:
        1px solid var(--teal);

    color:white;

    border-radius:3px;

    font-size:12px;

    font-weight:600;
}


.btn-filter:hover {

    background:#478F83;

    color:white;
}


/* ============================================================
   TABLE
============================================================ */

.table-container {

    overflow-x:auto;
}


.request-table {

    min-width:1300px;

    margin:0;

    font-size:12px;
}


.request-table thead th {

    background:var(--teal);

    color:white;

    border:0;

    padding:
        12px 11px;

    font-size:11px;

    font-weight:600;

    text-transform:uppercase;

    white-space:nowrap;
}


.request-table tbody td {

    padding:
        13px 11px;

    vertical-align:middle;

    border-color:#EDF0F2;

    color:#47555C;
}


.request-table tbody tr:hover {

    background:#F9FCFB;
}


.description-cell {

    min-width:230px;

    max-width:320px;

    white-space:normal;

    line-height:1.45;
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
   ACTION
============================================================ */

.btn-open {

    border:
        1px solid var(--teal);

    color:var(--teal);

    border-radius:3px;

    font-size:11px;

    font-weight:600;
}


.btn-open:hover {

    background:var(--teal);

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
        class="sidebar-link active">

        <i class="fa-solid fa-list-check"></i>

        My Requests

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
                    $tech_name,
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



    <!-- ========================================================
         PAGE TITLE
    ========================================================= -->

    <div class="mb-4">

        <h4 class="fw-bold mb-1">

            My Assigned Requests

        </h4>

        <div class="text-muted small">

            Review client problems assigned to you
            and submit service updates to the manager.

        </div>

    </div>



    <!-- ========================================================
         KPI
    ========================================================= -->

    <div class="row g-3">


        <div class="col-xl-3 col-md-6">

            <div class="summary-card">

                <div
                    class="d-flex
                           justify-content-between
                           align-items-center">

                    <div>

                        <div class="summary-label">

                            Total Assigned

                        </div>

                        <div class="summary-value">

                            <?php echo $total_requests; ?>

                        </div>

                    </div>


                    <div class="summary-icon">

                        <i class="fa-solid fa-clipboard-list"></i>

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

                            Active

                        </div>

                        <div class="summary-value">

                            <?php echo $active_requests; ?>

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

                            Pending

                        </div>

                        <div class="summary-value">

                            <?php echo $pending_requests; ?>

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

                <div
                    class="d-flex
                           justify-content-between
                           align-items-center">

                    <div>

                        <div class="summary-label">

                            Completed

                        </div>

                        <div class="summary-value">

                            <?php echo $completed_requests; ?>

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
         REQUEST LIST
    ========================================================= -->

    <section class="work-panel">


        <div class="panel-heading">


            <div>

                <h5 class="mb-1">

                    Assigned Service Requests

                </h5>

                <small class="text-muted">

                    Only requests assigned to your technician account are shown.

                </small>

            </div>


            <span class="small text-muted">

                <?php echo count($rows); ?>

                result(s)

            </span>


        </div>



        <!-- FILTER -->

        <div class="filter-area">


            <form
                method="GET"
                action="technician-requests.php">


                <div class="row g-2">


                    <div class="col-xl-2 col-md-4">


                        <select
                            name="status"
                            class="form-select filter-control">


                            <option value="">
                                All Status
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
                            value="<?php
                                echo htmlspecialchars(
                                    $filter_search,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
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
                            href="technician-requests.php"
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



        <!-- TABLE -->

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
                            Submitted
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>


                </thead>


                <tbody>


                <?php if (empty($rows)): ?>


                    <tr>

                        <td
                            colspan="9"
                            class="text-center py-5 text-muted">

                            <i
                                class="fa-regular
                                       fa-folder-open
                                       fs-3
                                       d-block
                                       mb-2">
                            </i>

                            No assigned service requests found.

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach ($rows as $request): ?>


                        <?php

                        $status =
                            $request["status"]
                            ?? "Assigned";

                        $priority =
                            $request["priority"]
                            ?? "Low";

                        ?>


                        <tr>


                            <td>

                                <strong>

                                    SR-<?php echo (int)$request["id"]; ?>

                                </strong>

                            </td>



                            <td>

                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $request["client_name"]
                                        ?? "Client",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </strong>


                                <?php if (!empty($request["client_email"])): ?>

                                    <small class="d-block text-muted">

                                        <?php
                                        echo htmlspecialchars(
                                            $request["client_email"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );
                                        ?>

                                    </small>

                                <?php endif; ?>

                            </td>



                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $request["elevator_id"]
                                    ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>

                            </td>



                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $request["category"]
                                    ?? "-",
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>

                            </td>



                            <td class="description-cell">

                                <?php
                                echo nl2br(
                                    htmlspecialchars(
                                        $request["description"]
                                        ?? "-",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    )
                                );
                                ?>

                            </td>



                            <td>

                                <span
                                    class="priority-pill
                                    <?php
                                    echo priority_class(
                                        $priority
                                    );
                                    ?>">

                                    <?php
                                    echo htmlspecialchars(
                                        $priority,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </span>

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
                                        $status,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </span>

                            </td>



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



                            <td>

                                <a
                                    href="technician-request-details.php?id=<?php echo (int)$request["id"]; ?>"
                                    class="btn btn-sm btn-open">

                                    Open

                                    <i class="fa-solid fa-arrow-right ms-1"></i>

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