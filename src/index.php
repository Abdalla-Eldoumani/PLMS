<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCalgary Parking Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Navigation Bar -->
    <nav class="bg-red-700 text-white shadow-lg">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center">
                <a class="text-xl font-bold" href="index.php">Parking Management System</a>
            </div>
            <div class="space-x-4">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="hover:text-gray-200">Dashboard</a>
                    <a href="logout.php" class="bg-white text-red-700 px-4 py-2 rounded hover:bg-gray-200">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="hover:text-gray-200">Login</a>
                    <a href="register.php" class="bg-white text-red-700 px-4 py-2 rounded hover:bg-gray-200">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="relative">
        <div class="absolute inset-0 bg-gradient-to-r from-red-800 to-red-600 opacity-90"></div>
        <div class="relative container mx-auto px-4 py-16 flex flex-col items-center">
            <h2 class="text-4xl font-bold text-white mb-6 text-center">Find and Reserve Parking at University of Calgary</h2>
            <p class="text-xl text-white mb-8 text-center max-w-2xl">Real-time parking availability, advanced booking, and seamless payment solutions for students, faculty, and visitors.</p>
            
            <!-- Quick Action Buttons -->
            <div class="flex flex-wrap justify-center gap-4 mb-8">
                <a href="find-parking.php" class="bg-white text-red-700 px-6 py-3 rounded-lg font-bold hover:bg-gray-100 transition">Find Available Parking</a>
                <a href="register.php" class="bg-transparent border-2 border-white text-white px-6 py-3 rounded-lg font-bold hover:bg-white hover:text-red-700 transition">Create Account</a>
            </div>
        </div>
    </div>

    <!-- Real-Time Availability Section -->
    <div class="container mx-auto px-4 py-16">
        <h3 class="text-2xl font-bold text-center mb-8">Current Parking Availability</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php
            $parkingLots = [
                ['id' => 1, 'name' => 'Lot A - North Campus', 'available' => 23, 'total' => 50],
                ['id' => 2, 'name' => 'Lot B - South Campus', 'available' => 8, 'total' => 30]
            ];
            
            foreach ($parkingLots as $lot):
                $percentFull = (($lot['total'] - $lot['available']) / $lot['total']) * 100;
                $statusColor = $percentFull > 80 ? 'bg-red-500' : ($percentFull > 50 ? 'bg-yellow-500' : 'bg-green-500');
            ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <h4 class="text-xl font-semibold mb-2"><?php echo $lot['name']; ?></h4>
                    <div class="flex justify-between mb-2">
                        <span>Available Spaces:</span>
                        <span class="font-bold"><?php echo $lot['available']; ?> / <?php echo $lot['total']; ?></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="<?php echo $statusColor; ?> h-2.5 rounded-full" style="width: <?php echo $percentFull; ?>%"></div>
                    </div>
                    <a href="lot-details.php?id=<?php echo $lot['id']; ?>" class="block text-center mt-4 bg-gray-800 text-white py-2 rounded hover:bg-gray-700 transition">View Details</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-8">
            <a href="all-lots.php" class="text-red-700 font-semibold hover:underline">View All Parking Lots →</a>
        </div>
    </div>

    <!-- Features Section -->
    <div class="bg-gray-800 text-white py-16">
        <div class="container mx-auto px-4">
            <h3 class="text-2xl font-bold text-center mb-12">Smart Parking Management Features</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="flex flex-col items-center text-center">
                    <div class="bg-red-700 p-4 rounded-full mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h4 class="text-xl font-semibold mb-2">Real-Time Updates</h4>
                    <p>Get instant information about available parking spots across campus.</p>
                </div>
                
                <div class="flex flex-col items-center text-center">
                    <div class="bg-red-700 p-4 rounded-full mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h4 class="text-xl font-semibold mb-2">Advanced Booking</h4>
                    <p>Reserve your parking space ahead of time to ensure availability.</p>
                </div>
                
                <div class="flex flex-col items-center text-center">
                    <div class="bg-red-700 p-4 rounded-full mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>
                    <h4 class="text-xl font-semibold mb-2">Digital Payments</h4>
                    <p>Hassle-free online payments for bookings and subscription plans.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- User Type Selection -->
    <div class="container mx-auto px-4 py-16">
        <h3 class="text-2xl font-bold text-center mb-8">Choose Your Parking Solution</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden border border-gray-200 hover:border-red-500 transition">
                <div class="p-6">
                    <h4 class="text-xl font-semibold mb-4 text-center">Students</h4>
                    <ul class="mb-6 space-y-2">
                        <li class="flex items-center">
                            <svg class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Special semester rates
                        </li>
                        <li class="flex items-center">
                            <svg class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Hourly booking for class schedules
                        </li>
                        <li class="flex items-center">
                            <svg class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Off-peak discounts
                        </li>
                    </ul>
                    <a href="register.php?type=student" class="block text-center bg-red-700 text-white py-2 rounded hover:bg-red-800 transition">Student Registration</a>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-lg overflow-hidden border border-gray-200 hover:border-red-500 transition">
                <div class="p-6">
                    <h4 class="text-xl font-semibold mb-4 text-center">Faculty & Staff</h4>
                    <ul class="mb-6 space-y-2">
                        <li class="flex items-center">
                            <svg class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Monthly subscription plans
                        </li>
                        <li class="flex items-center">
                            <svg class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Reserved faculty parking zones
                        </li>
                        <li class="flex items-center">
                            <svg class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Extended parking hours
                        </li>
                    </ul>
                    <a href="register.php?type=faculty" class="block text-center bg-red-700 text-white py-2 rounded hover:bg-red-800 transition">Faculty Registration</a>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-lg overflow-hidden border border-gray-200 hover:border-red-500 transition">
                <div class="p-6">
                    <h4 class="text-xl font-semibold mb-4 text-center">Visitors</h4>
                    <ul class="mb-6 space-y-2">
                        <li class="flex items-center">
                            <svg class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Hourly and daily rates
                        </li>
                        <li class="flex items-center">
                            <svg class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Guest parking areas
                        </li>
                        <li class="flex items-center">
                            <svg class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            No registration required
                        </li>
                    </ul>
                    <a href="visitor-parking.php" class="block text-center bg-red-700 text-white py-2 rounded hover:bg-red-800 transition">Find Visitor Parking</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-8">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h5 class="font-bold mb-4">Parking Management</h5>
                    <ul class="space-y-2">
                        <li><a href="about.php" class="hover:text-red-400">About</a></li>
                        <li><a href="contact.php" class="hover:text-red-400">Contact Us</a></li>
                        <li><a href="faq.php" class="hover:text-red-400">FAQ</a></li>
                    </ul>
                </div>
                
                <div>
                    <h5 class="font-bold mb-4">Quick Links</h5>
                    <ul class="space-y-2">
                        <li><a href="find-parking.php" class="hover:text-red-400">Find Parking</a></li>
                        <li><a href="rates.php" class="hover:text-red-400">Parking Rates</a></li>
                        <li><a href="subscriptions.php" class="hover:text-red-400">Subscription Plans</a></li>
                    </ul>
                </div>
                
                <div>
                    <h5 class="font-bold mb-4">Legal</h5>
                    <ul class="space-y-2">
                        <li><a href="terms.php" class="hover:text-red-400">Terms of Service</a></li>
                        <li><a href="privacy.php" class="hover:text-red-400">Privacy Policy</a></li>
                    </ul>
                </div>
                
                <div>
                    <h5 class="font-bold mb-4">Contact</h5>
                    <p>University of Calgary</p>
                    <p>2500 University Dr NW</p>
                    <p>Calgary, AB T2N 1N4</p>
                    <p>Email: plms@ucalgary.ca</p>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-sm">
                <p>&copy; <?php echo date('Y'); ?> University of Calgary Parking Management System. All rights reserved.</p>
                <p class="mt-2">Developed for CPSC 471 Project</p>
            </div>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>