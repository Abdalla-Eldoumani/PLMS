<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/email.php';

$db = new Database();
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    
    if (empty($email)) {
        $errors[] = "Please enter your email address.";
    } else {
        try {
            // Check if email exists
            $user = $db->query(
                "SELECT user_id, email FROM users WHERE email = ?",
                [$email]
            )->fetch();
            
            if (!$user) {
                // Don't reveal if email exists or not for security
                $success = true;
            } else {
                $db->beginTransaction();
                
                // Delete any existing reset tokens for this user
                $db->query(
                    "DELETE FROM password_resets WHERE user_id = ?",
                    [$user['user_id']]
                );
                
                // Generate new reset token
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Insert new reset token
                $db->query(
                    "INSERT INTO password_resets (user_id, token, expiry) VALUES (?, ?, ?)",
                    [$user['user_id'], $token, $expiry]
                );
                
                // Generate reset link
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $token;
                
                // Send reset email
                if (sendPasswordResetEmail($user['email'], $resetLink)) {
                    $db->commit();
                    $success = true;
                } else {
                    // If email sending fails, still show success message for security
                    // but log the error for debugging
                    error_log("Failed to send password reset email to: " . $user['email']);
                    $db->commit();
                    $success = true;
                }
            }
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Password reset error: " . $e->getMessage());
            $errors[] = "An error occurred while processing your request. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8">
        <h1 class="text-2xl font-bold text-center mb-6">Forgot Password</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <p>If an account exists with that email address, you will receive password reset instructions shortly.</p>
            </div>
        <?php endif; ?>
        
        <p class="text-gray-600 mb-4">Enter your email address and we'll send you instructions to reset your password.</p>
        
        <form method="POST" action="forgot-password.php">
            <div class="mb-6">
                <label for="email" class="block text-gray-700 font-medium mb-2">Email Address</label>
                <input type="email" id="email" name="email" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <button type="submit" 
                    class="w-full bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                Send Reset Link
            </button>
            
            <p class="mt-4 text-center">
                <a href="login.php" class="text-blue-500 hover:text-blue-600">Back to Login</a>
            </p>
        </form>
    </div>
</body>
</html> 