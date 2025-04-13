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
$lots = $db->query("SELECT pl.* FROM parking_lots pl
                    JOIN admin_parking_lots apl ON pl.lot_id = apl.lot_id
                    WHERE apl.admin_id = ?", [$userId])->fetchAll();

// Filter parameters
$startDate = filter_input(INPUT_GET, 'start_date') ?: date('Y-m-d', strtotime('-30 days'));
$endDate = filter_input(INPUT_GET, 'end_date') ?: date('Y-m-d');
$lotId = filter_input(INPUT_GET, 'lot_id', FILTER_VALIDATE_INT) ?: 'all';
$reportType = filter_input(INPUT_GET, 'report_type') ?: 'revenue';

// Build report query based on parameters
switch ($reportType) {
    case 'revenue':
        $query = "SELECT 
                    DATE(p.payment_date) as date,
                    pl.name as lot_name,
                    COUNT(p.payment_id) as payment_count,
                    SUM(p.amount) as total_revenue
                 FROM payments p
                 JOIN bookings b ON p.booking_id = b.booking_id
                 JOIN parking_slots ps ON b.slot_id = ps.slot_id
                 JOIN parking_lots pl ON ps.lot_id = pl.lot_id
                 JOIN admin_parking_lots apl ON pl.lot_id = apl.lot_id
                 WHERE apl.admin_id = ?
                 AND p.payment_date BETWEEN ? AND ?";
        
        if ($lotId != 'all') {
            $query .= " AND pl.lot_id = ?";
            $params = [$userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59', $lotId];
        } else {
            $params = [$userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59'];
        }
        
        $query .= " GROUP BY DATE(p.payment_date), pl.lot_id
                   ORDER BY DATE(p.payment_date)";
        
        $reportData = $db->query($query, $params)->fetchAll();
        break;
        
    case 'occupancy':
        $query = "SELECT 
                    pl.name as lot_name,
                    ps.slot_number,
                    COUNT(b.booking_id) as booking_count,
                    SUM(TIMESTAMPDIFF(HOUR, b.start_time, b.end_time)) as total_hours,
                    AVG(TIMESTAMPDIFF(HOUR, b.start_time, b.end_time)) as avg_duration
                 FROM bookings b
                 JOIN parking_slots ps ON b.slot_id = ps.slot_id
                 JOIN parking_lots pl ON ps.lot_id = pl.lot_id
                 JOIN admin_parking_lots apl ON pl.lot_id = apl.lot_id
                 WHERE apl.admin_id = ?
                 AND b.start_time BETWEEN ? AND ?";
        
        if ($lotId != 'all') {
            $query .= " AND pl.lot_id = ?";
            $params = [$userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59', $lotId];
        } else {
            $params = [$userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59'];
        }
        
        $query .= " GROUP BY ps.slot_id
                   ORDER BY pl.name, ps.slot_number";
        
        $reportData = $db->query($query, $params)->fetchAll();
        break;
        
    case 'peak_times':
        $query = "SELECT 
                    HOUR(b.start_time) as hour_of_day,
                    COUNT(b.booking_id) as booking_count
                 FROM bookings b
                 JOIN parking_slots ps ON b.slot_id = ps.slot_id
                 JOIN parking_lots pl ON ps.lot_id = pl.lot_id
                 JOIN admin_parking_lots apl ON pl.lot_id = apl.lot_id
                 WHERE apl.admin_id = ?
                 AND b.start_time BETWEEN ? AND ?";
        
        if ($lotId != 'all') {
            $query .= " AND pl.lot_id = ?";
            $params = [$userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59', $lotId];
        } else {
            $params = [$userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59'];
        }
        
        $query .= " GROUP BY HOUR(b.start_time)
                   ORDER BY booking_count DESC";
        
        $reportData = $db->query($query, $params)->fetchAll();
        break;
        
    case 'violations':
        $query = "SELECT 
                    DATE(b.end_time) as date,
                    pl.name as lot_name,
                    COUNT(oa.alert_id) as violation_count,
                    SUM(oa.fine_amount) as total_fines,
                    SUM(CASE WHEN oa.status = 'Paid' THEN oa.fine_amount ELSE 0 END) as collected_fines
                 FROM overstay_alerts oa
                 JOIN bookings b ON oa.booking_id = b.booking_id
                 JOIN parking_slots ps ON b.slot_id = ps.slot_id
                 JOIN parking_lots pl ON ps.lot_id = pl.lot_id
                 JOIN admin_parking_lots apl ON pl.lot_id = apl.lot_id
                 WHERE apl.admin_id = ?
                 AND b.end_time BETWEEN ? AND ?";
        
        if ($lotId != 'all') {
            $query .= " AND pl.lot_id = ?";
            $params = [$userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59', $lotId];
        } else {
            $params = [$userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59'];
        }
        
        $query .= " GROUP BY DATE(b.end_time), pl.lot_id
                   ORDER BY DATE(b.end_time)";
        
        $reportData = $db->query($query, $params)->fetchAll();
        break;
        
    default:
        $reportData = [];
}

// Get summary statistics
$revenueTotal = 0;
$bookingCount = 0;
$violationCount = 0;

if ($reportType === 'revenue') {
    foreach ($reportData as $row) {
        $revenueTotal += $row['total_revenue'];
        $bookingCount += $row['payment_count'];
    }
} elseif ($reportType === 'occupancy') {
    $bookingCount = count($reportData);
} elseif ($reportType === 'violations') {
    foreach ($reportData as $row) {
        $violationCount += $row['violation_count'];
        $revenueTotal += $row['collected_fines'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <?php include 'includes/admin-header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Parking Reports</h1>
        
        <!-- Report Filters -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Generate Report</h2>
            <form method="GET" action="reports.php" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                    <label for="report_type" class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                    <select id="report_type" name="report_type" class="form-select rounded-md border-gray-300 shadow-sm mt-1 block w-full">
                        <option value="revenue" <?php echo $reportType === 'revenue' ? 'selected' : ''; ?>>Revenue Report</option>
                        <option value="occupancy" <?php echo $reportType === 'occupancy' ? 'selected' : ''; ?>>Occupancy Report</option>
                        <option value="peak_times" <?php echo $reportType === 'peak_times' ? 'selected' : ''; ?>>Peak Usage Times</option>
                        <option value="violations" <?php echo $reportType === 'violations' ? 'selected' : ''; ?>>Violation Report</option>
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
                
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo $startDate; ?>" class="form-input rounded-md border-gray-300 shadow-sm mt-1 block w-full">
                </div>
                
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo $endDate; ?>" class="form-input rounded-md border-gray-300 shadow-sm mt-1 block w-full">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition w-full">
                        Generate Report
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Report Results -->
        <?php if (!empty($reportData)): ?>
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <?php if ($reportType === 'revenue' || $reportType === 'violations'): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-gray-500 text-sm">Total Revenue</h3>
                        <p class="text-3xl font-bold text-gray-800 mt-2">$<?php echo number_format($revenueTotal, 2); ?></p>
                        <p class="text-sm text-gray-500 mt-1"><?php echo date('M j, Y', strtotime($startDate)); ?> - <?php echo date('M j, Y', strtotime($endDate)); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($reportType === 'revenue' || $reportType === 'occupancy'): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-gray-500 text-sm"><?php echo $reportType === 'revenue' ? 'Total Bookings' : 'Slots Used'; ?></h3>
                        <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $bookingCount; ?></p>
                        <p class="text-sm text-gray-500 mt-1"><?php echo date('M j, Y', strtotime($startDate)); ?> - <?php echo date('M j, Y', strtotime($endDate)); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($reportType === 'violations'): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-gray-500 text-sm">Total Violations</h3>
                        <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $violationCount; ?></p>
                        <p class="text-sm text-gray-500 mt-1"><?php echo date('M j, Y', strtotime($startDate)); ?> - <?php echo date('M j, Y', strtotime($endDate)); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($reportType === 'peak_times'): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-gray-500 text-sm">Peak Hour</h3>
                        <p class="text-3xl font-bold text-gray-800 mt-2">
                            <?php 
                            if (!empty($reportData)) {
                                $peakHour = $reportData[0]['hour_of_day'];
                                echo $peakHour . ':00 - ' . ($peakHour + 1) . ':00';
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </p>
                        <p class="text-sm text-gray-500 mt-1">Most bookings during this hour</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Report Visualization -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <?php 
                    switch ($reportType) {
                        case 'revenue': echo 'Revenue Report'; break;
                        case 'occupancy': echo 'Occupancy Report'; break;
                        case 'peak_times': echo 'Peak Usage Times'; break;
                        case 'violations': echo 'Violation Report'; break;
                    }
                    ?>
                </h2>
                
                <div class="h-80">
                    <canvas id="reportChart"></canvas>
                </div>
            </div>
            
            <!-- Report Data Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800">
                        <?php 
                        switch ($reportType) {
                            case 'revenue': echo 'Revenue Details'; break;
                            case 'occupancy': echo 'Occupancy Details'; break;
                            case 'peak_times': echo 'Usage by Hour'; break;
                            case 'violations': echo 'Violation Details'; break;
                        }
                        ?>
                    </h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <?php if ($reportType === 'revenue'): ?>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lot</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bookings</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                <?php elseif ($reportType === 'occupancy'): ?>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lot</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slot</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bookings</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Hours</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Duration</th>
                                <?php elseif ($reportType === 'peak_times'): ?>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hour</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bookings</th>
                                <?php elseif ($reportType === 'violations'): ?>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lot</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Violations</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Fines</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Collected</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($reportData as $row): ?>
                                <tr>
                                    <?php if ($reportType === 'revenue'): ?>
                                        <td class="px-6 py-4 whitespace-nowrap"><?php echo date('M j, Y', strtotime($row['date'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($row['lot_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?php echo $row['payment_count']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">$<?php echo number_format($row['total_revenue'], 2); ?></td>
                                    <?php elseif ($reportType === 'occupancy'): ?>
                                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($row['lot_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($row['slot_number']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?php echo $row['booking_count']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?php echo $row['total_hours']; ?> hours</td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?php echo number_format($row['avg_duration'], 1); ?> hours</td>
                                    <?php elseif ($reportType === 'peak_times'): ?>
                                        <td class="px-6 py-4 whitespace-nowrap"><?php echo $row['hour_of_day'] . ':00 - ' . ($row['hour_of_day'] + 1) . ':00'; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?php echo $row['booking_count']; ?></td>
                                    <?php elseif ($reportType === 'violations'): ?>
                                        <td class="px-6 py-4 whitespace-nowrap"><?php echo date('M j, Y', strtotime($row['date'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($row['lot_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?php echo $row['violation_count']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">$<?php echo number_format($row['total_fines'], 2); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">$<?php echo number_format($row['collected_fines'], 2); ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <p class="text-gray-500">No data available for the selected criteria. Please adjust your filters or try a different report type.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($reportData)): ?>
                const ctx = document.getElementById('reportChart').getContext('2d');
                
                <?php if ($reportType === 'revenue'): ?>
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: [<?php echo implode(',', array_map(function($row) { return "'" . date('M j', strtotime($row['date'])) . "'"; }, $reportData)); ?>],
                            datasets: [{
                                label: 'Revenue ($)',
                                data: [<?php echo implode(',', array_column($reportData, 'total_revenue')); ?>],
                                borderColor: '#3B82F6',
                                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                                tension: 0.1,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return '$' + value;
                                        }
                                    }
                                }
                            }
                        }
                    });
                <?php elseif ($reportType === 'occupancy'): ?>
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: [<?php echo implode(',', array_map(function($row) { return "'" . $row['slot_number'] . "'"; }, $reportData)); ?>],
                            datasets: [{
                                label: 'Booking Count',
                                data: [<?php echo implode(',', array_column($reportData, 'booking_count')); ?>],
                                backgroundColor: '#10B981'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                <?php elseif ($reportType === 'peak_times'): ?>
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: [<?php echo implode(',', array_map(function($row) { return "'" . $row['hour_of_day'] . ":00'"; }, $reportData)); ?>],
                            datasets: [{
                                label: 'Number of Bookings',
                                data: [<?php echo implode(',', array_column($reportData, 'booking_count')); ?>],
                                backgroundColor: '#8B5CF6'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                <?php elseif ($reportType === 'violations'): ?>
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: [<?php echo implode(',', array_map(function($row) { return "'" . date('M j', strtotime($row['date'])) . "'"; }, $reportData)); ?>],
                            datasets: [{
                                label: 'Total Fines ($)',
                                data: [<?php echo implode(',', array_column($reportData, 'total_fines')); ?>],
                                backgroundColor: '#EF4444'
                            }, {
                                label: 'Collected Fines ($)',
                                data: [<?php echo implode(',', array_column($reportData, 'collected_fines')); ?>],
                                backgroundColor: '#10B981'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return '$' + value;
                                        }
                                    }
                                }
                            }
                        }
                    });
                <?php endif; ?>
            <?php endif; ?>
        });
    </script>
</body>
</html> 