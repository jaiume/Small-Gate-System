<?php
    session_start();
    require_once 'config.php';
    require_once 'functions.php';

    $is_admin_mode = isset($_GET['admin']) && $_GET['admin'] == 1;
    
    // Check access permissions
    if ($is_admin_mode) {
        // Admin mode - check admin login
        if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
            header('Location: admin_login.php');
            exit();
        }
        $current_user = null; // Admin can see all users
    } else {
        // Master user mode - check if user is master
        if (!isset($_GET['user'])) {
            die("Access denied");
        }
        
        $current_user = $_GET['user'];
        $user_status = user_enabled($current_user);
        
        if (!$user_status['master']) {
            die("Access denied - Master privileges required");
        }
    }

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['update_users'])) {
            $users = $_POST['users'];
            foreach ($users as $username) {
                $enabled = isset($_POST['enabled'][$username]) ? 1 : 0;
                $master = isset($_POST['master'][$username]) ? 1 : 0;
                update_user($username, $enabled, $master);
                
                // Update button assignments
                $button_ids = isset($_POST['buttons'][$username]) ? $_POST['buttons'][$username] : array();
                update_user_buttons($username, $button_ids);
            }
        } elseif (isset($_POST['add_user'])) {
            $new_username = $_POST['new_username'];
            $enabled = isset($_POST['new_enabled']) ? 1 : 0;
            $master = isset($_POST['new_master']) ? 1 : 0;
            $added_by = isset($_POST['new_added_by']) ? $_POST['new_added_by'] : $current_user;
            
            if (add_user($new_username, $enabled, $master, $added_by)) {
                // Add button assignments for new user
                $button_ids = isset($_POST['new_buttons']) ? $_POST['new_buttons'] : array();
                update_user_buttons($new_username, $button_ids);
            }
        } elseif (isset($_POST['delete_user'])) {
            $username = $_POST['delete_username'];
            delete_user($username);
        }
    }

    // Get user tree
    $user_tree = $is_admin_mode ? get_user_tree() : get_user_tree($current_user);

    // Function to render user row with tree structure
    function render_user_row($user, $level = 0, $is_admin_mode = false) {
        global $current_user;
        
        $user_button_ids = get_user_button_ids($user['username']);
        
        // Get available buttons based on mode
        if ($is_admin_mode) {
            $available_buttons = get_all_buttons();
        } else {
            $available_buttons = get_user_buttons($current_user);
        }
        
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
        $html = "<tr>
            <input type='hidden' name='users[]' value='" . htmlspecialchars($user['username']) . "'>
            <td class='tree-cell'>";
            
        // Only add the tree symbol if not a root level user
        if ($level > 0) {
            $html .= "{$indent}├─ " . htmlspecialchars($user['username']);
        } else {
            $html .= htmlspecialchars($user['username']);
        }
        
        $html .= "</td>
            <td><input type='checkbox' name='enabled[" . htmlspecialchars($user['username']) . "]' " . ($user['enabled'] ? 'checked' : '') . "></td>
            <td><input type='checkbox' name='master[" . htmlspecialchars($user['username']) . "]' " . ($user['master'] ? 'checked' : '') . "></td>
            <td class='button-assignments'>";
        
        // Show button assignments checkboxes in a more horizontal layout
        $html .= "<div class='button-checkbox-container'>";
        foreach ($available_buttons as $button) {
            $checked = in_array($button['id'], $user_button_ids) ? 'checked' : '';
            $html .= "<label class='button-checkbox'><input type='checkbox' name='buttons[" . htmlspecialchars($user['username']) . "][]' value='{$button['id']}' {$checked}> " . htmlspecialchars($button['label']) . "</label>";
        }
        $html .= "</div>";
        
        $html .= "</td><td class='action-buttons'>";
        
        // In admin mode, display buttons side by side
        if ($is_admin_mode) {
            $html .= "<div class='action-button-container'>";
            
            // Show URL button - use the same class as delete button
            $html .= "<button type='button' class='action-btn' onclick=\"showDialog('" . htmlspecialchars($user['username']) . "')\">Show URL</button>";
            
            // Only show delete button if user has no children
            if (empty($user['children'])) {
                $html .= "<button type='button' class='action-btn' onclick=\"if(confirm('Are you sure you want to delete this user?')) { document.getElementById('delete-form-" . htmlspecialchars($user['username']) . "').submit(); }\">Delete</button>";
                $html .= "<form id='delete-form-" . htmlspecialchars($user['username']) . "' class='delete-button hidden-form' method='post'>
                    <input type='hidden' name='delete_username' value='" . htmlspecialchars($user['username']) . "'>
                    <input type='hidden' name='delete_user' value='1'>
                </form>";
            }
            
            $html .= "</div>";
        } else {
            // For master mode, keep the original layout
            // Only show delete button if user has no children
            if (empty($user['children'])) {
                $html .= "<form class='delete-button' method='post'>
                    <input type='hidden' name='delete_username' value='" . htmlspecialchars($user['username']) . "'>
                    <input type='submit' name='delete_user' value='Delete' class='delete-btn' onclick='return confirm(\"Are you sure you want to delete this user?\");'>
                </form>";
            }
        }
        
        $html .= "</td></tr>\n";
        
        if (!empty($user['children'])) {
            foreach ($user['children'] as $child) {
                $html .= render_user_row($child, $level + 1, $is_admin_mode);
            }
        }
        
        return $html;
    }
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Manage Users</title>
        <link rel="stylesheet" type="text/css" href="/styles.css">
        <meta name="viewport" content="width=device-width, initial-scale=0.75" />
        <?php if ($is_admin_mode): ?>
        <script type="text/javascript">
            function showDialog(username) {
                const baseUrl = '<?php echo $base_url; ?>';
                const url = `${baseUrl}/${username}`;
                alert(`${url}`);
            }
        </script>
        <?php endif; ?>
    </head>
    <body class="user-management-page">
        <?php if ($is_admin_mode): ?>
            <?php include 'header.php'; ?>
        <?php endif; ?>
        
        <div class="content-container">
            <h1>Manage Users</h1>

            <!-- User Tree Table -->
            <h2>User Tree</h2>
            <form method="post">
                <table class="user-tree-table">
                    <tr>
                        <th>Username</th>
                        <th>Enabled</th>
                        <th>Master</th>
                        <th>Button Assignments</th>
                        <th>Actions</th>
                    </tr>
                    <?php
                    foreach ($user_tree as $user) {
                        echo render_user_row($user, 0, $is_admin_mode);
                    }
                    ?>
                </table>
                <div class="update-button">
                    <button type="submit" name="update_users" class="button-style-front">Update All Users</button>
                </div>
            </form>
            
            <!-- Separate Add User form -->
            <h2>Add New User</h2>
            <form method="post">
                <table class="add-user-table">
                    <tr>
                        <th>Username</th>
                        <th>Enabled/Master</th>
                        <?php if ($is_admin_mode): ?>
                        <th>Added By</th>
                        <?php endif; ?>
                        <th>Button Assignments</th>
                        <th>Action</th>
                    </tr>
                    <tr class="add-user-row">
                        <td>
                            <input type="text" name="new_username" placeholder="New Username" required>
                        </td>
                        <td>
                            <div class="checkbox-group">
                                <label><input type="checkbox" name="new_enabled" checked> Enabled</label>
                                <label><input type="checkbox" name="new_master"> Master</label>
                            </div>
                        </td>
                        <?php if ($is_admin_mode): ?>
                        <td>
                            <select name="new_added_by" id="new_added_by">
                                <option value="">None (Root User)</option>
                                <?php
                                $mysqli = new mysqli($db_servername, $db_username, $db_password, $db_name);
                                $result = $mysqli->query("SELECT username FROM users ORDER BY username");
                                while ($row = $result->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($row['username']) . "'>" . htmlspecialchars($row['username']) . "</option>";
                                }
                                $mysqli->close();
                                ?>
                            </select>
                        </td>
                        <?php endif; ?>
                        <td>
                            <div class="add-user-buttons">
                                <?php
                                if ($is_admin_mode) {
                                    $available_buttons = get_all_buttons();
                                } else {
                                    $available_buttons = get_user_buttons($current_user);
                                }
                                
                                if (empty($available_buttons)) {
                                    echo "<p>No buttons available to assign.</p>";
                                } else {
                                    foreach ($available_buttons as $button) {
                                        echo "<label class='button-checkbox'><input type='checkbox' name='new_buttons[]' value='{$button['id']}'> " . htmlspecialchars($button['label']) . "</label>";
                                    }
                                }
                                ?>
                            </div>
                        </td>
                        <td>
                            <button type="submit" name="add_user" class="button-style-front">Add User</button>
                        </td>
                    </tr>
                </table>
            </form>
            
            <?php if ($is_admin_mode): ?>
                <a href="admin_dashboard.php" class="button-style-front">Back to Dashboard</a>
            <?php else: ?>
                <a href="/<?= urlencode($current_user) ?>" class="button-style-front">Back to Home</a>
            <?php endif; ?>
        </div>
    </body>
</html>