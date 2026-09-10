<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'client') {
  header("Location: login.php");
  exit;
}

$user_id = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
  SELECT elevator_id, maintenance_type, last_service_date, next_service_date, status
  FROM maintenance_schedules
  WHERE user_id = ?
  ORDER BY next_service_date ASC
");
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Maintenance Overview</title>

<style>
  body { font-family: Arial, sans-serif; margin: 40px; }
  .header { display:flex; align-items:center; gap:15px; }
  .header img { height:60px; }
  h2 { margin:0; }
  hr { margin:20px 0; }
  table { width:100%; border-collapse:collapse; }
  th, td { border:1px solid #000; padding:8px; font-size:14px; }
  th { background:#f0f0f0; }

  @media print {
    body { margin: 20px; }
  }
</style>

<script>
  window.onload = function () {
    window.print();
  };
</script>

</head>
<body>

<div class="header">
  <img src="img/sonic-logo.jpeg" alt="Sonic Elevator Ltd">
  <div>
    <h2>Sonic Elevator Ltd.</h2>
    <small>Maintenance Overview</small>
  </div>
</div>

<hr>

<table>
  <tr>
    <th>Elevator</th>
    <th>Maintenance Type</th>
    <th>Last Service</th>
    <th>Next Service</th>
    <th>Status</th>
  </tr>

  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= htmlspecialchars($r['elevator_id']) ?></td>
    <td><?= htmlspecialchars($r['maintenance_type']) ?></td>
    <td><?= $r['last_service_date'] ?: 'N/A' ?></td>
    <td><?= htmlspecialchars($r['next_service_date']) ?></td>
    <td><?= htmlspecialchars($r['status']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>

</body>
</html>