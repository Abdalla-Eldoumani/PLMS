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
$dateRange = filter_input(INPUT_GET, 'date_range', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$sortBy = filter_input(INPUT_GET, 'sort_by', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'payment_date';
$sortOrder = filter_input(INPUT_GET, 'sort_order', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'DESC';

// Build the query
$query = "SELECT p.*, b.start_time, b.end_time, ps.slot_number, pl.name as lot_name, pl.location
          FROM payments p
          JOIN bookings b ON p.booking_id = b.booking_id
          JOIN parking_slots ps ON b.slot_id = ps.slot_id
          JOIN parking_lots pl ON ps.lot_id = pl.lot_id
          WHERE b.customer_id = ?";
$params = [$_SESSION['user_id']];

// Add date range filter
if ($dateRange) {
    switch ($dateRange) {
        case 'today':
            $query .= " AND DATE(p.payment_date) = CURDATE()";
            break;
        case 'week':
            $query .= " AND p.payment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $query .= " AND p.payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case 'year':
            $query .= " AND p.payment_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
    }
}

// Add sorting
$allowedSortFields = ['payment_date', 'amount'];
$sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'payment_date';
$sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
$query .= " ORDER BY p.$sortBy $sortOrder";

// Get payments
$payments = $db->query($query, $params)->fetchAll();

// Calculate statistics
$totalPayments = count($payments);
$totalAmount = array_sum(array_map(fn($p) => $p['amount'], $payments));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="bg-gray-100">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-6xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-800 mb-8">Payment History</h1>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">Total Payments</h3>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $totalPayments; ?></p>
                </div>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">Total Amount</h3>
                    <p class="text-3xl font-bold text-gray-800">$<?php echo number_format($totalAmount, 2); ?></p>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="date_range" class="block text-sm font-medium text-gray-700">Date Range</label>
                        <select id="date_range" name="date_range" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All Time</option>
                            <option value="today" <?php echo $dateRange === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $dateRange === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="month" <?php echo $dateRange === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="year" <?php echo $dateRange === 'year' ? 'selected' : ''; ?>>Last Year</option>
                        </select>
                    </div>
                    <div>
                        <label for="sort_by" class="block text-sm font-medium text-gray-700">Sort By</label>
                        <select id="sort_by" name="sort_by" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="payment_date" <?php echo $sortBy === 'payment_date' ? 'selected' : ''; ?>>Date</option>
                            <option value="amount" <?php echo $sortBy === 'amount' ? 'selected' : ''; ?>>Amount</option>
                        </select>
                    </div>
                    <div>
                        <label for="sort_order" class="block text-sm font-medium text-gray-700">Order</label>
                        <select id="sort_order" name="sort_order" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Oldest First</option>
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Payments Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slot</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    No payments found matching your criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y g:i A', strtotime($payment['payment_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        $<?php echo number_format($payment['amount'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($payment['lot_name']); ?><br>
                                        <span class="text-xs"><?php echo htmlspecialchars($payment['location']); ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($payment['slot_number']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($payment['payment_method']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <a href="payment-receipt.php?booking_id=<?php echo $payment['booking_id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900">View Receipt</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>