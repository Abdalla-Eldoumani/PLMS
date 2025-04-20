<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$db = new Database();

// Get user details
$user = $db->query("SELECT u.*, c.subscription_type, c.license_plate 
                   FROM users u 
                   JOIN customers c ON u.user_id = c.user_id 
                   WHERE u.user_id = ?", [$user_id])->fetch();

// Get active bookings
$activeBookings = $db->query("SELECT b.*, ps.slot_number, pl.name AS lot_name, ps.type AS slot_type 
                             FROM bookings b 
                             JOIN parking_slots ps ON b.slot_id = ps.slot_id 
                             JOIN parking_lots pl ON ps.lot_id = pl.lot_id 
                             WHERE b.customer_id = ? AND b.status = 'Active' 
                             ORDER BY b.start_time DESC", [$user_id])->fetchAll();

// Get past bookings
$pastBookings = $db->query("SELECT b.*, ps.slot_number, pl.name AS lot_name, ps.type AS slot_type 
                           FROM bookings b 
                           JOIN parking_slots ps ON b.slot_id = ps.slot_id 
                           JOIN parking_lots pl ON ps.lot_id = pl.lot_id 
                           WHERE b.customer_id = ? AND b.status != 'Active' 
                           ORDER BY b.start_time DESC 
                           LIMIT 5", [$user_id])->fetchAll();

// Get vehicles
$vehicles = $db->query("SELECT * FROM vehicles WHERE owner_id = ?", [$user_id])->fetchAll();

// Get payment history
$payments = $db->query("SELECT p.*, b.start_time, b.end_time 
                       FROM payments p 
                       JOIN bookings b ON p.booking_id = b.booking_id 
                       WHERE b.customer_id = ? 
                       ORDER BY p.payment_date DESC 
                       LIMIT 5", [$user_id])->fetchAll();

// Get overstay alerts
$alerts = $db->query("SELECT * FROM overstay_alerts 
                     WHERE customer_id = ? AND status = 'Pending'", [$user_id])->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 font-sans">
    <!-- Navigation Bar -->
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-red-800 to-red-600 rounded-lg shadow-lg p-6 mb-8 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Welcome, <?php echo htmlspecialchars($user['name']); ?></h1>
                    <p class="mt-1">Subscription: <?php echo htmlspecialchars($user['subscription_type']); ?></p>
                </div>
                <div class="hidden md:block">
                    <a href="find-parking.php" class="bg-white text-red-700 px-6 py-2 rounded-lg font-bold hover:bg-gray-100 transition">Find Parking</a>
                </div>
            </div>
        </div>

        <!-- Alerts Section (if any) -->
        <?php if (count($alerts) > 0): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-8" role="alert">
            <div class="flex">
                <div class="py-1">
                    <svg class="h-6 w-6 text-yellow-500 mr-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <p class="font-bold">Attention</p>
                    <p class="text-sm">You have <?php echo count($alerts); ?> outstanding overstay alert(s). Please view your alerts and settle any fines.</p>
                    <a href="#alerts" class="text-sm font-semibold underline">View Alerts</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Dashboard Sections -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Left Column -->
            <div class="md:col-span-2">
                <!-- Active Bookings -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                    <div class="bg-gray-800 text-white px-6 py-4 flex justify-between items-center">
                        <h2 class="text-xl font-semibold">Active Bookings</h2>
                        <a href="booking.php" class="text-sm bg-red-600 hover:bg-red-700 text-white py-1 px-3 rounded">New Booking</a>
                    </div>
                    <div class="p-6">
                        <?php if (count($activeBookings) > 0): ?>
                            <?php foreach ($activeBookings as $booking): ?>
                                <div class="border-b border-gray-200 pb-4 mb-4 last:border-0 last:pb-0 last:mb-0">
                                    <div class="flex flex-wrap justify-between">
                                        <div>
                                            <h3 class="font-semibold text-lg"><?php echo htmlspecialchars($booking['lot_name']); ?> - Slot <?php echo htmlspecialchars($booking['slot_number']); ?></h3>
                                            <p class="text-gray-600"><?php echo htmlspecialchars($booking['slot_type']); ?> Parking</p>
                                            <div class="mt-2 text-sm">
                                                <p>
                                                    <span class="font-semibold">Start:</span> 
                                                    <?php echo date('M d, Y h:i A', strtotime($booking['start_time'])); ?>
                                                </p>
                                                <p>
                                                    <span class="font-semibold">End:</span> 
                                                    <?php echo date('M d, Y h:i A', strtotime($booking['end_time'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-start space-x-2 mt-2 md:mt-0">
                                            <a href="booking-details.php?id=<?php echo $booking['booking_id']; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-800 py-1 px-3 rounded text-sm">Details</a>
                                            <a href="extend-booking.php?id=<?php echo $booking['booking_id']; ?>" class="bg-blue-600 hover:bg-blue-700 text-white py-1 px-3 rounded text-sm">Extend</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-6">
                                <p class="text-gray-500 mb-4">You don't have any active bookings.</p>
                                <a href="find-parking.php" class="bg-red-700 hover:bg-red-800 text-white px-4 py-2 rounded">Find Parking Now</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Past Bookings -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                    <div class="bg-gray-800 text-white px-6 py-4">
                        <h2 class="text-xl font-semibold">Past Bookings</h2>
                    </div>
                    <div class="p-6">
                        <?php if (count($pastBookings) > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($pastBookings as $booking): ?>
                                            <tr>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($booking['lot_name']); ?></div>
                                                    <div class="text-sm text-gray-500">Slot <?php echo htmlspecialchars($booking['slot_number']); ?></div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($booking['start_time'])); ?></div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">
                                                        <?php 
                                                        $start = new DateTime($booking['start_time']);
                                                        $end = new DateTime($booking['end_time']);
                                                        $diff = $start->diff($end);
                                                        echo $diff->format('%h hrs %i mins');
                                                        ?>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                        <?php echo $booking['status'] === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                        <?php echo htmlspecialchars($booking['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                                    <a href="booking-details.php?id=<?php echo $booking['booking_id']; ?>" class="text-blue-600 hover:text-blue-900">Details</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-4 text-right">
                                <a href="my-bookings.php" class="text-sm text-red-700 hover:underline">View All Bookings</a>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4">No past bookings found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- User Information -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                    <div class="bg-gray-800 text-white px-6 py-4">
                        <h2 class="text-xl font-semibold">Account Information</h2>
                    </div>
                    <div class="p-6">
                        <div class="mb-4">
                            <p class="text-sm text-gray-600">Name</p>
                            <p class="font-medium"><?php echo htmlspecialchars($user['name']); ?></p>
                        </div>
                        <div class="mb-4">
                            <p class="text-sm text-gray-600">Email</p>
                            <p class="font-medium"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                        <div class="mb-4">
                            <p class="text-sm text-gray-600">Phone</p>
                            <p class="font-medium"><?php echo htmlspecialchars($user['phone']); ?></p>
                        </div>
                        <div class="mt-4">
                            <a href="edit-profile.php" class="text-sm text-red-700 hover:underline">Edit Profile</a>
                        </div>
                    </div>
                </div>

                <!-- Vehicles -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                    <div class="bg-gray-800 text-white px-6 py-4 flex justify-between items-center">
                        <h2 class="text-xl font-semibold">My Vehicles</h2>
                        <a href="add-vehicle.php" class="text-sm bg-red-600 hover:bg-red-700 text-white py-1 px-3 rounded">Add New</a>
                    </div>
                    <div class="p-6">
                        <?php if (count($vehicles) > 0): ?>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <div class="border-b border-gray-200 pb-4 mb-4 last:border-0 last:pb-0 last:mb-0">
                                    <div class="flex justify-between">
                                        <div>
                                            <h3 class="font-semibold"><?php echo htmlspecialchars($vehicle['license_plate']); ?></h3>
                                            <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></p>
                                        </div>
                                        <div>
                                            <a href="edit-vehicle.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="text-blue-600 hover:text-blue-900 text-sm">Edit</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4">No vehicles registered.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Payment History -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-gray-800 text-white px-6 py-4">
                        <h2 class="text-xl font-semibold">Recent Payments</h2>
                    </div>
                    <div class="p-6">
                        <?php if (count($payments) > 0): ?>
                            <?php foreach ($payments as $payment): ?>
                                <div class="border-b border-gray-200 pb-4 mb-4 last:border-0 last:pb-0 last:mb-0">
                                    <div class="flex justify-between">
                                        <div>
                                            <p class="font-semibold">$<?php echo number_format($payment['amount'], 2); ?></p>
                                            <p class="text-gray-600 text-sm"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></p>
                                            <p class="text-gray-500 text-xs"><?php echo htmlspecialchars($payment['payment_method']); ?></p>
                                        </div>
                                        <div>
                                            <a href="payment-receipt.php?booking_id=<?php echo $payment['booking_id']; ?>" class="text-blue-600 hover:text-blue-900 text-sm">Receipt</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="mt-4 text-right">
                                <a href="payment-history.php" class="text-sm text-red-700 hover:underline">View All Payments</a>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4">No payment history available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overstay Alerts Section (if any) -->
        <?php if (count($alerts) > 0): ?>
        <div id="alerts" class="bg-white rounded-lg shadow-md overflow-hidden mt-8">
            <div class="bg-yellow-600 text-white px-6 py-4">
                <h2 class="text-xl font-semibold">Overstay Alerts</h2>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booking Date</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fine Amount</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($alerts as $alert): ?>
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?php 
                                        $booking = $db->query("SELECT start_time FROM bookings WHERE booking_id = ?", 
                                                            [$alert['booking_id']])->fetch();
                                        echo date('M d, Y', strtotime($booking['start_time']));
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">$<?php echo number_format($alert['fine_amount'], 2); ?></div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            <?php echo htmlspecialchars($alert['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                        <a href="pay-fine.php?id=<?php echo $alert['alert_id']; ?>" class="bg-red-600 hover:bg-red-700 text-white py-1 px-3 rounded text-xs">Pay Fine</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
</body>
</html>