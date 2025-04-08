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

// Handle cancel booking action
if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['id'])) {
    $booking_id = intval($_GET['id']);
    
    // Verify CSRF token
    if (!isset($_GET['token']) || !verifyCSRFToken($_GET['token'])) {
        $message = [
            'type' => 'error',
            'text' => 'Invalid request.'
        ];
    } else {
        // Check if booking belongs to user and is active
        $booking = $db->query(
            "SELECT * FROM bookings WHERE booking_id = ? AND customer_id = ? AND status = 'Active'", 
            [$booking_id, $user_id]
        )->fetch();
        
        if ($booking) {
            try {
                $db->beginTransaction();
                
                // Update booking status
                $db->query(
                    "UPDATE bookings SET status = 'Cancelled' WHERE booking_id = ?", 
                    [$booking_id]
                );
                
                // Update parking slot status
                $db->query(
                    "UPDATE parking_slots SET status = 'Available' WHERE slot_id = ?", 
                    [$booking['slot_id']]
                );
                
                $db->commit();
                
                $message = [
                    'type' => 'success',
                    'text' => 'Booking cancelled successfully.'
                ];
                
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollback();
                }
                $message = [
                    'type' => 'error',
                    'text' => 'An error occurred while cancelling the booking: ' . $e->getMessage()
                ];
            }
        } else {
            $message = [
                'type' => 'error',
                'text' => 'Booking not found, not active, or does not belong to you.'
            ];
        }
    }
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Count total bookings
$totalBookings = $db->query("SELECT COUNT(*) as count FROM bookings WHERE customer_id = ?", [$user_id])->fetch();
$totalPages = ceil($totalBookings['count'] / $perPage);

// Filter bookings by status
$statusFilter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$statusWhere = $statusFilter !== 'all' ? "AND b.status = '{$statusFilter}'" : "";

// FIXED: Use direct integer insertion for LIMIT and OFFSET to avoid prepared statement issues
$query = "SELECT b.*, ps.slot_number, pl.name AS lot_name, ps.type AS slot_type 
         FROM bookings b 
         JOIN parking_slots ps ON b.slot_id = ps.slot_id 
         JOIN parking_lots pl ON ps.lot_id = pl.lot_id 
         WHERE b.customer_id = ? {$statusWhere}
         ORDER BY b.start_time DESC 
         LIMIT {$perPage} OFFSET {$offset}";

