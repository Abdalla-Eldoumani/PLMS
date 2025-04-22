<?php
// Get the current page filename for active link styling
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<header class="bg-gray-800 text-white shadow-lg">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center py-3">
            <div class="flex items-center">
                <a href="dashboard.php" class="text-xl font-bold tracking-wider">
                    UCalgary Admin Panel
                </a>
                <nav class="hidden md:flex ml-10 space-x-8">
                    <a href="dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'text-red-400 border-b-2 border-red-400' : 'text-gray-300 hover:text-white'; ?> px-1 py-2 text-sm font-medium">
                        Dashboard
                    </a>
                    <a href="manage-lots.php" class="<?php echo $currentPage === 'manage-lots.php' ? 'text-red-400 border-b-2 border-red-400' : 'text-gray-300 hover:text-white'; ?> px-1 py-2 text-sm font-medium">
                        Parking Lots
                    </a>
                    <a href="manage-slots.php" class="<?php echo $currentPage === 'manage-slots.php' ? 'text-red-400 border-b-2 border-red-400' : 'text-gray-300 hover:text-white'; ?> px-1 py-2 text-sm font-medium">
                        Slots
                    </a>
                    <a href="bookings.php" class="<?php echo $currentPage === 'bookings.php' ? 'text-red-400 border-b-2 border-red-400' : 'text-gray-300 hover:text-white'; ?> px-1 py-2 text-sm font-medium">
                        Bookings
                    </a>
                    <a href="alerts.php" class="<?php echo $currentPage === 'alerts.php' ? 'text-red-400 border-b-2 border-red-400' : 'text-gray-300 hover:text-white'; ?> px-1 py-2 text-sm font-medium">
                        Alerts
                    </a>
                    <a href="reports.php" class="<?php echo $currentPage === 'reports.php' ? 'text-red-400 border-b-2 border-red-400' : 'text-gray-300 hover:text-white'; ?> px-1 py-2 text-sm font-medium">
                        Reports
                    </a>
                </nav>
            </div>
            <div class="flex items-center space-x-4">
                <div class="relative group">
                    <button type="button" class="flex items-center text-sm rounded-full focus:outline-none">
                        <span class="mr-2"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden group-hover:block">
                        <div class="border-t border-gray-100"></div>
                        <a href="../logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mobile menu -->
    <div class="md:hidden">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3 border-t border-gray-700">
            <a href="dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> block px-3 py-2 rounded-md text-base font-medium">
                Dashboard
            </a>
            <a href="manage-lots.php" class="<?php echo $currentPage === 'manage-lots.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> block px-3 py-2 rounded-md text-base font-medium">
                Parking Lots
            </a>
            <a href="manage-slots.php" class="<?php echo $currentPage === 'manage-slots.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> block px-3 py-2 rounded-md text-base font-medium">
                Slots
            </a>
            <a href="bookings.php" class="<?php echo $currentPage === 'bookings.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> block px-3 py-2 rounded-md text-base font-medium">
                Bookings
            </a>
            <a href="alerts.php" class="<?php echo $currentPage === 'alerts.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> block px-3 py-2 rounded-md text-base font-medium">
                Alerts
            </a>
            <a href="reports.php" class="<?php echo $currentPage === 'reports.php' ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> block px-3 py-2 rounded-md text-base font-medium">
                Reports
            </a>
        </div>
    </div>
</header>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const profileButton = document.querySelector('.flex.items-center.text-sm.rounded-full');
        const dropdownMenu = document.querySelector('.absolute.right-0.mt-2.w-48');
        
        // Toggle dropdown on click instead of relying only on hover
        if (profileButton && dropdownMenu) {
            profileButton.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownMenu.classList.toggle('hidden');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function() {
                if (!dropdownMenu.classList.contains('hidden')) {
                    dropdownMenu.classList.add('hidden');
                }
            });
            
            // Prevent clicks within dropdown from closing it
            dropdownMenu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    });
</script>