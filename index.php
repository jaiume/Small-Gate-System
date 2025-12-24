<?php
    require_once 'config.php';
    require_once 'functions.php';
	
    // Set cache-control headers to prevent caching
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    
    function get_buttons($user) {
        global $db_servername, $db_username, $db_password, $db_name;
        
        // Connect to database
        $mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);
        
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
		}
        
        // Prepared statement to get buttons associated with user
        $stmt = $mysqli->prepare(
		"SELECT user_buttons.button_id, buttons.label 
		FROM user_buttons 
		JOIN buttons ON user_buttons.button_id = buttons.id 
		WHERE user_buttons.username = ? 
		ORDER BY user_buttons.button_order ASC"
		);
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $result = $stmt->get_result();
        $buttons = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Check if each button has a status check definition
        foreach ($buttons as &$button) {
            $stmt = $mysqli->prepare("SELECT COUNT(*) as has_status FROM statuschecks WHERE button_id = ?");
            $stmt->bind_param("i", $button['button_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $status_check = $result->fetch_assoc();
            $button['has_status_check'] = ($status_check['has_status'] > 0);
            $stmt->close();
        }
        
        $mysqli->close();
        return $buttons;
	}
	
    // Initialize defaults
    $user = null;
    $user_status = ['enabled' => false, 'master' => false];
    $buttons = [];
    
    // If user parameter exists
    if(isset($_GET['user'])) {
        $user = $_GET['user'];
		$user_status = user_enabled($user);
		
		if ($user_status['enabled']) {
			$buttons = get_buttons($user);
		}
	}
?>

<!DOCTYPE html>
<html>
	<head>
		<title>Index Page</title>
		<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
		<meta http-equiv="Pragma" content="no-cache">
		<meta http-equiv="Expires" content="0">
		<link rel="stylesheet" type="text/css" href="styles.css?v=<?php echo time(); ?>&nocache=<?php echo uniqid(); ?>">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<meta name="viewport" content="width=device-width, initial-scale=0.75" />
	</head>
	<body>
		<div class="content-container">
			<img class="front-logo" src="<?php echo $logo_filename; ?>" alt="Guard Logging" /> <br>
			
			<?php if ($user !== null && $user_status['enabled']) {			
				foreach ($buttons as $button){ ?>
                <div class="button-container" id="container-<?= $button['button_id'] ?>">
                    <div class="button-status-wrapper">
                        <a href="#" class="button-style-front" data-button-id="<?= $button['button_id'] ?>">
                            <?= $button['label'] ?>
                            <?php if ($button['has_status_check']) { ?>
                                <span id="status-<?= $button['button_id'] ?>" class="status-icon status-unknown" title="Status: Unknown"><i class="fas fa-question"></i></span>
                            <?php } ?>
                        </a>
                    </div>
                    <div class="info-container" id="info-<?= $button['button_id'] ?>">
                        <div class="info-header" id="info-header-<?= $button['button_id'] ?>"></div>
                        <div class="info-detail" id="info-detail-<?= $button['button_id'] ?>"></div>
                    </div>
                </div>
				<?php } ?>
				<?php if ($user_status['master']) { ?>
					<br><a href="<?= $user ?>/manageusers" class="admin-button">Manage Users</a>
				<?php } ?>
			<?php } ?>
		</div>
        
        <script>
            // Force reload on iOS by adding timestamp to all AJAX requests
            (function() {
                // Add timestamp to all fetch requests
                const originalFetch = window.fetch;
                window.fetch = function(url, options) {
                    if (typeof url === 'string') {
                        url = url + (url.indexOf('?') > -1 ? '&' : '?') + '_t=' + new Date().getTime();
                    }
                    return originalFetch(url, options);
                };
                
                // Force reload on page load for iOS
                window.onpageshow = function(event) {
                    if (event.persisted) {
                        window.location.reload();
                    }
                };
            })();
            
            // Function to update the status of a button
            function updateButtonStatus(buttonId) {
                fetch('api.php?action=status_check&button_id=' + buttonId)
                    .then(response => response.text())
                    .then(status => {
                        const statusElement = document.getElementById('status-' + buttonId);
                        if (statusElement) {
                            // Remove all existing status classes
                            statusElement.classList.remove('status-unknown', 'status-open', 'status-closed', 'status-error');
                            
                            // Clear any existing icon
                            statusElement.innerHTML = '';
                            
                            // Add the appropriate class and icon based on the status
                            let iconClass = '';
                            let statusTitle = '';
                            
                            switch(status) {
                                case 'open':
                                    statusElement.classList.add('status-open');
                                    iconClass = 'fas fa-door-open';
                                    statusTitle = 'Status: Open';
                                    break;
                                case 'closed':
                                    statusElement.classList.add('status-closed');
                                    iconClass = 'fas fa-door-closed';
                                    statusTitle = 'Status: Closed';
                                    break;
                                case 'error':
                                    statusElement.classList.add('status-error');
                                    iconClass = 'fas fa-exclamation';
                                    statusTitle = 'Status: Error';
                                    break;
                                default:
                                    statusElement.classList.add('status-unknown');
                                    iconClass = 'fas fa-question';
                                    statusTitle = 'Status: Unknown';
                                    break;
                            }
                            
                            // Create and append the icon
                            const iconElement = document.createElement('i');
                            iconElement.className = iconClass;
                            statusElement.appendChild(iconElement);
                            statusElement.title = statusTitle;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching status:', error);
                    });
            }
            
            // Function to execute the engine
            function executeEngine(buttonId, user) {
                const url = `api.php?action=execute_engine&button_id=${buttonId}&user=${user}`;
                
                fetch(url)
                    .then(response => {
                        // Check if response is ok before trying to parse JSON
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        
                        // Try to parse as JSON, but handle non-JSON responses
                        return response.text().then(text => {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                // If it's not valid JSON, return an error object with the text
                                console.error("Failed to parse JSON response:", e);
                                console.log("Response text:", text);
                                return { 
                                    error: "Invalid JSON response", 
                                    responseText: text.substring(0, 100) + (text.length > 100 ? '...' : '')
                                };
                            }
                        });
                    })
                    .then(data => {
                        if (data && data.error) {
                            console.error('Error executing engine:', data.error);
                            if (data.responseText) {
                                console.error('Response text:', data.responseText);
                            }
                        } else {
                            console.log('Engine execution result:', data);
                        }
                    })
                    .catch((error) => {
                        console.error('Error executing engine:', error);
                    });
            }
            
            // Function to get button details
            function getButtonDetails(buttonId) {
                return fetch(`api.php?action=get_button_details&button_id=${buttonId}`)
                    .then(response => {
                        // Check if response is ok before trying to parse JSON
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        
                        // Try to parse as JSON, but handle non-JSON responses
                        return response.text().then(text => {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                // If it's not valid JSON, return an error object
                                console.error("Failed to parse JSON response:", e);
                                console.log("Response text:", text);
                                throw new Error("Invalid JSON response");
                            }
                        });
                    })
                    .catch(error => {
                        console.error('Error fetching button details:', error);
                        return null;
                    });
            }
            
            // Function to handle button click
            function handleButtonClick(event, buttonId, user) {
                event.preventDefault();
                
                // Get all button containers
                const allContainers = document.querySelectorAll('.button-container');
                
                // Hide all containers except the clicked one
                allContainers.forEach(container => {
                    if (container.id !== `container-${buttonId}`) {
                        container.classList.add('hidden');
                    }
                });
                
                // Get button details
                getButtonDetails(buttonId).then(buttonDetails => {
                    if (buttonDetails) {
                        // Show info container
                        const infoContainer = document.getElementById(`info-${buttonId}`);
                        const infoHeader = document.getElementById(`info-header-${buttonId}`);
                        const infoDetail = document.getElementById(`info-detail-${buttonId}`);
                        
                        infoHeader.textContent = buttonDetails.infoheader;
                        infoDetail.innerHTML = buttonDetails.infodetail.replace(/\n/g, '<br>');
                        infoContainer.style.display = 'block';
                        
                        // Execute the engine
                        executeEngine(buttonId, user);
                        
                        // Set timeout to restore all buttons after 10 seconds
                        setTimeout(() => {
                            allContainers.forEach(container => {
                                container.classList.remove('hidden');
                            });
                            infoContainer.style.display = 'none';
                        }, 10000);
                    }
                });
            }
            
            // Initialize status updates for all buttons with status checks
            document.addEventListener('DOMContentLoaded', function() {
                <?php foreach ($buttons as $button) { 
                    if ($button['has_status_check']) { ?>
                        // Initial update
                        updateButtonStatus(<?= $button['button_id'] ?>);
                        
                        // Set interval to update every 5second
                        setInterval(function() {
                            updateButtonStatus(<?= $button['button_id'] ?>);
                        }, 5000);
                    <?php }
                } ?>
                
                // Add click event listeners to all buttons
                const buttons = document.querySelectorAll('.button-style-front');
                buttons.forEach(button => {
                    const buttonId = button.getAttribute('data-button-id');
                    if (buttonId) {
                        button.addEventListener('click', (event) => {
                            handleButtonClick(event, buttonId, '<?= $user ?>');
                        });
                    }
                });
            });
        </script>
	</body>
</html>