$bookings = $db->query($query, [$user_id])->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Navigation Bar -->
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-6xl mx-auto">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800 mb-4 md:mb-0">My Bookings</h1>
                
                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
                    <!-- Status Filter -->
                    <div class="inline-block relative">
                        <select onchange="window.location.href='my-bookings.php?status='+this.value" class="block appearance-none bg-white border border-gray-300 text-gray-700 py-2 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Bookings</option>
                            <option value="Active" <?php echo $statusFilter === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Completed" <?php echo $statusFilter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled" <?php echo $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                        </div>
                    </div>
                    
                    <!-- New Booking Button -->
                    <a href="find-parking.php" class="bg-red-700 hover:bg-red-800 text-white font-bold py-2 px-4 rounded">
                        New Booking
                    </a>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="mb-6 <?php echo $message['type'] === 'error' ? 'bg-red-100 border-red-500 text-red-700' : 'bg-green-100 border-green-500 text-green-700'; ?> border-l-4 p-4" role="alert">
                    <p><?php echo $message['text']; ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Bookings List -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <?php if (count($bookings) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Booking Details
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date & Time
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Duration
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($bookings as $booking): ?>
                                    <?php 
                                    // Calculate if booking is active based on current time
                                    $now = new DateTime();
                                    $startTime = new DateTime($booking['start_time']);
                                    $endTime = new DateTime($booking['end_time']);
                                    $isOngoing = ($now >= $startTime && $now <= $endTime && $booking['status'] === 'Active');
                                    
                                    // Calculate duration
                                    $duration = calculateDuration($booking['start_time'], $booking['end_time']);
                                    ?>
                                    <tr class="<?php echo $isOngoing ? 'bg-blue-50' : ''; ?>">
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($booking['lot_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                Slot <?php echo htmlspecialchars($booking['slot_number']); ?> (<?php echo htmlspecialchars($booking['slot_type']); ?>)
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo formatDate($booking['start_time'], 'M d, Y'); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo formatDate($booking['start_time'], 'h:i A'); ?> - <?php echo formatDate($booking['end_time'], 'h:i A'); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo $duration['hours']; ?> h <?php echo $duration['minutes']; ?> m
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php 
                                                switch($booking['status']) {
                                                    case 'Active': echo $isOngoing ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; break;
                                                    case 'Completed': echo 'bg-gray-100 text-gray-800'; break;
                                                    case 'Cancelled': echo 'bg-red-100 text-red-800'; break;
                                                }
                                                ?>">
                                                <?php echo $isOngoing ? 'In Progress' : htmlspecialchars($booking['status']); ?>
                                            </span>
                                            <?php if ($isOngoing): ?>
                                                <p class="text-xs text-blue-600 mt-1">
                                                    <?php 
                                                    $timeLeft = $now->diff($endTime);
                                                    echo $timeLeft->format('%h hr %i min remaining');
                                                    ?>
                                                </p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="booking-details.php?id=<?php echo $booking['booking_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                                View
                                            </a>
                                            
                                            <?php if ($booking['status'] === 'Active' && $now < $startTime): ?>
                                                <a href="my-bookings.php?action=cancel&id=<?php echo $booking['booking_id']; ?>&token=<?php echo generateCSRFToken(); ?>" 
                                                   class="ml-3 text-red-600 hover:text-red-900 cancel-booking">
                                                    Cancel
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($isOngoing): ?>
                                                <a href="extend-booking.php?id=<?php echo $booking['booking_id']; ?>" 
                                                   class="ml-3 text-green-600 hover:text-green-900">
                                                    Extend
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700">
                                    Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $perPage, $totalBookings['count']); ?> of <?php echo $totalBookings['count']; ?> bookings
                                </div>
                                <div class="flex space-x-1">
                                    <?php if ($page > 1): ?>
                                        <a href="my-bookings.php?page=<?php echo ($page - 1); ?>&status=<?php echo $statusFilter; ?>" class="px-3 py-1 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-100">
                                            Previous
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <a href="my-bookings.php?page=<?php echo $i; ?>&status=<?php echo $statusFilter; ?>" 
                                           class="px-3 py-1 rounded-md text-sm font-medium <?php echo $i === $page ? 'bg-gray-800 text-white' : 'text-gray-700 bg-white hover:bg-gray-100'; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <a href="my-bookings.php?page=<?php echo ($page + 1); ?>&status=<?php echo $statusFilter; ?>" class="px-3 py-1 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-100">
                                            Next
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="p-6 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h3 class="mt-4 text-lg font-medium text-gray-900">No bookings found</h3>
                        <p class="mt-2 text-gray-500">
                            <?php if ($statusFilter !== 'all'): ?>
                                No <?php echo strtolower($statusFilter); ?> bookings found. Try changing the filter or create a new booking.
                            <?php else: ?>
                                You haven't made any bookings yet.
                            <?php endif; ?>
                        </p>
                        <div class="mt-6">
                            <a href="find-parking.php" class="bg-red-700 hover:bg-red-800 text-white font-bold py-2 px-4 rounded">
                                Find Parking
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script>
        // Confirmation for booking cancellation
        document.addEventListener('DOMContentLoaded', function() {
            const cancelButtons = document.querySelectorAll('.cancel-booking');
            cancelButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to cancel this booking? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>

    <script src="assets/js/main.js"></script>
</body>
</html>