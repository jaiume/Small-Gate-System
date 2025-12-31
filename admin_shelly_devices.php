<?php
    session_start();
    require_once 'config.php';
    require_once 'functions.php';

    if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
        header('Location: admin_login.php');
        exit();
    }

    $mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);

    if ($mysqli->connect_error) {
        die("Connection failed: " . $mysqli->connect_error);
    }

    function get_all_shelly_devices() {
        global $mysqli;
        $result = $mysqli->query("SELECT * FROM shellydevices ORDER BY name");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    function add_shelly_device($name, $server_uri, $auth_key, $device_id) {
        global $mysqli;
        $stmt = $mysqli->prepare("INSERT INTO shellydevices (name, server_uri, auth_key, device_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $server_uri, $auth_key, $device_id);
        $stmt->execute();
        $stmt->close();
    }

    function update_shelly_device($original_name, $name, $server_uri, $auth_key, $device_id) {
        global $mysqli;
        $stmt = $mysqli->prepare("UPDATE shellydevices SET name = ?, server_uri = ?, auth_key = ?, device_id = ? WHERE name = ?");
        $stmt->bind_param("sssss", $name, $server_uri, $auth_key, $device_id, $original_name);
        $stmt->execute();
        $stmt->close();
    }

    function delete_shelly_device($name) {
        global $mysqli;
        // Update routine steps that reference this device
        $stmt = $mysqli->prepare("UPDATE routinesteps SET shelly_device = NULL WHERE shelly_device = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $stmt->close();
        
        // Delete the device
        $stmt = $mysqli->prepare("DELETE FROM shellydevices WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $stmt->close();
    }

    $shelly_devices = get_all_shelly_devices();

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['add_device'])) {
            $name = $_POST['name'];
            $server_uri = $_POST['server_uri'];
            $auth_key = $_POST['auth_key'];
            $device_id = $_POST['device_id'];
            add_shelly_device($name, $server_uri, $auth_key, $device_id);
            header('Location: /admin/shelly_devices');
            exit();
        } elseif (isset($_POST['update_device'])) {
            $original_name = $_POST['original_name'];
            $name = $_POST['name'];
            $server_uri = $_POST['server_uri'];
            $auth_key = $_POST['auth_key'];
            $device_id = $_POST['device_id'];
            update_shelly_device($original_name, $name, $server_uri, $auth_key, $device_id);
            header('Location: /admin/shelly_devices');
            exit();
        } elseif (isset($_POST['delete_device'])) {
            $name = $_POST['name'];
            delete_shelly_device($name);
            $shelly_devices = get_all_shelly_devices();
        }
    }

    $mysqli->close();
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Shelly Devices</title>
        <link rel="stylesheet" href="styles.css">
    </head>
    <body>
        <?php include 'header.php'; ?>
        <h1>Shelly Devices</h1>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Server URI</th>
                    <th>Auth Key</th>
                    <th>Device ID</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shelly_devices as $device): ?>
                <tr>
                    <form method="post" action="/admin/shelly_devices">
                        <td>
                            <input type="hidden" name="original_name" value="<?= htmlspecialchars($device['name']) ?>">
                            <input type="text" name="name" value="<?= htmlspecialchars($device['name']) ?>" required>
                        </td>
                        <td>
                            <input type="text" name="server_uri" value="<?= htmlspecialchars($device['server_uri']) ?>" required>
                        </td>
                        <td>
                            <input type="text" name="auth_key" value="<?= htmlspecialchars($device['auth_key']) ?>" required>
                        </td>
                        <td>
                            <input type="text" name="device_id" value="<?= htmlspecialchars($device['device_id']) ?>" required>
                        </td>
                        <td>
                            <button type="submit" name="update_device">Update</button>
                            <button type="submit" name="delete_device" onclick="return confirm('Are you sure you want to delete this device?');">Delete</button>
                        </td>
                    </form>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <form method="post" action="/admin/shelly_devices">
                        <td><input type="text" name="name" placeholder="Device Name" required></td>
                        <td><input type="text" name="server_uri" placeholder="shelly-xx-eu.shelly.cloud" required></td>
                        <td><input type="text" name="auth_key" placeholder="Auth Key" required></td>
                        <td><input type="text" name="device_id" placeholder="Device ID" required></td>
                        <td><button type="submit" name="add_device">Add</button></td>
                    </form>
                </tr>
            </tbody>
        </table>
    </body>
</html>




