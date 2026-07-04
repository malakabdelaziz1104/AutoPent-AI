/**
 * ============================================
 * PENTEST SCANNER - Main JavaScript File
 * ============================================
 * Course Project: Part 1 - Lesson 5
 * This file contains all client-side JavaScript functionality
 */

// ============================================
// WAIT FOR DOM TO BE FULLY LOADED
// ============================================
// This ensures all HTML elements are available before we try to use them
document.addEventListener('DOMContentLoaded', function () {

    // Initialize all features
    initNavbar();
    initSmoothScroll();
    initMobileMenu();
    initAnimations();
    initUserMenu();

    console.log('🛡️ PenTest Scanner initialized successfully!');
});

// ============================================
// NAVBAR FUNCTIONALITY
// ============================================
// Changes navbar background on scroll for better visibility

function initNavbar() {
    const navbar = document.getElementById('navbar');

    // Check if navbar exists
    if (!navbar) return;

    // Add scroll event listener
    window.addEventListener('scroll', function () {
        // If scrolled more than 50 pixels, add 'scrolled' class
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
}

// ============================================
// SMOOTH SCROLLING
// ============================================
// Makes navigation links scroll smoothly to their sections

function initSmoothScroll() {
    // Get all links that start with #
    const links = document.querySelectorAll('a[href^="#"]');

    links.forEach(function (link) {
        link.addEventListener('click', function (e) {
            // Get the target section ID from href
            const targetId = this.getAttribute('href');

            // Skip if it's just "#"
            if (targetId === '#') return;

            // Find the target element
            const targetElement = document.querySelector(targetId);

            if (targetElement) {
                // Prevent default jump behavior
                e.preventDefault();

                // Calculate offset (account for fixed navbar)
                const navbarHeight = document.getElementById('navbar').offsetHeight;
                const targetPosition = targetElement.offsetTop - navbarHeight;

                // Smooth scroll to target
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });

                // Close mobile menu if open
                closeMobileMenu();
            }
        });
    });
}

// ============================================
// MOBILE MENU FUNCTIONALITY
// ============================================
// Handles the hamburger menu on mobile devices

function initMobileMenu() {
    const toggle = document.getElementById('mobileMenuToggle');
    const navMenu = document.getElementById('navMenu');

    // Check if elements exist
    if (!toggle || !navMenu) return;

    // Toggle menu on button click
    toggle.addEventListener('click', function () {
        this.classList.toggle('active');
        navMenu.classList.toggle('active');

        // Prevent body scrolling when menu is open
        document.body.classList.toggle('menu-open');
    });

    // Close menu when clicking outside
    document.addEventListener('click', function (e) {
        if (!toggle.contains(e.target) && !navMenu.contains(e.target)) {
            closeMobileMenu();
        }
    });
}

// Helper function to close mobile menu
function closeMobileMenu() {
    const toggle = document.getElementById('mobileMenuToggle');
    const navMenu = document.getElementById('navMenu');

    if (toggle && navMenu) {
        toggle.classList.remove('active');
        navMenu.classList.remove('active');
        document.body.classList.remove('menu-open');
    }
}

// ============================================
    function initUserMenu() {
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userDropdown');

    if (!userMenuBtn || !userDropdown) return;

    userMenuBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        userDropdown.classList.toggle('active');
    });

    document.addEventListener('click', function(e) {
        if (!userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
            userDropdown.classList.remove('active');
        }
    });
    }
// IMPORTANT: لازم تتنادى
document.addEventListener("DOMContentLoaded", initUserMenu);

// ============================================
// SCROLL ANIMATIONS
// ============================================
// Animates elements when they come into view

function initAnimations() {
    // Elements to animate when they enter viewport
    const animatedElements = document.querySelectorAll('.card, .section-header');

    // Check if IntersectionObserver is supported
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    // Add animation class when element is visible
                    entry.target.classList.add('fade-in');

                    // Stop observing after animation
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1, // Trigger when 10% of element is visible
            rootMargin: '0px 0px -50px 0px' // Offset from bottom
        });

        // Observe all animated elements
        animatedElements.forEach(function (el) {
            observer.observe(el);
        });
    } else {
        // Fallback for older browsers: show all elements
        animatedElements.forEach(function (el) {
            el.classList.add('fade-in');
        });
    }
}

// ============================================
// FORM VALIDATION UTILITIES
// ============================================
// Reusable form validation functions (will be used in login/signup)

/**
 * Validate email format
 * @param {string} email - Email to validate
 * @returns {boolean} - True if valid
 */
function isValidEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

/**
 * Validate URL format
 * @param {string} url - URL to validate
 * @returns {boolean} - True if valid
 */
function isValidURL(url) {
    try {
        new URL(url);
        return true;
    } catch (e) {
        return false;
    }
}

/**
 * Validate password strength
 * @param {string} password - Password to validate
 * @returns {object} - {valid: boolean, message: string}
 */
