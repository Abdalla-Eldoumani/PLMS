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
$message = '';
$messageType = '';

// Get admin's managed parking lots
$lots = $db->query("SELECT pl.* FROM parking_lots pl
                    JOIN admin_parking_lots apl ON pl.lot_id = apl.lot_id
                    WHERE apl.admin_id = ?", [$userId])->fetchAll();

// Selected lot for filtering
$selectedLotId = filter_input(INPUT_GET, 'lot_id', FILTER_VALIDATE_INT) ?: ($lots[0]['lot_id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Add new slot
if ($action === 'add') {
    $lotId = filter_input(INPUT_POST, 'lot_id', FILTER_VALIDATE_INT);
    $prefix = strtoupper(trim($_POST['slot_prefix']));
    $start = filter_input(INPUT_POST, 'slot_start', FILTER_VALIDATE_INT);
    $count = filter_input(INPUT_POST, 'slot_count', FILTER_VALIDATE_INT);
    $type = $_POST['type'];
    $hourlyRate = filter_input(INPUT_POST, 'hourly_rate', FILTER_VALIDATE_FLOAT);

    if (!$lotId || !$prefix || !$start || !$count || empty($type) || !$hourlyRate) {
        $message = "All fields are required";
        $messageType = "error";
    } else {
        try {
            $added = 0;
            for ($i = 0; $i < $count; $i++) {
                $slotNum = $prefix . ($start + $i);

                // Check for duplicate
                $exists = $db->query("SELECT slot_id FROM parking_slots WHERE lot_id = ? AND slot_number = ?", [$lotId, $slotNum])->fetch();
                if ($exists) continue;

                $db->query("INSERT INTO parking_slots (lot_id, slot_number, status, type, hourly_rate)
                            VALUES (?, ?, 'Available', ?, ?)", [$lotId, $slotNum, $type, $hourlyRate]);
                $added++;
            }

            // Update total slots
            $db->query("UPDATE parking_lots SET total_slots = (
                            SELECT COUNT(*) FROM parking_slots WHERE lot_id = ?
                        ) WHERE lot_id = ?", [$lotId, $lotId]);

            $message = "$added slots added successfully.";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error adding slots: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

    
    // Edit slot
    else if ($action === 'edit') {
        $slotId = filter_input(INPUT_POST, 'slot_id', FILTER_VALIDATE_INT);
        $status = $_POST['status'];
        $type = $_POST['type'];
        $hourlyRate = filter_input(INPUT_POST, 'hourly_rate', FILTER_VALIDATE_FLOAT);
        
        if (!$slotId || empty($status) || empty($type) || !$hourlyRate) {
            $message = "All fields are required";
            $messageType = "error";
        } else {
            try {
                $db->query("UPDATE parking_slots 
                           SET status = ?, type = ?, hourly_rate = ? 
                           WHERE slot_id = ?", 
                           [$status, $type, $hourlyRate, $slotId]);
                
                $message = "Slot updated successfully";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Error updating slot: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
    
    // Delete slot
    else if ($action === 'delete') {
        $slotId = filter_input(INPUT_POST, 'slot_id', FILTER_VALIDATE_INT);
        $lotId = filter_input(INPUT_POST, 'lot_id', FILTER_VALIDATE_INT);
        
        if (!$slotId) {
            $message = "Invalid slot ID";
            $messageType = "error";
        } else {
            // Check if slot is being used in active bookings
            $activeBooking = $db->query("SELECT booking_id FROM bookings 
                                        WHERE slot_id = ? AND status = 'Active'", 
                                        [$slotId])->fetch();
            
            if ($activeBooking) {
                $message = "Cannot delete this slot as it is currently in use";
                $messageType = "error";
            } else {
                try {
                    $db->query("DELETE FROM parking_slots WHERE slot_id = ?", [$slotId]);
                    
                    // Update total slots count in parking lot
                    $db->query("UPDATE parking_lots SET total_slots = (
                                SELECT COUNT(*) FROM parking_slots WHERE lot_id = ?
                               ) WHERE lot_id = ?", [$lotId, $lotId]);
                    
                    $message = "Slot deleted successfully";
                    $messageType = "success";
                } catch (Exception $e) {
                    $message = "Error deleting slot: " . $e->getMessage();
                    $messageType = "error";
                }
            }
        }
    }
}

// Get slot types from enum
$slotTypes = ['Compact', 'Large', 'Handicapped'];
$slotStatuses = ['Available', 'Occupied', 'Reserved'];

// Get slots for the selected lot
$slots = [];
if ($selectedLotId) {
    $slots = $db->query("SELECT ps.*, pl.name as lot_name 
                         FROM parking_slots ps
                         JOIN parking_lots pl ON ps.lot_id = pl.lot_id
                         WHERE ps.lot_id = ? 
                         ORDER BY ps.slot_number", [$selectedLotId])->fetchAll();
}

// Get slot type distribution for the selected lot
$typeStats = $db->query("SELECT type, COUNT(*) as count FROM parking_slots 
                        WHERE lot_id = ? GROUP BY type", [$selectedLotId])->fetchAll();

// Get slot status distribution for the selected lot
$statusStats = $db->query("SELECT status, COUNT(*) as count FROM parking_slots 
                          WHERE lot_id = ? GROUP BY status", [$selectedLotId])->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Parking Slots - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <?php include 'includes/admin-header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Manage Parking Slots</h1>
            <button type="button" onclick="openModal('addSlotModal')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                Add New Slot
            </button>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="mb-6 <?php echo $messageType === 'error' ? 'bg-red-100 text-red-700 border-red-500' : 'bg-green-100 text-green-700 border-green-500'; ?> border-l-4 p-4 rounded">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Lot Selection -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Select Parking Lot</h2>
            <form method="GET" action="manage-slots.php" class="flex items-center">
                <select name="lot_id" id="lot_id" class="form-select rounded-md border-gray-300 shadow-sm mt-1 block w-full sm:w-auto" onchange="this.form.submit()">
                    <?php foreach ($lots as $lot): ?>
                        <option value="<?php echo $lot['lot_id']; ?>" <?php echo $selectedLotId == $lot['lot_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lot['name'] . ' - ' . $lot['location']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        
        <!-- Slot Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Type Distribution -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Slot Types</h2>
                <div class="relative h-64">
                    <canvas id="typeChart"></canvas>
                </div>
            </div>
            
            <!-- Status Distribution -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Slot Status</h2>
                <div class="relative h-64">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Slot Listing -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-800">All Slots in <?php echo htmlspecialchars($slots[0]['lot_name'] ?? 'Selected Lot'); ?></h2>
            </div>
            
            <?php if (count($slots) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Slot Number
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Type
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Hourly Rate
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($slots as $slot): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($slot['slot_number']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                        switch($slot['type']) {
                                            case 'Compact': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'Large': echo 'bg-purple-100 text-purple-800'; break;
                                            case 'Handicapped': echo 'bg-yellow-100 text-yellow-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                            <?php echo htmlspecialchars($slot['type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                        switch($slot['status']) {
                                            case 'Available': echo 'bg-green-100 text-green-800'; break;
                                            case 'Occupied': echo 'bg-red-100 text-red-800'; break;
                                            case 'Reserved': echo 'bg-blue-100 text-blue-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                            <?php echo htmlspecialchars($slot['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        $<?php echo number_format($slot['hourly_rate'], 2); ?>/hour
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button type="button" 
                                                onclick="editSlot(<?php echo htmlspecialchars(json_encode($slot)); ?>)" 
                                                class="text-indigo-600 hover:text-indigo-900 mr-3">
                                            Edit
                                        </button>
                                        <button type="button" 
                                                onclick="confirmDelete(<?php echo $slot['slot_id']; ?>, '<?php echo htmlspecialchars($slot['slot_number']); ?>', <?php echo $slot['lot_id']; ?>)" 
                                                class="text-red-600 hover:text-red-900">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-6 text-center">
                    <p class="text-gray-500">No slots found for this parking lot.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add Slot Modal -->
    <div id="addSlotModal" class="fixed inset-0 z-10 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form method="POST" action="manage-slots.php">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Parking Slot</h3>
                        
                        <div class="mb-4">
                            <label for="lot_id" class="block text-sm font-medium text-gray-700 mb-1">Parking Lot</label>
                            <select id="lot_id" name="lot_id" required class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <?php foreach ($lots as $lot): ?>
                                    <option value="<?php echo $lot['lot_id']; ?>" <?php echo $selectedLotId == $lot['lot_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lot['name'] . ' - ' . $lot['location']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Slot Prefix</label>
                            <input type="text" name="slot_prefix" placeholder="e.g., A" required class="mt-1 block w-full ...">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Starting Number</label>
                            <input type="number" name="slot_start" placeholder="e.g., 1" min="1" required class="mt-1 block w-full ...">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Number of Slots to Create</label>
                            <input type="number" name="slot_count" placeholder="e.g., 50" min="1" required class="mt-1 block w-full ...">
                        </div>

                        
                        <div class="mb-4">
                            <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select id="type" name="type" required class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <?php foreach ($slotTypes as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="hourly_rate" class="block text-sm font-medium text-gray-700 mb-1">Hourly Rate ($)</label>
                            <input type="number" id="hourly_rate" name="hourly_rate" step="0.01" min="0" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Add Slot
                        </button>
                        <button type="button" onclick="closeModal('addSlotModal')" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Slot Modal -->
    <div id="editSlotModal" class="fixed inset-0 z-10 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form method="POST" action="manage-slots.php">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="slot_id" id="edit_slot_id">
                    
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Parking Slot</h3>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Slot Number</label>
                            <p id="edit_slot_number" class="font-medium"></p>
                        </div>
                        
                        <div class="mb-4">
                            <label for="edit_type" class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select id="edit_type" name="type" required class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <?php foreach ($slotTypes as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="edit_status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="edit_status" name="status" required class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <?php foreach ($slotStatuses as $status): ?>
                                    <option value="<?php echo $status; ?>"><?php echo $status; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="edit_hourly_rate" class="block text-sm font-medium text-gray-700 mb-1">Hourly Rate ($)</label>
                            <input type="number" id="edit_hourly_rate" name="hourly_rate" step="0.01" min="0" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Save Changes
                        </button>
                        <button type="button" onclick="closeModal('editSlotModal')" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="fixed inset-0 z-10 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form method="POST" action="manage-slots.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="slot_id" id="delete_slot_id">
                    <input type="hidden" name="lot_id" id="delete_lot_id">
                    
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Delete Parking Slot</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        Are you sure you want to delete slot <span id="delete_slot_number" class="font-medium"></span>? This action cannot be undone.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Delete
                        </button>
                        <button type="button" onclick="closeModal('deleteConfirmModal')" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Modal functions
        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }
        
        // Edit slot
        function editSlot(slot) {
            document.getElementById('edit_slot_id').value = slot.slot_id;
            document.getElementById('edit_slot_number').textContent = slot.slot_number;
            document.getElementById('edit_type').value = slot.type;
            document.getElementById('edit_status').value = slot.status;
            document.getElementById('edit_hourly_rate').value = slot.hourly_rate;
            
            openModal('editSlotModal');
        }
        
        // Confirm delete
        function confirmDelete(slotId, slotNumber, lotId) {
            document.getElementById('delete_slot_id').value = slotId;
            document.getElementById('delete_slot_number').textContent = slotNumber;
            document.getElementById('delete_lot_id').value = lotId;
            
            openModal('deleteConfirmModal');
        }
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Type chart
            new Chart(document.getElementById('typeChart'), {
                type: 'pie',
                data: {
                    labels: [<?php echo implode(', ', array_map(function($stat) { return "'" . addslashes($stat['type']) . "'"; }, $typeStats)); ?>],
                    datasets: [{
                        data: [<?php echo implode(', ', array_column($typeStats, 'count')); ?>],
                        backgroundColor: ['#3B82F6', '#8B5CF6', '#F59E0B']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            
            // Status chart
            new Chart(document.getElementById('statusChart'), {
                type: 'pie',
                data: {
                    labels: [<?php echo implode(', ', array_map(function($stat) { return "'" . addslashes($stat['status']) . "'"; }, $statusStats)); ?>],
                    datasets: [{
                        data: [<?php echo implode(', ', array_column($statusStats, 'count')); ?>],
                        backgroundColor: ['#10B981', '#EF4444', '#3B82F6']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        });
    </script>
</body>
</html> 