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

// Get admin details
$admin = $db->query("SELECT u.*, a.role FROM users u 
                     JOIN admins a ON u.user_id = a.user_id 
                     WHERE u.user_id = ?", [$userId])->fetch();

// Get managed parking lots
$lots = $db->query("SELECT pl.* FROM parking_lots pl
                    JOIN admin_parking_lots apl ON pl.lot_id = apl.lot_id
                    WHERE apl.admin_id = ?", [$userId])->fetchAll();

// Get current occupancy statistics for managed lots
$lotStats = [];
foreach ($lots as $lot) {
    $stats = $db->query("SELECT 
                         SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available,
                         SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occupied,
                         SUM(CASE WHEN status = 'Reserved' THEN 1 ELSE 0 END) as reserved,
                         COUNT(*) as total
                         FROM parking_slots
                         WHERE lot_id = ?", [$lot['lot_id']])->fetch();
    
    $lotStats[$lot['lot_id']] = $stats;
}

// Get recent bookings across all managed lots
$recentBookings = $db->query("SELECT b.*, ps.slot_number, pl.name as lot_name, 
                              u.name as customer_name, u.email as customer_email
                              FROM bookings b 
                              JOIN parking_slots ps ON b.slot_id = ps.slot_id
                              JOIN parking_lots pl ON ps.lot_id = pl.lot_id
                              JOIN users u ON b.customer_id = u.user_id
                              JOIN admin_parking_lots apl ON pl.lot_id = apl.lot_id
                              WHERE apl.admin_id = ?
                              ORDER BY b.start_time DESC
                              LIMIT 10", [$userId])->fetchAll();

// Get revenue statistics
$revenue = $db->query("SELECT pl.name as lot_name, 
                       SUM(p.amount) as total_revenue,
                       COUNT(DISTINCT b.booking_id) as booking_count
                       FROM payments p
                       JOIN bookings b ON p.booking_id = b.booking_id
                       JOIN parking_slots ps ON b.slot_id = ps.slot_id
                       JOIN parking_lots pl ON ps.lot_id = pl.lot_id
                       JOIN admin_parking_lots apl ON pl.lot_id = apl.lot_id
                       WHERE apl.admin_id = ?
                       GROUP BY pl.lot_id", [$userId])->fetchAll();

// Get recent overstay alerts
$overstayAlerts = $db->query("SELECT oa.*, b.start_time, b.end_time, 
                             u.name as customer_name, ps.slot_number, pl.name as lot_name
                             FROM overstay_alerts oa
                             JOIN bookings b ON oa.booking_id = b.booking_id
                             JOIN users u ON oa.customer_id = u.user_id
                             JOIN parking_slots ps ON b.slot_id = ps.slot_id
                             JOIN parking_lots pl ON ps.lot_id = pl.lot_id
                             JOIN admin_parking_lots apl ON pl.lot_id = apl.lot_id
                             WHERE apl.admin_id = ? AND oa.status = 'Pending'
                             ORDER BY b.end_time DESC
                             LIMIT 5", [$userId])->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <?php include 'includes/admin-header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Admin Dashboard</h1>
            <div class="text-right">
                <div class="text-gray-600">Welcome, <?php echo htmlspecialchars($admin['name']); ?></div>
                <div class="text-sm text-gray-500"><?php echo ucfirst(htmlspecialchars($admin['role'])); ?> Admin</div>
            </div>
        </div>
        
        <!-- Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Parking Lots -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-gray-500 text-sm">Managed Lots</h3>
                        <p class="text-3xl font-bold text-gray-800"><?php echo count($lots); ?></p>
                    </div>
                    <div class="p-3 rounded-full bg-blue-100 text-blue-800">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                </div>
                <a href="manage-lots.php" class="text-sm text-blue-600 hover:underline mt-4 inline-block">Manage Lots</a>
            </div>
            
            <!-- Available Slots -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-gray-500 text-sm">Available Slots</h3>
                        <?php 
                        $totalAvailable = array_sum(array_column($lotStats, 'available'));
                        ?>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $totalAvailable; ?></p>
                    </div>
                    <div class="p-3 rounded-full bg-green-100 text-green-800">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <a href="manage-slots.php" class="text-sm text-blue-600 hover:underline mt-4 inline-block">Manage Slots</a>
            </div>
            
            <!-- Occupied Slots -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-gray-500 text-sm">Occupied Slots</h3>
                        <?php 
                        $totalOccupied = array_sum(array_column($lotStats, 'occupied'));
                        ?>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $totalOccupied; ?></p>
                    </div>
                    <div class="p-3 rounded-full bg-red-100 text-red-800">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <a href="bookings.php?filter=active" class="text-sm text-blue-600 hover:underline mt-4 inline-block">View Active Bookings</a>
            </div>
            
            <!-- Alerts -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-gray-500 text-sm">Overstay Alerts</h3>
                        <p class="text-3xl font-bold text-gray-800"><?php echo count($overstayAlerts); ?></p>
                    </div>
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-800">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                </div>
                <a href="alerts.php" class="text-sm text-blue-600 hover:underline mt-4 inline-block">View Alerts</a>
            </div>
        </div>
        
        <!-- Lot Occupancy Charts -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Parking Lot Occupancy</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($lots as $lot): ?>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-700 mb-2"><?php echo htmlspecialchars($lot['name']); ?></h3>
                        <div class="relative h-40">
                            <canvas id="chart-lot-<?php echo $lot['lot_id']; ?>"></canvas>
                        </div>
                        <div class="mt-2 text-center text-sm text-gray-600">
                            <span class="inline-block px-2 py-1 bg-green-100 text-green-800 rounded mr-2">
                                <?php echo $lotStats[$lot['lot_id']]['available']; ?> Available
                            </span>
                            <span class="inline-block px-2 py-1 bg-red-100 text-red-800 rounded mr-2">
                                <?php echo $lotStats[$lot['lot_id']]['occupied']; ?> Occupied
                            </span>
                            <span class="inline-block px-2 py-1 bg-blue-100 text-blue-800 rounded">
                                <?php echo $lotStats[$lot['lot_id']]['reserved']; ?> Reserved
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Recent Bookings -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800">Recent Bookings</h2>
                    <a href="bookings.php" class="text-sm text-blue-600 hover:underline">View All</a>
                </div>
                
                <?php if (count($recentBookings) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                    <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slot</th>
                                    <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                    <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($recentBookings as $booking): ?>
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($booking['customer_name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($booking['customer_email']); ?></div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($booking['lot_name']); ?></div>
                                            <div class="text-sm text-gray-500">Slot <?php echo htmlspecialchars($booking['slot_number']); ?></div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($booking['start_time'])); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?></div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php echo $booking['status'] === 'Active' ? 'bg-green-100 text-green-800' : ($booking['status'] === 'Completed' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800'); ?>">
                                                <?php echo htmlspecialchars($booking['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-center py-4">No recent bookings found.</p>
                <?php endif; ?>
            </div>
            
            <!-- Overstay Alerts -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800">Overstay Alerts</h2>
                    <a href="alerts.php" class="text-sm text-blue-600 hover:underline">View All</a>
                </div>
                
                <?php if (count($overstayAlerts) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                    <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                    <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>
                                    <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fine</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($overstayAlerts as $alert): ?>
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($alert['customer_name']); ?></div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($alert['lot_name']); ?></div>
                                            <div class="text-sm text-gray-500">Slot <?php echo htmlspecialchars($alert['slot_number']); ?></div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($alert['end_time'])); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo date('g:i A', strtotime($alert['end_time'])); ?></div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="text-sm font-medium text-red-600">$<?php echo number_format($alert['fine_amount'], 2); ?></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-center py-4">No overstay alerts at the moment.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Revenue Statistics -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">Revenue Overview</h2>
                <a href="reports.php" class="text-sm text-blue-600 hover:underline">Detailed Reports</a>
            </div>
            
            <?php if (count($revenue) > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="col-span-2">
                        <canvas id="revenue-chart" height="300"></canvas>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-700 mb-4">Revenue Summary</h3>
                        <div class="space-y-4">
                            <?php foreach ($revenue as $rev): ?>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <div class="flex justify-between items-center">
                                        <div class="text-gray-700 font-medium"><?php echo htmlspecialchars($rev['lot_name']); ?></div>
                                        <div class="text-green-600 font-semibold">$<?php echo number_format($rev['total_revenue'], 2); ?></div>
                                    </div>
                                    <div class="mt-2 text-sm text-gray-500">
                                        <span><?php echo $rev['booking_count']; ?> bookings</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">No revenue data available.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Lot occupancy charts
            <?php foreach ($lots as $lot): ?>
            new Chart(document.getElementById('chart-lot-<?php echo $lot['lot_id']; ?>'), {
                type: 'pie',
                data: {
                    labels: ['Available', 'Occupied', 'Reserved'],
                    datasets: [{
                        data: [
                            <?php echo $lotStats[$lot['lot_id']]['available']; ?>,
                            <?php echo $lotStats[$lot['lot_id']]['occupied']; ?>,
                            <?php echo $lotStats[$lot['lot_id']]['reserved']; ?>
                        ],
                        backgroundColor: ['#10B981', '#EF4444', '#3B82F6']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            <?php endforeach; ?>
            
            // Revenue chart
            <?php if (count($revenue) > 0): ?>
            new Chart(document.getElementById('revenue-chart'), {
                type: 'bar',
                data: {
                    labels: [<?php echo implode(', ', array_map(function($rev) { return "'" . addslashes($rev['lot_name']) . "'"; }, $revenue)); ?>],
                    datasets: [{
                        label: 'Revenue ($)',
                        data: [<?php echo implode(', ', array_column($revenue, 'total_revenue')); ?>],
                        backgroundColor: '#3B82F6'
                    }, {
                        label: 'Bookings',
                        data: [<?php echo implode(', ', array_column($revenue, 'booking_count')); ?>],
                        backgroundColor: '#10B981'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            <?php endif; ?>
        });
    </script>
</body>
</html> 