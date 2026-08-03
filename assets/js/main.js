// assets/js/main.js
// Environmental Reporting System - Main JavaScript

// DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    initMobileMenu();
    initFormValidation();
    initNotifications();
    initConfirmDialogs();
    initDataTables();
});

// Mobile Menu Toggle
function initMobileMenu() {
    const menuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.querySelector('.sidebar');
    
    if (menuBtn && sidebar) {
        menuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }
}

// Form Validation
function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
}

function validateForm(form) {
    let isValid = true;
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            isValid = false;
            showFieldError(input, 'This field is required');
        } else {
            clearFieldError(input);
        }
        
        // Email validation
        if (input.type === 'email' && input.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(input.value)) {
                isValid = false;
                showFieldError(input, 'Please enter a valid email address');
            }
        }
        
        // Password validation
        if (input.type === 'password' && input.id === 'password') {
            if (input.value.length < 6) {
                isValid = false;
                showFieldError(input, 'Password must be at least 6 characters');
            }
        }
        
        // Confirm password
        if (input.id === 'confirm_password') {
            const password = document.getElementById('password');
            if (password && input.value !== password.value) {
                isValid = false;
                showFieldError(input, 'Passwords do not match');
            }
        }
    });
    
    return isValid;
}

function showFieldError(input, message) {
    input.classList.add('border-red-500');
    let error = input.nextElementSibling;
    if (!error || !error.classList.contains('error-message')) {
        error = document.createElement('p');
        error.className = 'error-message text-red-500 text-xs mt-1';
        input.parentNode.insertBefore(error, input.nextSibling);
    }
    error.textContent = message;
}

function clearFieldError(input) {
    input.classList.remove('border-red-500');
    const error = input.nextElementSibling;
    if (error && error.classList.contains('error-message')) {
        error.remove();
    }
}

// Notifications
function initNotifications() {
    const notifications = document.querySelectorAll('.notification');
    notifications.forEach(notification => {
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    });
}

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `fixed bottom-4 right-4 z-50 px-4 py-2 rounded-lg shadow-lg text-white animate-slide-up ${
        type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'
    }`;
    toast.innerHTML = `
        <div class="flex items-center space-x-2">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Confirm Dialogs
function initConfirmDialogs() {
    const deleteButtons = document.querySelectorAll('[data-confirm]');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || 'Are you sure?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
}

// Data Tables
function initDataTables() {
    const tables = document.querySelectorAll('.data-table');
    
    tables.forEach(table => {
        const rows = table.querySelectorAll('tbody tr');
        if (rows.length > 10) {
            addTableSearch(table);
            addTablePagination(table, rows);
        }
    });
}

function addTableSearch(table) {
    const searchHtml = `
        <div class="mb-4 flex justify-end">
            <input type="text" class="table-search px-4 py-2 border rounded-lg w-64" placeholder="Search...">
        </div>
    `;
    table.insertAdjacentHTML('beforebegin', searchHtml);
    
    const searchInput = table.previousElementSibling.querySelector('.table-search');
    searchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });
}

function addTablePagination(table, rows) {
    const rowsPerPage = 10;
    const pageCount = Math.ceil(rows.length / rowsPerPage);
    
    const paginationHtml = `
        <div class="table-pagination flex justify-between items-center mt-4">
            <div class="text-sm text-gray-600">
                Showing <span class="start-entry">1</span> to <span class="end-entry">${Math.min(rowsPerPage, rows.length)}</span> of <span class="total-entries">${rows.length}</span> entries
            </div>
            <div class="flex space-x-2">
                <button class="prev-page px-3 py-1 border rounded hover:bg-gray-100" ${pageCount <= 1 ? 'disabled' : ''}>Previous</button>
                <span class="page-info px-3 py-1">Page 1 of ${pageCount}</span>
                <button class="next-page px-3 py-1 border rounded hover:bg-gray-100" ${pageCount <= 1 ? 'disabled' : ''}>Next</button>
            </div>
        </div>
    `;
    table.insertAdjacentHTML('afterend', paginationHtml);
    
    let currentPage = 1;
    
    function showPage(page) {
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        
        rows.forEach((row, index) => {
            row.style.display = (index >= start && index < end) ? '' : 'none';
        });
        
        const paginationDiv = table.nextElementSibling;
        paginationDiv.querySelector('.start-entry').textContent = start + 1;
        paginationDiv.querySelector('.end-entry').textContent = Math.min(end, rows.length);
        paginationDiv.querySelector('.page-info').textContent = `Page ${page} of ${pageCount}`;
        
        const prevBtn = paginationDiv.querySelector('.prev-page');
        const nextBtn = paginationDiv.querySelector('.next-page');
        prevBtn.disabled = page === 1;
        nextBtn.disabled = page === pageCount;
    }
    
    const paginationDiv = table.nextElementSibling;
    paginationDiv.querySelector('.prev-page').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            showPage(currentPage);
        }
    });
    
    paginationDiv.querySelector('.next-page').addEventListener('click', () => {
        if (currentPage < pageCount) {
            currentPage++;
            showPage(currentPage);
        }
    });
    
    showPage(1);
}

// AJAX Helper
async function fetchJSON(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            ...options
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        return await response.json();
    } catch (error) {
        console.error('AJAX Error:', error);
        showToast('Request failed', 'error');
        throw error;
    }
}

// Theme Toggle (Dark/Light)
function initThemeToggle() {
    const themeToggle = document.getElementById('themeToggle');
    if (!themeToggle) return;
    
    const currentTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', currentTheme);
    
    themeToggle.addEventListener('click', () => {
        const newTheme = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
    });
}

// Print Function
function printElement(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Print Report</title>
                <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
            </head>
            <body>
                ${element.innerHTML}
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Export to CSV
function exportToCSV(data, filename = 'export.csv') {
    const headers = Object.keys(data[0]);
    const csvRows = [];
    
    csvRows.push(headers.join(','));
    
    for (const row of data) {
        const values = headers.map(header => {
            const value = row[header] || '';
            return `"${String(value).replace(/"/g, '""')}"`;
        });
        csvRows.push(values.join(','));
    }
    
    const blob = new Blob([csvRows.join('\n')], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
    
    showToast('Export successful!', 'success');
}