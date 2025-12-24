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

    function get_all_mqtttopics() {
        global $mysqli;
        $result = $mysqli->query("SELECT * FROM mqtttopics");
        $mqtttopics = $result->fetch_all(MYSQLI_ASSOC);
        return $mqtttopics;
    }

    function add_mqtttopic($name, $mqttserver, $mqttport, $mqttusername, $mqttpassword, $mqtttopic) {
        global $mysqli;
        $stmt = $mysqli->prepare("INSERT INTO mqtttopics (name, mqttserver, mqttport, mqttusername, mqttpassword, mqtttopic) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssisss", $name, $mqttserver, $mqttport, $mqttusername, $mqttpassword, $mqtttopic);
        $stmt->execute();
    }

    function delete_mqtttopic($name) {
        global $mysqli;
		//Delete from routine steps first
		$stmt = $mysqli->prepare("DELETE FROM routinesteps WHERE actiontype = 'MQTT Publish' and mqtttopic = ?");
		$stmt->bind_param("s", $name);		
        $stmt->execute();
		
		//Now delete mqqt topic
        $stmt = $mysqli->prepare("DELETE FROM mqtttopics WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
    }

    $mqtttopics = get_all_mqtttopics();

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_mqtttopic'])) {
        $name = $_POST['name'];
        $mqttserver = $_POST['mqttserver'];
        $mqttport = $_POST['mqttport'];
        $mqttusername = $_POST['mqttusername'];
        $mqttpassword = $_POST['mqttpassword'];
        $mqtttopic = $_POST['mqtttopic'];
        add_mqtttopic($name, $mqttserver, $mqttport, $mqttusername, $mqttpassword, $mqtttopic);
        $mqtttopics = get_all_mqtttopics();
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_mqtttopic'])) {
        $name = $_POST['name'];
        delete_mqtttopic($name);
        $mqtttopics = get_all_mqtttopics();
    }

    $mysqli->close();
?>

<!DOCTYPE html>
<html>
    <head>
        <title>MQTT Topics</title>
        <link rel="stylesheet" href="styles.css">
    </head>
    <body>
        <?php include 'header.php'; ?>
        <h1>MQTT Topics</h1>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>MQTT Server</th>
                    <th>MQTT Port</th>
                    <th>MQTT Username</th>
                    <th>MQTT Password</th>
                    <th>MQTT Topic</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mqtttopics as $mqtttopic): ?>
                <tr>
                    <form method="post" action="admin_MQTT_Topics.php">
                        <td><?= $mqtttopic['name'] ?>
                            <input type="hidden" name="name" value="<?= $mqtttopic['name'] ?>">
                        </td>
                        <td><?= $mqtttopic['mqttserver'] ?></td>
                        <td><?= $mqtttopic['mqttport'] ?></td>
                        <td><?= $mqtttopic['mqttusername'] ?></td>
                        <td><?= $mqtttopic['mqttpassword'] ?></td>
                        <td><?= $mqtttopic['mqtttopic'] ?></td>
                        <td><button type="submit" name="delete_mqtttopic">Delete</button></td>
                    </form>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <form method="post" action="admin_MQTT_Topics.php">
                        <td><input type="text" name="name" required></td>
                        <td><input type="text" name="mqttserver" required></td>
                        <td><input type="text" name="mqttport" required></td>
                        <td><input type="text" name="mqttusername" required></td>
                        <td><input type="text" name="mqttpassword" required></td>
                        <td><input type="text" name="mqtttopic" required></td>
                        <td><button type="submit" name="add_mqtttopic">Add</button></td>
                    </form>
                </tr>
            </tbody>
        </table>
    </body>
</html>
