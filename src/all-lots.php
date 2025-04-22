<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$db = new Database();

// Get all parking lots
$lots = $db->query(
    "SELECT * FROM parking_lots ORDER BY lot_id"
)->fetchAll();

// Get availability for each lot
$availability = [];
foreach ($lots as $lot) {
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
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parking Lots - UCalgary Parking</title>
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

    <main class="container mx-auto px-4 py-8">
        <h2 class="text-3xl font-bold mb-6 text-center">Available Parking Lots</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($lots as $lot): 
                $percentFull = $availability[$lot['lot_id']]['occupancy'];
                $statusColor = $percentFull > 80 ? 'bg-red-500' : ($percentFull > 50 ? 'bg-yellow-500' : 'bg-green-500');
            ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-6">
                        <h3 class="text-xl font-bold mb-2"><?php echo htmlspecialchars($lot['name']); ?></h3>
                        <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($lot['location']); ?></p>
                        
                        <div class="mb-4">
                            <div class="flex justify-between mb-1">
                                <span class="text-sm font-medium">Occupancy</span>
                                <span class="text-sm font-medium"><?php echo $availability[$lot['lot_id']]['occupancy']; ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="<?php echo $statusColor; ?> h-2.5 rounded-full" style="width: <?php echo $percentFull; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <p class="text-sm text-gray-600">
                                <span class="font-medium"><?php echo $availability[$lot['lot_id']]['available']; ?></span> of 
                                <span class="font-medium"><?php echo $availability[$lot['lot_id']]['total']; ?></span> spots available
                            </p>
                        </div>
                        
                        <div class="mb-6">
                            <h4 class="font-medium mb-2">Spot Types & Rates:</h4>
                            <ul class="space-y-1">
                                <?php foreach ($availability[$lot['lot_id']]['slot_types'] as $type): ?>
                                    <li class="text-sm">
                                        <span class="font-medium"><?php echo htmlspecialchars($type['type']); ?>:</span> 
                                        <?php echo $type['count']; ?> spots at $<?php echo number_format($type['min_rate'], 2); ?>/hour
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-2">
                            <?php if ($isLoggedIn): ?>
                                <a href="find-parking.php?lot_id=<?php echo $lot['lot_id']; ?>" class="bg-red-700 hover:bg-red-800 text-white py-2 px-4 rounded text-center text-sm">Book Now</a>
                            <?php else: ?>
                                <a href="login.php" class="bg-red-700 hover:bg-red-800 text-white py-2 px-4 rounded text-center text-sm">Login to Book</a>
                            <?php endif; ?>
                            <a href="lot-details.php?id=<?php echo $lot['lot_id']; ?>" class="border border-red-700 text-red-700 hover:bg-red-50 py-2 px-4 rounded text-center text-sm">View Details</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (!$isLoggedIn): ?>
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h3 class="text-xl font-bold mb-4">Need to Book a Parking Spot?</h3>
            <p class="mb-4">Create an account to book parking spots, manage your vehicles, and view your booking history.</p>
            <div class="flex space-x-4">
                <a href="register.php" class="bg-red-700 text-white px-6 py-2 rounded hover:bg-red-800 transition">Register Now</a>
                <a href="login.php" class="border border-red-700 text-red-700 px-6 py-2 rounded hover:bg-red-50">Login</a>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
</body>
</html> 