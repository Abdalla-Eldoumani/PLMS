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
        }  elseif (!preg_match('/^[A-Z0-9\- ]{2,8}$/', $license_plate)) {
            $errors[] = "License plate must be 2–8 characters long and contain only uppercase letters, numbers, dashes, or spaces.";
        } else {
            $existingVehicle = $db->query("SELECT vehicle_id FROM vehicles WHERE license_plate = ?", [$license_plate])->fetch();
            if ($existingVehicle) {
                $errors[] = "A vehicle with this license plate already exists.";
            }
        }
        
        if (empty($vehicle_type)) {
            $errors[] = "Vehicle type is required.";
        }
        
        // Check if license plate already exists
     //   $existingVehicle = $db->query("SELECT vehicle_id FROM vehicles WHERE license_plate = ?", [$license_plate])->fetch();
       // if ($existingVehicle) {
         //   $errors[] = "A vehicle with this license plate already exists.";
      //  }


      //  if (!preg_match('/^[A-Z0-9\-]{2,8}$/', $license_plate)) {
         //   $errors[] = "License plate must be 2–8 characters long and contain only uppercase letters, numbers, or dashes.";
     //   }
        
        // If no errors, add vehicle
        if (empty($errors)) {
            try {
                $db->query("INSERT INTO vehicles (license_plate, vehicle_type, owner_id) VALUES (?, ?, ?)", 
                          [$license_plate, $vehicle_type, $user_id]);
                
                $success = true;
                
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
    <title>Add Vehicle - UCalgary Parking</title>
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
                    <h2 class="text-xl font-semibold">Add New Vehicle</h2>
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
                    
                    <form method="POST" action="add-vehicle.php">
                        <!-- CSRF token -->
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-4">
                            <label for="license_plate" class="form-label">License Plate</label>
                            <input type="text" id="license_plate" name="license_plate" class="input-field" placeholder="e.g. ABC123" required>
                            <p class="text-sm text-gray-500 mt-1">Enter the license plate exactly as it appears on your vehicle.</p>
                        </div>
                        
                        <div class="mb-6">
                            <label for="vehicle_type" class="form-label">Vehicle Type</label>
                            <select id="vehicle_type" name="vehicle_type" class="input-field" required>
                                <option value="">Select a vehicle type</option>
                                <option value="Sedan">Sedan</option>
                                <option value="SUV">SUV</option>
                                <option value="Truck">Truck</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="flex justify-between">
                            <a href="my-vehicles.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Add Vehicle</button>
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