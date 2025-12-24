<?php
require_once 'config.php';
require_once 'functions.php';
require 'vendor/autoload.php';
use Bluerhinos\phpMQTT;

// Set content type to JSON by default
header('Content-Type: application/json');

// Helper functions for status check
function isJson($string) {
    if (empty($string)) return false;
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}

function extractJsonValues($arr, &$values) {
    foreach ($arr as $key => $value) {
        if (is_array($value)) {
            extractJsonValues($value, $values);
        } else {
            $values[] = (string)$value;
        }
    }
}

// Helper functions for execute engine
function get_routine_steps($button_id) {
    global $db_servername, $db_username, $db_password, $db_name;
    
    // Connect to database
    $mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    $stmt = $mysqli->prepare("SELECT * from routinesteps where button_id= ? order by stepnumber");
    $stmt->bind_param("i", $button_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $steps = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $mysqli->close();
    return $steps;
}

function get_mqtt_topic($mqtt_name) {
    global $db_servername, $db_username, $db_password, $db_name;
    
    // Connect to database
    $mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    $stmt = $mysqli->prepare("SELECT * from mqtttopics where name= ?");
    $stmt->bind_param("s", $mqtt_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $topic = $result->fetch_assoc();
    $stmt->close();
    $mysqli->close();
    return $topic;
}

function http_request($url, $method, $body = null) {
    $ch = curl_init();
    // set URL and other appropriate options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Maximum number of redirects to follow
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout in seconds
    
    // Add detailed debugging
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, 1);
        if ($body) {
            // Check if $body is JSON
            if (is_string($body) && is_array(json_decode($body, true)) && json_last_error() === JSON_ERROR_NONE) {
                // Set content type header for JSON
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            } else {
                // For non-JSON data, set appropriate content type
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }
    } else if ($method === 'GET') {
        curl_setopt($ch, CURLOPT_HTTPGET, 1);
    }    
    
    $result = curl_exec($ch);
    $info = curl_getinfo($ch);
    $error = curl_error($ch);
    
    // Get verbose debug information
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    fclose($verbose);
    
    // Log detailed information about the request and response
    error_log("HTTP Request to: $url");
    error_log("HTTP Method: $method");
    if ($body) {
        error_log("Request Body: " . substr($body, 0, 1000) . (strlen($body) > 1000 ? '...' : ''));
    }
    error_log("Response Status Code: " . $info['http_code']);
    error_log("Response Content Type: " . ($info['content_type'] ?? 'unknown'));
    error_log("Response Size: " . ($info['size_download'] ?? 'unknown'));
    if ($error) {
        error_log("cURL Error: $error");
    }
    error_log("Verbose Log: " . $verboseLog);
    
    curl_close($ch);
    
    // Return an array with the result and additional information
    return [
        'result' => $result,
        'status_code' => $info['http_code'],
        'content_type' => $info['content_type'] ?? '',
        'error' => $error,
        'is_html' => (strpos($result, '<!DOCTYPE') !== false || strpos($result, '<html') !== false)
    ];
}

function mqtt_publish($mqtt_server, $mqtt_port, $mqtt_username, $mqtt_password, $mqtt_topic, $mqtt_payload) {
    global $mqtt_client_id;
    $mqtt = new Bluerhinos\phpMQTT($mqtt_server, $mqtt_port, $mqtt_client_id ?? "entryzen_".uniqid());
    if ($mqtt->connect(true, NULL, $mqtt_username, $mqtt_password)) {
        $mqtt->publish($mqtt_topic, $mqtt_payload, 0);
        $mqtt->close();            
    }
}

function write_button_log($user, $button_name) {
    global $db_servername, $db_username, $db_password, $db_name;
    
    // Connect to database
    $mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    $stmt = $mysqli->prepare("INSERT INTO button_logs (username, button) VALUES (?, ?)");
    $stmt->bind_param("ss", $user, $button_name);
    $stmt->execute();
    $stmt->close();
    $mysqli->close();
}

// Function to handle status check
function handle_status_check($button_id) {
    global $db_servername, $db_username, $db_password, $db_name;
    
    // Connect to database
    $mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);
    
    if ($mysqli->connect_error) {
        return "error: database connection failed";
    }
    
    // Explicitly set character set to utf8mb4
    $mysqli->set_charset("utf8mb4");

    
    // Get status check definition for this button
    $stmt = $mysqli->prepare("SELECT * FROM statuschecks WHERE button_id = ?");
    $stmt->bind_param("i", $button_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $statuscheck = $result->fetch_assoc();
    $stmt->close();
    

    
    // If no status check is defined for this button
    if (!$statuscheck) {
        $mysqli->close();
        return "unknown";
    }
    
    // Extract status check parameters
    $action_type = $statuscheck['action_type'];
    $httpurl = $statuscheck['httpurl'];
    $httpbody = $statuscheck['httpbody'];
    $mqtt_trigger_topic = $statuscheck['mqtt_trigger_topic'];
    $mqtt_trigger_payload = $statuscheck['mqtt_trigger_payload'];
    $mqtt_status_topic = $statuscheck['mqtt_status_topic'];
    $shelly_device_name = $statuscheck['shelly_device'] ?? '';
    $shelly_command = $statuscheck['shelly_command'] ?? '';
    $open_result = $statuscheck['open_result'];
    $closed_result = $statuscheck['closed_result'];
    
    // Perform the status check based on action_type
    $response = "";
    $status = "unknown";
    
    try {
        // Handle different check types
        if ($action_type == 'http_get' || $action_type == 'http_post') {
            // Initialize cURL
            $ch = curl_init();
            
            // Set cURL options
            curl_setopt($ch, CURLOPT_URL, $httpurl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 seconds timeout
            
            // Set request method
            if ($action_type == 'http_post') {
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($httpbody)) {
                    // Check if body is JSON and set appropriate headers
                    if (isJson($httpbody)) {
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                    }
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $httpbody);
                }
            }
            
            // Execute the request
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Close cURL
            curl_close($ch);
            
        } elseif ($action_type == 'mqtt') {
            // Get MQTT connection details for trigger topic
            if (!empty($mqtt_trigger_topic)) {
                $stmt = $mysqli->prepare("SELECT * FROM mqtttopics WHERE name = ?");
                $stmt->bind_param("s", $mqtt_trigger_topic);
                $stmt->execute();
                $trigger_topic_result = $stmt->get_result();
                $trigger_topic_details = $trigger_topic_result->fetch_assoc();
                $stmt->close();
                
                if (!$trigger_topic_details) {
                    throw new Exception("MQTT trigger topic not found in mqtttopics table");
                }
            }
            
            // Get MQTT connection details for status topic
            if (!empty($mqtt_status_topic)) {
                $stmt = $mysqli->prepare("SELECT * FROM mqtttopics WHERE name = ?");
                $stmt->bind_param("s", $mqtt_status_topic);
                $stmt->execute();
                $status_topic_result = $stmt->get_result();
                $status_topic_details = $status_topic_result->fetch_assoc();
                $stmt->close();
                
                if (!$status_topic_details) {
                    throw new Exception("MQTT status topic not found in mqtttopics table");
                }
            } else {
                throw new Exception("MQTT status topic is required for MQTT checks");
            }
            
            // Check if we have the required MQTT library
            if (!class_exists('Mosquitto\Client')) {
                throw new Exception("PHP Mosquitto extension not installed");
            }
            
            // Create a unique client ID
            $client_id = "entryzen_statuscheck_" . uniqid();
            
            // Initialize MQTT client for status topic
            $mqtt_client = new Mosquitto\Client($client_id);
            
            // Set credentials if provided
            if (!empty($status_topic_details['mqttusername'])) {
                $mqtt_client->setCredentials(
                    $status_topic_details['mqttusername'],
                    $status_topic_details['mqttpassword']
                );
            }
            
            // Set up message callback
            $mqtt_message = null;
            $mqtt_client->onMessage(function($message) use (&$mqtt_message) {
                $mqtt_message = $message->payload;
            });
            
            // Connect to the MQTT broker
            $mqtt_client->connect(
                $status_topic_details['mqttserver'], 
                $status_topic_details['mqttport'], 
                60
            );
            
            // Subscribe to the status topic
            $mqtt_client->subscribe($status_topic_details['mqtttopic'], 0);
            
            // If we have a trigger topic, publish to it
            if (!empty($mqtt_trigger_topic) && $trigger_topic_details) {
                // Prepare the payload - use the provided payload or an empty string if not specified
                $payload = !empty($mqtt_trigger_payload) ? $mqtt_trigger_payload : "";
                
                // If trigger and status use the same broker, use the same client
                if ($trigger_topic_details['mqttserver'] == $status_topic_details['mqttserver'] &&
                    $trigger_topic_details['mqttport'] == $status_topic_details['mqttport']) {
                    
                    // Publish the payload to the trigger topic
                    $mqtt_client->publish($trigger_topic_details['mqtttopic'], $payload, 0, false);
                } else {
                    // Different brokers, create a new client for the trigger
                    $trigger_client_id = "entryzen_statuscheck_trigger_" . uniqid();
                    $trigger_client = new Mosquitto\Client($trigger_client_id);
                    
                    // Set credentials if provided
                    if (!empty($trigger_topic_details['mqttusername'])) {
                        $trigger_client->setCredentials(
                            $trigger_topic_details['mqttusername'],
                            $trigger_topic_details['mqttpassword']
                        );
                    }
                    
                    // Connect and publish
                    $trigger_client->connect(
                        $trigger_topic_details['mqttserver'], 
                        $trigger_topic_details['mqttport'], 
                        60
                    );
                    $trigger_client->publish($trigger_topic_details['mqtttopic'], $payload, 0, false);
                    $trigger_client->loop();
                    $trigger_client->disconnect();
                }
            }
            
            // Wait for a message (with timeout)
            $timeout = time() + 10; // 10 second timeout
            while (time() < $timeout && $mqtt_message === null) {
                $mqtt_client->loop(100); // Process for 100ms
                usleep(100000); // Sleep for 100ms
            }
            
            // Disconnect from the broker
            $mqtt_client->disconnect();
            
            if ($mqtt_message !== null) {
                $response = $mqtt_message;
            } else {
                $response = "No response received from MQTT broker within timeout period";
            }
        } elseif ($action_type == 'shelly') {
            // Get Shelly device configuration
            if (empty($shelly_device_name)) {
                throw new Exception("Shelly device not configured for this status check");
            }
            
            $shelly_device = get_shelly_device($shelly_device_name);
            if (!$shelly_device) {
                throw new Exception("Shelly device not found: " . $shelly_device_name);
            }
            
            // Call Shelly Cloud API with the configured command
            $result = shelly_call($shelly_device, $shelly_command);
            
            if (isset($result['error'])) {
                throw new Exception("Shelly API error: " . $result['error']);
            }
            
            // Use raw response for pattern matching
            $response = $result['raw_response'] ?? json_encode($result);
        }
        
        // Compare response with open_result and closed_result
        // Simple direct comparison with the response
        // No JSON parsing - just check if the strings are contained in the response
        if (!empty($open_result) && (strpos($response, $open_result) !== false)) {
            $status = "open";
        } elseif (!empty($closed_result) && (strpos($response, $closed_result) !== false)) {
            $status = "closed";
        }
        
        // Update the last result in the database
        $stmt = $mysqli->prepare("UPDATE statuschecks SET last_result = ?, last_result_datetime = NOW() WHERE button_id = ?");
        $stmt->bind_param("si", $response, $button_id);
        $stmt->execute();
        $stmt->close();
        
    } catch (Exception $e) {
        // Log error
        error_log("Status check error: " . $e->getMessage());
        $status = "error";
    }
    
    $mysqli->close();
    return $status;
}

