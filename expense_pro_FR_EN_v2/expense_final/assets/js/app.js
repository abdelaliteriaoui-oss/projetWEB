/**
 * ExpensePro - JavaScript Main
 * Modern Expense Management System
 */

class ExpensePro {
    constructor() {
        this.init();
    }

    init() {
        this.initSidebar();
        this.initDropdowns();
        this.initModals();
        this.initFileUpload();
        this.initForms();
        this.initToasts();
        this.initSearch();
        this.initTheme();
        this.initAnimations();
    }

    // ==================== SIDEBAR ====================
    initSidebar() {
        const toggleBtn = document.querySelector('.toggle-sidebar');
        const sidebar = document.querySelector('.sidebar');

        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('collapsed'));
            });

            // Restore state
            if (localStorage.getItem('sidebar-collapsed') === 'true') {
                sidebar.classList.add('collapsed');
            }
        }

        // Mobile sidebar
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        if (mobileToggle && sidebar) {
            mobileToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });

            // Close on outside click
            document.addEventListener('click', (e) => {
                if (!sidebar.contains(e.target) && !mobileToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            });
        }
    }

    // ==================== DROPDOWNS ====================
    initDropdowns() {
        document.querySelectorAll('[data-dropdown]').forEach(trigger => {
            const dropdown = document.querySelector(trigger.dataset.dropdown);
            
            if (dropdown) {
                trigger.addEventListener('click', (e) => {
                    e.stopPropagation();
                    dropdown.classList.toggle('active');
                });
            }
        });

        // Close dropdowns on outside click
        document.addEventListener('click', () => {
            document.querySelectorAll('.dropdown-menu.active, .notifications-dropdown.active').forEach(d => {
                d.classList.remove('active');
            });
        });
    }

    // ==================== MODALS ====================
    initModals() {
        // Open modal
        document.querySelectorAll('[data-modal]').forEach(trigger => {
            trigger.addEventListener('click', () => {
                const modal = document.querySelector(trigger.dataset.modal);
                if (modal) {
                    modal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
            });
        });

        // Close modal
        document.querySelectorAll('.modal-close, .modal-overlay').forEach(el => {
            el.addEventListener('click', (e) => {
                if (e.target === el) {
                    const modal = el.closest('.modal-overlay');
                    if (modal) {
                        modal.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                }
            });
        });

        // Close on ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }
        });
    }

    // ==================== FILE UPLOAD ====================
    initFileUpload() {
        document.querySelectorAll('.file-upload').forEach(upload => {
            const input = upload.querySelector('input[type="file"]');
            const preview = upload.querySelector('.file-preview');

            if (input) {
                // Click to upload
                upload.addEventListener('click', () => input.click());

                // Prevent input click propagation
                input.addEventListener('click', (e) => e.stopPropagation());

                // Drag and drop
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    upload.addEventListener(eventName, (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                    });
                });

                ['dragenter', 'dragover'].forEach(eventName => {
                    upload.addEventListener(eventName, () => upload.classList.add('dragover'));
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    upload.addEventListener(eventName, () => upload.classList.remove('dragover'));
                });

                upload.addEventListener('drop', (e) => {
                    const files = e.dataTransfer.files;
                    input.files = files;
                    this.handleFilePreview(input, preview);
                });

                // File change
                input.addEventListener('change', () => {
                    this.handleFilePreview(input, preview);
                });
            }
        });
    }

    handleFilePreview(input, preview) {
        if (!preview) return;
        
        preview.innerHTML = '';
        
        Array.from(input.files).forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'file-preview-item';
            
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    item.innerHTML = `
                        <img src="${e.target.result}" alt="${file.name}">
                        <button type="button" class="remove" data-index="${index}">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                item.innerHTML = `
                    <div style="display:flex;align-items:center;justify-content:center;height:100%;background:#f3f4f6;">
                        <i class="fas fa-file-pdf" style="font-size:24px;color:#ef4444;"></i>
                    </div>
                    <button type="button" class="remove" data-index="${index}">
                        <i class="fas fa-times"></i>
                    </button>
                `;
            }
            
            preview.appendChild(item);
        });
    }

    // ==================== FORMS ====================
    initForms() {
        // Auto-calculate totals
        document.querySelectorAll('[data-calculate-total]').forEach(form => {
            const inputs = form.querySelectorAll('.amount-input');
            const totalDisplay = form.querySelector('.total-amount');

            if (inputs.length && totalDisplay) {
                inputs.forEach(input => {
                    input.addEventListener('input', () => {
                        let total = 0;
                        inputs.forEach(i => {
                            total += parseFloat(i.value) || 0;
                        });
                        totalDisplay.textContent = this.formatMoney(total);
                    });
                });
            }
        });

        // Form validation
        document.querySelectorAll('form[data-validate]').forEach(form => {
            form.addEventListener('submit', (e) => {
                let isValid = true;
                
                form.querySelectorAll('[required]').forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('error');
                        
                        let error = field.parentElement.querySelector('.form-error');
                        if (!error) {
                            error = document.createElement('div');
                            error.className = 'form-error';
                            error.textContent = 'Ce champ est requis';
                            field.parentElement.appendChild(error);
                        }
                    } else {
                        field.classList.remove('error');
                        const error = field.parentElement.querySelector('.form-error');
                        if (error) error.remove();
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                }
            });
        });

        // Real-time validation
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('blur', () => {
                if (input.required && !input.value.trim()) {
                    input.classList.add('error');
                } else {
                    input.classList.remove('error');
                }
            });
        });
    }

    // ==================== TOASTS ====================
    initToasts() {
        this.toastContainer = document.querySelector('.toast-container');
        if (!this.toastContainer) {
            this.toastContainer = document.createElement('div');
            this.toastContainer.className = 'toast-container';
            document.body.appendChild(this.toastContainer);
        }
    }

    showToast(message, type = 'success', duration = 4000) {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}"></i>
            </div>
            <span class="toast-message">${message}</span>
            <button class="toast-close">
                <i class="fas fa-times"></i>
            </button>
        `;

        this.toastContainer.appendChild(toast);

        toast.querySelector('.toast-close').addEventListener('click', () => {
            toast.remove();
        });

        setTimeout(() => {
            toast.style.animation = 'slideIn 0.3s ease reverse';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    // ==================== SEARCH ====================
    initSearch() {
        const searchInput = document.querySelector('.header-search input');
        
        if (searchInput) {
            let timeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    const query = e.target.value.trim();
                    if (query.length >= 2) {
                        this.performSearch(query);
                    }
                }, 300);
            });
        }
    }

    performSearch(query) {
        // Implement search functionality
        console.log('Searching for:', query);
    }

    // ==================== THEME ====================
    initTheme() {
        const theme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', theme);

        document.querySelectorAll('[data-toggle-theme]').forEach(btn => {
            btn.addEventListener('click', () => {
                const current = document.documentElement.getAttribute('data-theme');
                const newTheme = current === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
            });
        });
    }

    // ==================== ANIMATIONS ====================
    initAnimations() {
        // Intersection Observer for scroll animations
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.stat-card, .card, .expense-item').forEach(el => {
            observer.observe(el);
        });
    }

    // ==================== UTILITIES ====================
    formatMoney(amount) {
        return new Intl.NumberFormat('fr-MA', {
            style: 'decimal',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount) + ' DH';
    }

    formatDate(date) {
        return new Intl.DateTimeFormat('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        }).format(new Date(date));
    }
}

// ==================== EXPENSE FORM HANDLER ====================
class ExpenseFormHandler {
    constructor(formId) {
        this.form = document.getElementById(formId);
        if (!this.form) return;
        
        this.expenseItems = [];
        this.init();
    }

    init() {
        this.bindEvents();
        this.updateTotal();
    }

    bindEvents() {
        // Add expense line
        const addBtn = this.form.querySelector('.add-expense-line');
        if (addBtn) {
            addBtn.addEventListener('click', () => this.addExpenseLine());
        }

        // Remove expense line
        this.form.addEventListener('click', (e) => {
            if (e.target.closest('.remove-expense-line')) {
                e.target.closest('.expense-line').remove();
                this.updateTotal();
            }
        });

        // Update total on input change
        this.form.addEventListener('input', (e) => {
            if (e.target.classList.contains('expense-amount')) {
                this.updateTotal();
            }
        });
    }

    addExpenseLine() {
        const container = this.form.querySelector('.expense-lines');
        const template = this.form.querySelector('.expense-line-template');
        
        if (container && template) {
            const clone = template.content.cloneNode(true);
            container.appendChild(clone);
        }
    }

    updateTotal() {
        let total = 0;
        this.form.querySelectorAll('.expense-amount').forEach(input => {
            total += parseFloat(input.value) || 0;
        });

        const totalEl = this.form.querySelector('.expense-total-value');
        if (totalEl) {
            totalEl.textContent = new Intl.NumberFormat('fr-MA', {
                style: 'decimal',
                minimumFractionDigits: 2
            }).format(total) + ' DH';
        }

        const totalInput = this.form.querySelector('input[name="montant_total"]');
        if (totalInput) {
            totalInput.value = total;
        }
    }
}

// ==================== SMART RECEIPT SCANNER ====================
class ReceiptScanner {
    constructor() {
        this.canvas = document.createElement('canvas');
        this.ctx = this.canvas.getContext('2d');
    }

    async scan(file) {
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                // Simulate OCR result
                setTimeout(() => {
                    resolve({
                        merchant: 'Hôtel Ibis Casablanca',
                        amount: 450.00,
                        date: new Date().toISOString().split('T')[0],
                        category: 'Hébergement'
                    });
                }, 1500);
            };
            reader.readAsDataURL(file);
        });
    }
}

