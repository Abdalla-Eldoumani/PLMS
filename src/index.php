<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$db = new Database();

// Get all parking lots
try {
    $lots = $db->query(
        "SELECT * FROM parking_lots ORDER BY lot_id"
    )->fetchAll();
    
    if (empty($lots)) {
        error_log("No parking lots found in the database");
    }
} catch (PDOException $e) {
    error_log("Error fetching parking lots: " . $e->getMessage());
    $lots = [];
}

// Get availability for each lot
$availability = [];
foreach ($lots as $lot) {
    try {
        // Get total slots
        $totalSlots = $db->query(
            "SELECT COUNT(*) as total FROM parking_slots WHERE lot_id = ?",
            [$lot['lot_id']]
        )->fetch()['total'];
        
        // Get available slots
        $availableSlots = $db->query(
            "SELECT COUNT(*) as available FROM parking_slots WHERE lot_id = ? AND status = 'Available'",
            [$lot['lot_id']]
        )->fetch()['available'];
        
        // Get slot types and rates
        $slotTypes = $db->query(
            "SELECT type, COUNT(*) as count, MIN(hourly_rate) as min_rate, MAX(hourly_rate) as max_rate 
             FROM parking_slots 
             WHERE lot_id = ? 
             GROUP BY type",
            [$lot['lot_id']]
        )->fetchAll();
        
        $availability[$lot['lot_id']] = [
            'total' => $totalSlots,
            'available' => $availableSlots,
            'occupancy' => $totalSlots > 0 ? round(($totalSlots - $availableSlots) / $totalSlots * 100) : 0,
            'slot_types' => $slotTypes
        ];
    } catch (PDOException $e) {
        error_log("Error fetching availability for lot {$lot['lot_id']}: " . $e->getMessage());
        $availability[$lot['lot_id']] = [
            'total' => 0,
            'available' => 0,
            'occupancy' => 0,
            'slot_types' => []
        ];
    }
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCalgary Parking Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Navigation Bar -->
    <nav class="bg-red-700 text-white shadow-lg">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center">
                <a class="text-xl font-bold" href="index.php">Parking Management System</a>
            </div>
            <div class="space-x-4">
                <?php if($isLoggedIn): ?>
                    <a href="dashboard.php" class="hover:text-gray-200">Dashboard</a>
                    <a href="logout.php" class="bg-white text-red-700 px-4 py-2 rounded hover:bg-gray-200">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="hover:text-gray-200">Login</a>
                    <a href="register.php" class="bg-white text-red-700 px-4 py-2 rounded hover:bg-gray-200">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="relative">
        <div class="absolute inset-0 bg-gradient-to-r from-red-800 to-red-600 opacity-90"></div>
        <div class="relative container mx-auto px-4 py-16 flex flex-col items-center">
            <h2 class="text-4xl font-bold text-white mb-6 text-center">Find and Reserve Parking at University of Calgary</h2>
            <p class="text-xl text-white mb-8 text-center max-w-2xl">Real-time parking availability, advanced booking, and seamless payment solutions for students, faculty, and visitors.</p>
            
            <!-- Quick Action Buttons -->
            <div class="flex flex-wrap justify-center gap-4 mb-8">
                <a href="find-parking.php" class="bg-white text-red-700 px-6 py-3 rounded-lg font-bold hover:bg-gray-100 transition">Find Available Parking</a>
                <?php if (!$isLoggedIn): ?>
                    <a href="register.php" class="bg-transparent border-2 border-white text-white px-6 py-3 rounded-lg font-bold hover:bg-white hover:text-red-700 transition">Create Account</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Real-Time Availability Section -->
    <div class="container mx-auto px-4 py-16">
        <h3 class="text-2xl font-bold text-center mb-8">Current Parking Availability</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($lots as $lot): 
                $percentFull = $availability[$lot['lot_id']]['occupancy'];
                $statusColor = $percentFull > 80 ? 'bg-red-500' : ($percentFull > 50 ? 'bg-yellow-500' : 'bg-green-500');
            ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <h4 class="text-xl font-semibold mb-2"><?php echo htmlspecialchars($lot['name']); ?></h4>
                    <p class="text-gray-600 mb-2"><?php echo htmlspecialchars($lot['location']); ?></p>
                    <div class="flex justify-between mb-2">
                        <span>Available Spaces:</span>
                        <span class="font-bold"><?php echo $availability[$lot['lot_id']]['available']; ?> / <?php echo $availability[$lot['lot_id']]['total']; ?></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5 mb-4">
                        <div class="<?php echo $statusColor; ?> h-2.5 rounded-full" style="width: <?php echo $percentFull; ?>%"></div>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="text-sm text-gray-500">Starting at</span>
                            <span class="font-semibold">
                                <?php 
                                $minRate = PHP_FLOAT_MAX;
                                foreach($availability[$lot['lot_id']]['slot_types'] as $type) {
                                    $minRate = min($minRate, $type['min_rate']);
                                }
                                echo '$' . number_format($minRate, 2) . '/hr';
                                ?>
                            </span>
                        </div>
                        <a href="lot-details.php?id=<?php echo $lot['lot_id']; ?>" class="bg-red-700 hover:bg-red-800 text-white px-4 py-2 rounded text-sm">View Details</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-8">
            <a href="all-lots.php" class="text-red-700 font-semibold hover:underline">View All Parking Lots →</a>
        </div>
    </div>

    <!-- Features Section -->
    <div class="bg-gray-800 text-white py-16">
        <div class="container mx-auto px-4">
            <h3 class="text-2xl font-bold text-center mb-12">Smart Parking Management Features</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="flex flex-col items-center text-center">
                    <div class="bg-red-700 p-4 rounded-full mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h4 class="text-xl font-semibold mb-2">Real-Time Updates</h4>
                    <p>Get instant information about available parking spots across campus.</p>
                </div>
                
                <div class="flex flex-col items-center text-center">
                    <div class="bg-red-700 p-4 rounded-full mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h4 class="text-xl font-semibold mb-2">Advanced Booking</h4>
                    <p>Reserve your parking space ahead of time to ensure availability.</p>
                </div>
                
                <div class="flex flex-col items-center text-center">
                    <div class="bg-red-700 p-4 rounded-full mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>
                    <h4 class="text-xl font-semibold mb-2">Digital Payments</h4>
                    <p>Hassle-free online payments for bookings and subscription plans.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
</body>
</html>