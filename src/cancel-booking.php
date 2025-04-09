<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/email.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$db = new Database();
$errors = [];
$success = false;

// Get booking_id from URL
$bookingId = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT);

if (!$bookingId) {
    header("Location: my-bookings.php");
    exit();
}

// Get booking details
$booking = $db->query("SELECT b.*, ps.slot_number, pl.name as lot_name, pl.location, ps.hourly_rate,
                              p.amount, p.payment_method, p.status as payment_status, p.payment_id
                       FROM bookings b
                       JOIN parking_slots ps ON b.slot_id = ps.slot_id
                       JOIN parking_lots pl ON ps.lot_id = pl.lot_id
                       JOIN payments p ON b.booking_id = p.booking_id
                       WHERE b.booking_id = ? AND b.customer_id = ?", 
                       [$bookingId, $_SESSION['user_id']])->fetch();

if (!$booking) {
    header("Location: my-bookings.php");
    exit();
}

// Get user details
$user = $db->query("SELECT * FROM users WHERE user_id = ?", [$_SESSION['user_id']])->fetch();

// Get vehicle details
$vehicle = $db->query("SELECT v.* FROM vehicles v 
                       JOIN bookings b ON v.vehicle_id = b.vehicle_id
                       WHERE b.booking_id = ?", [$bookingId])->fetch();

// Check if booking can be cancelled
$now = new DateTime();
$startTime = new DateTime($booking['start_time']);
$endTime = new DateTime($booking['end_time']);

// Booking can be cancelled if:
// 1. It's in the future (start time is after now)
// 2. It's active but hasn't started yet
// 3. It's pending
if ($startTime <= $now && $booking['status'] === 'Active') {
    $errors[] = "This booking has already started and cannot be cancelled.";
} elseif ($booking['status'] === 'Cancelled') {
    $errors[] = "This booking has already been cancelled.";
} elseif ($booking['status'] === 'Completed') {
    $errors[] = "This booking has already been completed.";
}

// Process cancellation form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid request. Please try again.";
    } else {
        // Begin transaction
        $db->query("START TRANSACTION");
        
        try {
            // Update booking status to Cancelled
            $db->query("UPDATE bookings SET status = 'Cancelled', updated_at = NOW() WHERE booking_id = ?", 
                       [$bookingId]);
            
            // Update slot status to Available
            $db->query("UPDATE parking_slots ps 
                       JOIN bookings b ON ps.slot_id = b.slot_id 
                       SET ps.status = 'Available' 
                       WHERE b.booking_id = ?", [$bookingId]);
            
            // If payment was made, process refund
            if ($booking['payment_status'] === 'Completed') {
                // In a real system, you would integrate with a payment processor here
                // For this example, we'll just update the payment status
                $db->query("UPDATE payments SET status = 'Refunded', updated_at = NOW() WHERE payment_id = ?", 
                           [$booking['payment_id']]);
                
                // Create a refund record
                $db->query("INSERT INTO payments (booking_id, amount, payment_method, status, description, payment_date) 
                           VALUES (?, ?, ?, 'Refunded', 'Refund for cancelled booking', NOW())", 
                           [$bookingId, $booking['amount'], $booking['payment_method']]);
            }
            
            $db->query("COMMIT");
            $success = true;
            
            // Send cancellation email
            sendCancellationEmail($booking, $user, $vehicle);
            
            // Redirect to my-bookings page with success message
            $_SESSION['success_message'] = "Your booking has been successfully cancelled.";
            header("Location: my-bookings.php");
            exit();
            
        } catch (Exception $e) {
            $db->query("ROLLBACK");
            $errors[] = "An error occurred while cancelling your booking. Please try again.";
            error_log("Booking cancellation error: " . $e->getMessage());
        }
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Calculate refund amount if applicable
$refundAmount = 0;
if ($booking['payment_status'] === 'Completed') {
    // If booking hasn't started yet, full refund
    if ($startTime > $now) {
        $refundAmount = $booking['amount'];
    } else {
        // Partial refund based on remaining time
        // This is a simplified calculation - in a real system, you'd have more complex rules
        $refundAmount = $booking['amount'] * 0.5; // 50% refund for example
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Booking - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="bg-gray-100 font-sans">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-800 mb-8">Cancel Booking</h1>
            
            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <ul class="list-disc pl-4">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <p>Your booking has been successfully cancelled.</p>
                </div>
            <?php endif; ?>

            <!-- Booking Summary -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Booking Details</h2>
                <div class="space-y-4">
                    <div>
                        <p class="text-gray-600">Location</p>
                        <p class="font-medium"><?php echo htmlspecialchars($booking['lot_name']); ?> - <?php echo htmlspecialchars($booking['location']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Slot Number</p>
                        <p class="font-medium"><?php echo htmlspecialchars($booking['slot_number']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Start Time</p>
                        <p class="font-medium"><?php echo date('F j, Y g:i A', strtotime($booking['start_time'])); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">End Time</p>
                        <p class="font-medium"><?php echo date('F j, Y g:i A', strtotime($booking['end_time'])); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Rate</p>
                        <p class="font-medium">$<?php echo number_format($booking['hourly_rate'], 2); ?>/hour</p>
                    </div>
                    <div>
                        <p class="text-gray-600">Total Amount</p>
                        <p class="font-medium">$<?php echo number_format($booking['amount'], 2); ?></p>
                    </div>
                    <?php if ($booking['payment_status'] === 'Completed'): ?>
                    <div>
                        <p class="text-gray-600">Refund Amount</p>
                        <p class="font-medium text-green-600">$<?php echo number_format($refundAmount, 2); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cancellation Form -->
            <?php if (empty($errors) && !$success): ?>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Confirm Cancellation</h2>
                <p class="mb-6">Are you sure you want to cancel this booking? This action cannot be undone.</p>
                
                <form method="POST" action="cancel-booking.php?booking_id=<?php echo $bookingId; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="flex justify-end space-x-4">
                        <a href="my-bookings.php" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition">
                            Go Back
                        </a>
                        <button type="submit" class="bg-red-700 text-white px-6 py-2 rounded-lg hover:bg-red-800 transition">
                            Confirm Cancellation
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 