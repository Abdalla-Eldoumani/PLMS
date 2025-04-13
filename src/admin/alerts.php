<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
$auth = new Auth();
if (!$auth->isAdmin()) {
    header("Location: ../login.php");
    exit();
}

$db = new Database();
$userId = $_SESSION['user_id'];

// Get admin's managed parking lots
$query = "SELECT pl.* FROM parking_lots pl
          JOIN admin_parking_lots apl ON pl.lot_id = apl.lot_id
          WHERE apl.admin_id = ?";
$lots = $db->query($query, [$userId])->fetchAll();

// Process alert status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $alertId = filter_input(INPUT_POST, 'alert_id', FILTER_VALIDATE_INT);
    
    if ($_POST['action'] === 'resolve') {
        $db->query(
            "UPDATE overstay_alerts SET status = 'Resolved', resolved_date = NOW() WHERE alert_id = ?",
            [$alertId]
        );
        // Redirect to prevent form resubmission
        header("Location: alerts.php");
        exit();
    } elseif ($_POST['action'] === 'send_reminder') {
        // In a real system, this would send an email or SMS
        $db->query(
            "UPDATE overstay_alerts SET notification_sent = 1, last_notification_date = NOW() WHERE alert_id = ?",
            [$alertId]
        );
        // Redirect to prevent form resubmission
        header("Location: alerts.php");
        exit();
    }
}

// Filter parameters
$status = filter_input(INPUT_GET, 'status') ?: 'all';
$lotId = filter_input(INPUT_GET, 'lot_id', FILTER_VALIDATE_INT) ?: 'all';

// Build query based on filters
$query = "SELECT oa.*, b.booking_id, b.start_time, b.end_time, b.status as booking_status,
          u.first_name, u.last_name, u.email, u.phone,
          v.license_plate, ps.slot_number, pl.name as lot_name
          FROM overstay_alerts oa
          JOIN bookings b ON oa.booking_id = b.booking_id
          JOIN users u ON b.user_id = u.user_id
          JOIN vehicles v ON b.vehicle_id = v.vehicle_id
          JOIN parking_slots ps ON b.slot_id = ps.slot_id
          JOIN parking_lots pl ON ps.lot_id = pl.lot_id
          JOIN admin_parking_lots apl ON pl.lot_id = apl.lot_id
          WHERE apl.admin_id = ?";

$params = [$userId];

if ($status !== 'all') {
    $query .= " AND oa.status = ?";
    $params[] = $status;
}

if ($lotId !== 'all') {
    $query .= " AND pl.lot_id = ?";
    $params[] = $lotId;
}

$query .= " ORDER BY CASE WHEN oa.status = 'Pending' THEN 0
                          WHEN oa.status = 'In Progress' THEN 1
                          WHEN oa.status = 'Paid' THEN 2
                          ELSE 3 END,
            oa.created_date DESC";

$alerts = $db->query($query, $params)->fetchAll();

// Get summary counts
$pendingCount = 0;
$resolvedCount = 0;
$paidCount = 0;
$totalFines = 0;
$collectedFines = 0;

