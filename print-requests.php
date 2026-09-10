<?php
require_once "config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'client') {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

/* ✅ IMPORTANT: your real table name is client_requests */
$stmt = $pdo->prepare("
    SELECT id, elevator_id, category, priority, status, created_at
    FROM client_requests
    WHERE user_id = ?
    ORDER BY id DESC
");
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Service Requests Report</title>

<style>
  body { font-family: Arial, sans-serif; margin: 40px; }
  .header { display:flex; align-items:center; gap:15px; }
  .header img { height:60px; }
  h2 { margin:0; }
  hr { margin:20px 0; }
  table { width:100%; border-collapse:collapse; }
  th, td { border:1px solid #000; padding:8px; font-size:14px; }
  th { background:#f0f0f0; }
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
    <small>Client Requests Report</small>
  </div>
</div>

<hr>

<table>
  <tr>
    <th>Request ID</th>
    <th>Elevator</th>
    <th>Category</th>
    <th>Priority</th>
    <th>Status</th>
    <th>Date</th>
  </tr>

  <?php if (empty($rows)): ?>
    <tr><td colspan="6">No requests found.</td></tr>
  <?php else: ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td>SR-<?php echo (int)$r['id']; ?></td>
        <td><?php echo htmlspecialchars($r['elevator_id']); ?></td>
        <td><?php echo htmlspecialchars($r['category']); ?></td>
        <td><?php echo htmlspecialchars($r['priority']); ?></td>
        <td><?php echo htmlspecialchars($r['status']); ?></td>
        <td><?php echo htmlspecialchars($r['created_at']); ?></td>
      </tr>
    <?php endforeach; ?>
  <?php endif; ?>
</table>

</body>
</html>