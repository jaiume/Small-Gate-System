<!DOCTYPE html>
<html>
<head>
    <title>Gate Management</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<?php
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] == false) {
        header('Location: /admin/login');
        exit();
    }
?>
<body>
    <header>
        <div class="logo">
            <img src="<?php echo "/".$logo_filename; ?>" alt="Logo" class="logo">

        </div>
        <nav>
            <ul>
                <li><a href="/admin">Users</a></li>
				<li><a href="/admin/buttons">Buttons</a></li>
                <li><a href="/admin/mqtt_topics">MQTT Topics</a></li>
				<li><a href="/admin/shelly_devices">Shelly Devices</a></li>
				<li><a href="/admin/button_logs">Button Logs</a></li>
                <li><a href="/admin/logout">Logout</a></li>
            </ul>
        </nav>
    </header>