// ==================== MILEAGE CALCULATOR ====================
class MileageCalculator {
    constructor(rate = 2.5) {
        this.rate = rate;
    }

    calculate(distance) {
        return distance * this.rate;
    }

    getReimbursement(from, to, distance) {
        return {
            from,
            to,
            distance,
            rate: this.rate,
            total: this.calculate(distance)
        };
    }
}

// ==================== NOTIFICATION HANDLER ====================
class NotificationHandler {
    constructor() {
        this.badge = document.querySelector('.notification-badge');
        this.list = document.querySelector('.notifications-list');
        this.init();
    }

    init() {
        this.checkNotifications();
        setInterval(() => this.checkNotifications(), 30000);
    }

    async checkNotifications() {
        try {
            const response = await fetch('api/notifications.php');
            const data = await response.json();
            
            if (data.count > 0) {
                this.updateBadge(data.count);
                this.updateList(data.notifications);
            }
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
        }
    }

    updateBadge(count) {
        if (this.badge) {
            this.badge.textContent = count > 99 ? '99+' : count;
            this.badge.style.display = count > 0 ? 'flex' : 'none';
        }
    }

    updateList(notifications) {
        if (!this.list) return;
        
        this.list.innerHTML = notifications.map(n => `
            <div class="notification-item ${n.lu ? '' : 'unread'}" data-id="${n.id}">
                <div class="notification-content">
                    <div class="notification-icon ${n.type}">
                        <i class="fas fa-${this.getIcon(n.type)}"></i>
                    </div>
                    <div class="notification-text">
                        <div class="notification-message">${n.message}</div>
                        <div class="notification-time">${n.time_ago}</div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    getIcon(type) {
        const icons = {
            success: 'check-circle',
            warning: 'exclamation-circle',
            danger: 'times-circle',
            info: 'info-circle'
        };
        return icons[type] || 'bell';
    }

    markAsRead(id) {
        fetch(`api/notifications.php?action=read&id=${id}`, { method: 'POST' });
    }
}

// ==================== CHART HANDLER ====================
class ChartHandler {
    constructor() {
        this.charts = {};
    }

    createExpenseChart(canvasId, data) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;

        this.charts[canvasId] = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: [
                        '#0066FF',
                        '#00D4AA',
                        '#FF6B35',
                        '#F59E0B',
                        '#EF4444',
                        '#8B5CF6',
                        '#10B981',
                        '#6B7280'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                },
                cutout: '70%'
            }
        });
    }

    createTrendChart(canvasId, data) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;

        this.charts[canvasId] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Dépenses',
                    data: data.values,
                    borderColor: '#0066FF',
                    backgroundColor: 'rgba(0, 102, 255, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => value + ' DH'
                        }
                    }
                }
            }
        });
    }
}

// ==================== INITIALIZE ====================
document.addEventListener('DOMContentLoaded', () => {
    window.expensePro = new ExpensePro();
    window.chartHandler = new ChartHandler();
    
    // Initialize expense form if present
    if (document.getElementById('expense-form')) {
        window.expenseForm = new ExpenseFormHandler('expense-form');
    }

    // Initialize notification handler if logged in
    if (document.querySelector('.header-btn[data-dropdown]')) {
        window.notificationHandler = new NotificationHandler();
    }
});

// ==================== AJAX HELPERS ====================
const ajax = {
    async get(url) {
        const response = await fetch(url);
        return response.json();
    },

    async post(url, data) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        return response.json();
    },

    async submit(form) {
        const formData = new FormData(form);
        const response = await fetch(form.action, {
            method: form.method || 'POST',
            body: formData
        });
        return response.json();
    }
};

// ==================== CONFIRMATION DIALOG ====================
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// ==================== EXPORT ====================
function exportToExcel(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const wb = XLSX.utils.table_to_book(table, { sheet: 'Données' });
    XLSX.writeFile(wb, `${filename}.xlsx`);
}

function exportToPDF(elementId, filename) {
    const element = document.getElementById(elementId);
    if (!element) return;

    html2pdf()
        .from(element)
        .save(`${filename}.pdf`);
}

// ==================== PRINT ====================
function printElement(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;

    const printWindow = window.open('', '', 'width=800,height=600');
    printWindow.document.write(`
        <html>
        <head>
            <title>Impression</title>
            <link rel="stylesheet" href="assets/css/style.css">
            <style>
                body { padding: 20px; }
                @media print {
                    .no-print { display: none !important; }
                }
            </style>
        </head>
        <body>
            ${element.innerHTML}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
