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

$buttons = get_all_buttons();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_button'])) {
        $label = $_POST['label'];
        $infoheader = $_POST['infoheader'];
        $infodetail = $_POST['infodetail'];
        add_button($label, $infoheader, $infodetail);
        header('Location: /admin/buttons');
        exit();
    } elseif (isset($_POST['update_button'])) {
        $id = $_POST['id'];
        $label = $_POST['label'];
        $infoheader = $_POST['infoheader'];
        $infodetail = $_POST['infodetail'];
        update_button($id, $label, $infoheader, $infodetail);
        header('Location: /admin/buttons');
        exit();
    } elseif (isset($_POST['delete_button'])) {
        $id = $_POST['id'];
        delete_button($id);
        $buttons = get_all_buttons();
    }
}

$mysqli->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Button Management</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <h1>Button Management</h1>
    <table>
        <tr>
            <th>ID</th>
            <th>Label</th>
            <th>Info Header</th>
            <th>Info Detail</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($buttons as $button): ?>
        <tr>
            <form method="post" action="/admin/buttons">
                <td>
                    <?= $button['id'] ?>
                    <input type="hidden" name="id" value="<?= $button['id'] ?>">
                </td>
                <td>
                    <input type="text" name="label" value="<?= $button['label'] ?>">
                </td>
                <td>
                    <input type="text" name="infoheader" value="<?= $button['infoheader'] ?>">
                </td>
                <td>
                    <textarea name="infodetail"><?= $button['infodetail'] ?></textarea>
                </td>
                <td>
                    <button type="submit" name="update_button">Update</button>
                    <button type="submit" name="delete_button">Delete</button>
                    <a href="/admin/buttons/<?= $button['id'] ?>/steps" class="button-style">Action Steps</a>
                    <a href="/admin/buttons/<?= $button['id'] ?>/status" class="button-style">Status Check</a>
                    <button type="button" class="test-button" data-button-id="<?= $button['id'] ?>">Test</button>
                </td>
            </form>
        </tr>
        <?php endforeach; ?>
        <!-- Add Button row -->
        <tr>
            <form method="post" action="/admin/buttons">
                <td>#</td>
                <td>
                    <input type="text" name="label" required>
                </td>
                <td>
                    <input type="text" name="infoheader" required>
                </td>
                <td>
                    <textarea name="infodetail" required></textarea>
                </td>
                <td>
                    <button type="submit" name="add_button">Add Button</button>
                </td>
            </form>
        </tr>
    </table>
    
    <!-- Test Modal -->
    <div id="testModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div class="info-header" id="modalHeader"></div>
            <div class="info-detail" id="modalDetail"></div>
            <div id="modalStatus"></div>
        </div>
    </div>
    
    <script>
        // Get the modal
        const modal = document.getElementById('testModal');
        const modalHeader = document.getElementById('modalHeader');
        const modalDetail = document.getElementById('modalDetail');
        const modalStatus = document.getElementById('modalStatus');
        
        // Get the <span> element that closes the modal
        const closeBtn = document.getElementsByClassName('close')[0];
        
        // When the user clicks on <span> (x), close the modal
        closeBtn.onclick = function() {
            modal.style.display = 'none';
        }
        
        // When the user clicks anywhere outside of the modal, close it
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        
        // Add event listeners to all test buttons
        document.querySelectorAll('.test-button').forEach(button => {
            button.addEventListener('click', function() {
                const buttonId = this.getAttribute('data-button-id');
                testButton(buttonId);
            });
        });
        
        // Function to test a button
        function testButton(buttonId) {
            // Show the modal with loading message
            modalHeader.textContent = 'Loading...';
            modalDetail.textContent = '';
            modalStatus.textContent = 'Fetching button details...';
            modal.style.display = 'block';
            
            // Fetch button details
            fetch(`/api.php?action=get_button_details&button_id=${buttonId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        modalStatus.textContent = `Error: ${data.error}`;
                        return;
                    }
                    
                    // Display button details
                    modalHeader.textContent = data.infoheader;
                    modalDetail.innerHTML = data.infodetail.replace(/\n/g, '<br>');
                    modalStatus.textContent = 'Button details loaded successfully.';
                    
                    // Execute the engine (optional in test mode)
                    modalStatus.textContent += ' Testing engine execution...';
                    
                    return fetch(`/api.php?action=execute_engine&button_id=${buttonId}&user=admin_test`);
                })
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
                    if (data && data.success) {
                        modalStatus.textContent += ' Engine executed successfully.';
                    } else if (data && data.error) {
                        modalStatus.textContent += ` Error: ${data.error}`;
                        if (data.responseText) {
                            modalStatus.textContent += ` Response: ${data.responseText}`;
                        }
                    } else {
                        modalStatus.textContent += ' Unknown response from server.';
                    }
                })
                .catch(error => {
                    modalStatus.textContent = `Error: ${error.message}`;
                    console.error('Error during button test:', error);
                });
        }
    </script>
</body>
</html>


