<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if booking ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: my-bookings.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$booking_id = intval($_GET['id']);
$db = new Database();

// Get booking details
$booking = $db->query(
    "SELECT b.*, ps.slot_number, ps.type as slot_type, ps.hourly_rate, 
            pl.name as lot_name, pl.location as lot_location 
     FROM bookings b 
     JOIN parking_slots ps ON b.slot_id = ps.slot_id 
     JOIN parking_lots pl ON ps.lot_id = pl.lot_id 
     WHERE b.booking_id = ? AND b.customer_id = ?", 
    [$booking_id, $user_id]
)->fetch();

// If booking not found or doesn't belong to user, redirect
if (!$booking) {
    header("Location: my-bookings.php");
    exit();
}

// Get payment information
$payment = $db->query(
    "SELECT * FROM payments WHERE booking_id = ? ORDER BY payment_date DESC LIMIT 1", 
    [$booking_id]
)->fetch();

// Get vehicle information
$vehicle = $db->query(
    "SELECT v.* FROM vehicles v 
     JOIN bookings b ON v.vehicle_id = b.vehicle_id
     WHERE b.booking_id = ? AND b.customer_id = ? LIMIT 1", 
    [$booking_id, $user_id]
)->fetch();

// Calculate booking duration and cost
$start_time = new DateTime($booking['start_time']);
$end_time = new DateTime($booking['end_time']);
$interval = $start_time->diff($end_time);
$hours = $interval->h + ($interval->days * 24);
$minutes = $interval->i;
$duration = $hours . ' hr ' . $minutes . ' min';

// Check if booking is active
$now = new DateTime();
$is_active = ($now >= $start_time && $now <= $end_time && $booking['status'] === 'Active');
$can_extend = $is_active && $booking['status'] === 'Active';
$can_cancel = $booking['status'] === 'Active' && $now < $start_time;

