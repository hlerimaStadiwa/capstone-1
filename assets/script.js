// assets/js/script.js - Complete and Safe
document.addEventListener('DOMContentLoaded', function() {
    console.log('Danborough Student Management System loaded successfully');
    
    initializeApp();
});

function initializeApp() {
    setupFormHandling();
    setupAutoHideMessages();
    setupConfirmations();
    setupSidebarToggle();
    setupBasicValidation();
}

// FORM HANDLING
function setupFormHandling() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = 'Processing...';
                submitBtn.setAttribute('data-original-text', originalText);
            }
        });
    });

    // Phone number formatting
    const phoneInputs = document.querySelectorAll('input[type="tel"], input[name*="phone"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
    });

    // Auto-capitalize names
    const nameInputs = document.querySelectorAll('input[name*="name"], input[name*="Name"]');
    nameInputs.forEach(input => {
        input.addEventListener('input', function() {
            if (this.value) {
                this.value = this.value.charAt(0).toUpperCase() + this.value.slice(1);
            }
        });
    });
}

// AUTO-HIDE MESSAGES
function setupAutoHideMessages() {
    const messages = document.querySelectorAll('.message');
    messages.forEach(message => {
        setTimeout(() => {
            message.style.opacity = '0';
            setTimeout(() => {
                if (message.parentNode) {
                    message.remove();
                }
            }, 500);
        }, 4000);
    });
}

// CONFIRMATION DIALOGS
function setupConfirmations() {
    // Delete confirmation
    const deleteLinks = document.querySelectorAll('a[href*="delete"], .btn-danger');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
    
    // Logout confirmation
    const logoutLinks = document.querySelectorAll('a[href*="logout"]');
    logoutLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });
    });
}

function setupSidebarToggle() {
    const toggle = document.querySelector('.sidebar-toggle');
    const overlay = document.querySelector('.site-overlay');
    const body = document.body;

    if (!toggle || !overlay) {
        return;
    }

    const closeSidebar = () => {
        body.classList.remove('sidebar-open');
    };

    toggle.addEventListener('click', function() {
        body.classList.toggle('sidebar-open');
    });

    overlay.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });
}

// BASIC VALIDATION
function setupBasicValidation() {
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const email = this.value;
            if (email && !isValidEmail(email)) {
                this.style.borderColor = '#e74c3c';
            } else {
                this.style.borderColor = '';
            }
        });
    });
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// GLOBAL UTILITY FUNCTIONS
window.confirmDelete = function() {
    return confirm('Are you sure you want to delete this record? This action cannot be undone.');
};

window.showLoading = function(button) {
    if (button) {
        const originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = 'Processing...';
        return originalText;
    }
};

window.hideLoading = function(button, originalText) {
    if (button && originalText) {
        button.disabled = false;
        button.innerHTML = originalText;
    }
};

// Table search functionality
window.filterTable = function(input, tableId) {
    const filter = input.value.toLowerCase();
    const table = document.getElementById(tableId);
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
        const cells = rows[i].getElementsByTagName('td');
        let showRow = false;
        
        for (let j = 0; j < cells.length; j++) {
            if (cells[j]) {
                const text = cells[j].textContent || cells[j].innerText;
                if (text.toLowerCase().indexOf(filter) > -1) {
                    showRow = true;
                    break;
                }
            }
        }
        
        rows[i].style.display = showRow ? '' : 'none';
    }
};