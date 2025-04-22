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

// Get booking_id from URL
$bookingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$bookingId) {
    header("Location: my-bookings.php");
    exit();
}

// Get booking details
$booking = $db->query("SELECT b.*, ps.slot_number, pl.name as lot_name, pl.location, ps.hourly_rate,
                              p.amount, p.payment_method
                       FROM bookings b
                       JOIN parking_slots ps ON b.slot_id = ps.slot_id
                       JOIN parking_lots pl ON ps.lot_id = pl.lot_id
                       LEFT JOIN payments p ON b.booking_id = p.booking_id
                       WHERE b.booking_id = ? AND b.customer_id = ?", 
                       [$bookingId, $_SESSION['user_id']])->fetch();

if (!$booking) {
    header("Location: my-bookings.php");
    exit();
}

// Check if booking is active
if ($booking['status'] !== 'Active') {
    $errors[] = "This booking is not active and cannot be extended";
}

// Process extension form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $newEndTime = filter_input(INPUT_POST, 'new_end_time', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    
    // Validation
    if (!$newEndTime) {
        $errors[] = "Please select a new end time";
    } else {
        // Convert times to DateTime objects for comparison
        $currentEndDateTime = new DateTime($booking['end_time']);
        $newEndDateTime = new DateTime($newEndTime);
        
        if ($newEndDateTime <= $currentEndDateTime) {
            $errors[] = "New end time must be after current end time";
        } else {
            // Check if slot is available for the extended time
            $isAvailable = $db->query("SELECT COUNT(*) as count FROM bookings 
                                      WHERE slot_id = ? 
                                      AND booking_id != ?
                                      AND status = 'Active'
                                      AND (
                                          (start_time <= ? AND end_time >= ?) OR
                                          (start_time <= ? AND end_time >= ?) OR
                                          (start_time >= ? AND end_time <= ?)
                                      )", [
                $booking['slot_id'], 
                $bookingId,
                $newEndTime, $newEndTime,
                $newEndTime, $newEndTime,
                $booking['end_time'], $newEndTime
            ])->fetch()['count'] === 0;
            
            if (!$isAvailable) {
                $errors[] = "Sorry, this slot is not available for the extended time";
            } else {
                // Calculate additional duration and cost
                $currentEndDateTime = new DateTime($booking['end_time']);
                $newEndDateTime = new DateTime($newEndTime);
                $duration = $currentEndDateTime->diff($newEndDateTime);
                $hours = $duration->h + ($duration->days * 24);
                $additionalCost = $hours * $booking['hourly_rate'];
                
                // Begin transaction
                $db->query("START TRANSACTION");
                
                try {
                    // Update booking end time
                    $db->query("UPDATE bookings SET end_time = ? WHERE booking_id = ?", 
                               [$newEndTime, $bookingId]);
                    
                    // Create new payment record for extension
                    $db->query("INSERT INTO payments (booking_id, amount, payment_method) 
                               VALUES (?, ?, 'Card')", 
                               [$bookingId, $additionalCost]);
                    
                    // Ensure the slot status remains Occupied
                    $db->query("UPDATE parking_slots ps 
                               JOIN bookings b ON ps.slot_id = b.slot_id 
                               SET ps.status = 'Occupied' 
                               WHERE b.booking_id = ?", [$bookingId]);
                    
                    $db->query("COMMIT");
                    
                    // Redirect to payment page
                    header("Location: payment.php?booking_id=" . $bookingId);
                    exit();
                    
                } catch (Exception $e) {
                    $db->query("ROLLBACK");
                    $errors[] = "Extension failed: " . $e->getMessage();
                }
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
    <title>Extend Booking - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <!-- Add Flatpickr for better date/time picking -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body class="bg-gray-100 font-sans">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-800 mb-8">Extend Your Booking</h1>
            
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
                <h2 class="text-xl font-bold text-gray-800 mb-4">Current Booking Details</h2>
                <div class="space-y-4">
                    <div>
                        <p class="text-gray-600">Location</p>
                        <p class="font-medium"><?php echo htmlspecialchars($booking['lot_name']); ?> - <?php echo htmlspecialchars($booking['location']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Slot Number</p>
                        <p class="font-medium"><?php echo htmlspecialchars($booking['slot_number']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Start Time</p>
                        <p class="font-medium"><?php echo date('F j, Y g:i A', strtotime($booking['start_time'])); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Current End Time</p>
                        <p class="font-medium"><?php echo date('F j, Y g:i A', strtotime($booking['end_time'])); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Rate</p>
                        <p class="font-medium">$<?php echo number_format($booking['hourly_rate'], 2); ?>/hour</p>
                    </div>
                </div>
            </div>

            <!-- Extension Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Select New End Time</h2>
                <form method="POST" action="extend-booking.php?id=<?php echo $bookingId; ?>">
                    <div class="mb-6">
                        <label for="new_end_time" class="block text-gray-700 font-medium mb-2">New End Time</label>
                        <input type="text" id="new_end_time" name="new_end_time" required
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                               value="<?php echo isset($_POST['new_end_time']) ? htmlspecialchars($_POST['new_end_time']) : ''; ?>">
                        <p class="mt-2 text-sm text-gray-500">Select a time after your current end time</p>
                    </div>
                    
                    <div class="flex justify-end space-x-4">
                        <a href="my-bookings.php" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition">
                            Cancel
                        </a>
                        <button type="submit" class="bg-red-700 text-white px-6 py-2 rounded-lg hover:bg-red-800 transition">
                            Extend Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Flatpickr for date/time picking -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date/time picker
        flatpickr("#new_end_time", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            minDate: "<?php echo $booking['end_time']; ?>",
            time_24hr: true
        });
    </script>
</body>
</html>