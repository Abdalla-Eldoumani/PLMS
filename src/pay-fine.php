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
$alert = null;
$booking = null;

// Check if alert ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$alert_id = intval($_GET['id']);

// Get alert information and verify it belongs to the user
try {
    $alert = $db->query(
        "SELECT oa.*, b.booking_id, b.start_time, b.end_time, b.status AS booking_status, 
                ps.slot_number, pl.name AS lot_name
         FROM overstay_alerts oa
         JOIN bookings b ON oa.booking_id = b.booking_id
         JOIN parking_slots ps ON b.slot_id = ps.slot_id
         JOIN parking_lots pl ON ps.lot_id = pl.lot_id
         WHERE oa.alert_id = ? AND b.customer_id = ? AND oa.status = 'Pending'",
        [$alert_id, $user_id]
    )->fetch();

    if (!$alert) {
        $_SESSION['error_message'] = "Fine not found or already paid.";
        header("Location: dashboard.php");
        exit();
    }

} catch (Exception $e) {
    $_SESSION['error_message'] = "An error occurred while retrieving fine details.";
    header("Location: dashboard.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid form submission.";
    } else {
        // Get form data
        $payment_method = sanitize($_POST['payment_method']);
        $card_number = isset($_POST['card_number']) ? sanitize($_POST['card_number']) : '';
        $card_name = isset($_POST['card_name']) ? sanitize($_POST['card_name']) : '';
        $card_expiry = isset($_POST['card_expiry']) ? sanitize($_POST['card_expiry']) : '';
        $card_cvv = isset($_POST['card_cvv']) ? sanitize($_POST['card_cvv']) : '';
        
        // Validate payment method
        if (empty($payment_method)) {
            $errors[] = "Please select a payment method.";
        }
        
        // Validate card details for card payments
        if ($payment_method === 'card') {
            if (empty($card_number) || !preg_match('/^[0-9]{13,19}$/', str_replace(' ', '', $card_number))) {
                $errors[] = "Please enter a valid card number.";
            }
            
            if (empty($card_name)) {
                $errors[] = "Please enter the name on card.";
            }
            
            if (empty($card_expiry) || !preg_match('/^(0[1-9]|1[0-2])\/[0-9]{2}$/', $card_expiry)) {
                $errors[] = "Please enter a valid expiry date (MM/YY).";
            } else {
                // Check if card is expired
                list($exp_month, $exp_year) = explode('/', $card_expiry);
                $exp_year = '20' . $exp_year;
                $exp_timestamp = mktime(0, 0, 0, $exp_month, 1, $exp_year);
                
                if ($exp_timestamp < time()) {
                    $errors[] = "Your card has expired.";
                }
            }
            
            if (empty($card_cvv) || !preg_match('/^[0-9]{3,4}$/', $card_cvv)) {
                $errors[] = "Please enter a valid CVV code.";
            }
        }
        
        // Process payment if no errors
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                // Create payment record
                $payment_reference = generatePaymentReference();
                $payment_date = date('Y-m-d H:i:s');
                
                $db->query(
                    "INSERT INTO payments (booking_id, amount, payment_method, payment_reference, payment_date, payment_type)
                     VALUES (?, ?, ?, ?, ?, 'Fine')",
                    [$alert['booking_id'], $alert['fine_amount'], $payment_method, $payment_reference, $payment_date]
                );
                
                $payment_id = $db->getLastInsertId();
                
                // Update alert status
                $db->query(
                    "UPDATE overstay_alerts SET status = 'Paid', payment_id = ? WHERE alert_id = ?",
                    [$payment_id, $alert_id]
                );
                
                // Add fine payment to system logs
                logActivity($user_id, 'fine_payment', "User paid fine #{$alert_id} for booking #{$alert['booking_id']}", $db);
                
                $db->commit();
                $success = true;
                
            } catch (Exception $e) {
                $db->rollback();
                $errors[] = "Payment processing failed: " . $e->getMessage();
            }
        }
    }
}

