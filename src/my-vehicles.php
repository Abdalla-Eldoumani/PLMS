<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$db = new Database();
$message = '';

// Handle vehicle deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $vehicle_id = intval($_GET['id']);
    
    // Verify CSRF token
    if (!isset($_GET['token']) || !verifyCSRFToken($_GET['token'])) {
        $message = [
            'type' => 'error',
            'text' => 'Invalid request.'
        ];
    } else {
        // Check if vehicle belongs to user
        $vehicle = $db->query("SELECT * FROM vehicles WHERE vehicle_id = ? AND owner_id = ?", 
                            [$vehicle_id, $user_id])->fetch();
        
        if ($vehicle) {
            // Check if vehicle is currently in use in an active booking
            $activeBooking = $db->query(
                "SELECT b.booking_id FROM bookings b 
                JOIN vehicles v ON b.customer_id = v.owner_id
                WHERE v.vehicle_id = ? AND b.status = 'Active'", 
                [$vehicle_id]
            )->fetch();
            
            if ($activeBooking) {
                $message = [
                    'type' => 'error',
                    'text' => 'Cannot delete vehicle: it is currently used in an active booking.'
                ];
            } else {
                // Delete vehicle
                try {
                    $db->query("DELETE FROM vehicles WHERE vehicle_id = ?", [$vehicle_id]);
                    $message = [
                        'type' => 'success',
                        'text' => 'Vehicle deleted successfully.'
                    ];
                    
                    // Log activity
                    logActivity($user_id, 'vehicle_delete', "User deleted vehicle: {$vehicle['license_plate']}", $db);
                    
                } catch (Exception $e) {
                    $message = [
                        'type' => 'error',
                        'text' => 'An error occurred while deleting the vehicle.'
                    ];
                }
            }
        } else {
            $message = [
                'type' => 'error',
                'text' => 'Vehicle not found or does not belong to you.'
            ];
        }
    }
}

// Get all vehicles for the user
$vehicles = $db->query("SELECT * FROM vehicles WHERE owner_id = ? ORDER BY vehicle_id DESC", [$user_id])->fetchAll();

// Get user information for display
$user = $db->query("SELECT * FROM users WHERE user_id = ?", [$user_id])->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Vehicles - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Navigation Bar -->
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-5xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">My Vehicles</h1>
                <a href="add-vehicle.php" class="bg-red-700 hover:bg-red-800 text-white font-bold py-2 px-4 rounded">
                    Add New Vehicle
                </a>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="mb-6 <?php echo $message['type'] === 'error' ? 'bg-red-100 border-red-500 text-red-700' : 'bg-green-100 border-green-500 text-green-700'; ?> border-l-4 p-4" role="alert">
                    <p><?php echo $message['text']; ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Vehicles Cards -->
            <?php if (count($vehicles) > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($vehicles as $vehicle): ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden">
                            <div class="bg-gray-800 text-white px-4 py-3 flex justify-between items-center">
                                <h3 class="font-semibold"><?php echo htmlspecialchars($vehicle['license_plate']); ?></h3>
                                <span class="px-2 py-1 text-xs rounded-full 
                                    <?php 
                                    switch($vehicle['vehicle_type']) {
                                        case 'Sedan': echo 'bg-blue-200 text-blue-800'; break;
                                        case 'SUV': echo 'bg-green-200 text-green-800'; break;
                                        case 'Truck': echo 'bg-yellow-200 text-yellow-800'; break;
                                        default: echo 'bg-gray-200 text-gray-800';
                                    }
                                    ?>">
                                    <?php echo htmlspecialchars($vehicle['vehicle_type']); ?>
                                </span>
                            </div>
                            
                            <div class="p-4">
                                <div class="flex items-center mb-4">
                                    <svg class="h-6 w-6 text-gray-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                                    </svg>
                                    <span class="text-gray-600">Added on <?php echo date('M d, Y', strtotime($vehicle['vehicle_id'])); ?></span>
                                </div>
                                
                                <!-- Check for active bookings with this vehicle -->
                                <?php 
                                $activeBooking = $db->query(
                                    "SELECT b.booking_id FROM bookings b 
                                    WHERE b.customer_id = ? AND b.status = 'Active'", 
                                    [$user_id]
                                )->fetch();
                                ?>
                                
                                <?php if ($activeBooking): ?>
                                    <div class="mb-4 bg-blue-50 text-blue-700 px-3 py-2 rounded">
                                        <p class="text-sm">This vehicle is currently being used in an active booking.</p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="flex justify-between mt-4">
                                    <a href="edit-vehicle.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="bg-gray-800 hover:bg-gray-700 text-white py-2 px-4 rounded">
                                        Edit
                                    </a>
                                    <a href="my-vehicles.php?action=delete&id=<?php echo $vehicle['vehicle_id']; ?>&token=<?php echo generateCSRFToken(); ?>" 
                                       class="bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded delete-vehicle"
                                       <?php echo $activeBooking ? 'disabled' : ''; ?>>
                                        Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-6 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                        </svg>
                        <h3 class="mt-4 text-lg font-medium text-gray-900">No vehicles found</h3>
                        <p class="mt-2 text-gray-500">You haven't added any vehicles to your account yet.</p>
                        <div class="mt-6">
                            <a href="add-vehicle.php" class="bg-red-700 hover:bg-red-800 text-white font-bold py-2 px-4 rounded">
                                Add Your First Vehicle
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
</body>
</html>