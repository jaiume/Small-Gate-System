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

// Get button_id from URL parameter
$button_id = isset($_GET['button_id']) ? intval($_GET['button_id']) : 0;

// Verify button exists
$stmt = $mysqli->prepare("SELECT label FROM buttons WHERE id = ?");
$stmt->bind_param("i", $button_id);
$stmt->execute();
$result = $stmt->get_result();
$button = $result->fetch_assoc();

if (!$button) {
    die("Button not found");
}

// Get existing status check configuration
function get_status_check($button_id) {
    global $mysqli;
    $stmt = $mysqli->prepare("SELECT * FROM statuschecks WHERE button_id = ?");
    $stmt->bind_param("i", $button_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Get list of MQTT topics
$mqtt_topics_query = $mysqli->query("SELECT name, mqtttopic FROM mqtttopics ORDER BY name");
$mqtt_topics = $mqtt_topics_query->fetch_all(MYSQLI_ASSOC);

// Get list of Shelly devices
$shelly_devices_query = $mysqli->query("SELECT name, device_id FROM shellydevices ORDER BY name");
$shelly_devices = $shelly_devices_query->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action_type = $_POST['action_type'];
    $httpurl = $_POST['httpurl'] ?? '';
    $httpbody = $_POST['httpbody'] ?? '';
    $mqtt_trigger_topic = $_POST['mqtt_trigger_topic'] ?? '';
    $mqtt_trigger_payload = $_POST['mqtt_trigger_payload'] ?? '';
    $mqtt_status_topic = $_POST['mqtt_status_topic'] ?? '';
    $shelly_device = $_POST['shelly_device'] ?? '';
    $shelly_command = $_POST['shelly_command'] ?? '';
    $open_result = $_POST['open_result'] ?? '';
    $closed_result = $_POST['closed_result'] ?? '';

    // Check if status check already exists
    $existing_check = get_status_check($button_id);
    
    if ($existing_check) {
        // Update existing status check
        $stmt = $mysqli->prepare("UPDATE statuschecks SET 
            action_type = ?, 
            httpurl = ?, 
            httpbody = ?,
            mqtt_trigger_topic = ?, 
            mqtt_trigger_payload = ?,
            mqtt_status_topic = ?,
            shelly_device = ?,
            shelly_command = ?,
            open_result = ?, 
            closed_result = ? 
            WHERE button_id = ?");
        $stmt->bind_param("ssssssssssi", $action_type, $httpurl, $httpbody, 
                         $mqtt_trigger_topic, $mqtt_trigger_payload, $mqtt_status_topic, 
                         $shelly_device, $shelly_command, $open_result, $closed_result, $button_id);
    } else {
        // Insert new status check
        $stmt = $mysqli->prepare("INSERT INTO statuschecks 
            (button_id, action_type, httpurl, httpbody, mqtt_trigger_topic, mqtt_trigger_payload, mqtt_status_topic, shelly_device, shelly_command, open_result, closed_result) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssssssss", $button_id, $action_type, $httpurl, $httpbody, 
                         $mqtt_trigger_topic, $mqtt_trigger_payload, $mqtt_status_topic, 
                         $shelly_device, $shelly_command, $open_result, $closed_result);
    }
    
    $stmt->execute();
    header("Location: /admin/buttons/" . $button_id . "/status");
    exit();
}

$status_check = get_status_check($button_id);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Status Check Definition</title>
    <link rel="stylesheet" href="/styles.css">
    <style>
        .action-fields {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
        .action-fields.active {
            display: block;
        }
    </style>
    <script>
        function toggleFields() {
            var actionType = document.getElementById('action_type').value;
            
            // Hide all field containers
            document.getElementById('http_fields').classList.remove('active');
            document.getElementById('mqtt_fields').classList.remove('active');
            document.getElementById('shelly_fields').classList.remove('active');
            
            // Show appropriate fields
            if (actionType === 'http_get' || actionType === 'http_post') {
                document.getElementById('http_fields').classList.add('active');
            } else if (actionType === 'mqtt') {
                document.getElementById('mqtt_fields').classList.add('active');
            } else if (actionType === 'shelly') {
                document.getElementById('shelly_fields').classList.add('active');
            }
        }
        
        // Run on page load
        window.onload = function() {
            toggleFields();
        };
    </script>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="form-container">
        <h1>Status Check Definition for Button: <?= htmlspecialchars($button['label']) ?></h1>
        
        <form method="post" action="/admin/buttons/<?= $button_id ?>/status">
            <div class="form-group">
                <label for="action_type">Check Type:</label>
                <select name="action_type" id="action_type" onchange="toggleFields()" required>
                    <option value="http_get" <?= ($status_check['action_type'] ?? '') === 'http_get' ? 'selected' : '' ?>>HTTP GET</option>
                    <option value="http_post" <?= ($status_check['action_type'] ?? '') === 'http_post' ? 'selected' : '' ?>>HTTP POST</option>
                    <option value="mqtt" <?= ($status_check['action_type'] ?? '') === 'mqtt' ? 'selected' : '' ?>>MQTT</option>
                    <option value="shelly" <?= ($status_check['action_type'] ?? '') === 'shelly' ? 'selected' : '' ?>>Shelly Device</option>
                </select>
            </div>

            <!-- HTTP Fields -->
            <div id="http_fields" class="action-fields">
                <div class="form-group">
                    <label for="httpurl">HTTP URL:</label>
                    <input type="text" name="httpurl" id="httpurl" value="<?= htmlspecialchars($status_check['httpurl'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="httpbody">HTTP Body (optional, for POST):</label>
                    <textarea name="httpbody" id="httpbody"><?= htmlspecialchars($status_check['httpbody'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- MQTT Fields -->
            <div id="mqtt_fields" class="action-fields">
                <div class="form-group">
                    <label for="mqtt_trigger_topic">MQTT Trigger Topic (optional):</label>
                    <select name="mqtt_trigger_topic" id="mqtt_trigger_topic">
                        <option value="">-- None --</option>
                        <?php foreach ($mqtt_topics as $topic): ?>
                            <option value="<?= htmlspecialchars($topic['name']) ?>" 
                                <?= ($status_check['mqtt_trigger_topic'] ?? '') === $topic['name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($topic['name']) ?> (<?= htmlspecialchars($topic['mqtttopic']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="mqtt_trigger_payload">MQTT Trigger Payload:</label>
                    <textarea name="mqtt_trigger_payload" id="mqtt_trigger_payload"><?= htmlspecialchars($status_check['mqtt_trigger_payload'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="mqtt_status_topic">MQTT Status Topic:</label>
                    <select name="mqtt_status_topic" id="mqtt_status_topic">
                        <option value="">-- Select Topic --</option>
                        <?php foreach ($mqtt_topics as $topic): ?>
                            <option value="<?= htmlspecialchars($topic['name']) ?>" 
                                <?= ($status_check['mqtt_status_topic'] ?? '') === $topic['name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($topic['name']) ?> (<?= htmlspecialchars($topic['mqtttopic']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Shelly Fields -->
            <div id="shelly_fields" class="action-fields">
                <div class="form-group">
                    <label for="shelly_device">Shelly Device:</label>
                    <select name="shelly_device" id="shelly_device">
                        <option value="">-- Select Device --</option>
                        <?php foreach ($shelly_devices as $device): ?>
                            <option value="<?= htmlspecialchars($device['name']) ?>" 
                                <?= ($status_check['shelly_device'] ?? '') === $device['name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($device['name']) ?> (<?= htmlspecialchars($device['device_id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="shelly_command">Shelly Command (JSON):</label>
                    <textarea name="shelly_command" id="shelly_command" placeholder='{"_endpoint": "get/status"}'><?= htmlspecialchars($status_check['shelly_command'] ?? '') ?></textarea>
                    <small style="color: #666;">
                        Use <code>{"_endpoint": "get/status"}</code> to get device status, or <code>{"_endpoint": "set/switch", "on": true}</code> for switch commands.
                        Default endpoint is "set/switch" if not specified.
                    </small>
                </div>
            </div>

            <div class="form-group">
                <label for="open_result">Open Result Contains:</label>
                <input type="text" name="open_result" id="open_result" value="<?= htmlspecialchars($status_check['open_result'] ?? '') ?>" placeholder="e.g., &quot;output&quot;:true">
                <small style="color: #666;">Text that indicates the device/gate is OPEN</small>
            </div>

            <div class="form-group">
                <label for="closed_result">Closed Result Contains:</label>
                <input type="text" name="closed_result" id="closed_result" value="<?= htmlspecialchars($status_check['closed_result'] ?? '') ?>" placeholder="e.g., &quot;output&quot;:false">
                <small style="color: #666;">Text that indicates the device/gate is CLOSED</small>
            </div>

            <div class="form-group button-group">
                <button type="submit">Save Status Check</button>
                <a href="/admin/buttons" class="button-style">Back to Buttons</a>
            </div>
        </form>

        <?php if ($status_check): ?>
        <div class="status-info" style="margin-top: 30px; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">
            <h2>Last Check Result</h2>
            <p><strong>Result:</strong></p>
            <pre style="background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word;"><?= htmlspecialchars($status_check['last_result'] ?? 'No result yet') ?></pre>
            <p><strong>Time:</strong> <?= $status_check['last_result_datetime'] ? date('Y-m-d H:i:s', strtotime($status_check['last_result_datetime'])) : 'Never' ?></p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
