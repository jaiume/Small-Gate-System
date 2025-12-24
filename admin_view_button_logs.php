<?php
	session_start();
	require_once 'config.php';
	require_once 'functions.php';
	
	// Check if the admin is logged in
	if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
		header('Location: admin_login.php');
		exit();
	}
	$mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);
	if ($mysqli->connect_error) {
		die("Connection failed: " . $mysqli->connect_error);
	}
	
	// Get all button logs
	function get_all_button_logs() {
		global $mysqli, $timezone;
		$result = $mysqli->query("SELECT * FROM button_logs order by datetime desc");
		$button_logs = $result->fetch_all(MYSQLI_ASSOC);
		foreach ($button_logs as &$log) {
			$utcDateTime = new DateTime($log['datetime'], new DateTimeZone('UTC'));
			$utcDateTime->setTimezone(new DateTimeZone($timezone));
			$log['datetime'] = $utcDateTime->format('Y-m-d H:i:s');
		}
		return $button_logs;
	}
	
	// Clear all button logs
	function clear_button_logs() {
		global $mysqli;
		$mysqli->query("DELETE FROM button_logs");
	}
	
	// Handle clear logs form submission
	if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['clear_logs'])) {
		clear_button_logs();
	}
	
	// Get all button logs
	$button_logs = get_all_button_logs();
	
	$mysqli->close();
?>

<!DOCTYPE html>
<html>
	<head>
		<title>Button Logs</title>
		<link rel="stylesheet" href="styles.css">
	</head>
	<body>
		<?php include 'header.php'; ?>
		
		<h1>Button Logs</h1>
		<form method="post" action="/admin/button_logs">
			<button type="submit" name="clear_logs">Clear Logs</button>
		</form>
		<table>
			<tr>
				<th>ID</th>
				<th>DateTime</th>
				<th>Username</th>
				<th>Button</th>
			</tr>
			<?php foreach ($button_logs as $log): ?>
			<tr>
				<td><?= $log['id'] ?></td>
				<td><?= $log['datetime'] ?></td>
				<td><?= $log['username'] ?></td>
				<td><?= $log['button'] ?></td>
			</tr>
			<?php endforeach; ?>
		</table>
	</body>
</html>
