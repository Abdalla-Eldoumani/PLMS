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

// Get booking_id from URL
$bookingId = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT);

if (!$bookingId) {
    header("Location: my-bookings.php");
    exit();
}

// Get booking and payment details
$booking = $db->query("SELECT b.*, ps.slot_number, pl.name as lot_name, pl.location, ps.hourly_rate,
                              p.amount, p.payment_method, p.status as payment_status, p.payment_date
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

// Calculate duration
$startDateTime = new DateTime($booking['start_time']);
$endDateTime = new DateTime($booking['end_time']);
$duration = $startDateTime->diff($endDateTime);
$hours = $duration->h + ($duration->days * 24);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="bg-gray-100 font-sans">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-3xl mx-auto">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <!-- Receipt Header -->
                <div class="bg-red-700 text-white px-6 py-4">
                    <div class="flex justify-between items-center">
                        <h1 class="text-2xl font-bold">Payment Receipt</h1>
                        <div class="text-right">
                            <p class="text-sm">Receipt #<?php echo str_pad($booking['booking_id'], 8, '0', STR_PAD_LEFT); ?></p>
                            <p class="text-sm"><?php echo date('M d, Y', strtotime($booking['payment_date'])); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Receipt Content -->
                <div class="p-6">
                    <!-- Customer Information -->
                    <div class="mb-8">
                        <h2 class="text-lg font-semibold mb-4">Customer Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-gray-600">Name</p>
                                <p class="font-medium"><?php echo htmlspecialchars($user['name']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600">Email</p>
                                <p class="font-medium"><?php echo htmlspecialchars($user['email']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Booking Details -->
                    <div class="mb-8">
                        <h2 class="text-lg font-semibold mb-4">Booking Details</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                <p class="text-gray-600">Duration</p>
                                <p class="font-medium"><?php echo $hours; ?> hours</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Rate</p>
                                <p class="font-medium">$<?php echo number_format($booking['hourly_rate'], 2); ?>/hour</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Vehicle Information -->
                    <?php if ($vehicle): ?>
                    <div class="mb-8">
                        <h2 class="text-lg font-semibold mb-4">Vehicle Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-gray-600">License Plate</p>
                                <p class="font-medium"><?php echo htmlspecialchars($vehicle['license_plate']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600">Vehicle Type</p>
                                <p class="font-medium"><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Payment Information -->
                    <div class="mb-8">
                        <h2 class="text-lg font-semibold mb-4">Payment Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-gray-600">Payment Method</p>
                                <p class="font-medium"><?php echo htmlspecialchars($booking['payment_method']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600">Payment Date</p>
                                <p class="font-medium"><?php echo date('F j, Y g:i A', strtotime($booking['payment_date'])); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600">Payment Status</p>
                                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Paid</span>
                            </div>
                        </div>
                        
                        <!-- Payment Summary -->
                        <div class="mt-6 border-t border-gray-200 pt-4">
                            <div class="flex justify-between items-center">
                                <p class="text-gray-600">Total Amount</p>
                                <p class="text-2xl font-bold">$<?php echo number_format($booking['amount'], 2); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex justify-end space-x-4">
                        <a href="my-bookings.php" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition">
                            Back to My Bookings
                        </a>
                        <button onclick="window.print()" class="bg-red-700 text-white px-6 py-2 rounded-lg hover:bg-red-800 transition">
                            Print Receipt
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>