function validatePassword(password) {
    const result = { valid: true, message: 'Password is strong' };

    if (password.length < 8) {
        result.valid = false;
        result.message = 'Password must be at least 8 characters';
        return result;
    }

    if (!/[A-Z]/.test(password)) {
        result.valid = false;
        result.message = 'Password must contain an uppercase letter';
        return result;
    }

    if (!/[a-z]/.test(password)) {
        result.valid = false;
        result.message = 'Password must contain a lowercase letter';
        return result;
    }

    if (!/[0-9]/.test(password)) {
        result.valid = false;
        result.message = 'Password must contain a number';
        return result;
    }

    return result;
}

/**
 * Show error message on form field
 * @param {HTMLElement} input - The input element
 * @param {string} message - Error message to show
 */
function showError(input, message) {
    // Remove any existing error
    clearError(input);

    // Add error class to input
    input.classList.add('is-invalid');

    // Create error message element
    const error = document.createElement('span');
    error.className = 'form-error';
    error.textContent = message;

    // Insert after input
    input.parentNode.insertBefore(error, input.nextSibling);
}

/**
 * Clear error message from form field
 * @param {HTMLElement} input - The input element
 */
function clearError(input) {
    input.classList.remove('is-invalid');
    const error = input.parentNode.querySelector('.form-error');
    if (error) {
        error.remove();
    }
}

/**
 * Show success state on form field
 * @param {HTMLElement} input - The input element
 */
function showSuccess(input) {
    clearError(input);
    input.classList.add('is-valid');
}

// ============================================
// ALERT/NOTIFICATION SYSTEM
// ============================================
// Shows temporary alert messages to users

/**
 * Show a notification/alert message
 * @param {string} message - Message to display
 * @param {string} type - 'success', 'error', 'warning', 'info'
 * @param {number} duration - How long to show (ms), default 5000
 */
function showAlert(message, type = 'info', duration = 5000) {
    // Create alert container if it doesn't exist
    let container = document.getElementById('alertContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'alertContainer';
        container.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        `;
        document.body.appendChild(container);
    }

    // Create alert element
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} fade-in`;
    alert.innerHTML = `
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;cursor:pointer;padding:0;margin-left:10px;">✕</button>
    `;

    // Add to container
    container.appendChild(alert);

    // Auto-remove after duration
    setTimeout(function () {
        if (alert.parentElement) {
            alert.style.opacity = '0';
            setTimeout(function () {
                alert.remove();
            }, 300);
        }
    }, duration);
}

// ============================================
// PASSWORD VISIBILITY TOGGLE
// ============================================
// Toggles password field between hidden and visible

/**
 * Toggle password visibility
 * @param {string} inputId - ID of the password input
 * @param {HTMLElement} toggleBtn - The toggle button element
 */
function togglePasswordVisibility(inputId, toggleBtn) {
    const input = document.getElementById(inputId);

    if (input.type === 'password') {
        input.type = 'text';
        toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
        input.type = 'password';
        toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
    }
}

// ============================================
// LOADING STATE UTILITIES
// ============================================
// Shows loading spinner on buttons during async operations

/**
 * Set loading state on button
 * @param {HTMLElement} button - The button element
 * @param {boolean} isLoading - Whether to show loading state
 */
function setButtonLoading(button, isLoading) {
    if (isLoading) {
        // Store original text
        button.dataset.originalText = button.innerHTML;

        // Replace with spinner
        button.innerHTML = '<span class="spinner" style="width:20px;height:20px;border-width:2px;"></span> Loading...';
        button.disabled = true;
    } else {
        // Restore original text
        button.innerHTML = button.dataset.originalText || button.innerHTML;
        button.disabled = false;
    }
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

/**
 * Debounce function - limits how often a function can fire
 * Useful for scroll/resize events
 * @param {Function} func - Function to debounce
 * @param {number} wait - Milliseconds to wait
 */
function debounce(func, wait = 250) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

/**
 * Format date to readable string
 * @param {string|Date} date - Date to format
 * @returns {string} - Formatted date
 */
function formatDate(date) {
    const d = new Date(date);
    return d.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Copy text to clipboard
 * @param {string} text - Text to copy
 */
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        showAlert('Copied to clipboard!', 'success', 2000);
    } catch (err) {
        showAlert('Failed to copy', 'error');
    }
}

// ============================================
// CONSOLE EASTER EGG
// ============================================
// A little surprise for developers who check the console

console.log(`
%c🛡️ PenTest Scanner %c
%cWelcome, fellow developer! 
If you're here to learn, you're in the right place.
If you're here to hack... we're watching you! 👀

%cCourse Project - Part 1
`,
    'color: #00d4ff; font-size: 24px; font-weight: bold;',
    '',
    'color: #9ca3af; font-size: 14px;',
    'color: #a855f7; font-size: 12px; font-style: italic;'
);
