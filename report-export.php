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


/* ============================================================
   EXPORT TYPE
============================================================ */

$type =
    strtolower(
        trim($_GET["type"] ?? "csv")
    );


if ($type !== "csv") {
    die("Invalid export type.");
}


/* ============================================================
   LOAD SERVICE REQUESTS
============================================================ */

try {

    $stmt = $pdo->query("
        SELECT

            cr.id,

            cr.elevator_id,

            cr.category,

            cr.description,

            cr.priority,

            cr.status,

            cr.contact_method,

            cr.created_at,

            client.name AS client_name,

            client.email AS client_email,

            technician.name AS technician_name,

            technician.email AS technician_email

        FROM client_requests cr

        LEFT JOIN users client
            ON client.id = cr.user_id

        LEFT JOIN users technician
            ON technician.id = cr.technician_id

        ORDER BY cr.id DESC
    ");


    $rows =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (Throwable $e) {

    die("Could not generate the report.");
}


/* ============================================================
   CSV DOWNLOAD HEADERS
============================================================ */

$filename =
    "armor-service-report-"
    .
    date("Y-m-d-His")
    .
    ".csv";


header(
    "Content-Type: text/csv; charset=UTF-8"
);

header(
    'Content-Disposition: attachment; filename="' .
    $filename .
    '"'
);

header(
    "Pragma: no-cache"
);

header(
    "Expires: 0"
);


/* ============================================================
   OPEN OUTPUT
============================================================ */

$output =
    fopen("php://output", "w");


if ($output === false) {
    die("Could not create CSV file.");
}


/*
|--------------------------------------------------------------------------
| Excel UTF-8 BOM
|--------------------------------------------------------------------------
| Helps Bangla / special characters display correctly in Excel.
*/

fwrite(
    $output,
    "\xEF\xBB\xBF"
);


/* ============================================================
   CSV HEADER ROW
============================================================ */

fputcsv(
    $output,
    [
        "Request ID",
        "Client Name",
        "Client Email",
        "Terminal ID",
        "Problem Category",
        "Problem Description",
        "Priority",
        "Status",
        "Preferred Contact",
        "Assigned Technician",
        "Technician Email",
        "Submitted Date"
    ]
);


/* ============================================================
   CSV DATA
============================================================ */

foreach ($rows as $row) {

    fputcsv(
        $output,
        [

            "SR-" .
            (int)$row["id"],

            $row["client_name"]
            ?? "",

            $row["client_email"]
            ?? "",

            $row["elevator_id"]
            ?? "",

            $row["category"]
            ?? "",

            $row["description"]
            ?? "",

            $row["priority"]
            ?? "",

            $row["status"]
            ?? "",

            $row["contact_method"]
            ?? "",

            $row["technician_name"]
            ?? "Not Assigned",

            $row["technician_email"]
            ?? "",

            $row["created_at"]
            ?? ""
        ]
    );
}


fclose($output);

exit;