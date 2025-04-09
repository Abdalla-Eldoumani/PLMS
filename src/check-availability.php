<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if this is an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Direct access not allowed']);
    exit();
}

$db = new Database();
$response = ['success' => false, 'message' => '', 'available' => false];

// Get parameters from POST request
$lotId = filter_input(INPUT_POST, 'lot_id', FILTER_VALIDATE_INT);
$startTime = filter_input(INPUT_POST, 'start_time', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$endTime = filter_input(INPUT_POST, 'end_time', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$vehicleType = filter_input(INPUT_POST, 'vehicle_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// Validate inputs
if (!$lotId || !$startTime || !$endTime || !$vehicleType) {
    $response['message'] = 'Missing required parameters';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Convert times to DateTime objects for comparison
$startDateTime = new DateTime($startTime);
$endDateTime = new DateTime($endTime);

if ($endDateTime <= $startDateTime) {
    $response['message'] = 'End time must be after start time';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

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
         AND ps.status = 'Available'
         HAVING is_booked = 0
         ORDER BY ps.slot_number";

$availableSlots = $db->query($query, [
    $startTime, $startTime,
    $endTime, $endTime,
    $startTime, $endTime,
    $lotId,
    $vehicleType
])->fetchAll();

// Prepare response
$response['success'] = true;
$response['available'] = count($availableSlots) > 0;
$response['message'] = count($availableSlots) > 0 
    ? 'Found ' . count($availableSlots) . ' available slots' 
    : 'No available slots found for your criteria';
$response['slots'] = $availableSlots;

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response); 