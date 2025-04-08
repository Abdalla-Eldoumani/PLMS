<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$db = new Database();
$errors = [];
$success = false;

// Get current user information
$user = $db->query("SELECT * FROM users WHERE user_id = ?", [$user_id])->fetch();
$customer = $db->query("SELECT * FROM customers WHERE user_id = ?", [$user_id])->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid form submission.";
    } else {
        // Validate input
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $subscription_type = isset($_POST['subscription_type']) ? sanitize($_POST['subscription_type']) : $customer['subscription_type'];
        
        // Optional password change
        $current_password = trim($_POST['current_password']);
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);
        
        // Basic validation
        if (empty($name)) {
            $errors[] = "Name is required.";
        }
        
        if (empty($email)) {
            $errors[] = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }
        
        // Check if email exists (if changed)
        if ($email != $user['email']) {
            $existingEmail = $db->query("SELECT user_id FROM users WHERE email = ? AND user_id != ?", 
                                      [$email, $user_id])->fetch();
            if ($existingEmail) {
                $errors[] = "Email already in use by another account.";
            }
        }
        
        // Password validation (only if user is trying to change it)
        if (!empty($current_password)) {
            // Verify current password
            if (!password_verify($current_password, $user['password'])) {
                $errors[] = "Current password is incorrect.";
            }
            
            if (empty($new_password)) {
                $errors[] = "New password is required when changing password.";
            } elseif (strlen($new_password) < 8) {
                $errors[] = "New password must be at least 8 characters.";
            }
            
            if ($new_password !== $confirm_password) {
                $errors[] = "New passwords do not match.";
            }
        }
        
        // If no errors, update user information
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                // Update user information
                if (!empty($new_password)) {
                    // Update with new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $db->query("UPDATE users SET name = ?, email = ?, phone = ?, password = ? WHERE user_id = ?", 
                              [$name, $email, $phone, $hashed_password, $user_id]);
                } else {
                    // Update without changing password
                    $db->query("UPDATE users SET name = ?, email = ?, phone = ? WHERE user_id = ?", 
                              [$name, $email, $phone, $user_id]);
                }
                
                // Update customer subscription type if different
                if ($subscription_type !== $customer['subscription_type']) {
                    $db->query("UPDATE customers SET subscription_type = ? WHERE user_id = ?", 
                              [$subscription_type, $user_id]);
                }
                
                $db->commit();
                $success = true;
                
                // Update user information for display
                $user = $db->query("SELECT * FROM users WHERE user_id = ?", [$user_id])->fetch();
                $customer = $db->query("SELECT * FROM customers WHERE user_id = ?", [$user_id])->fetch();
                
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollback();
                }
                $errors[] = "An error occurred: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Navigation Bar -->
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-3xl mx-auto">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gray-800 text-white px-6 py-4">
                    <h2 class="text-xl font-semibold">Edit Profile</h2>
                </div>
                
                <div class="p-6">
                    <?php if ($success): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                            <p>Your profile has been updated successfully.</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                            <p class="font-bold">Please correct the following errors:</p>
                            <ul class="list-disc ml-5">
                                <?php foreach($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="edit-profile.php">
                        <!-- CSRF token -->
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-4">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" id="name" name="name" class="input-field" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" class="input-field" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" id="phone" name="phone" class="input-field" value="<?php echo htmlspecialchars($user['phone']); ?>">
                        </div>
                        
                        <div class="mb-6">
                            <label for="subscription_type" class="form-label">Subscription Type</label>
                            <select id="subscription_type" name="subscription_type" class="input-field">
                                <option value="Monthly" <?php echo $customer['subscription_type'] === 'Monthly' ? 'selected' : ''; ?>>Monthly</option>
                                <option value="Daily" <?php echo $customer['subscription_type'] === 'Daily' ? 'selected' : ''; ?>>Daily</option>
                                <option value="Hourly" <?php echo $customer['subscription_type'] === 'Hourly' ? 'selected' : ''; ?>>Hourly</option>
                            </select>
                        </div>
                        
                        <div class="border-t border-gray-200 pt-6 mb-6">
                            <h3 class="text-lg font-semibold mb-4">Change Password (optional)</h3>
                            
                            <div class="mb-4">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" id="current_password" name="current_password" class="input-field">
                                <p class="text-sm text-gray-500 mt-1">Leave blank if you don't want to change your password</p>
                            </div>
                            
                            <div class="mb-4">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="input-field">
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="input-field">
                            </div>
                        </div>
                        
                        <div class="flex justify-between">
                            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add toggle buttons to all password fields
            addPasswordToggle('current_password');
            addPasswordToggle('new_password');
            addPasswordToggle('confirm_password');
            
            function addPasswordToggle(fieldId) {
                const passwordField = document.getElementById(fieldId);
                if (!passwordField) return; // Skip if field doesn't exist
                
                const passwordContainer = passwordField.parentElement;
                
                // Create wrapper to maintain layout
                const wrapper = document.createElement('div');
                wrapper.className = 'relative';
                
                // Move password field into wrapper
                passwordField.parentNode.insertBefore(wrapper, passwordField);
                wrapper.appendChild(passwordField);
                
                // Add toggle button
                const toggleButton = document.createElement('button');
                toggleButton.type = 'button';
                toggleButton.className = 'absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700';
                toggleButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/></svg>';
                wrapper.appendChild(toggleButton);
                
                // Add event listener
                toggleButton.addEventListener('click', function() {
                    const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordField.setAttribute('type', type);
                    
                    // Change icon based on password visibility
                    this.innerHTML = type === 'password' 
                        ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/></svg>'
                        : '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye-slash" viewBox="0 0 16 16"><path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7.028 7.028 0 0 0-2.79.588l.77.771A5.944 5.944 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.134 13.134 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755-.165.165-.337.328-.517.486l.708.709z"/><path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829l.822.822zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829z"/><path d="M3.35 5.47c-.18.16-.353.322-.518.487A13.134 13.134 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7.029 7.029 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12-.708.708z"/></svg>';
                });
            }
        });
    </script>
</body>
</html>