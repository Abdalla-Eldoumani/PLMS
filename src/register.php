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
</body>
</html>