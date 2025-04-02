<nav class="bg-red-700 text-white shadow-lg">
    <div class="container mx-auto px-4 py-3 flex justify-between items-center">
        <div class="flex items-center">
            <a class="text-xl font-bold" href="index.php">Parking Management System</a>
        </div>
        <div class="hidden md:flex space-x-6">
            <?php if(isset($_SESSION['user_id'])): ?>
                <?php 
                    $db = new Database();
                    $role = getUserRole($_SESSION['user_id'], $db);
                    
                    if ($role === 'admin' || $role === 'SuperAdmin'): 
                ?>
                    <a href="admin/dashboard.php" class="hover:text-gray-200">Admin Panel</a>
                <?php else: ?>
                    <a href="dashboard.php" class="hover:text-gray-200">Dashboard</a>
                    <a href="find-parking.php" class="hover:text-gray-200">Find Parking</a>
                    <a href="my-bookings.php" class="hover:text-gray-200">My Bookings</a>
                    <a href="my-vehicles.php" class="hover:text-gray-200">My Vehicles</a>
                <?php endif; ?>
                <div class="relative group">
                    <button class="flex items-center hover:text-gray-200">
                        Account
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden group-hover:block">
                        <a href="edit-profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Edit Profile</a>
                        <a href="payment-history.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Payment History</a>
                        <div class="border-t border-gray-100"></div>
                        <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="find-parking.php" class="hover:text-gray-200">Find Parking</a>
                <a href="rates.php" class="hover:text-gray-200">Parking Rates</a>
                <a href="login.php" class="hover:text-gray-200">Login</a>
                <a href="register.php" class="bg-white text-red-700 px-4 py-2 rounded hover:bg-gray-200">Register</a>
            <?php endif; ?>
        </div>
        
        <!-- Mobile menu button -->
        <div class="md:hidden">
            <button type="button" class="mobile-menu-button">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>
    </div>
    
    <!-- Mobile menu -->
    <div class="mobile-menu hidden md:hidden">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
            <?php if(isset($_SESSION['user_id'])): ?>
                <?php 
                    $db = new Database();
                    $role = getUserRole($_SESSION['user_id'], $db);
                    
                    if ($role === 'admin' || $role === 'SuperAdmin'): 
                ?>
                    <a href="admin/dashboard.php" class="block px-3 py-2 text-white hover:bg-red-600 rounded">Admin Panel</a>
                <?php else: ?>
                    <a href="dashboard.php" class="block px-3 py-2 text-white hover:bg-red-600 rounded">Dashboard</a>
                    <a href="find-parking.php" class="block px-3 py-2 text-white hover:bg-red-600 rounded">Find Parking</a>
                    <a href="my-bookings.php" class="block px-3 py-2 text-white hover:bg-red-600 rounded">My Bookings</a>
                    <a href="my-vehicles.php" class="block px-3 py-2 text-white hover:bg-red-600 rounded">My Vehicles</a>
                    <a href="edit-profile.php" class="block px-3 py-2 text-white hover:bg-red-600 rounded">Edit Profile</a>
                    <a href="payment-history.php" class="block px-3 py-2 text-white hover:bg-red-600 rounded">Payment History</a>
                    <a href="logout.php" class="block px-3 py-2 text-white hover:bg-red-600 rounded">Logout</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="find-parking.php" class="block px-3 py-2 text-white hover:bg-red-600 rounded">Find Parking</a>
                <a href="rates.php" class="block px-3 py-2 text-white hover:bg-red-600 rounded">Parking Rates</a>
                <a href="login.php" class="block px-3 py-2 text-white hover:bg-red-600 rounded">Login</a>
                <a href="register.php" class="block px-3 py-2 text-white hover:bg-red-600 rounded">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<script>
    document.querySelector('.mobile-menu-button').addEventListener('click', function() {
        document.querySelector('.mobile-menu').classList.toggle('hidden');
    });

    document.querySelector('.group').addEventListener('click', function() {
        this.querySelector('.absolute').classList.toggle('hidden');
    });
</script>