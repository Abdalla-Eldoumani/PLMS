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

    // Mobile menu toggle
    const menuButton = document.querySelector('.mobile-menu-button');
    if (menuButton) {
        menuButton.addEventListener('click', function() {
            document.querySelector('.mobile-menu').classList.toggle('hidden');
        });
    }
    
    // Form validation
    const forms = document.querySelectorAll('form.validate');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            let hasError = false;
            
            // Check required fields
            const requiredFields = form.querySelectorAll('[required]');
            requiredFields.forEach(function(field) {
                if (!field.value.trim()) {
                    hasError = true;
                    field.classList.add('border-red-500');
                    
                    // Add error message if it doesn't exist
                    let errorMsg = field.parentNode.querySelector('.error-message');
                    if (!errorMsg) {
                        errorMsg = document.createElement('p');
                        errorMsg.className = 'text-red-500 text-xs mt-1 error-message';
                        errorMsg.textContent = 'This field is required';
                        field.parentNode.appendChild(errorMsg);
                    }
                } else {
                    field.classList.remove('border-red-500');
                    const errorMsg = field.parentNode.querySelector('.error-message');
                    if (errorMsg) {
                        errorMsg.remove();
                    }
                }
            });
            
            // Validate email format
            const emailFields = form.querySelectorAll('input[type="email"]');
            emailFields.forEach(function(field) {
                if (field.value.trim() && !isValidEmail(field.value)) {
                    hasError = true;
                    field.classList.add('border-red-500');
                    
                    // Add error message if it doesn't exist
                    let errorMsg = field.parentNode.querySelector('.error-message');
                    if (!errorMsg) {
                        errorMsg = document.createElement('p');
                        errorMsg.className = 'text-red-500 text-xs mt-1 error-message';
                        errorMsg.textContent = 'Please enter a valid email address';
                        field.parentNode.appendChild(errorMsg);
                    } else {
                        errorMsg.textContent = 'Please enter a valid email address';
                    }
                }
            });
            
            // Validate password length
            const passwordFields = form.querySelectorAll('input[name="password"]');
            passwordFields.forEach(function(field) {
                if (field.value.trim() && field.value.length < 8) {
                    hasError = true;
                    field.classList.add('border-red-500');
                    
                    // Add error message if it doesn't exist
                    let errorMsg = field.parentNode.querySelector('.error-message');
                    if (!errorMsg) {
                        errorMsg = document.createElement('p');
                        errorMsg.className = 'text-red-500 text-xs mt-1 error-message';
                        errorMsg.textContent = 'Password must be at least 8 characters';
                        field.parentNode.appendChild(errorMsg);
                    } else {
                        errorMsg.textContent = 'Password must be at least 8 characters';
                    }
                }
            });
            
            // Check password confirmation
            const password = form.querySelector('input[name="password"]');
            const confirmPassword = form.querySelector('input[name="confirm_password"]');
            if (password && confirmPassword && password.value !== confirmPassword.value) {
                hasError = true;
                confirmPassword.classList.add('border-red-500');
                
                // Add error message if it doesn't exist
                let errorMsg = confirmPassword.parentNode.querySelector('.error-message');
                if (!errorMsg) {
                    errorMsg = document.createElement('p');
                    errorMsg.className = 'text-red-500 text-xs mt-1 error-message';
                    errorMsg.textContent = 'Passwords do not match';
                    confirmPassword.parentNode.appendChild(errorMsg);
                } else {
                    errorMsg.textContent = 'Passwords do not match';
                }
            }
            
            if (hasError) {
                e.preventDefault();
            }
        });
    });
    
    // Helper function to validate email
    function isValidEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }
});