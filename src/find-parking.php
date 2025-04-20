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
$availableSlots = [];

// Get all parking lots for the dropdown
$parkingLots = $db->query("SELECT * FROM parking_lots ORDER BY name")->fetchAll();

// Process search form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lotId = filter_input(INPUT_POST, 'lot_id', FILTER_VALIDATE_INT);
    $startTime = filter_input(INPUT_POST, 'start_time', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $endTime = filter_input(INPUT_POST, 'end_time', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $vehicleType = filter_input(INPUT_POST, 'vehicle_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    
    // Validation
    if (!$lotId) $errors[] = "Please select a parking lot";
    if (!$startTime) $errors[] = "Please select a start time";
    if (!$endTime) $errors[] = "Please select an end time";
    if (!$vehicleType) $errors[] = "Please select your vehicle type";
    
    if (empty($errors)) {
        // Convert times to DateTime objects for comparison
        $startDateTime = new DateTime($startTime);
        $endDateTime = new DateTime($endTime);
        
        if ($endDateTime <= $startDateTime) {
            $errors[] = "End time must be after start time";
        } else {
            // Query available slots
            $query = "SELECT ps.*, pl.name as lot_name, pl.location,
                     (SELECT COUNT(*) FROM bookings b 
                      WHERE b.slot_id = ps.slot_id 
                      AND b.status = 'Active'
                      AND (
                          (b.start_time <= ? AND b.end_time >= ?) OR
                          (b.start_time <= ? AND b.end_time >= ?) OR
                          (b.start_time >= ? AND b.end_time <= ?)
                      )) as is_booked
                     FROM parking_slots ps
                     JOIN parking_lots pl ON ps.lot_id = pl.lot_id
                     WHERE ps.lot_id = ?
                     AND ps.type = ?
                     HAVING is_booked = 0
                     ORDER BY ps.slot_number";
            
            $availableSlots = $db->query($query, [
                $startTime, $startTime,
                $endTime, $endTime,
                $startTime, $endTime,
                $lotId,
                $vehicleType
            ])->fetchAll();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Parking - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <!-- Add Flatpickr for better date/time picking -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body class="bg-gray-100 font-sans">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-800 mb-8">Find Available Parking</h1>
            
            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <ul class="list-disc pl-4">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Search Form -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <form method="POST" action="find-parking.php" id="searchForm" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="lot_id" class="block text-gray-700 font-medium mb-2">Parking Lot</label>
                            <select id="lot_id" name="lot_id" required
                                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                                <option value="">Select a parking lot</option>
                                <?php foreach ($parkingLots as $lot): ?>
                                    <option value="<?php echo $lot['lot_id']; ?>"
                                            <?php echo (isset($_POST['lot_id']) && $_POST['lot_id'] == $lot['lot_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lot['name']); ?> - <?php echo htmlspecialchars($lot['location']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="vehicle_type" class="block text-gray-700 font-medium mb-2">Vehicle Type</label>
                            <select id="vehicle_type" name="vehicle_type" required
                                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                                <option value="">Select vehicle type</option>
                                <option value="Compact" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] === 'Compact') ? 'selected' : ''; ?>>Compact</option>
                                <option value="Large" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] === 'Large') ? 'selected' : ''; ?>>Large</option>
                                <option value="Handicapped" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] === 'Handicapped') ? 'selected' : ''; ?>>Handicapped</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="start_time" class="block text-gray-700 font-medium mb-2">Start Time</label>
                            <input type="text" id="start_time" name="start_time" required
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                                   value="<?php echo isset($_POST['start_time']) ? htmlspecialchars($_POST['start_time']) : ''; ?>">
                        </div>
                        
                        <div>
                            <label for="end_time" class="block text-gray-700 font-medium mb-2">End Time</label>
                            <input type="text" id="end_time" name="end_time" required
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                                   value="<?php echo isset($_POST['end_time']) ? htmlspecialchars($_POST['end_time']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" id="searchButton" class="bg-red-700 text-white px-6 py-2 rounded-lg hover:bg-red-800 transition">
                            Search Available Slots
                        </button>
                    </div>
                </form>
            </div>

            <!-- Results Section -->
            <div id="resultsSection" class="bg-white rounded-lg shadow-md p-6 <?php echo empty($availableSlots) && $_SERVER['REQUEST_METHOD'] !== 'POST' ? 'hidden' : ''; ?>">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Available Parking Slots</h2>
                <div id="loadingIndicator" class="text-center py-8 hidden">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-red-700"></div>
                    <p class="mt-2 text-gray-600">Checking availability...</p>
                </div>
                <div id="noResultsMessage" class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded hidden">
                    No available slots found for your criteria. Please try different times or locations.
                </div>
                <div id="availableSlotsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php if (!empty($availableSlots)): ?>
                        <?php foreach ($availableSlots as $slot): ?>
                            <div class="border rounded-lg p-4 hover:shadow-lg transition">
                                <h3 class="font-bold text-lg mb-2">Slot <?php echo htmlspecialchars($slot['slot_number']); ?></h3>
                                <p class="text-gray-600 mb-2"><?php echo htmlspecialchars($slot['lot_name']); ?></p>
                                <p class="text-gray-600 mb-2">Type: <?php echo htmlspecialchars($slot['type']); ?></p>
                                <p class="text-gray-600 mb-4">Rate: $<?php echo number_format($slot['hourly_rate'], 2); ?>/hour</p>
                                <a href="booking.php?slot_id=<?php echo $slot['slot_id']; ?>&start_time=<?php echo urlencode($startTime); ?>&end_time=<?php echo urlencode($endTime); ?>" 
                                   class="block text-center bg-red-700 text-white py-2 rounded hover:bg-red-800 transition">
                                    Book Now
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Flatpickr for date/time picking -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date/time pickers
        flatpickr("#start_time", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            time_24hr: true,
            minDate: new Date(),
            defaultDate: document.getElementById("start_time").value ? null : new Date()
        });

        flatpickr("#end_time", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            time_24hr: true,
            minDate: new Date()
        });

        // Real-time availability checking
        document.addEventListener('DOMContentLoaded', function() {
            const searchForm = document.getElementById('searchForm');
            const lotSelect = document.getElementById('lot_id');
            const vehicleTypeSelect = document.getElementById('vehicle_type');
            const startTimeInput = document.getElementById('start_time');
            const endTimeInput = document.getElementById('end_time');
            const searchButton = document.getElementById('searchButton');
            const resultsSection = document.getElementById('resultsSection');
            const loadingIndicator = document.getElementById('loadingIndicator');
            const noResultsMessage = document.getElementById('noResultsMessage');
            const availableSlotsContainer = document.getElementById('availableSlotsContainer');

            // Function to check availability
            function checkAvailability() {
                const lotId = lotSelect.value;
                const vehicleType = vehicleTypeSelect.value;
                const startTime = startTimeInput.value;
                const endTime = endTimeInput.value;

                // Only check if all fields are filled
                if (lotId && vehicleType && startTime && endTime) {
                    // Show loading indicator
                    resultsSection.classList.remove('hidden');
                    loadingIndicator.classList.remove('hidden');
                    noResultsMessage.classList.add('hidden');
                    availableSlotsContainer.innerHTML = '';

                    // Create form data
                    const formData = new FormData();
                    formData.append('lot_id', lotId);
                    formData.append('vehicle_type', vehicleType);
                    formData.append('start_time', startTime);
                    formData.append('end_time', endTime);

                    // Send AJAX request
                    fetch('check-availability.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Hide loading indicator
                        loadingIndicator.classList.add('hidden');

                        if (data.success) {
                            if (data.available) {
                                // Display available slots
                                noResultsMessage.classList.add('hidden');
                                availableSlotsContainer.innerHTML = '';

                                data.slots.forEach(slot => {
                                    const slotElement = document.createElement('div');
                                    slotElement.className = 'border rounded-lg p-4 hover:shadow-lg transition';
                                    slotElement.innerHTML = `
                                        <h3 class="font-bold text-lg mb-2">Slot ${slot.slot_number}</h3>
                                        <p class="text-gray-600 mb-2">${slot.lot_name}</p>
                                        <p class="text-gray-600 mb-2">Type: ${slot.type}</p>
                                        <p class="text-gray-600 mb-4">Rate: $${parseFloat(slot.hourly_rate).toFixed(2)}/hour</p>
                                        <a href="booking.php?slot_id=${slot.slot_id}&start_time=${encodeURIComponent(startTime)}&end_time=${encodeURIComponent(endTime)}" 
                                           class="block text-center bg-red-700 text-white py-2 rounded hover:bg-red-800 transition">
                                            Book Now
                                        </a>
                                    `;
                                    availableSlotsContainer.appendChild(slotElement);
                                });
                            } else {
                                // Show no results message
                                noResultsMessage.classList.remove('hidden');
                            }
                        } else {
                            // Show error message
                            noResultsMessage.textContent = data.message || 'An error occurred while checking availability';
                            noResultsMessage.classList.remove('hidden');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        loadingIndicator.classList.add('hidden');
                        noResultsMessage.textContent = 'An error occurred while checking availability';
                        noResultsMessage.classList.remove('hidden');
                    });
                }
            }

            // Add event listeners for real-time checking
            lotSelect.addEventListener('change', checkAvailability);
            vehicleTypeSelect.addEventListener('change', checkAvailability);
            startTimeInput.addEventListener('change', checkAvailability);
            endTimeInput.addEventListener('change', checkAvailability);

            // Prevent form submission and use AJAX instead
            searchForm.addEventListener('submit', function(e) {
                e.preventDefault();
                checkAvailability();
            });
        });
    </script>
</body>
</html> 