// Helper function to generate a payment reference
function generatePaymentReference() {
    return 'FINE-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Fine - UCalgary Parking</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Navigation Bar -->
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-red-700 text-white px-6 py-4">
                    <h2 class="text-xl font-semibold">Pay Parking Fine</h2>
                </div>
                
                <div class="p-6">
                    <?php if ($success): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                            <p class="font-bold">Payment Successful!</p>
                            <p>Your fine has been paid successfully. Thank you for your payment.</p>
                            <div class="mt-4">
                                <a href="dashboard.php" class="bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded text-sm">
                                    Return to Dashboard
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
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

                        <div class="mb-6 bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                            <h3 class="font-semibold text-lg text-yellow-800 mb-2">Fine Details</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-600">Booking Date</p>
                                    <p class="font-medium"><?php echo formatDate($alert['start_time'], 'M d, Y'); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Location</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($alert['lot_name']); ?> - Slot <?php echo htmlspecialchars($alert['slot_number']); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Overstay Time</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($alert['overstay_minutes']); ?> minutes</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Fine Amount</p>
                                    <p class="font-medium text-lg text-red-600">$<?php echo number_format($alert['fine_amount'], 2); ?></p>
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="pay-fine.php?id=<?php echo $alert_id; ?>" id="payment-form">
                            <!-- CSRF token -->
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-6">
                                <h3 class="font-semibold text-lg mb-3">Payment Method</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <label class="flex items-center bg-white p-4 border rounded-lg cursor-pointer hover:border-red-500 transition-colors">
                                        <input type="radio" name="payment_method" value="card" class="mr-2" checked>
                                        <div>
                                            <span class="font-medium">Credit Card</span>
                                            <div class="flex mt-1 space-x-1">
                                                <img src="assets/images/visa.svg" alt="Visa" class="h-6">
                                                <img src="assets/images/mastercard.svg" alt="Mastercard" class="h-6">
                                                <img src="assets/images/amex.svg" alt="Amex" class="h-6">
                                            </div>
                                        </div>
                                    </label>
                                    
                                    <label class="flex items-center bg-white p-4 border rounded-lg cursor-pointer hover:border-red-500 transition-colors">
                                        <input type="radio" name="payment_method" value="paypal" class="mr-2">
                                        <div>
                                            <span class="font-medium">PayPal</span>
                                            <div class="mt-1">
                                                <img src="assets/images/paypal.svg" alt="PayPal" class="h-6">
                                            </div>
                                        </div>
                                    </label>
                                    
                                    <label class="flex items-center bg-white p-4 border rounded-lg cursor-pointer hover:border-red-500 transition-colors">
                                        <input type="radio" name="payment_method" value="mobile_pay" class="mr-2">
                                        <div>
                                            <span class="font-medium">Mobile Pay</span>
                                            <div class="mt-1">
                                                <img src="assets/images/apple-pay.svg" alt="Apple Pay" class="h-6">
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Credit Card Details - shown/hidden based on selection -->
                            <div id="card-details" class="mb-6 border-t border-gray-200 pt-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="card_number" class="form-label">Card Number</label>
                                        <input type="text" id="card_number" name="card_number" class="input-field" placeholder="1234 5678 9012 3456">
                                    </div>
                                    <div>
                                        <label for="card_name" class="form-label">Name on Card</label>
                                        <input type="text" id="card_name" name="card_name" class="input-field" placeholder="John Doe">
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="card_expiry" class="form-label">Expiry Date</label>
                                        <input type="text" id="card_expiry" name="card_expiry" class="input-field" placeholder="MM/YY">
                                    </div>
                                    <div>
                                        <label for="card_cvv" class="form-label">Security Code (CVV)</label>
                                        <input type="text" id="card_cvv" name="card_cvv" class="input-field" placeholder="123">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg mb-6">
                                <div class="flex justify-between mb-2">
                                    <span>Fine Amount:</span>
                                    <span>$<?php echo number_format($alert['fine_amount'], 2); ?></span>
                                </div>
                                <div class="flex justify-between mb-2">
                                    <span>Processing Fee:</span>
                                    <span>$0.00</span>
                                </div>
                                <div class="flex justify-between font-bold text-lg border-t border-gray-300 pt-2 mt-2">
                                    <span>Total:</span>
                                    <span>$<?php echo number_format($alert['fine_amount'], 2); ?></span>
                                </div>
                            </div>
                            
                            <div class="text-sm text-gray-600 mb-4">
                                <p>By proceeding with payment, you acknowledge that this fine is due to overstaying your parking booking duration.</p>
                            </div>
                            
                            <div class="flex justify-between">
                                <a href="dashboard.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 px-4 rounded">
                                    Cancel
                                </a>
                                <button type="submit" class="bg-red-700 hover:bg-red-800 text-white py-2 px-4 rounded">
                                    Pay $<?php echo number_format($alert['fine_amount'], 2); ?>
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Payment method toggle
            const paymentMethodRadios = document.querySelectorAll('input[name="payment_method"]');
            const cardDetailsDiv = document.getElementById('card-details');
            
            function toggleCardDetails() {
                const selectedMethod = document.querySelector('input[name="payment_method"]:checked').value;
                if (selectedMethod === 'card') {
                    cardDetailsDiv.style.display = 'block';
                } else {
                    cardDetailsDiv.style.display = 'none';
                }
            }
            
            // Initial state
            toggleCardDetails();
            
            // Event listeners
            paymentMethodRadios.forEach(function(radio) {
                radio.addEventListener('change', toggleCardDetails);
            });
            
            // Format card number input
            const cardNumberInput = document.getElementById('card_number');
            if (cardNumberInput) {
                cardNumberInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    let formattedValue = '';
                    
                    for (let i = 0; i < value.length; i++) {
                        if (i > 0 && i % 4 === 0) {
                            formattedValue += ' ';
                        }
                        formattedValue += value[i];
                    }
                    
                    e.target.value = formattedValue;
                });
            }
            
            // Format expiry date input
            const expiryInput = document.getElementById('card_expiry');
            if (expiryInput) {
                expiryInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    
                    if (value.length > 2) {
                        value = value.substr(0, 2) + '/' + value.substr(2, 2);
                    }
                    
                    e.target.value = value;
                });
            }
        });
    </script>
</body>
</html>