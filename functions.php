<?php
	require_once 'config.php';
	
	function get_button_details($button_id) {
        global $db_servername, $db_username, $db_password, $db_name;
        
        // Connect to database
        $mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);
        
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
		}
        
        
        $stmt = $mysqli->prepare("SELECT * from buttons where id= ?");
        $stmt->bind_param("i", $button_id);
        $stmt->execute();
        $result = $stmt->get_result();
		return $result->fetch_assoc();
        $stmt->close();
        $mysqli->close();      
	}

    function check_user_chain($user, $visited = array()) {
        global $db_servername, $db_username, $db_password, $db_name;
        
        // Prevent infinite loops from circular references
        if (in_array($user, $visited)) {
            return false;
        }
        $visited[] = $user;
        
        $mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);
        
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        
        $stmt = $mysqli->prepare("SELECT enabled, master, added_by FROM users WHERE username = ?");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $result = $stmt->get_result();
        $userresult = $result->fetch_assoc();
        $stmt->close();
        $mysqli->close();
        
        // If user doesn't exist
        if (!$userresult) {
            return false;
        }
        
        // If user is disabled
        if ($userresult['enabled'] != 1) {
            return false;
        }
        
        // If no parent user (root user), and enabled
        if (empty($userresult['added_by'])) {
            return true;
        }
        
        // Check parent user's status
        return check_user_chain($userresult['added_by'], $visited);
    }

    function user_enabled($user) {
        global $db_servername, $db_username, $db_password, $db_name;
        
        $mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);
        
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        
        $stmt = $mysqli->prepare("SELECT enabled, master FROM users WHERE username = ?");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        $mysqli->close();

        if (!$row) {
            return array(
                'enabled' => false,
                'master' => false
            );
        }

        return array(
            'enabled' => check_user_chain($user) && $row['enabled'] == 1,
            'master' => $row['master'] == 1
        );
    }
	
    // Get user tree structure
    function get_user_tree($parent_username = null) {
        global $db_servername, $db_username, $db_password, $db_name;
        
        $mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);
        
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        
        if ($parent_username === null) {
            // Get root users (no added_by)
            $stmt = $mysqli->prepare("SELECT username, enabled, master FROM users WHERE added_by IS NULL ORDER BY username");
            $stmt->execute();
        } else {
            // Get children of a specific user
            $stmt = $mysqli->prepare("SELECT username, enabled, master FROM users WHERE added_by = ? ORDER BY username");
            $stmt->bind_param("s", $parent_username);
            $stmt->execute();
        }
        
        $result = $stmt->get_result();
        $users = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Recursively get children for each user
        foreach ($users as &$user) {
            $user['children'] = get_user_tree($user['username']);
        }
        
        $mysqli->close();
        return $users;
    }

    // Update user
    function update_user($username, $enabled, $master) {
        global $db_servername, $db_username, $db_password, $db_name;
        
        $mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);
        
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        
        $stmt = $mysqli->prepare("UPDATE users SET enabled = ?, master = ? WHERE username = ?");
        $stmt->bind_param("iis", $enabled, $master, $username);
        $stmt->execute();
        $stmt->close();
        $mysqli->close();
    }

    // Add a user
    function add_user($username, $enabled, $master, $added_by = null) {
        global $db_servername, $db_username, $db_password, $db_name;
        
        $mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);
        
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        
        $stmt = $mysqli->prepare("INSERT INTO users (username, enabled, master, added_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("siis", $username, $enabled, $master, $added_by);
        $result = $stmt->execute();
        $stmt->close();
        $mysqli->close();
        return $result;
    }

    // Delete a user
    function delete_user($username) {
        global $db_servername, $db_username, $db_password, $db_name;
        
        $mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);
        
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        
        // Delete user's button associations
        $stmt = $mysqli->prepare("DELETE FROM user_buttons WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        
        // Delete the user
        $stmt = $mysqli->prepare("DELETE FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        
        $mysqli->close();
    }

    // Get buttons assigned to a user
    function get_user_buttons($username) {
        global $db_servername, $db_username, $db_password, $db_name;
        
        $mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);
        
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        
        $stmt = $mysqli->prepare("SELECT ub.button_id, b.label FROM user_buttons ub 
                                  JOIN buttons b ON ub.button_id = b.id 
                                  WHERE ub.username = ? 
                                  ORDER BY ub.button_order");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $buttons = array();
        
        while ($row = $result->fetch_assoc()) {
            $buttons[] = array(
                'id' => $row['button_id'],
                'label' => $row['label']
            );
        }
        
        $stmt->close();
        $mysqli->close();
        return $buttons;
    }

    // Get just the button IDs for a user (for checking if buttons are assigned)
    function get_user_button_ids($username) {
        $buttons = get_user_buttons($username);
        $button_ids = array();
        foreach ($buttons as $button) {
            $button_ids[] = $button['id'];
        }
        return $button_ids;
    }

    // Get all available buttons
    function get_all_buttons() {
        global $db_servername, $db_username, $db_password, $db_name;
        
        $mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);
        
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        
        $result = $mysqli->query("SELECT * FROM buttons ORDER BY id");
        $buttons = $result->fetch_all(MYSQLI_ASSOC);
        
        $mysqli->close();
        return $buttons;
    }

    // Update button assignments for a user
    function update_user_buttons($username, $button_ids) {
        global $db_servername, $db_username, $db_password, $db_name;
        
        $mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);
        
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        
        // Delete existing button assignments
        $stmt = $mysqli->prepare("DELETE FROM user_buttons WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->close();
        
        // Add new button assignments
        if (!empty($button_ids)) {
            $stmt = $mysqli->prepare("INSERT INTO user_buttons (username, button_id, button_order) VALUES (?, ?, ?)");
            $order = 1; // Start with order 1
            foreach ($button_ids as $button_id) {
                $stmt->bind_param("sii", $username, $button_id, $order);
                $stmt->execute();
                $order++; // Increment order for each button
            }
            $stmt->close();
        }
        
        $mysqli->close();
    }

    // Add a new button
    function add_button($label, $infoheader, $infodetail) {
        global $db_servername, $db_username, $db_password, $db_name;
        
        $mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);
        
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        
        $stmt = $mysqli->prepare("INSERT INTO buttons (label, infoheader, infodetail) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $label, $infoheader, $infodetail);
        $stmt->execute();
        $stmt->close();
        $mysqli->close();
    }
    
    // Update an existing button
    function update_button($id, $label, $infoheader, $infodetail) {
        global $db_servername, $db_username, $db_password, $db_name;
        
        $mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);
        
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        
        $stmt = $mysqli->prepare("UPDATE buttons SET label = ?, infoheader = ?, infodetail = ? WHERE id = ?");
        $stmt->bind_param("sssi", $label, $infoheader, $infodetail, $id);
        $stmt->execute();
        $stmt->close();
        $mysqli->close();
    }
    
    // Delete a button and its related data
    function delete_button($id) {
        global $db_servername, $db_username, $db_password, $db_name;
        
        $mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);
        
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        
        // Delete Button Routine Steps first
        $stmt = $mysqli->prepare("DELETE FROM routinesteps WHERE button_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        // Delete Button from User_Buttons
        $stmt = $mysqli->prepare("DELETE FROM user_buttons WHERE button_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        // Delete Button Status Check
        $stmt = $mysqli->prepare("DELETE FROM statuschecks WHERE button_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        // Finally delete the button itself
        $stmt = $mysqli->prepare("DELETE FROM buttons WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $mysqli->close();
    }

    // Get Shelly device configuration by name
    function get_shelly_device($name) {
        global $db_servername, $db_username, $db_password, $db_name;
        
        $mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);
        
        if ($mysqli->connect_error) {
            return null;
        }
        
        $stmt = $mysqli->prepare("SELECT * FROM shellydevices WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        $device = $result->fetch_assoc();
        $stmt->close();
        $mysqli->close();
        
        return $device;
    }

    // Execute Shelly Cloud API V2 call
    // Command JSON can include optional "_endpoint" to override default (e.g., "get/status" instead of "set/switch")
    function shelly_call($device, $command_json) {
        // Parse user's command JSON
        $command = json_decode($command_json, true);
        if ($command === null) {
            $command = [];
        }
        
        // Check for custom endpoint, default to set/switch
        $endpoint = 'set/switch';
        if (isset($command['_endpoint'])) {
            $endpoint = $command['_endpoint'];
            unset($command['_endpoint']); // Remove from command body
        }
        
        // Build URL with auth_key in query string
        // Strip https:// or http:// if user included it in server_uri
        $server_uri = preg_replace('#^https?://#', '', $device['server_uri']);
        $url = "https://" . $server_uri . "/v2/devices/api/" . $endpoint . "?auth_key=" . urlencode($device['auth_key']);
        
        // Add device_id to command
        $command['id'] = $device['device_id'];
        
        // POST JSON body
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($command));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($error) {
            error_log("Shelly API call failed: " . $error);
            return ['error' => $error, 'raw_response' => ''];
        }
        
        error_log("Shelly API response (HTTP $http_code): " . $response);
        
        $result = json_decode($response, true);
        if ($result === null) {
            $result = [];
        }
        $result['raw_response'] = $response;
        $result['http_code'] = $http_code;
        
        return $result;
    }

    function sonoff_opener($sonoff_email, $sonoff_password, $sonoff_region, $sonoff_deviceId, $sonoff_channel_id) {
        // Create an array to collect debug information
        $debug = [];
        $debug[] = "Sonoff opener called for device: $sonoff_deviceId in region: $sonoff_region, channel: $sonoff_channel_id";

        // Base URL based on region
        $baseUrl = "https://{$sonoff_region}-api.coolkit.cc";
        
        // Step 1: Login to get access token
        $loginUrl = "$baseUrl/v1/user/login";
        $loginData = [
            'email' => $sonoff_email,
            'password' => $sonoff_password,
            'region' => $sonoff_region
        ];

        $debug[] = "Attempting Sonoff login for: $sonoff_email";
        
        $ch = curl_init($loginUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
        
        $loginResponse = curl_exec($ch);
        if ($loginResponse === false) {
            $error = curl_error($ch);
            curl_close($ch);
            $debug[] = "Sonoff login failed: $error";
            return ['error' => 'Login failed: ' . $error, 'debug' => $debug];
        }
        
        $loginData = json_decode($loginResponse, true);
        curl_close($ch);

        $debug[] = "Sonoff login response: " . json_encode($loginData);

        if (!isset($loginData['at'])) {
            $debug[] = "No access token received from Sonoff API";
            return ['error' => 'Login failed: No access token received', 'response' => $loginData, 'debug' => $debug];
        }

        $accessToken = $loginData['at'];

        // Step 2: Get current device status to preserve other channels
        $statusUrl = "$baseUrl/v2/device/status?deviceid=$sonoff_deviceId";
        $debug[] = "Checking device status: $statusUrl";
        
        $ch = curl_init($statusUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $accessToken"
        ]);
        
        $statusResponse = curl_exec($ch);
        if ($statusResponse === false) {
            $error = curl_error($ch);
            curl_close($ch);
            $debug[] = "Sonoff status check failed: $error";
            return ['error' => 'Status check failed: ' . $error, 'debug' => $debug];
        }
        
        $statusData = json_decode($statusResponse, true);
        curl_close($ch);

        $debug[] = "Device status response: " . json_encode($statusData);

        // Step 3: Prepare the update data
        // Check if we have the expected data structure
        if (!isset($statusData['data']) || !isset($statusData['data']['switches'])) {
            $debug[] = "Invalid status response from Sonoff API, trying alternative approach";
            
            // Try an alternative approach - send the command without checking status first
            $switches = [];
            for ($i = 0; $i < 4; $i++) { // Assuming max 4 channels
                if ($i == ($sonoff_channel_id - 1)) { // Convert to 0-based index
                    $switches[] = ['outlet' => $i, 'switch' => 'on'];
                }
            }
            
            $updateData = [
                'deviceid' => $sonoff_deviceId,
                'params' => [
                    'switches' => $switches
                ]
            ];
            
            $debug[] = "Trying direct command without status check: " . json_encode($updateData);
        } else {
            // Use the existing switches data
            $switches = $statusData['data']['switches'];
            
            // Find the specific channel to update
            $channelFound = false;
            foreach ($switches as &$switch) {
                if (isset($switch['outlet']) && $switch['outlet'] == ($sonoff_channel_id - 1)) {
                    $switch['switch'] = 'on';
                    $channelFound = true;
                    break;
                }
            }
            
            // If channel not found in existing switches, add it
            if (!$channelFound) {
                $switches[] = ['outlet' => ($sonoff_channel_id - 1), 'switch' => 'on'];
            }

            $updateData = [
                'deviceid' => $sonoff_deviceId,
                'params' => [
                    'switches' => $switches
                ]
            ];
            
            $debug[] = "Preparing update with existing status: " . json_encode($updateData);
        }

        // Step 4: Send the update to turn on the specified channel
        $updateUrl = "$baseUrl/v2/device/status";
        $ch = curl_init($updateUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer $accessToken"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
        
        $debug[] = "Sending command to Sonoff API: $updateUrl";
        
        $updateResponse = curl_exec($ch);
        if ($updateResponse === false) {
            $error = curl_error($ch);
            curl_close($ch);
            $debug[] = "Sonoff update failed: $error";
            return ['error' => 'Update failed: ' . $error, 'debug' => $debug];
        }
        
        $updateResult = json_decode($updateResponse, true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $debug[] = "Sonoff update response (HTTP $httpCode): " . json_encode($updateResult);

        // Try alternative endpoint if the first one fails
        if ($httpCode >= 400 || (isset($updateResult['error']) && $updateResult['error'] !== 0)) {
            $debug[] = "First attempt failed, trying alternative endpoint";
            
            // Alternative endpoint format
            $altUpdateUrl = "$baseUrl/v2/device/update";
            $ch = curl_init($altUpdateUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: Bearer $accessToken"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
            
            $debug[] = "Sending command to alternative Sonoff API: $altUpdateUrl";
            
            $updateResponse = curl_exec($ch);
            if ($updateResponse === false) {
                $error = curl_error($ch);
                curl_close($ch);
                $debug[] = "Alternative Sonoff update failed: $error";
                return ['error' => 'Update failed: ' . $error, 'debug' => $debug];
            }
            
            $updateResult = json_decode($updateResponse, true);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $debug[] = "Alternative Sonoff update response (HTTP $httpCode): " . json_encode($updateResult);
        }

        if (isset($updateResult['error']) && $updateResult['error'] === 0) {
            $debug[] = "Command successful: Channel $sonoff_channel_id turned on";
            return [
                'success' => true, 
                'message' => "Channel $sonoff_channel_id turned on successfully",
                'debug' => $debug
            ];
        } else {
            $debug[] = "Command failed: " . json_encode($updateResult);
            return [
                'error' => 'Update failed', 
                'response' => $updateResult, 
                'http_code' => $httpCode,
                'debug' => $debug
            ];
        }
    }






