<?php
/**
 * Email notification utility for the parking management system
 * 
 * This file contains functions for sending various types of email notifications
 * to users regarding their parking bookings, payments, and other system events.
 */

/**
 * Send an email notification
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $textBody Plain text version of the email body
 * @return bool Whether the email was sent successfully
 */
function sendEmail($to, $subject, $body, $textBody = '') {
    // If no plain text version is provided, create one by stripping HTML
    if (empty($textBody)) {
        $textBody = strip_tags($body);
    }
    
    // Email headers
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: UCalgary Parking <noreply@ucalgary.ca>',
        'Reply-To: parking@ucalgary.ca',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // Send the email
    return mail($to, $subject, $body, implode("\r\n", $headers));
}

/**
 * Send a booking confirmation email
 * 
 * @param array $booking Booking details
 * @param array $user User details
 * @param array $vehicle Vehicle details
 * @return bool Whether the email was sent successfully
 */
function sendBookingConfirmation($booking, $user, $vehicle) {
    $subject = "Booking Confirmation - UCalgary Parking";
    
    // Format dates
    $startDate = date('F j, Y g:i A', strtotime($booking['start_time']));
    $endDate = date('F j, Y g:i A', strtotime($booking['end_time']));
    
    // Calculate duration
    $startDateTime = new DateTime($booking['start_time']);
    $endDateTime = new DateTime($booking['end_time']);
    $duration = $startDateTime->diff($endDateTime);
    $hours = $duration->h + ($duration->days * 24);
    
    // Create email body
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #D2001A; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            .button { display: inline-block; background-color: #D2001A; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
            .details { margin: 20px 0; }
            .details p { margin: 5px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Booking Confirmation</h1>
            </div>
            <div class='content'>
                <p>Dear {$user['name']},</p>
                <p>Thank you for booking a parking spot with UCalgary Parking Services. Your booking has been confirmed.</p>
                
                <div class='details'>
                    <h2>Booking Details</h2>
                    <p><strong>Booking ID:</strong> #" . str_pad($booking['booking_id'], 8, '0', STR_PAD_LEFT) . "</p>
                    <p><strong>Location:</strong> {$booking['lot_name']} - {$booking['location']}</p>
                    <p><strong>Slot Number:</strong> {$booking['slot_number']}</p>
                    <p><strong>Start Time:</strong> {$startDate}</p>
                    <p><strong>End Time:</strong> {$endDate}</p>
                    <p><strong>Duration:</strong> {$hours} hours</p>
                    <p><strong>Rate:</strong> $" . number_format($booking['hourly_rate'], 2) . "/hour</p>
                </div>
                
                <div class='details'>
                    <h2>Vehicle Information</h2>
                    <p><strong>License Plate:</strong> {$vehicle['license_plate']}</p>
                    <p><strong>Vehicle Type:</strong> {$vehicle['vehicle_type']}</p>
                </div>
                
                <p>You can view your booking details and manage your parking anytime by logging into your account.</p>
                
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='https://parking.ucalgary.ca/my-bookings.php' class='button'>View My Bookings</a>
                </p>
                
                <p>If you have any questions or need assistance, please contact our support team at parking@ucalgary.ca or call (403) 555-1234.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message, please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " University of Calgary Parking Services. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($user['email'], $subject, $body);
}

/**
 * Send a payment confirmation email
 * 
 * @param array $booking Booking details
 * @param array $user User details
 * @param array $payment Payment details
 * @return bool Whether the email was sent successfully
 */
function sendPaymentConfirmation($booking, $user, $payment) {
    $subject = "Payment Confirmation - UCalgary Parking";
    
    // Format dates
    $paymentDate = date('F j, Y g:i A', strtotime($payment['payment_date']));
    
    // Create email body
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #D2001A; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            .button { display: inline-block; background-color: #D2001A; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
            .details { margin: 20px 0; }
            .details p { margin: 5px 0; }
            .receipt { background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Payment Confirmation</h1>
            </div>
            <div class='content'>
                <p>Dear {$user['name']},</p>
                <p>Thank you for your payment to UCalgary Parking Services. Your payment has been processed successfully.</p>
                
                <div class='receipt'>
                    <h2>Payment Receipt</h2>
                    <p><strong>Receipt #:</strong> " . str_pad($booking['booking_id'], 8, '0', STR_PAD_LEFT) . "</p>
                    <p><strong>Payment Date:</strong> {$paymentDate}</p>
                    <p><strong>Payment Method:</strong> {$payment['payment_method']}</p>
                    <p><strong>Amount Paid:</strong> $" . number_format($payment['amount'], 2) . "</p>
                    <p><strong>Booking ID:</strong> #" . str_pad($booking['booking_id'], 8, '0', STR_PAD_LEFT) . "</p>
                </div>
                
                <p>You can view your payment receipt and booking details anytime by logging into your account.</p>
                
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='https://parking.ucalgary.ca/payment-receipt.php?booking_id={$booking['booking_id']}' class='button'>View Receipt</a>
                </p>
                
                <p>If you have any questions about your payment, please contact our support team at parking@ucalgary.ca or call (403) 555-1234.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message, please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " University of Calgary Parking Services. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($user['email'], $subject, $body);
}

/**
 * Send a booking extension confirmation email
 * 
 * @param array $booking Booking details
 * @param array $user User details
 * @param array $payment Payment details for the extension
 * @param string $oldEndTime Previous end time
 * @return bool Whether the email was sent successfully
 */
function sendExtensionConfirmation($booking, $user, $payment, $oldEndTime) {
    $subject = "Booking Extension Confirmation - UCalgary Parking";
    
    // Format dates
    $oldEndDate = date('F j, Y g:i A', strtotime($oldEndTime));
    $newEndDate = date('F j, Y g:i A', strtotime($booking['end_time']));
    $paymentDate = date('F j, Y g:i A', strtotime($payment['payment_date']));
    
    // Create email body
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #D2001A; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            .button { display: inline-block; background-color: #D2001A; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
            .details { margin: 20px 0; }
            .details p { margin: 5px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Booking Extension Confirmation</h1>
            </div>
            <div class='content'>
                <p>Dear {$user['name']},</p>
                <p>Your parking booking has been successfully extended. Here are the details of your extension:</p>
                
                <div class='details'>
                    <h2>Extension Details</h2>
                    <p><strong>Booking ID:</strong> #" . str_pad($booking['booking_id'], 8, '0', STR_PAD_LEFT) . "</p>
                    <p><strong>Location:</strong> {$booking['lot_name']} - {$booking['location']}</p>
                    <p><strong>Slot Number:</strong> {$booking['slot_number']}</p>
                    <p><strong>Previous End Time:</strong> {$oldEndDate}</p>
                    <p><strong>New End Time:</strong> {$newEndDate}</p>
                </div>
                
                <div class='details'>
                    <h2>Payment Details</h2>
                    <p><strong>Payment Date:</strong> {$paymentDate}</p>
                    <p><strong>Payment Method:</strong> {$payment['payment_method']}</p>
                    <p><strong>Amount Paid:</strong> $" . number_format($payment['amount'], 2) . "</p>
                </div>
                
                <p>You can view your updated booking details anytime by logging into your account.</p>
                
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='https://parking.ucalgary.ca/my-bookings.php' class='button'>View My Bookings</a>
                </p>
                
                <p>If you have any questions about your booking extension, please contact our support team at parking@ucalgary.ca or call (403) 555-1234.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message, please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " University of Calgary Parking Services. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($user['email'], $subject, $body);
}

/**
 * Send a booking reminder email
 * 
 * @param array $booking Booking details
 * @param array $user User details
 * @return bool Whether the email was sent successfully
 */
function sendBookingReminder($booking, $user) {
    $subject = "Upcoming Parking Booking Reminder - UCalgary Parking";
    
    // Format dates
    $startDate = date('F j, Y g:i A', strtotime($booking['start_time']));
    $endDate = date('F j, Y g:i A', strtotime($booking['end_time']));
    
    // Create email body
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #D2001A; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            .button { display: inline-block; background-color: #D2001A; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
            .details { margin: 20px 0; }
            .details p { margin: 5px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Booking Reminder</h1>
            </div>
            <div class='content'>
                <p>Dear {$user['name']},</p>
                <p>This is a reminder about your upcoming parking booking with UCalgary Parking Services.</p>
                
                <div class='details'>
                    <h2>Booking Details</h2>
                    <p><strong>Booking ID:</strong> #" . str_pad($booking['booking_id'], 8, '0', STR_PAD_LEFT) . "</p>
                    <p><strong>Location:</strong> {$booking['lot_name']} - {$booking['location']}</p>
                    <p><strong>Slot Number:</strong> {$booking['slot_number']}</p>
                    <p><strong>Start Time:</strong> {$startDate}</p>
                    <p><strong>End Time:</strong> {$endDate}</p>
                </div>
                
                <p>Please ensure your vehicle is parked in the designated spot within 15 minutes of your booking start time.</p>
                
                <p>If you need to extend your booking, you can do so through your account before your booking expires.</p>
                
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='https://parking.ucalgary.ca/my-bookings.php' class='button'>View My Bookings</a>
                </p>
                
                <p>If you have any questions about your booking, please contact our support team at parking@ucalgary.ca or call (403) 555-1234.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message, please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " University of Calgary Parking Services. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($user['email'], $subject, $body);
}

/**
 * Send an expiring booking notification
 * 
 * @param array $booking Booking details
 * @param array $user User details
 * @return bool Whether the email was sent successfully
 */
function sendExpiringBookingNotification($booking, $user) {
    $subject = "Your Parking Booking is Expiring Soon - UCalgary Parking";
    
    // Format dates
    $endDate = date('F j, Y g:i A', strtotime($booking['end_time']));
    
    // Create email body
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #D2001A; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            .button { display: inline-block; background-color: #D2001A; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
            .details { margin: 20px 0; }
            .details p { margin: 5px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Booking Expiring Soon</h1>
            </div>
            <div class='content'>
                <p>Dear {$user['name']},</p>
                <p>Your parking booking with UCalgary Parking Services is expiring soon.</p>
                
                <div class='details'>
                    <h2>Booking Details</h2>
                    <p><strong>Booking ID:</strong> #" . str_pad($booking['booking_id'], 8, '0', STR_PAD_LEFT) . "</p>
                    <p><strong>Location:</strong> {$booking['lot_name']} - {$booking['location']}</p>
                    <p><strong>Slot Number:</strong> {$booking['slot_number']}</p>
                    <p><strong>End Time:</strong> {$endDate}</p>
                </div>
                
                <p>If you need more time, you can extend your booking through your account before it expires.</p>
                
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='https://parking.ucalgary.ca/extend-booking.php?booking_id={$booking['booking_id']}' class='button'>Extend Booking</a>
                </p>
                
                <p>If you have already left the parking area, please ignore this notification.</p>
                
                <p>If you have any questions, please contact our support team at parking@ucalgary.ca or call (403) 555-1234.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message, please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " University of Calgary Parking Services. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($user['email'], $subject, $body);
}

/**
 * Send a booking cancellation email
 * 
 * @param array $booking Booking details
 * @param array $user User details
 * @param array $vehicle Vehicle details
 * @return bool Whether the email was sent successfully
 */
function sendCancellationEmail($booking, $user, $vehicle) {
    $subject = "Booking Cancellation Confirmation - UCalgary Parking";
    
    // Format dates
    $startDate = date('F j, Y g:i A', strtotime($booking['start_time']));
    $endDate = date('F j, Y g:i A', strtotime($booking['end_time']));
    $cancellationDate = date('F j, Y g:i A');
    
    // Create email body
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #D2001A; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            .button { display: inline-block; background-color: #D2001A; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
            .details { margin: 20px 0; }
            .details p { margin: 5px 0; }
            .refund { background-color: #f0f9f0; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #4CAF50; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Booking Cancellation Confirmation</h1>
            </div>
            <div class='content'>
                <p>Dear {$user['name']},</p>
                <p>Your parking booking with UCalgary Parking Services has been cancelled as requested.</p>
                
                <div class='details'>
                    <h2>Cancelled Booking Details</h2>
                    <p><strong>Booking ID:</strong> #" . str_pad($booking['booking_id'], 8, '0', STR_PAD_LEFT) . "</p>
                    <p><strong>Location:</strong> {$booking['lot_name']} - {$booking['location']}</p>
                    <p><strong>Slot Number:</strong> {$booking['slot_number']}</p>
                    <p><strong>Start Time:</strong> {$startDate}</p>
                    <p><strong>End Time:</strong> {$endDate}</p>
                    <p><strong>Cancellation Date:</strong> {$cancellationDate}</p>
                </div>
                
                <div class='details'>
                    <h2>Vehicle Information</h2>
                    <p><strong>License Plate:</strong> {$vehicle['license_plate']}</p>
                    <p><strong>Vehicle Type:</strong> {$vehicle['vehicle_type']}</p>
                </div>
                
                <div class='refund'>
                    <h2>Refund Information</h2>
                    <p>If you have already paid for this booking, a refund will be processed according to our cancellation policy.</p>
                    <p>The refund will be issued to the original payment method used for the booking.</p>
                    <p>Please allow 5-10 business days for the refund to appear in your account.</p>
                </div>
                
                <p>If you would like to make a new booking, you can do so through your account.</p>
                
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='https://parking.ucalgary.ca/find-parking.php' class='button'>Find Parking</a>
                </p>
                
                <p>If you have any questions about your cancellation, please contact our support team at parking@ucalgary.ca or call (403) 555-1234.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message, please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " University of Calgary Parking Services. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($user['email'], $subject, $body);
} 