// Get any overstay alerts
$overstay = $db->query(
    "SELECT * FROM overstay_alerts WHERE booking_id = ?", 
    [$booking_id]
)->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Navigation Bar -->
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Back button -->
            <div class="mb-6">
                <a href="my-bookings.php" class="inline-flex items-center text-blue-600 hover:text-blue-800">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to My Bookings
                </a>
            </div>
            
            <!-- Booking Header -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="bg-gray-800 text-white px-6 py-4 flex justify-between items-center">
                    <h2 class="text-xl font-semibold">Booking #<?php echo $booking_id; ?></h2>
                    <span class="px-3 py-1 rounded-full text-sm font-semibold
                        <?php 
                        switch($booking['status']) {
                            case 'Active': echo $is_active ? 'bg-green-200 text-green-800' : 'bg-blue-200 text-blue-800'; break;
                            case 'Completed': echo 'bg-gray-200 text-gray-800'; break;
                            case 'Cancelled': echo 'bg-red-200 text-red-800'; break;
                            default: echo 'bg-gray-200 text-gray-800';
                        }
                        ?>">
                        <?php echo $is_active ? 'In Progress' : $booking['status']; ?>
                    </span>
                </div>
                
                <div class="p-6">
                    <?php if ($is_active): ?>
                        <div class="mb-6 bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4">
                            <div class="flex">
                                <div class="py-1">
                                    <svg class="h-6 w-6 text-blue-500 mr-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-bold">Booking in progress</p>
                                    <p class="text-sm">
                                        <?php 
                                        $timeLeft = $now->diff($end_time);
                                        $remaining_hours = $timeLeft->h + ($timeLeft->days * 24);
                                        echo 'Time remaining: ' . $remaining_hours . ' hr ' . $timeLeft->i . ' min';
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($can_cancel): ?>
                        <div class="mb-6 bg-yellow-50 border-l-4 border-yellow-500 text-yellow-700 p-4">
                            <div class="flex">
                                <div class="py-1">
                                    <svg class="h-6 w-6 text-yellow-500 mr-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-bold">Upcoming booking</p>
                                    <p class="text-sm">
                                        Starting in: 
                                        <?php 
                                        $timeToStart = $now->diff($start_time);
                                        echo $timeToStart->format('%d days %h hours %i minutes');
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($overstay): ?>
                        <div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-700 p-4">
                            <div class="flex">
                                <div class="py-1">
                                    <svg class="h-6 w-6 text-red-500 mr-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-bold">Overstay Alert</p>
                                    <p class="text-sm">You have exceeded your booked time and incurred a fine of $<?php echo number_format($overstay['fine_amount'], 2); ?></p>
                                    <a href="pay-fine.php?id=<?php echo $overstay['alert_id']; ?>" class="inline-block mt-2 bg-red-600 hover:bg-red-700 text-white text-sm py-1 px-3 rounded">
                                        Pay Fine
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Left Column - Booking Details -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Booking Details</h3>
                            
                            <div class="mb-4">
                                <p class="text-sm text-gray-600">Parking Location</p>
                                <p class="font-medium"><?php echo htmlspecialchars($booking['lot_name']); ?></p>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($booking['lot_location']); ?></p>
                            </div>
                            
                            <div class="mb-4">
                                <p class="text-sm text-gray-600">Parking Slot</p>
                                <p class="font-medium"><?php echo htmlspecialchars($booking['slot_number']); ?> (<?php echo htmlspecialchars($booking['slot_type']); ?>)</p>
                            </div>
                            
                            <div class="mb-4">
                                <p class="text-sm text-gray-600">Booked Time</p>
                                <p class="font-medium">
                                    <?php echo date('M d, Y', strtotime($booking['start_time'])); ?>
                                </p>
                                <p class="text-sm">
                                    <?php echo date('h:i A', strtotime($booking['start_time'])); ?> - 
                                    <?php echo date('h:i A', strtotime($booking['end_time'])); ?>
                                </p>
                            </div>
                            
                            <div class="mb-4">
                                <p class="text-sm text-gray-600">Duration</p>
                                <p class="font-medium"><?php echo $duration; ?></p>
                            </div>
                            
                            <div class="mb-4">
                                <p class="text-sm text-gray-600">Rate</p>
                                <p class="font-medium">$<?php echo number_format($booking['hourly_rate'], 2); ?> / hour</p>
                            </div>
                            
                            <?php if ($vehicle): ?>
                                <div class="mb-4">
                                    <p class="text-sm text-gray-600">Vehicle</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($vehicle['license_plate']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Right Column - Payment Information -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Payment Information</h3>
                            
                            <?php if ($payment): ?>
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                    <div class="mb-3">
                                        <p class="text-sm text-gray-600">Total Amount</p>
                                        <p class="text-xl font-semibold"><?php echo '$' . number_format($payment['amount'], 2); ?></p>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <p class="text-sm text-gray-600">Payment Method</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($payment['payment_method']); ?></p>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <p class="text-sm text-gray-600">Payment Date</p>
                                        <p class="font-medium"><?php echo date('M d, Y h:i A', strtotime($payment['payment_date'])); ?></p>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <p class="text-sm text-gray-600">Payment Status</p>
                                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Paid</span>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <a href="payment-receipt.php?booking_id=<?php echo $booking['booking_id']; ?>" class="text-blue-600 hover:text-blue-800 flex items-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            View Receipt
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200 text-center">
                                    <p class="text-yellow-700">No payment information found for this booking.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="mt-8 border-t border-gray-200 pt-6 flex justify-between">
                        <div>
                            <?php if ($can_cancel): ?>
                                <a href="my-bookings.php?action=cancel&id=<?php echo $booking_id; ?>&token=<?php echo generateCSRFToken(); ?>" 
                                   class="bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded mr-2 cancel-booking">
                                    Cancel Booking
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <?php if ($can_extend): ?>
                                <a href="extend-booking.php?id=<?php echo $booking_id; ?>" 
                                   class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded">
                                    Extend Booking
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script>
        // Confirmation for booking cancellation
        document.addEventListener('DOMContentLoaded', function() {
            const cancelButtons = document.querySelectorAll('.cancel-booking');
            cancelButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to cancel this booking? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>

    <script src="assets/js/main.js"></script>
</body>
</html>