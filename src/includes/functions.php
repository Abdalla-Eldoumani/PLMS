<?php

// Timezone match
date_default_timezone_set('America/Edmonton');

// Security functions

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

function calculateDuration($start, $end) {
    $startTime = new DateTime($start);
    $endTime = new DateTime($end);
    $interval = $startTime->diff($endTime);
    
    $hours = $interval->h + ($interval->days * 24);
    $minutes = $interval->i;
    
    return [
        'hours' => $hours,
        'minutes' => $minutes,
        'total_minutes' => ($hours * 60) + $minutes
    ];
}

function calculateParkingFee($hourlyRate, $startTime, $endTime) {
    $duration = calculateDuration($startTime, $endTime);
    $totalHours = $duration['hours'] + ($duration['minutes'] / 60);
    return round($hourlyRate * $totalHours, 2);
}

function formatDate($date, $format = 'M d, Y h:i A') {
    return date($format, strtotime($date));
}

function isBookingActive($endTime) {
    $now = new DateTime();
    $end = new DateTime($endTime);
    return $now < $end;
}

function getUserRole($userId, $db) {
    // Check if admin
    $admin = $db->query("SELECT role FROM admins WHERE user_id = ?", [$userId])->fetch();
    if ($admin) {
        return $admin['role'];
    }
    
    // Check if customer
    $customer = $db->query("SELECT user_id FROM customers WHERE user_id = ?", [$userId])->fetch();
    if ($customer) {
        return 'customer';
    }
    
    return 'unknown';
}

function logActivity($userId, $action, $details, $db) {
    $db->query(
        "INSERT INTO system_logs (user_id, action, details, log_time) VALUES (?, ?, ?, NOW())",
        [$userId, $action, $details]
    );
}
?>