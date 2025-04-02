document.addEventListener('DOMContentLoaded', function() {
        
    // Add booking countdown timer
    const activeBookings = document.querySelectorAll('.booking-countdown');
    activeBookings.forEach(function(element) {
        const endTime = new Date(element.dataset.endTime).getTime();
        
        const countdownTimer = setInterval(function() {
            const now = new Date().getTime();
            const distance = endTime - now;
            
            if (distance < 0) {
                clearInterval(countdownTimer);
                element.innerHTML = "Booking has ended";
                return;
            }
            
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            element.innerHTML = `${hours}h ${minutes}m ${seconds}s remaining`;
            
            // Alert user when less than 30 minutes remaining
            if (distance < 30 * 60 * 1000 && !element.classList.contains('text-red-500')) {
                element.classList.add('text-red-500', 'font-bold');
            }
        }, 1000);
    });
    
    // Handle booking form submission with confirmation
    const bookingForm = document.getElementById('booking-form');
    if (bookingForm) {
        bookingForm.addEventListener('submit', function(e) {
            if (!confirm('Confirm your booking details before proceeding. Continue?')) {
                e.preventDefault();
            }
        });
    }
    
    // Handle vehicle deletion with confirmation
    const deleteVehicleButtons = document.querySelectorAll('.delete-vehicle');
    deleteVehicleButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to remove this vehicle?')) {
                e.preventDefault();
            }
        });
    });
    
    // Handle extend booking modals
    const extendButtons = document.querySelectorAll('.extend-booking-btn');
    extendButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const modal = document.getElementById('extend-modal');
            if (modal) {
                document.getElementById('booking-id').value = this.dataset.bookingId;
                modal.classList.remove('hidden');
            }
        });
    });
    
    // Close modals
    const closeModalButtons = document.querySelectorAll('.close-modal');
    closeModalButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(function(modal) {
                modal.classList.add('hidden');
            });
        });
    });
});