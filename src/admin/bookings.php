<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Ensure admin access
$auth = new Auth();
if (!$auth->isAdmin()) {
    header("Location: ../login.php");
    exit();
}

$db = new Database();
$userId = $_SESSION['user_id'];

// Filter values from GET
$statusFilter = $_GET['status'] ?? '';
$lotFilter = $_GET['lot'] ?? '';

// Bonus logic: Auto-apply active filter if coming from dashboard
if (isset($_GET['filter']) && $_GET['filter'] === 'active' && $statusFilter === '') {
    $statusFilter = 'Active';
}

$lotFilter = $_GET['lot'] ?? '';

// Base query with filter logic
$query = "SELECT b.*, 
                 u.name AS customer_name, 
                 u.user_id AS customer_id,
                 v.license_plate, 
                 ps.slot_number, 
                 ps.type AS slot_type, 
                 pl.name AS lot_name
          FROM bookings b
          JOIN users u ON b.customer_id = u.user_id
          JOIN vehicles v ON b.vehicle_id = v.vehicle_id
          JOIN parking_slots ps ON b.slot_id = ps.slot_id
          JOIN parking_lots pl ON ps.lot_id = pl.lot_id
          JOIN admin_parking_lots apl ON pl.lot_id = apl.lot_id
          WHERE apl.admin_id = ?";

$params = [$userId];

if ($statusFilter !== '') {
    $query .= " AND b.status = ?";
    $params[] = $statusFilter;
}

if ($lotFilter !== '') {
    $query .= " AND pl.name = ?";
    $params[] = $lotFilter;
}

$query .= " ORDER BY b.start_time DESC";
$bookings = $db->query($query, $params)->fetchAll();

// Get lots for filter dropdown
$lots = $db->query("SELECT DISTINCT pl.name FROM parking_lots pl
                    JOIN admin_parking_lots apl ON pl.lot_id = apl.lot_id
                    WHERE apl.admin_id = ?", [$userId])->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Bookings - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include 'includes/admin-header.php'; ?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">All Bookings</h1>

    <!-- Filters -->
    <form method="GET" class="mb-6 flex flex-wrap gap-4 items-end">
        <div>
            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
            <select name="status" id="status" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm">
                <option value="">All</option>
                <option value="Active" <?php if ($statusFilter === 'Active') echo 'selected'; ?>>Active</option>
                <option value="Completed" <?php if ($statusFilter === 'Completed') echo 'selected'; ?>>Completed</option>
                <option value="Cancelled" <?php if ($statusFilter === 'Cancelled') echo 'selected'; ?>>Cancelled</option>
            </select>
        </div>
        <div>
            <label for="lot" class="block text-sm font-medium text-gray-700">Parking Lot</label>
            <select name="lot" id="lot" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm">
                <option value="">All</option>
                <?php foreach ($lots as $lot): ?>
                    <option value="<?php echo $lot['name']; ?>" <?php if ($lotFilter === $lot['name']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($lot['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 mt-5">
                Filter
            </button>
        </div>
    </form>

    <div class="bg-white rounded-lg shadow-md overflow-x-auto">
        <?php if (count($bookings) === 0): ?>
            <div class="p-6 text-center text-gray-500">No bookings found.</div>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Booking ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vehicle</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lot & Slot</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $booking['booking_id']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $booking['customer_id']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($booking['license_plate']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php echo htmlspecialchars($booking['lot_name']); ?> - Slot <?php echo htmlspecialchars($booking['slot_number']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php echo date('M d, Y g:i A', strtotime($booking['start_time'])); ?> - 
                                <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $booking['status'] === 'Active' ? 'bg-green-100 text-green-800' : ($booking['status'] === 'Completed' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800'); ?>">
                                    <?php echo htmlspecialchars($booking['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