foreach ($alerts as $alert) {
    if ($alert['status'] === 'Pending') {
        $pendingCount++;
    } elseif ($alert['status'] === 'Resolved') {
        $resolvedCount++;
    } elseif ($alert['status'] === 'Paid') {
        $paidCount++;
        $collectedFines += $alert['fine_amount'];
    }
    
    $totalFines += $alert['fine_amount'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerts & Violations - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include 'includes/admin-header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Alerts & Violations</h1>
        
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-gray-500 text-sm">Pending Alerts</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $pendingCount; ?></p>
                <p class="text-sm text-red-500 mt-1">Require attention</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-gray-500 text-sm">Resolved Alerts</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $resolvedCount; ?></p>
                <p class="text-sm text-green-500 mt-1">No action needed</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-gray-500 text-sm">Total Fines</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2">$<?php echo number_format($totalFines, 2); ?></p>
                <p class="text-sm text-gray-500 mt-1">From all violations</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-gray-500 text-sm">Collected Fines</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2">$<?php echo number_format($collectedFines, 2); ?></p>
                <p class="text-sm text-green-500 mt-1"><?php echo $totalFines > 0 ? round(($collectedFines / $totalFines) * 100) . '% collected' : '0% collected'; ?></p>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Filter Alerts</h2>
            <form method="GET" action="alerts.php" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" class="form-select rounded-md border-gray-300 shadow-sm mt-1 block w-full">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="In Progress" <?php echo $status === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="Resolved" <?php echo $status === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="Paid" <?php echo $status === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                    </select>
                </div>
                
                <div>
                    <label for="lot_id" class="block text-sm font-medium text-gray-700 mb-1">Parking Lot</label>
                    <select id="lot_id" name="lot_id" class="form-select rounded-md border-gray-300 shadow-sm mt-1 block w-full">
                        <option value="all">All Lots</option>
                        <?php foreach ($lots as $lot): ?>
                            <option value="<?php echo $lot['lot_id']; ?>" <?php echo $lotId == $lot['lot_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lot['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition w-full">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Alerts Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-800">Violation Alerts</h2>
            </div>
            
            <?php if (empty($alerts)): ?>
                <div class="p-6 text-center">
                    <p class="text-gray-500">No alerts found matching your criteria.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Overstay Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fine Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($alerts as $alert): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $alert['alert_id']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                        $statusClass = '';
                                        switch ($alert['status']) {
                                            case 'Pending':
                                                $statusClass = 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'In Progress':
                                                $statusClass = 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'Resolved':
                                                $statusClass = 'bg-green-100 text-green-800';
                                                break;
                                            case 'Paid':
                                                $statusClass = 'bg-purple-100 text-purple-800';
                                                break;
                                            default:
                                                $statusClass = 'bg-gray-100 text-gray-800';
                                        }
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($alert['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($alert['license_plate']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($alert['first_name'] . ' ' . $alert['last_name']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($alert['email']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($alert['lot_name']); ?></div>
                                        <div class="text-sm text-gray-500">Slot #<?php echo htmlspecialchars($alert['slot_number']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                        // Calculate overstay duration in minutes
                                        $endTime = new DateTime($alert['end_time']);
                                        $detectedTime = new DateTime($alert['detection_time']);
                                        $interval = $endTime->diff($detectedTime);
                                        $minutes = $interval->days * 24 * 60 + $interval->h * 60 + $interval->i;
                                        
                                        echo $minutes . ' minutes';
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">$<?php echo number_format($alert['fine_amount'], 2); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($alert['created_date'])); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo date('g:i A', strtotime($alert['created_date'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <?php if ($alert['status'] === 'Pending'): ?>
                                            <form method="POST" action="alerts.php" class="inline">
                                                <input type="hidden" name="alert_id" value="<?php echo $alert['alert_id']; ?>">
                                                <input type="hidden" name="action" value="resolve">
                                                <button type="submit" class="text-green-600 hover:text-green-900 mr-3">
                                                    Resolve
                                                </button>
                                            </form>
                                            <form method="POST" action="alerts.php" class="inline">
                                                <input type="hidden" name="alert_id" value="<?php echo $alert['alert_id']; ?>">
                                                <input type="hidden" name="action" value="send_reminder">
                                                <button type="submit" class="text-blue-600 hover:text-blue-900">
                                                    Send Reminder
                                                </button>
                                            </form>
                                        <?php elseif ($alert['status'] === 'In Progress'): ?>
                                            <form method="POST" action="alerts.php" class="inline">
                                                <input type="hidden" name="alert_id" value="<?php echo $alert['alert_id']; ?>">
                                                <input type="hidden" name="action" value="resolve">
                                                <button type="submit" class="text-green-600 hover:text-green-900 mr-3">
                                                    Resolve
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-gray-400">No actions</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 