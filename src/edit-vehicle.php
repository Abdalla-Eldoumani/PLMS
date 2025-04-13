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
$errors = [];
$success = false;

// Check if vehicle ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: my-vehicles.php");
    exit();
}

$vehicle_id = intval($_GET['id']);

// Check if vehicle belongs to user
$vehicle = $db->query("SELECT * FROM vehicles WHERE vehicle_id = ? AND owner_id = ?", 
                     [$vehicle_id, $user_id])->fetch();

if (!$vehicle) {
    header("Location: my-vehicles.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid form submission.";
    } else {
        // Validate input
        $license_plate = strtoupper(sanitize($_POST['license_plate']));
        $vehicle_type = sanitize($_POST['vehicle_type']);
        
        // Basic validation
        if (empty($license_plate)) {
            $errors[] = "License plate is required.";
        }
        
        if (empty($vehicle_type)) {
            $errors[] = "Vehicle type is required.";
        }
        
        // Check if license plate already exists (if changed)
        if ($license_plate !== $vehicle['license_plate']) {
            $existingVehicle = $db->query("SELECT vehicle_id FROM vehicles WHERE license_plate = ? AND vehicle_id != ?", 
                                        [$license_plate, $vehicle_id])->fetch();
            if ($existingVehicle) {
                $errors[] = "A vehicle with this license plate already exists.";
            }
        }
        
        // If no errors, update vehicle
        if (empty($errors)) {
            try {
                $db->query("UPDATE vehicles SET license_plate = ?, vehicle_type = ? WHERE vehicle_id = ?", 
                          [$license_plate, $vehicle_type, $vehicle_id]);
                
                $success = true;
                
                // Refresh vehicle data
                $vehicle = $db->query("SELECT * FROM vehicles WHERE vehicle_id = ?", [$vehicle_id])->fetch();
                
            } catch (Exception $e) {
                $errors[] = "An error occurred: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Vehicle - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Navigation Bar -->
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-lg mx-auto">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gray-800 text-white px-6 py-4">
                    <h2 class="text-xl font-semibold">Edit Vehicle</h2>
                </div>
                
                <div class="p-6">
                    <?php if ($success): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                            <p>Vehicle added successfully!</p>
                            <div class="mt-3">
                                <a href="my-vehicles.php" class="text-green-700 font-semibold hover:underline">Return to My Vehicles</a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                            <p class="font-bold">Please correct the following errors:</p>
                            <ul class="list-disc ml-5">
                                <?php foreach($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Check for active bookings with this vehicle -->
                    <?php 
                    $activeBooking = $db->query(
                        "SELECT b.booking_id FROM bookings b 
                        WHERE b.customer_id = ? AND b.status = 'Active'", 
                        [$user_id]
                    )->fetch();
                    
                    if ($activeBooking): 
                    ?>
                        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
                            <p class="font-bold">Warning:</p>
                            <p>This vehicle is associated with an active booking. Any changes may affect your current booking.</p>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="edit-vehicle.php?id=<?php echo $vehicle_id; ?>">
                        <!-- CSRF token -->
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-4">
                            <label for="license_plate" class="form-label">License Plate</label>
                            <input type="text" id="license_plate" name="license_plate" class="input-field" value="<?php echo htmlspecialchars($vehicle['license_plate']); ?>" required>
                            <p class="text-sm text-gray-500 mt-1">Enter the license plate exactly as it appears on your vehicle.</p>
                        </div>
                        
                        <div class="mb-6">
                            <label for="vehicle_type" class="form-label">Vehicle Type</label>
                            <select id="vehicle_type" name="vehicle_type" class="input-field" required>
                                <option value="">Select a vehicle type</option>
                                <option value="Sedan" <?php echo $vehicle['vehicle_type'] === 'Sedan' ? 'selected' : ''; ?>>Sedan</option>
                                <option value="SUV" <?php echo $vehicle['vehicle_type'] === 'SUV' ? 'selected' : ''; ?>>SUV</option>
                                <option value="Truck" <?php echo $vehicle['vehicle_type'] === 'Truck' ? 'selected' : ''; ?>>Truck</option>
                                <option value="Other" <?php echo $vehicle['vehicle_type'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="flex justify-between">
                            <a href="my-vehicles.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
</body>
</html>