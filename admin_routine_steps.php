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
	
    $button_id = $_GET['button_id'];
	
    function get_steps($button_id) {
        global $mysqli;
        $stmt = $mysqli->prepare("SELECT * FROM routinesteps WHERE button_id = ? ORDER BY stepnumber ASC");
        $stmt->bind_param("i", $button_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
	
    function add_step($button_id, $stepnumber, $actiontype, $waittime, $httpurl, $httpbody, $mqtttopic, $mqttpayload, $shelly_device, $shelly_command) {
        global $mysqli;
        $stmt = $mysqli->prepare("INSERT INTO routinesteps (button_id, stepnumber, actiontype, waittime, httpurl, httpbody, mqtttopic, mqttpayload, shelly_device, shelly_command) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissssssss", $button_id, $stepnumber, $actiontype, $waittime, $httpurl, $httpbody, $mqtttopic, $mqttpayload, $shelly_device, $shelly_command);
        $stmt->execute();
    }
	
    function update_step($button_id, $step_id, $stepnumber, $actiontype, $waittime, $httpurl, $httpbody, $mqtttopic, $mqttpayload, $shelly_device, $shelly_command) {
        global $mysqli;
        $stmt = $mysqli->prepare("UPDATE routinesteps SET stepnumber = ?, actiontype = ?, waittime = ?, httpurl = ?, httpbody = ?, mqtttopic = ?, mqttpayload = ?, shelly_device = ?, shelly_command = ? WHERE button_id = ? AND id = ?");
        $stmt->bind_param("issssssssis", $stepnumber, $actiontype, $waittime, $httpurl, $httpbody, $mqtttopic, $mqttpayload, $shelly_device, $shelly_command, $button_id, $step_id);
        $stmt->execute();
    }
	
    function delete_step($button_id, $step_id) {
        global $mysqli;
        $stmt = $mysqli->prepare("DELETE FROM routinesteps WHERE button_id = ? AND id = ?");
        $stmt->bind_param("ii", $button_id, $step_id);
        $stmt->execute();
    }
	
    function get_mqtt_topics() {
        global $mysqli;
        $stmt = $mysqli->prepare("SELECT * FROM mqtttopics");
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    function get_shelly_devices() {
        global $mysqli;
        $stmt = $mysqli->prepare("SELECT * FROM shellydevices ORDER BY name");
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
	
    $steps = get_steps($button_id);
    $mqtt_topics = get_mqtt_topics();
    $shelly_devices = get_shelly_devices();
	
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['add_step'])) {
            $stepnumber = $_POST['stepnumber'];
            $actiontype = $_POST['actiontype'];
            $waittime = isset($_POST['waittime']) && $_POST['waittime'] !== '' ? $_POST['waittime'] : NULL;
            $httpurl = isset($_POST['httpurl']) && $_POST['httpurl'] !== '' ? $_POST['httpurl'] : NULL;
            $httpbody = isset($_POST['httpbody']) && $_POST['httpbody'] !== '' ? $_POST['httpbody'] : NULL;
            $mqtttopic = isset($_POST['mqtttopic']) && $_POST['mqtttopic'] !== '' ? $_POST['mqtttopic'] : NULL;
            $mqttpayload = isset($_POST['mqttpayload']) && $_POST['mqttpayload'] !== '' ? $_POST['mqttpayload'] : NULL;
            $shelly_device = isset($_POST['shelly_device']) && $_POST['shelly_device'] !== '' ? $_POST['shelly_device'] : NULL;
            $shelly_command = isset($_POST['shelly_command']) && $_POST['shelly_command'] !== '' ? $_POST['shelly_command'] : NULL;
            add_step($button_id, $stepnumber, $actiontype, $waittime, $httpurl, $httpbody, $mqtttopic, $mqttpayload, $shelly_device, $shelly_command);
            header('Location: /admin/buttons/' . $button_id . '/steps');
            exit();
        } elseif (isset($_POST['update_step'])) {
            $step_id = $_POST['step_id'];
            $stepnumber = $_POST['stepnumber'];
            $actiontype = $_POST['actiontype'];
            $waittime = isset($_POST['waittime']) && $_POST['waittime'] !== '' ? $_POST['waittime'] : NULL;
            $httpurl = isset($_POST['httpurl']) && $_POST['httpurl'] !== '' ? $_POST['httpurl'] : NULL;
            $httpbody = isset($_POST['httpbody']) && $_POST['httpbody'] !== '' ? $_POST['httpbody'] : NULL;
            $mqtttopic = isset($_POST['mqtttopic']) && $_POST['mqtttopic'] !== '' ? $_POST['mqtttopic'] : NULL;
            $mqttpayload = isset($_POST['mqttpayload']) && $_POST['mqttpayload'] !== '' ? $_POST['mqttpayload'] : NULL;
            $shelly_device = isset($_POST['shelly_device']) && $_POST['shelly_device'] !== '' ? $_POST['shelly_device'] : NULL;
            $shelly_command = isset($_POST['shelly_command']) && $_POST['shelly_command'] !== '' ? $_POST['shelly_command'] : NULL;
            update_step($button_id, $step_id, $stepnumber, $actiontype, $waittime, $httpurl, $httpbody, $mqtttopic, $mqttpayload, $shelly_device, $shelly_command);
            header('Location: /admin/buttons/' . $button_id . '/steps');
            exit();
        } elseif (isset($_POST['delete_step'])) {
            $step_id = $_POST['step_id'];
            delete_step($button_id, $step_id);
            header('Location: /admin/buttons/' . $button_id . '/steps');
            exit();
        }
    }
	
    $mysqli->close();
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Routine Steps Management</title>
        <link rel="stylesheet" href="/styles.css">
        <style>
            .action-details {
                min-width: 400px;
            }
            .action-fields {
                display: none;
            }
            .action-fields.active {
                display: block;
            }
            .action-fields label {
                display: inline-block;
                min-width: 80px;
                font-weight: bold;
                margin-right: 5px;
            }
            .action-fields .field-row {
                margin-bottom: 8px;
            }
            .action-fields input[type="text"],
            .action-fields textarea,
            .action-fields select {
                width: calc(100% - 90px);
                max-width: 300px;
            }
            .action-fields textarea {
                min-height: 60px;
                vertical-align: top;
            }
        </style>
        <script>
            function updateFields(rowId) {
                var actionType = document.getElementById('actiontype' + rowId).value;
                
                // Hide all field groups for this row
                var allFields = document.querySelectorAll('#row' + rowId + ' .action-fields');
                allFields.forEach(function(el) {
                    el.classList.remove('active');
                });
                
                // Show the appropriate field group
                var fieldMap = {
                    'Wait': 'wait-fields',
                    'HTTP Post': 'http-post-fields',
                    'HTTP Get': 'http-get-fields',
                    'MQTT Publish': 'mqtt-fields',
                    'Shelly Call': 'shelly-fields'
                };
                
                var targetClass = fieldMap[actionType];
                if (targetClass) {
                    var targetEl = document.querySelector('#row' + rowId + ' .' + targetClass);
                    if (targetEl) {
                        targetEl.classList.add('active');
                    }
                }
            }
            
            // Update fields on page load
            window.onload = function() {
                var rows = <?= count($steps) ?>;
                for (var i = 0; i < rows; i++) {
                    updateFields(i);
                }
                updateFields('New');
            };
        </script>
    </head>
    <body>
        <?php include 'header.php'; ?>
		
        <h1>Routine Steps Management</h1>
        <table>
            <tr>
                <th>Step #</th>
                <th>Action Type</th>
                <th>Action Details</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($steps as $key => $step): ?>
            <tr id="row<?= $key ?>">
                <form method="post" action="/admin/buttons/<?= $button_id ?>/steps">
                    <td>
                        <input type="text" name="stepnumber" value="<?= htmlspecialchars($step['stepnumber']) ?>" style="width: 60px;">
                    </td>
                    <td>
                        <select name="actiontype" id="actiontype<?= $key ?>" onchange="updateFields(<?= $key ?>)">
                            <option value="Wait" <?= $step['actiontype'] == 'Wait' ? 'selected' : '' ?>>Wait</option>
                            <option value="HTTP Post" <?= $step['actiontype'] == 'HTTP Post' ? 'selected' : '' ?>>HTTP Post</option>
                            <option value="HTTP Get" <?= $step['actiontype'] == 'HTTP Get' ? 'selected' : '' ?>>HTTP Get</option>
                            <option value="MQTT Publish" <?= $step['actiontype'] == 'MQTT Publish' ? 'selected' : '' ?>>MQTT Publish</option>
                            <option value="Shelly Call" <?= $step['actiontype'] == 'Shelly Call' ? 'selected' : '' ?>>Shelly Call</option>
                        </select>
                    </td>
                    <td class="action-details">
                        <!-- Wait Fields -->
                        <div class="action-fields wait-fields <?= $step['actiontype'] == 'Wait' ? 'active' : '' ?>">
                            <div class="field-row">
                                <label>Wait Time:</label>
                                <input type="text" name="waittime" value="<?= htmlspecialchars($step['waittime'] ?? '') ?>" placeholder="seconds">
                            </div>
                        </div>
                        
                        <!-- HTTP Post Fields -->
                        <div class="action-fields http-post-fields <?= $step['actiontype'] == 'HTTP Post' ? 'active' : '' ?>">
                            <div class="field-row">
                                <label>URL:</label>
                                <input type="text" name="httpurl" value="<?= htmlspecialchars($step['httpurl'] ?? '') ?>">
                            </div>
                            <div class="field-row">
                                <label>Body:</label>
                                <textarea name="httpbody"><?= htmlspecialchars($step['httpbody'] ?? '') ?></textarea>
                            </div>
                        </div>
                        
                        <!-- HTTP Get Fields -->
                        <div class="action-fields http-get-fields <?= $step['actiontype'] == 'HTTP Get' ? 'active' : '' ?>">
                            <div class="field-row">
                                <label>URL:</label>
                                <input type="text" name="httpurl" value="<?= htmlspecialchars($step['httpurl'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <!-- MQTT Fields -->
                        <div class="action-fields mqtt-fields <?= $step['actiontype'] == 'MQTT Publish' ? 'active' : '' ?>">
                            <div class="field-row">
                                <label>Topic:</label>
                                <select name="mqtttopic">
                                    <option value="">-- Select Topic --</option>
                                    <?php foreach ($mqtt_topics as $topic): ?>
                                    <option value="<?= htmlspecialchars($topic['name']) ?>" <?= ($step['mqtttopic'] ?? '') == $topic['name'] ? 'selected' : '' ?>><?= htmlspecialchars($topic['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field-row">
                                <label>Payload:</label>
                                <input type="text" name="mqttpayload" value="<?= htmlspecialchars($step['mqttpayload'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <!-- Shelly Fields -->
                        <div class="action-fields shelly-fields <?= $step['actiontype'] == 'Shelly Call' ? 'active' : '' ?>">
                            <div class="field-row">
                                <label>Device:</label>
                                <select name="shelly_device">
                                    <option value="">-- Select Device --</option>
                                    <?php foreach ($shelly_devices as $device): ?>
                                    <option value="<?= htmlspecialchars($device['name']) ?>" <?= ($step['shelly_device'] ?? '') == $device['name'] ? 'selected' : '' ?>><?= htmlspecialchars($device['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field-row">
                                <label>Command:</label>
                                <textarea name="shelly_command" placeholder='{"on": true, "channel": 0}'><?= htmlspecialchars($step['shelly_command'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </td>
                    <td>
                        <button type="submit" name="update_step">Update</button>
                        <button type="submit" name="delete_step" onclick="return confirm('Are you sure?');">Delete</button>
                        <input type="hidden" name="step_id" value="<?= $step['id'] ?>">
                    </td>
                </form>
            </tr>
            <?php endforeach; ?>
            
            <!-- Add New Step Row -->
            <tr id="rowNew">
                <form method="post" action="/admin/buttons/<?= $button_id ?>/steps">
                    <td>
                        <input type="text" name="stepnumber" required style="width: 60px;">
                    </td>
                    <td>
                        <select name="actiontype" id="actiontypeNew" onchange="updateFields('New')" required>
                            <option value="Wait">Wait</option>
                            <option value="HTTP Post">HTTP Post</option>
                            <option value="HTTP Get">HTTP Get</option>
                            <option value="MQTT Publish">MQTT Publish</option>
                            <option value="Shelly Call">Shelly Call</option>
                        </select>
                    </td>
                    <td class="action-details">
                        <!-- Wait Fields -->
                        <div class="action-fields wait-fields active">
                            <div class="field-row">
                                <label>Wait Time:</label>
                                <input type="text" name="waittime" placeholder="seconds">
                            </div>
                        </div>
                        
                        <!-- HTTP Post Fields -->
                        <div class="action-fields http-post-fields">
                            <div class="field-row">
                                <label>URL:</label>
                                <input type="text" name="httpurl">
                            </div>
                            <div class="field-row">
                                <label>Body:</label>
                                <textarea name="httpbody"></textarea>
                            </div>
                        </div>
                        
                        <!-- HTTP Get Fields -->
                        <div class="action-fields http-get-fields">
                            <div class="field-row">
                                <label>URL:</label>
                                <input type="text" name="httpurl">
                            </div>
                        </div>
                        
                        <!-- MQTT Fields -->
                        <div class="action-fields mqtt-fields">
                            <div class="field-row">
                                <label>Topic:</label>
                                <select name="mqtttopic">
                                    <option value="">-- Select Topic --</option>
                                    <?php foreach ($mqtt_topics as $topic): ?>
                                    <option value="<?= htmlspecialchars($topic['name']) ?>"><?= htmlspecialchars($topic['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field-row">
                                <label>Payload:</label>
                                <input type="text" name="mqttpayload">
                            </div>
                        </div>
                        
                        <!-- Shelly Fields -->
                        <div class="action-fields shelly-fields">
                            <div class="field-row">
                                <label>Device:</label>
                                <select name="shelly_device">
                                    <option value="">-- Select Device --</option>
                                    <?php foreach ($shelly_devices as $device): ?>
                                    <option value="<?= htmlspecialchars($device['name']) ?>"><?= htmlspecialchars($device['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field-row">
                                <label>Command:</label>
                                <textarea name="shelly_command" placeholder='{"on": true, "channel": 0}'></textarea>
                            </div>
                        </div>
                    </td>
                    <td>
                        <button type="submit" name="add_step">Add Step</button>
                    </td>
                </form>
            </tr>
        </table>
        
        <div style="margin-top: 20px;">
            <a href="/admin/buttons" class="button-style">Back to Buttons</a>
        </div>
    </body>
</html>
