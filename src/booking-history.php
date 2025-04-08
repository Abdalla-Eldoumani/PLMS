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

// Get filter parameters
$status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING);
$dateRange = filter_input(INPUT_GET, 'date_range', FILTER_SANITIZE_STRING);
$sortBy = filter_input(INPUT_GET, 'sort_by', FILTER_SANITIZE_STRING) ?? 'start_time';
$sortOrder = filter_input(INPUT_GET, 'sort_order', FILTER_SANITIZE_STRING) ?? 'DESC';

// Build the query
$query = "SELECT b.*, ps.slot_number, pl.name as lot_name, pl.location, ps.hourly_rate,
                 p.amount, p.payment_method, p.status as payment_status,
                 v.license_plate, v.vehicle_type
          FROM bookings b
          JOIN parking_slots ps ON b.slot_id = ps.slot_id
          JOIN parking_lots pl ON ps.lot_id = pl.lot_id
          LEFT JOIN payments p ON b.booking_id = p.booking_id
          LEFT JOIN vehicles v ON b.vehicle_id = v.vehicle_id
          WHERE b.customer_id = ?";
$params = [$_SESSION['user_id']];

// Add status filter
if ($status && $status !== 'all') {
    $query .= " AND b.status = ?";
    $params[] = $status;
}

// Add date range filter
if ($dateRange) {
    switch ($dateRange) {
        case 'upcoming':
            $query .= " AND b.start_time > NOW()";
            break;
        case 'past':
            $query .= " AND b.end_time < NOW()";
            break;
        case 'current':
            $query .= " AND b.start_time <= NOW() AND b.end_time >= NOW()";
            break;
        case 'today':
            $query .= " AND DATE(b.start_time) = CURDATE()";
            break;
        case 'week':
            $query .= " AND b.start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $query .= " AND b.start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

// Add sorting
$allowedSortFields = ['start_time', 'end_time', 'amount', 'status'];
$sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'start_time';
$sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
$query .= " ORDER BY b.$sortBy $sortOrder";

// Get bookings
$bookings = $db->query($query, $params)->fetchAll();

// Calculate statistics
$totalBookings = count($bookings);
$activeBookings = array_filter($bookings, fn($b) => $b['status'] === 'Active');
$completedBookings = array_filter($bookings, fn($b) => $b['status'] === 'Completed');
$cancelledBookings = array_filter($bookings, fn($b) => $b['status'] === 'Cancelled');
$totalSpent = array_sum(array_map(fn($b) => $b['amount'] ?? 0, $bookings));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking History - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="bg-gray-100">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Booking History</h1>
                <a href="find-parking.php" class="bg-red-700 text-white px-6 py-2 rounded-lg hover:bg-red-800 transition">
                    Book New Parking
                </a>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">Total Bookings</h3>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $totalBookings; ?></p>
                </div>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">Active Bookings</h3>
                    <p class="text-3xl font-bold text-green-600"><?php echo count($activeBookings); ?></p>
                </div>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">Completed Bookings</h3>
                    <p class="text-3xl font-bold text-blue-600"><?php echo count($completedBookings); ?></p>
                </div>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">Total Spent</h3>
                    <p class="text-3xl font-bold text-gray-800">$<?php echo number_format($totalSpent, 2); ?></p>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full rounded-lg border-gray-300 focus:border-red-500 focus:ring-red-500">
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Active" <?php echo $status === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Completed" <?php echo $status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled" <?php echo $status === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                        <select name="date_range" class="w-full rounded-lg border-gray-300 focus:border-red-500 focus:ring-red-500">
                            <option value="" <?php echo !$dateRange ? 'selected' : ''; ?>>All Time</option>
                            <option value="upcoming" <?php echo $dateRange === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                            <option value="current" <?php echo $dateRange === 'current' ? 'selected' : ''; ?>>Current</option>
                            <option value="past" <?php echo $dateRange === 'past' ? 'selected' : ''; ?>>Past</option>
                            <option value="today" <?php echo $dateRange === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $dateRange === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="month" <?php echo $dateRange === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                        <select name="sort_by" class="w-full rounded-lg border-gray-300 focus:border-red-500 focus:ring-red-500">
                            <option value="start_time" <?php echo $sortBy === 'start_time' ? 'selected' : ''; ?>>Start Time</option>
                            <option value="end_time" <?php echo $sortBy === 'end_time' ? 'selected' : ''; ?>>End Time</option>
                            <option value="amount" <?php echo $sortBy === 'amount' ? 'selected' : ''; ?>>Amount</option>
                            <option value="status" <?php echo $sortBy === 'status' ? 'selected' : ''; ?>>Status</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                        <select name="sort_order" class="w-full rounded-lg border-gray-300 focus:border-red-500 focus:ring-red-500">
                            <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Oldest First</option>
                        </select>
                    </div>
                    <div class="md:col-span-4 flex justify-end">
                        <button type="submit" class="bg-red-700 text-white px-6 py-2 rounded-lg hover:bg-red-800 transition">
                            Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Bookings Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booking ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($bookings)): ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                        No bookings found matching your criteria.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            #<?php echo str_pad($booking['booking_id'], 8, '0', STR_PAD_LEFT); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($booking['lot_name']); ?><br>
                                            <span class="text-xs"><?php echo htmlspecialchars($booking['location']); ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($booking['license_plate']); ?><br>
                                            <span class="text-xs"><?php echo htmlspecialchars($booking['vehicle_type']); ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M j, Y g:i A', strtotime($booking['start_time'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M j, Y g:i A', strtotime($booking['end_time'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            $<?php echo number_format($booking['amount'] ?? 0, 2); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php
                                                switch ($booking['status']) {
                                                    case 'Pending':
                                                        echo 'bg-yellow-100 text-yellow-800';
                                                        break;
                                                    case 'Active':
                                                        echo 'bg-green-100 text-green-800';
                                                        break;
                                                    case 'Completed':
                                                        echo 'bg-blue-100 text-blue-800';
                                                        break;
                                                    case 'Cancelled':
                                                        echo 'bg-red-100 text-red-800';
                                                        break;
                                                }
                                                ?>">
                                                <?php echo htmlspecialchars($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div class="flex space-x-2">
                                                <a href="booking-details.php?booking_id=<?php echo $booking['booking_id']; ?>" 
                                                   class="text-blue-600 hover:text-blue-900">View</a>
                                                <?php if ($booking['status'] === 'Active'): ?>
                                                    <a href="extend-booking.php?booking_id=<?php echo $booking['booking_id']; ?>" 
                                                       class="text-green-600 hover:text-green-900">Extend</a>
                                                    <a href="cancel-booking.php?booking_id=<?php echo $booking['booking_id']; ?>" 
                                                       class="text-red-600 hover:text-red-900">Cancel</a>
                                                <?php endif; ?>
                                                <?php if ($booking['payment_status'] === 'Completed'): ?>
                                                    <a href="payment-receipt.php?booking_id=<?php echo $booking['booking_id']; ?>" 
                                                       class="text-purple-600 hover:text-purple-900">Receipt</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize any JavaScript functionality here
        document.addEventListener('DOMContentLoaded', function() {
            // Add any additional JavaScript functionality as needed
        });
    </script>
</body>
</html> 