<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$db = new Database();
$errors = [];
$success = false;

// Get token from URL
$token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);

if (empty($token)) {
    $errors[] = "Invalid password reset link.";
} else {
    // Check if token exists and is valid
    $reset = $db->query(
        "SELECT pr.*, u.email 
         FROM password_resets pr 
         JOIN users u ON pr.user_id = u.user_id 
         WHERE pr.token = ? AND pr.expiry > NOW()",
        [$token]
    )->fetch();
    
    if (!$reset) {
        $errors[] = "This password reset link is invalid or has expired.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate password
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Update user's password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $db->query(
                "UPDATE users SET password = ? WHERE user_id = ?",
                [$hashed_password, $reset['user_id']]
            );
            
            // Delete the used reset token
            $db->query(
                "DELETE FROM password_resets WHERE token = ?",
                [$token]
            );
            
            $db->commit();
            $success = true;
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "An error occurred while resetting your password. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8">
        <h1 class="text-2xl font-bold text-center mb-6">Reset Password</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <p>Your password has been successfully reset.</p>
                <p class="mt-2">You can now <a href="login.php" class="text-green-700 underline">login</a> with your new password.</p>
            </div>
        <?php elseif (empty($errors)): ?>
            <form method="POST" action="reset-password.php?token=<?php echo htmlspecialchars($token); ?>">
                <div class="mb-6">
                    <label for="password" class="block text-gray-700 font-medium mb-2">New Password</label>
                    <input type="password" id="password" name="password" required minlength="8"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-6">
                    <label for="confirm_password" class="block text-gray-700 font-medium mb-2">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <button type="submit" 
                        class="w-full bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Reset Password
                </button>
            </form>
        <?php endif; ?>
        
        <p class="mt-4 text-center">
            <a href="login.php" class="text-blue-500 hover:text-blue-600">Back to Login</a>
        </p>
    </div>
</body>
</html> 