<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if payment ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: payment-history.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$payment_id = intval($_GET['id']);
$db = new Database();

// Get payment details with booking and slot information
$payment = $db->query(
    "SELECT p.*, b.booking_id, b.customer_id, b.start_time, b.end_time, b.status as booking_status,
            ps.slot_number, ps.type as slot_type, ps.hourly_rate,
            pl.name as lot_name, pl.location as lot_location,
            u.name as customer_name, u.email as customer_email
     FROM payments p 
     JOIN bookings b ON p.booking_id = b.booking_id 
     JOIN parking_slots ps ON b.slot_id = ps.slot_id 
     JOIN parking_lots pl ON ps.lot_id = pl.lot_id 
     JOIN users u ON b.customer_id = u.user_id
     WHERE p.payment_id = ? AND b.customer_id = ?", 
    [$payment_id, $user_id]
)->fetch();

// If payment not found or doesn't belong to user, redirect
if (!$payment) {
    header("Location: payment-history.php");
    exit();
}

// Format the receipt number
$receipt_number = sprintf("UCPS-%08d", $payment_id);

// Calculate booking duration
$duration = calculateDuration($payment['start_time'], $payment['end_time']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        @media print {
            .no-print {
                display: none;
            }
            body {
                background-color: white;
                padding: 20px;
            }
            .receipt {
                box-shadow: none;
                border: 1px solid #eee;
            }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <!-- Navigation Bar (hidden in print) -->
    <div class="no-print">
        <?php include 'includes/header.php'; ?>
    </div>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-3xl mx-auto">
            <!-- Back button (hidden in print) -->
            <div class="mb-6 no-print">
                <a href="payment-history.php" class="inline-flex items-center text-blue-600 hover:text-blue-800">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Payment History
                </a>
            </div>
            
            <!-- Print button (hidden in print) -->
            <div class="flex justify-end mb-4 no-print">
                <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print Receipt
                </button>
            </div>
            
            <!-- Receipt -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden receipt">
                <!-- Receipt Header -->
                <div class="bg-red-700 text-white px-6 py-4 flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-bold">Payment Receipt</h2>
                        <p class="text-sm"><?php echo $receipt_number; ?></p>
                    </div>
                    <div class="text-right">
                        <img src="assets/images/ucalgary-logo-white.png" alt="UCalgary Logo" class="h-10">
                    </div>
                </div>
                
                <div class="p-6">
                    <!-- Receipt Date -->
                    <div class="text-right mb-6">
                        <p class="text-sm text-gray-600">Receipt Date:</p>
                        <p class="font-medium"><?php echo date('F d, Y', strtotime($payment['payment_date'])); ?></p>
                    </div>
                    
                    <!-- Customer & Parking Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <h3 class="text-lg font-semibold mb-3">Customer Information</h3>
                            <p class="mb-1"><span class="font-medium">Name:</span> <?php echo htmlspecialchars($payment['customer_name']); ?></p>
                            <p class="mb-1"><span class="font-medium">Email:</span> <?php echo htmlspecialchars($payment['customer_email']); ?></p>
                            <p><span class="font-medium">Booking ID:</span> <?php echo htmlspecialchars($payment['booking_id']); ?></p>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold mb-3">Parking Information</h3>
                            <p class="mb-1"><span class="font-medium">Location:</span> <?php echo htmlspecialchars($payment['lot_name']); ?></p>
                            <p class="mb-1"><span class="font-medium">Parking Slot:</span> <?php echo htmlspecialchars($payment['slot_number']); ?> (<?php echo htmlspecialchars($payment['slot_type']); ?>)</p>
                            <p class="mb-1">
                                <span class="font-medium">Duration:</span> 
                                <?php echo date('M d, Y h:i A', strtotime($payment['start_time'])); ?> - 
                                <?php echo date('M d, Y h:i A', strtotime($payment['end_time'])); ?>
                            </p>
                            <p><span class="font-medium">Total Hours:</span> <?php echo $duration['hours']; ?> hours <?php echo $duration['minutes']; ?> minutes</p>
                        </div>
                    </div>
                    
                    <!-- Payment Details -->
                    <div class="border-t border-gray-200 pt-6 mb-6">
                        <h3 class="text-lg font-semibold mb-3">Payment Details</h3>
                        
                        <div class="bg-gray-50 rounded-lg p-4 mb-4">
                            <div class="flex justify-between mb-2">
                                <span>Payment Date:</span>
                                <span><?php echo date('F d, Y h:i A', strtotime($payment['payment_date'])); ?></span>
                            </div>
                            
                            <div class="flex justify-between mb-2">
                                <span>Payment Method:</span>
                                <span><?php echo htmlspecialchars($payment['payment_method']); ?></span>
                            </div>
                            
                            <div class="flex justify-between mb-2">
                                <span>Description:</span>
                                <span><?php echo htmlspecialchars($payment['description'] ?? 'Parking fee'); ?></span>
                            </div>
                            
                            <div class="flex justify-between font-bold text-lg border-t border-gray-300 pt-2 mt-2">
                                <span>Total Amount:</span>
                                <span>$<?php echo number_format($payment['amount'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Terms and Notes -->
                    <div class="text-sm text-gray-600 border-t border-gray-200 pt-6">
                        <p class="mb-2">Thank you for using UCalgary Parking Services.</p>
                        <p class="mb-2">This receipt serves as proof of payment for your parking booking.</p>
                        <p>For questions or assistance, please contact parking services at plms@ucalgary.ca or call (403) 555-1234.</p>
                    </div>
                </div>
                
                <!-- Receipt Footer -->
                <div class="bg-gray-50 px-6 py-4 text-center text-sm text-gray-600 border-t border-gray-200">
                    <p>University of Calgary Parking Management System</p>
                    <p>2500 University Dr NW, Calgary, AB T2N 1N4</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer (hidden in print) -->
    <div class="no-print">
        <?php include 'includes/footer.php'; ?>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>