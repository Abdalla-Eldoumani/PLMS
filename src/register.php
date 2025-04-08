<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();
session_start();
require_once 'includes/auth.php';

$auth = new Auth();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$userType = $_GET['type'] ?? 'customer';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid form submission";
    } else {
        // Validate form data
        $name = isset($_POST['name']) ? trim(htmlspecialchars($_POST['name'])) : '';
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $phone = isset($_POST['phone']) ? trim(htmlspecialchars($_POST['phone'])) : '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $userType = isset($_POST['user_type']) ? trim(htmlspecialchars($_POST['user_type'])) : '';
        $licensePlate = isset($_POST['license_plate']) ? trim(htmlspecialchars($_POST['license_plate'])) : '';
        $subscriptionType = isset($_POST['subscription_type']) ? trim(htmlspecialchars($_POST['subscription_type'])) : '';
        $licensePlate = filter_input(INPUT_POST, 'license_plate', FILTER_SANITIZE_STRING);
        $subscriptionType = filter_input(INPUT_POST, 'subscription_type', FILTER_SANITIZE_STRING);
        
        // Validation
        if (empty($name)) $errors[] = "Name is required";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
        if (empty($password)) $errors[] = "Password is required";
        if ($password !== $confirmPassword) $errors[] = "Passwords do not match";
        if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
        
        if ($userType === 'customer' && empty($licensePlate)) {
            $errors[] = "License plate is required for customers";
        }
        
        // If no errors, process registration
        if (empty($errors)) {
            $result = $auth->register($name, $email, $phone, $password, $userType, $licensePlate, $subscriptionType);
            
            if ($result['success']) {
                // Auto-login user
                $_SESSION['user_id'] = $result['user_id'];
                $_SESSION['user_type'] = $result['user_type'];
                $_SESSION['name'] = $name;
                $_SESSION['email'] = $email;
                
                // Redirect to dashboard
                header('Location: dashboard.php');
                exit;
            } else {
                $errors[] = $result['message'];
                error_log("Registration failed: " . $result['message']);
            }
        }
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - UCalgary Parking Management</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Navigation Bar -->
    <nav class="bg-red-700 text-white shadow-lg">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center">
                <a class="text-xl font-bold" href="index.php">Parking Management System</a>
            </div>
            <div>
                <a href="index.php" class="hover:text-gray-200">Home</a>
                <a href="login.php" class="ml-4 hover:text-gray-200">Login</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold text-center mb-6">Create an Account</h2>
            
            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <ul class="list-disc pl-4">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="register.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="user_type" value="<?php echo htmlspecialchars($userType); ?>">
                
                <div class="mb-4">
                    <label for="name" class="block text-gray-700 font-medium mb-2">Full Name</label>
                    <input type="text" id="name" name="name" required
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                </div>
                
                <div class="mb-4">
                    <label for="email" class="block text-gray-700 font-medium mb-2">Email Address</label>
                    <input type="email" id="email" name="email" required
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="mb-4">
                    <label for="phone" class="block text-gray-700 font-medium mb-2">Phone Number</label>
                    <input type="tel" id="phone" name="phone"
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                </div>
                
                <?php if ($userType === 'customer'): ?>
                    <div class="mb-4">
                        <label for="license_plate" class="block text-gray-700 font-medium mb-2">License Plate</label>
                        <input type="text" id="license_plate" name="license_plate" required
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                               value="<?php echo isset($_POST['license_plate']) ? htmlspecialchars($_POST['license_plate']) : ''; ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="subscription_type" class="block text-gray-700 font-medium mb-2">Subscription Type</label>
                        <select id="subscription_type" name="subscription_type" required
                                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                            <option value="Hourly" <?php echo (isset($_POST['subscription_type']) && $_POST['subscription_type'] === 'Hourly') ? 'selected' : ''; ?>>Hourly</option>
                            <option value="Daily" <?php echo (isset($_POST['subscription_type']) && $_POST['subscription_type'] === 'Daily') ? 'selected' : ''; ?>>Daily</option>
                            <option value="Monthly" <?php echo (isset($_POST['subscription_type']) && $_POST['subscription_type'] === 'Monthly') ? 'selected' : ''; ?>>Monthly</option>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="mb-4">
                    <label for="password" class="block text-gray-700 font-medium mb-2">Password</label>
                    <input type="password" id="password" name="password" required
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                           minlength="8">
                    <p class="text-sm text-gray-500 mt-1">Password must be at least 8 characters</p>
                </div>
                
                <div class="mb-6">
                    <label for="confirm_password" class="block text-gray-700 font-medium mb-2">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                           minlength="8">
                </div>
                
                <button type="submit" class="w-full bg-red-700 text-white py-2 rounded-lg hover:bg-red-800 transition">
                    Create Account
                </button>
            </form>
            
            <p class="mt-4 text-center text-gray-600">
                Already have an account? <a href="login.php" class="text-red-700 hover:underline">Log in</a>
            </p>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add toggle buttons to password fields
            addPasswordToggle('password');
            addPasswordToggle('confirm_password');
            
            function addPasswordToggle(fieldId) {
                const passwordField = document.getElementById(fieldId);
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