<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$db = new Database();
$errors = [];
$success = false;

// Get slot_id and times from URL
$slotId = filter_input(INPUT_GET, 'slot_id', FILTER_VALIDATE_INT);
$startTime = filter_input(INPUT_GET, 'start_time', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$endTime = filter_input(INPUT_GET, 'end_time', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// Validate inputs
if (!$slotId || !$startTime || !$endTime) {
    header("Location: find-parking.php");
    exit();
}

// Get slot details
$slot = $db->query("SELECT ps.*, pl.name as lot_name, pl.location 
                    FROM parking_slots ps 
                    JOIN parking_lots pl ON ps.lot_id = pl.lot_id 
                    WHERE ps.slot_id = ?", [$slotId])->fetch();

if (!$slot) {
    header("Location: find-parking.php");
    exit();
}

// Get user's vehicles
$vehicles = $db->query("SELECT * FROM vehicles WHERE owner_id = ?", [$_SESSION['user_id']])->fetchAll();

// Process booking form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicleId = filter_input(INPUT_POST, 'vehicle_id', FILTER_VALIDATE_INT);
    
    // Validation
    if (!$vehicleId) {
        $errors[] = "Please select a vehicle";
    }
    
    // Check if slot is still available
    $isAvailable = $db->query("SELECT COUNT(*) as count FROM bookings 
                              WHERE slot_id = ? 
                              AND status = 'Active'
                              AND (
                                  (start_time <= ? AND end_time >= ?) OR
                                  (start_time <= ? AND end_time >= ?) OR
                                  (start_time >= ? AND end_time <= ?)
                              )", [
        $slotId, $startTime, $startTime, $endTime, $endTime, $startTime, $endTime
    ])->fetch()['count'] === 0;
    
    if (!$isAvailable) {
        $errors[] = "Sorry, this slot is no longer available";
    }
    
    if (empty($errors)) {
        // Calculate duration and cost
        $startDateTime = new DateTime($startTime);
        $endDateTime = new DateTime($endTime);
        $duration = $startDateTime->diff($endDateTime);
        $hours = $duration->h + ($duration->days * 24);
        $totalCost = $hours * $slot['hourly_rate'];
        
        // Begin transaction
        $db->query("START TRANSACTION");
        
        try {
            // Create booking
            $db->query("INSERT INTO bookings (customer_id, slot_id, start_time, end_time, status) 
                       VALUES (?, ?, ?, ?, 'Active')", 
                       [$_SESSION['user_id'], $slotId, $startTime, $endTime]);
            
            $bookingId = $db->getLastInsertId();
            
            // Create payment record
            $db->query("INSERT INTO payments (booking_id, amount, payment_method) 
                       VALUES (?, ?, 'Card')", 
                       [$bookingId, $totalCost]);
            
            // Update slot status to Occupied
            $db->query("UPDATE parking_slots SET status = 'Occupied' WHERE slot_id = ?", [$slotId]);
            
            $db->query("COMMIT");
            
            // Redirect to payment page
            header("Location: payment.php?booking_id=" . $bookingId);
            exit();
            
        } catch (Exception $e) {
            $db->query("ROLLBACK");
            $errors[] = "Booking failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Parking - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="bg-gray-100 font-sans">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-800 mb-8">Confirm Your Booking</h1>
            
            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <ul class="list-disc pl-4">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Booking Summary -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Booking Summary</h2>
                <div class="space-y-4">
                    <div>
                        <p class="text-gray-600">Location</p>
                        <p class="font-medium"><?php echo htmlspecialchars($slot['lot_name']); ?> - <?php echo htmlspecialchars($slot['location']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Slot Number</p>
                        <p class="font-medium"><?php echo htmlspecialchars($slot['slot_number']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Start Time</p>
                        <p class="font-medium"><?php echo date('F j, Y g:i A', strtotime($startTime)); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">End Time</p>
                        <p class="font-medium"><?php echo date('F j, Y g:i A', strtotime($endTime)); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Rate</p>
                        <p class="font-medium">$<?php echo number_format($slot['hourly_rate'], 2); ?>/hour</p>
                    </div>
                    <?php
                    $startDateTime = new DateTime($startTime);
                    $endDateTime = new DateTime($endTime);
                    $duration = $startDateTime->diff($endDateTime);
                    $hours = $duration->h + ($duration->days * 24);
                    $totalCost = $hours * $slot['hourly_rate'];
                    ?>
                    <div>
                        <p class="text-gray-600">Duration</p>
                        <p class="font-medium"><?php echo $hours; ?> hours</p>
                    </div>
                    <div>
                        <p class="text-gray-600">Total Cost</p>
                        <p class="font-medium">$<?php echo number_format($totalCost, 2); ?></p>
                    </div>
                </div>
            </div>

            <!-- Booking Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Select Vehicle</h2>
                <form method="POST" action="booking.php?slot_id=<?php echo $slotId; ?>&start_time=<?php echo urlencode($startTime); ?>&end_time=<?php echo urlencode($endTime); ?>">
                    <div class="mb-6">
                        <label for="vehicle_id" class="block text-gray-700 font-medium mb-2">Vehicle</label>
                        <select id="vehicle_id" name="vehicle_id" required
                                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                            <option value="">Select your vehicle</option>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <option value="<?php echo $vehicle['vehicle_id']; ?>">
                                    <?php echo htmlspecialchars($vehicle['license_plate']); ?> - 
                                    <?php echo htmlspecialchars($vehicle['vehicle_type']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-4">
                        <a href="find-parking.php" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition">
                            Cancel
                        </a>
                        <button type="submit" class="bg-red-700 text-white px-6 py-2 rounded-lg hover:bg-red-800 transition">
                            Proceed to Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 