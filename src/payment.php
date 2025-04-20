<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$db = new Database();
$errors = [];
$success = false;

// Get booking_id from URL
/*
$bookingId = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT);

if (!$bookingId) {
    header("Location: dashboard.php");
    exit();
} */

// Get booking and payment details
/*
$booking = $db->query("SELECT b.*, ps.slot_number, pl.name as lot_name, pl.location, ps.hourly_rate,
                              p.amount, p.payment_method
                       FROM bookings b
                       JOIN parking_slots ps ON b.slot_id = ps.slot_id
                       JOIN parking_lots pl ON ps.lot_id = pl.lot_id
                       JOIN payments p ON b.booking_id = p.booking_id
                       WHERE b.booking_id = ? AND b.customer_id = ?", 
                       [$bookingId, $_SESSION['user_id']])->fetch(); */


                       $pending = $_SESSION['pending_booking'] ?? null;

                       if (!$pending) {
                           header("Location: dashboard.php");
                           exit();
                       }
                       
                       // Get slot and lot details
                       $booking = $db->query("SELECT ps.slot_id, ps.slot_number, ps.hourly_rate, pl.name as lot_name, pl.location 
                                              FROM parking_slots ps
                                              JOIN parking_lots pl ON ps.lot_id = pl.lot_id
                                              WHERE ps.slot_id = ?", [$pending['slot_id']])->fetch();
                       
                       if (!$booking) {
                           header("Location: dashboard.php");
                           exit();
                       }
                       
                       // Merge in session data
                       $booking['start_time'] = $pending['start_time'];
                       $booking['end_time'] = $pending['end_time'];
                       $booking['total_cost'] = $pending['total_cost'];
                       $booking['vehicle_id'] = $pending['vehicle_id'];
                       


if (!$booking) {
    header("Location: dashboard.php");
    exit();
}

// Process payment form
error_log("Payment form submitted!");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = htmlspecialchars($_POST['payment_method'] ?? ''); 

    if (!$paymentMethod) {
        $errors[] = "Please select a payment method.";
    }
    

    if (!in_array($paymentMethod, ['Card', 'Mobile Pay', 'Cash'])) {
        $errors[] = "Invalid payment method";
    }
    
    if (empty($errors)) {
        $db->query("START TRANSACTION");
    
        try {
            // Create booking
            $db->query("INSERT INTO bookings (customer_id, slot_id, vehicle_id, start_time, end_time, status) 
                        VALUES (?, ?, ?, ?, ?, 'Active')", 
                        [$_SESSION['user_id'], $booking['slot_id'], $booking['vehicle_id'], $booking['start_time'], $booking['end_time']]);
    
            $bookingId = $db->getLastInsertId();
    
            // Create payment
            $db->query("INSERT INTO payments (booking_id, amount, payment_method) 
                        VALUES (?, ?, ?)", 
                        [$bookingId, $booking['total_cost'], $paymentMethod]);
    
            // Set slot to occupied
            $db->query("UPDATE parking_slots SET status = 'Occupied' WHERE slot_id = ?", [$booking['slot_id']]);
    
            $db->query("COMMIT");
    
            unset($_SESSION['pending_booking']);
            $success = true;
    
            header("refresh:2;url=payment-receipt.php?booking_id=" . $bookingId);
            //exit();
    
        } catch (Exception $e) {
            $db->query("ROLLBACK");
            $errors[] = "Payment failed: " . $e->getMessage();
        }
    }
    
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="bg-gray-100 font-sans">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-800 mb-8">Complete Your Payment</h1>
            
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
                    Payment successful! Redirecting to receipt...
                </div>
            <?php endif; ?>

            <!-- Payment Summary -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Payment Summary</h2>
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
                        <p class="text-gray-600">Amount Due</p>
                        <p class="font-medium text-xl">$<?php echo number_format($booking['total_cost'], 2); ?></p>
                    </div>
                </div>
            </div>

            <!-- Payment Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Select Payment Method</h2>
                <form method="POST" action="payment.php">
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <input type="radio" id="card" name="payment_method" value="Card" required
                                   class="h-4 w-4 text-red-700 focus:ring-red-500">
                            <label for="card" class="ml-2 block text-gray-700">Credit/Debit Card</label>
                        </div>
                        <div class="flex items-center">
                            <input type="radio" id="cash" name="payment_method" value="Cash" required
                                   class="h-4 w-4 text-red-700 focus:ring-red-500">
                            <label for="cash" class="ml-2 block text-gray-700">Cash</label>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-4">
                        <a href="dashboard.php" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition">
                            Cancel
                        </a>
                        <button type="submit" class="bg-red-700 text-white px-6 py-2 rounded-lg hover:bg-red-800 transition">
                            Complete Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 
<?php exit(); ?>