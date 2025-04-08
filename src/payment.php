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
$bookingId = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT);

if (!$bookingId) {
    header("Location: dashboard.php");
    exit();
}

// Get booking and payment details
$booking = $db->query("SELECT b.*, ps.slot_number, pl.name as lot_name, pl.location, ps.hourly_rate,
                              p.amount, p.payment_method
                       FROM bookings b
                       JOIN parking_slots ps ON b.slot_id = ps.slot_id
                       JOIN parking_lots pl ON ps.lot_id = pl.lot_id
                       JOIN payments p ON b.booking_id = p.booking_id
                       WHERE b.booking_id = ? AND b.customer_id = ?", 
                       [$bookingId, $_SESSION['user_id']])->fetch();

if (!$booking) {
    header("Location: dashboard.php");
    exit();
}

// Process payment form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = htmlspecialchars($_POST['payment_method'] ?? ''); 
    
    // Validation
    if (!in_array($paymentMethod, ['Card', 'Mobile Pay', 'Cash'])) {
        $errors[] = "Invalid payment method";
    }
    
    if (empty($errors)) {
        // Update payment record
        try {
            $db->query("UPDATE payments 
                       SET payment_method = ?
                       WHERE booking_id = ?", 
                       [$paymentMethod, $bookingId]);
            
            // Update booking status
            $db->query("UPDATE bookings SET status = 'Active' WHERE booking_id = ?", [$bookingId]);
            
            $success = true;
            
            // Redirect to receipt page after a short delay
            header("refresh:2;url=payment-receipt.php?booking_id=" . $bookingId);
            
        } catch (Exception $e) {
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
                        <p class="font-medium text-xl">$<?php echo number_format($booking['amount'], 2); ?></p>
                    </div>
                </div>
            </div>

            <!-- Payment Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Select Payment Method</h2>
                <form method="POST" action="payment.php?booking_id=<?php echo $bookingId; ?>">
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