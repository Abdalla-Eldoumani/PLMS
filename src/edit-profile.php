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
                
                // Log activity
                logActivity($user_id, 'profile_update', 'User updated profile information', $db);
                
                // Update user information for display
                $user = $db->query("SELECT * FROM users WHERE user_id = ?", [$user_id])->fetch();
                $customer = $db->query("SELECT * FROM customers WHERE user_id = ?", [$user_id])->fetch();
                
            } catch (Exception $e) {
                $db->rollback();
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
</body>
</html>