<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$db = new Database();

// Get lot ID from URL
$lotId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// If no valid lot ID provided, redirect to all lots page
if (!$lotId) {
    header("Location: all-lots.php");
    exit();
}

// Get lot details
$lot = $db->query("SELECT * FROM parking_lots WHERE lot_id = ?", [$lotId])->fetch();

// If lot doesn't exist, redirect
if (!$lot) {
    header("Location: all-lots.php");
    exit();
}

// Get slot statistics
$slotStats = $db->query(
    "SELECT 
        COUNT(*) as total_slots,
        SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available_slots,
        SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occupied_slots,
        SUM(CASE WHEN status = 'Reserved' THEN 1 ELSE 0 END) as reserved_slots
     FROM parking_slots
     WHERE lot_id = ?", 
    [$lotId]
)->fetch();

// Get slot types
$slotTypes = $db->query(
    "SELECT 
        type, 
        COUNT(*) as count,
        MIN(hourly_rate) as min_rate,
        MAX(hourly_rate) as max_rate
     FROM parking_slots
     WHERE lot_id = ?
     GROUP BY type", 
    [$lotId]
)->fetchAll();

// Get available slots data for each type
$availableSlots = $db->query(
    "SELECT 
        type,
        COUNT(*) as count
     FROM parking_slots
     WHERE lot_id = ? AND status = 'Available'
     GROUP BY type", 
    [$lotId]
)->fetchAll();

// Calculate the current occupancy percentage
$occupancyRate = 0;
if ($slotStats['total_slots'] > 0) {
    $occupancyRate = round(($slotStats['occupied_slots'] / $slotStats['total_slots']) * 100);
}

// Get recent bookings for this lot (for popularity metrics)
$recentBookings = $db->query(
    "SELECT COUNT(*) as count 
     FROM bookings b
     JOIN parking_slots ps ON b.slot_id = ps.slot_id
     WHERE ps.lot_id = ? 
     AND b.start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    [$lotId]
)->fetch()['count'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($lot['name']); ?> - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-6xl mx-auto">
            <!-- Back button -->
            <div class="mb-6">
                <a href="javascript:history.back()" class="text-blue-700 hover:underline flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                    </svg>
                    Back
                </a>
            </div>

            <!-- Lot Header -->
            <header class="bg-gradient-to-r from-red-800 to-red-600 rounded-lg shadow-md p-6 text-white mb-8">
                <h1 class="text-3xl font-bold"><?php echo htmlspecialchars($lot['name']); ?></h1>
                <p class="text-xl mt-2"><?php echo htmlspecialchars($lot['location']); ?></p>
                <div class="flex items-center mt-4">
                    <div class="bg-white text-red-700 rounded-full px-4 py-1 text-sm font-semibold">
                        <?php echo $slotStats['available_slots']; ?> / <?php echo $slotStats['total_slots']; ?> slots available
                    </div>
                </div>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                <!-- Left Column - Basic Info & Stats -->
                <div class="md:col-span-2">
                    <!-- Status and Statistics -->
                    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Current Status</h2>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                            <!-- Total Capacity -->
                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <p class="text-gray-600 text-sm">Total Capacity</p>
                                <p class="text-2xl font-bold"><?php echo $slotStats['total_slots']; ?> slots</p>
                            </div>
                            
                            <!-- Available Slots -->
                            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                                <p class="text-green-600 text-sm">Available Now</p>
                                <p class="text-2xl font-bold"><?php echo $slotStats['available_slots']; ?> slots</p>
                            </div>
                            
                            <!-- Occupancy Rate -->
                            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                                <p class="text-blue-600 text-sm">Occupancy Rate</p>
                                <p class="text-2xl font-bold"><?php echo $occupancyRate; ?>%</p>
                            </div>
                        </div>
                        
                        <!-- Occupancy Chart -->
                        <div class="relative h-64 mt-6">
                            <canvas id="occupancyChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Slot Types and Pricing -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Slot Types and Pricing</h2>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Type
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Total
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Available
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Rate
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($slotTypes as $type): ?>
                                        <?php 
                                        // Find available count for this type
                                        $availableCount = 0;
                                        foreach ($availableSlots as $available) {
                                            if ($available['type'] === $type['type']) {
                                                $availableCount = $available['count'];
                                                break;
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($type['type']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo $type['count']; ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $availableCount > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo $availableCount; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                $<?php echo number_format($type['min_rate'], 2); ?>/hour
                                                <?php if ($type['min_rate'] != $type['max_rate']): ?>
                                                    - $<?php echo number_format($type['max_rate'], 2); ?>/hour
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column - Booking & Actions -->
                <div>
                    <!-- Quick Booking Card -->
                    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Book a Parking Spot</h2>
                        
                        <p class="text-gray-600 mb-4">Find and book available parking spots at this location.</p>
                        
                        <a href="find-parking.php?lot_id=<?php echo $lotId; ?>" class="block bg-red-700 hover:bg-red-800 text-white font-semibold py-2 px-4 rounded w-full text-center">
                            Book Now
                        </a>
                    </div>
                    
                    <!-- Additional Information -->
                    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Lot Information</h2>
                        
                        <div class="mb-4">
                            <h3 class="text-gray-600 text-sm">Location</h3>
                            <p class="font-medium"><?php echo htmlspecialchars($lot['location']); ?></p>
                        </div>
                        
                        <div class="mb-4">
                            <h3 class="text-gray-600 text-sm">Total Capacity</h3>
                            <p class="font-medium"><?php echo $lot['total_slots']; ?> parking slots</p>
                        </div>
                        
                        <div class="mb-4">
                            <h3 class="text-gray-600 text-sm">Popularity</h3>
                            <div class="flex items-center">
                                <?php
                                // Calculate popularity (1-5 stars based on recent bookings)
                                $popularity = min(5, ceil($recentBookings / 10));
                                for ($i = 0; $i < 5; $i++) {
                                    if ($i < $popularity) {
                                        echo '<svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>';
                                    } else {
                                        echo '<svg class="w-5 h-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>';
                                    }
                                }
                                ?>
                                <span class="ml-2 text-gray-600">(<?php echo $recentBookings; ?> recent bookings)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Occupancy Chart
            const occupancyCtx = document.getElementById('occupancyChart').getContext('2d');
            new Chart(occupancyCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Available', 'Occupied', 'Reserved'],
                    datasets: [{
                        data: [
                            <?php echo $slotStats['available_slots']; ?>, 
                            <?php echo $slotStats['occupied_slots']; ?>, 
                            <?php echo $slotStats['reserved_slots']; ?>
                        ],
                        backgroundColor: [
                            'rgba(52, 211, 153, 0.8)',
                            'rgba(239, 68, 68, 0.8)',
                            'rgba(59, 130, 246, 0.8)'
                        ],
                        borderColor: [
                            'rgba(52, 211, 153, 1)',
                            'rgba(239, 68, 68, 1)',
                            'rgba(59, 130, 246, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html> 