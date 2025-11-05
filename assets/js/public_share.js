/**
 * Public Share Page JavaScript
 * Handles user interactions for the public share view
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize particles background if available
    if (typeof particlesJS !== 'undefined' && document.getElementById('particles-js')) {
        initParticles();
    }
    
    // Set up copy buttons
    setupCopyButtons();
    
    // Set up download buttons
    setupDownloadButtons();
    
    // Handle password form if present
    const passwordForm = document.querySelector('.password-form');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            const passwordInput = this.querySelector('input[type="password"]');
            if (!passwordInput.value.trim()) {
                e.preventDefault();
                showToast('Please enter a password', 'error');
            }
        });
    }
});

/**
 * Initializes the particles background effect
 */
function initParticles() {
    particlesJS('particles-js', {
        particles: {
            number: {
                value: 80,
                density: {
                    enable: true,
                    value_area: 800
                }
            },
            color: {
                value: '#3b82f6'
            },
            opacity: {
                value: 0.5,
                random: true
            },
            size: {
                value: 3,
                random: true
            },
            line_linked: {
                enable: true,
                distance: 150,
                color: '#3b82f6',
                opacity: 0.4,
                width: 1
            },
            move: {
                enable: true,
                speed: 2,
                direction: 'none',
                random: true,
                straight: false,
                out_mode: 'out',
                bounce: false
            }
        },
        interactivity: {
            detect_on: 'canvas',
            events: {
                onhover: {
                    enable: true,
                    mode: 'grab'
                },
                onclick: {
                    enable: true,
                    mode: 'push'
                },
                resize: true
            }
        }
    });
}

/**
 * Sets up copy URL buttons
 */
function setupCopyButtons() {
    const copyButtons = document.querySelectorAll('.copy-button');
    
    copyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const url = this.getAttribute('data-url');
            copyToClipboard(url);
            showToast('URL copied to clipboard', 'success');
        });
    });
}

/**
 * Sets up file download buttons
 */
function setupDownloadButtons() {
    const downloadAllButton = document.querySelector('.download-all');
    if (downloadAllButton) {
        downloadAllButton.addEventListener('click', function() {
            const shareId = this.getAttribute('data-id');
            window.location.href = `download.php?share=${shareId}&all=1`;
        });
    }
}

/**
 * Copies text to clipboard
 * @param {string} text - The text to copy
 */
function copyToClipboard(text) {
    const tempInput = document.createElement('input');
    tempInput.style.position = 'absolute';
    tempInput.style.left = '-1000px';
    tempInput.value = text;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    document.body.removeChild(tempInput);
}

/**
 * Shows a toast notification
 * @param {string} message - The message to display
 * @param {string} type - The type of notification (success, error, info, warning)
 */
function showToast(message, type = 'info') {
    // Create toast container if it doesn't exist
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.position = 'fixed';
        container.style.bottom = '20px';
        container.style.right = '20px';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.style.padding = '12px 20px';
    toast.style.borderRadius = '4px';
    toast.style.marginTop = '10px';
    toast.style.animation = 'fadeIn 0.3s ease, fadeOut 0.3s ease 2.7s';
    
    // Set background color based on type
    switch (type) {
        case 'success':
            toast.style.background = 'linear-gradient(to right, #00b09b, #96c93d)';
            break;
        case 'error':
            toast.style.background = 'linear-gradient(to right, #ff5f6d, #ffc371)';
            break;
        case 'warning':
            toast.style.background = 'linear-gradient(to right, #f7b733, #fc4a1a)';
            break;
        default:
            toast.style.background = 'linear-gradient(to right, #2193b0, #6dd5ed)';
    }
    
    toast.style.color = 'white';
    toast.style.boxShadow = '0 3px 10px rgba(0,0,0,0.1)';
    toast.textContent = message;
    
    container.appendChild(toast);
    
    // Remove toast after animation
    setTimeout(() => {
        container.removeChild(toast);
        if (container.children.length === 0) {
            document.body.removeChild(container);
        }
    }, 3000);
}
