<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

$auth = new Auth();
if (!$auth->isAdmin()) {
    header("Location: ../login.php");
    exit();
}

$db = new Database();
$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle lot creation, update, or deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'create_lot') {
            $name = trim($_POST['name']);
            $location = trim($_POST['location']);

            if (empty($name) || empty($location)) {
                $message = "Name and location are required.";
                $messageType = "error";
            } else {
                try {
                    $db->query("INSERT INTO parking_lots (name, location, total_slots) VALUES (?, ?, 0)", [$name, $location]);
                    $lotId = $db->getLastInsertId();
                    $db->query("INSERT INTO admin_parking_lots (admin_id, lot_id) VALUES (?, ?)", [$userId, $lotId]);
                    $message = "Parking lot created successfully.";
                    $messageType = "success";
                } catch (Exception $e) {
                    $message = "Error creating lot: " . $e->getMessage();
                    $messageType = "error";
                }
            }
        }

        if ($action === 'update_lot') {
            $lotId = filter_input(INPUT_POST, 'lot_id', FILTER_VALIDATE_INT);
            $name = trim($_POST['name']);
            $location = trim($_POST['location']);

            if ($lotId && $name && $location) {
                $db->query("UPDATE parking_lots SET name = ?, location = ? WHERE lot_id = ?", [$name, $location, $lotId]);
                $message = "Lot updated successfully.";
                $messageType = "success";
            }
        }

        if ($action === 'delete_lot') {
            $lotId = filter_input(INPUT_POST, 'lot_id', FILTER_VALIDATE_INT);

            // Check for active bookings
            $active = $db->query("SELECT 1 FROM bookings b
                                  JOIN parking_slots ps ON b.slot_id = ps.slot_id
                                  WHERE ps.lot_id = ? AND b.status = 'Active'", [$lotId])->fetch();
            if ($active) {
                $message = "Cannot delete lot with active bookings.";
                $messageType = "error";
            } else {
                $db->query("DELETE FROM admin_parking_lots WHERE lot_id = ?", [$lotId]);
                $db->query("DELETE FROM parking_lots WHERE lot_id = ?", [$lotId]);
                $message = "Parking lot deleted.";
                $messageType = "success";
            }
        }
    }
}

$lots = $db->query("SELECT * FROM parking_lots pl JOIN admin_parking_lots apl ON pl.lot_id = apl.lot_id WHERE apl.admin_id = ? ORDER BY pl.lot_id DESC", [$userId])->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Lots - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script>
        function enableEdit(id) {
            document.querySelectorAll('.edit-' + id).forEach(el => el.removeAttribute('readonly'));
            document.getElementById('save-btn-' + id).classList.remove('hidden');
        }

        function confirmDelete(id) {
            if (confirm('Are you sure you want to delete this lot? Ensure no active bookings exist.')) {
                document.getElementById('delete-form-' + id).submit();
            }
        }
    </script>
</head>
<body class="bg-gray-100">
<?php include 'includes/admin-header.php'; ?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manage Parking Lots</h1>

    <?php if (!empty($message)): ?>
        <div class="mb-6 <?php echo $messageType === 'error' ? 'bg-red-100 text-red-700 border-red-500' : 'bg-green-100 text-green-700 border-green-500'; ?> border-l-4 p-4 rounded">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Add New Parking Lot</h2>
        <form method="POST" action="manage-lots.php" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <input type="hidden" name="action" value="create_lot">
            <input type="text" name="name" placeholder="Lot Name" required class="rounded border-gray-300 p-2">
            <input type="text" name="location" placeholder="Location" required class="rounded border-gray-300 p-2">
            <div class="md:col-span-3">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Create Lot</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lot ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Slots</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($lots as $lot): ?>
                    <tr>
                        <form method="POST" action="manage-lots.php">
                            <input type="hidden" name="action" value="update_lot">
                            <input type="hidden" name="lot_id" value="<?php echo $lot['lot_id']; ?>">
                            <td class="px-6 py-4">#<?php echo $lot['lot_id']; ?></td>
                            <td class="px-6 py-4"><input type="text" name="name" class="edit-<?php echo $lot['lot_id']; ?> border-none bg-transparent" readonly value="<?php echo htmlspecialchars($lot['name']); ?>"></td>
                            <td class="px-6 py-4"><input type="text" name="location" class="edit-<?php echo $lot['lot_id']; ?> border-none bg-transparent" readonly value="<?php echo htmlspecialchars($lot['location']); ?>"></td>
                            <td class="px-6 py-4"><?php echo $lot['total_slots']; ?></td>
                            <td class="px-6 py-4 space-x-2">
                                <button type="button" onclick="enableEdit(<?php echo $lot['lot_id']; ?>)" class="bg-yellow-500 text-white px-2 py-1 rounded">Edit</button>
                                <button type="submit" id="save-btn-<?php echo $lot['lot_id']; ?>" class="bg-green-600 text-white px-2 py-1 rounded hidden">Save</button>
                        </form>
                        <form id="delete-form-<?php echo $lot['lot_id']; ?>" method="POST" action="manage-lots.php" class="inline">
                            <input type="hidden" name="action" value="delete_lot">
                            <input type="hidden" name="lot_id" value="<?php echo $lot['lot_id']; ?>">
                            <button type="button" onclick="confirmDelete(<?php echo $lot['lot_id']; ?>)" class="bg-red-600 text-white px-2 py-1 rounded">Delete</button>
                        </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