// Function to handle engine execution
function handle_execute_engine($button_id, $user) {
    try {
        $button = get_button_details($button_id);
        if (!$button) {
            throw new Exception("Button not found");
        }
        
        $routine_steps = get_routine_steps($button_id);
        
        // Log the button press
        write_button_log($user, $button['label']);
        
        // Execute each step in order
        foreach ($routine_steps as $step) {
            switch ($step['actiontype']) {
                case "Wait":
                    // Convert wait time to seconds and sleep
                    $wait_seconds = intval($step['waittime']);
                    if ($wait_seconds > 0) {
                        sleep($wait_seconds);
                    }
                    break;
                
                case "MQTT Publish":
                    // Get MQTT topic details and publish message
                    $mqtt_topic = get_mqtt_topic($step['mqtttopic']);
                    if ($mqtt_topic) {
                        mqtt_publish(
                            $mqtt_topic['mqttserver'],
                            $mqtt_topic['mqttport'],
                            $mqtt_topic['mqttusername'],
                            $mqtt_topic['mqttpassword'],
                            $mqtt_topic['mqtttopic'],
                            $step['mqttpayload']
                        );
                    }
                    break;
                
                case "HTTP Post":
                    // Make HTTP POST request
                    $response = http_request($step['httpurl'], 'POST', $step['httpbody']);
                    // Log response details for debugging
                    error_log("HTTP POST Response: Status: " . $response['status_code'] . ", Content-Type: " . $response['content_type']);
                    
                    // Check for HTML response or errors
                    if ($response['is_html']) {
                        error_log("HTTP POST received HTML response instead of expected data format");
                        error_log("HTML Response (first 500 chars): " . substr($response['result'], 0, 500));
                    }
                    
                    if ($response['error']) {
                        error_log("HTTP POST Error: " . $response['error']);
                        throw new Exception("HTTP POST request failed: " . $response['error']);
                    }
                    
                    if ($response['status_code'] >= 400) {
                        error_log("HTTP POST Error: Received status code " . $response['status_code']);
                        throw new Exception("HTTP POST request failed with status code: " . $response['status_code']);
                    }
                    break;
                
                case "HTTP Get":
                    // Make HTTP GET request
                    $response = http_request($step['httpurl'], 'GET');
                    // Log response details for debugging
                    error_log("HTTP GET Response: Status: " . $response['status_code'] . ", Content-Type: " . $response['content_type']);
                    
                    // Check for HTML response or errors
                    if ($response['is_html']) {
                        error_log("HTTP GET received HTML response instead of expected data format");
                        error_log("HTML Response (first 500 chars): " . substr($response['result'], 0, 500));
                    }
                    
                    if ($response['error']) {
                        error_log("HTTP GET Error: " . $response['error']);
                        throw new Exception("HTTP GET request failed: " . $response['error']);
                    }
                    
                    if ($response['status_code'] >= 400) {
                        error_log("HTTP GET Error: Received status code " . $response['status_code']);
                        throw new Exception("HTTP GET request failed with status code: " . $response['status_code']);
                    }
                    break;
                
                case "Shelly Call":
                    // Get Shelly device configuration and make API call
                    $shelly_device = get_shelly_device($step['shelly_device']);
                    if ($shelly_device) {
                        $result = shelly_call($shelly_device, $step['shelly_command']);
                        if (isset($result['error'])) {
                            error_log("Shelly Call Error: " . $result['error']);
                        }
                    } else {
                        error_log("Shelly device not found: " . $step['shelly_device']);
                    }
                    break;
                
                default:
                    // Unknown action type - log error
                    error_log("Unknown action type: " . $step['actiontype']);
                    break;
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Execute engine error: " . $e->getMessage());
        return false;
    }
}

// Check if action is provided
// Check for action in both GET and POST requests
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if ($action === null) {
    echo json_encode(['error' => 'Action is required']);
    exit;
}

switch ($action) {
    case 'get_button_details':
        // Check if button_id is provided
        if (!isset($_GET['button_id'])) {
            echo json_encode(['error' => 'Button ID is required']);
            exit;
        }
        
        $button_id = $_GET['button_id'];
        
        // Get button details
        $button = get_button_details($button_id);
        
        if (!$button) {
            echo json_encode(['error' => 'Button not found']);
            exit;
        }
        
        // Return button details as JSON
        echo json_encode($button);
        break;
        
    case 'status_check':
        // Check if button_id is provided
        if (!isset($_GET['button_id'])) {
            echo json_encode(['error' => 'Button ID is required']);
            exit;
        }
        
        $button_id = $_GET['button_id'];
        
        // Override content type for status check to match original behavior
        header('Content-Type: text/plain');
        
        // Get status
        $status = handle_status_check($button_id);
        
        // Output status as plain text
        echo $status;
        break;
        
    case 'execute_engine':
        // Check if button_id and user are provided
        if (!isset($_GET['button_id']) || !isset($_GET['user'])) {
            echo json_encode(['error' => 'Button ID and User are required']);
            exit;
        }
        
        $button_id = $_GET['button_id'];
        $user = $_GET['user'];
        
        try {
            // Execute engine
            $success = handle_execute_engine($button_id, $user);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Engine executed successfully']);
            } else {
                echo json_encode(['error' => 'Failed to execute engine']);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => 'Exception: ' . $e->getMessage()]);
        }
        break;

    case 'sonoff_opener':
        // Get JSON input
        $jsonInput = file_get_contents('php://input');
        $data = json_decode($jsonInput, true);
        
        // Check if required parameters exist in the JSON data
        if (!isset($data['sonoff_email']) || !isset($data['sonoff_password']) || 
            !isset($data['sonoff_region']) || !isset($data['sonoff_deviceId']) || !isset($data['sonoff_channel_id'])) {
            echo json_encode([
                'error' => 'All parameters are required', 
                'debug' => [
                    'received_post' => $_POST,
                    'received_get' => $_GET,
                    'request_method' => $_SERVER['REQUEST_METHOD']
                ]
            ]);
            exit;
        }
        
        // Call function with JSON data
        $result = sonoff_opener(
            $data['sonoff_email'],
            $data['sonoff_password'],
            $data['sonoff_region'],
            $data['sonoff_deviceId'],
            $data['sonoff_channel_id']
        );
        
        // Return result...
        echo json_encode($result);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
} 