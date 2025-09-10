class SalesReportModule {
    constructor(toast, apiClient) {
        this.toast = toast;
        this.apiClient = apiClient;
        this.currentReportData = null;
        this.charts = {};
        this.initializeModule();
    }

    initializeModule() {
        this.setupEventListeners();
        this.setDefaultDates();
        this.loadCategories();
        // Initialize empty report state - no auto-loading
        this.showEmptyReportState();
    }

    setupEventListeners() {
        // Date range change handler - only update inputs, don't auto-generate
        const dateRangeSelect = document.getElementById('reportDateRange');
        if (dateRangeSelect) {
            dateRangeSelect.addEventListener('change', () => {
                this.updateDateInputs();
            });
        }

        // Custom date inputs - only validate, don't auto-generate
        const startDate = document.getElementById('startDate');
        const endDate = document.getElementById('endDate');
        if (startDate && endDate) {
            startDate.addEventListener('change', () => {
                this.validateDateRange();
            });
            endDate.addEventListener('change', () => {
                this.validateDateRange();
            });
        }

        // Generate Report button
        const generateBtn = document.getElementById('generateReportBtn');
        if (generateBtn) {
            generateBtn.addEventListener('click', () => {
                this.generateReport();
            });
        }

        // Export Report button
        const exportBtn = document.getElementById('exportReportBtn');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                this.exportReport();
            });
        }

        // Table search and sort handlers
        const searchInput = document.getElementById('reportTableSearch');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                this.filterReportTable();
            });
        }

        const sortSelect = document.getElementById('reportTableSort');
        if (sortSelect) {
            sortSelect.addEventListener('change', () => {
                this.sortReportTable();
            });
        }
    }

    setDefaultDates() {
        const today = new Date();
        const startDate = document.getElementById('startDate');
        const endDate = document.getElementById('endDate');
        
        if (startDate && endDate) {
            endDate.value = today.toISOString().split('T')[0];
            startDate.value = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
        }
    }

    showEmptyReportState() {
        // Clear summary cards
        const summaryElements = [
            'reportTotalSales', 'reportTotalTransactions', 'reportAvgTransaction', 'reportTopProduct',
            'salesChange', 'transactionsChange', 'avgChange', 'topProductSales'
        ];
        
        summaryElements.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                if (id.includes('Change')) {
                    element.textContent = '0.0%';
                    element.className = 'summary-change';
                } else if (id === 'topProductSales') {
                    element.textContent = '0 sold';
                } else if (id === 'reportTopProduct') {
                    element.textContent = '-';
                } else {
                    element.textContent = id.includes('Total') || id.includes('Avg') ? '₱0.00' : '0';
                }
            }
        });

        // Clear table
        const tableBody = document.getElementById('reportTableBody');
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem; color: #666;">Click "Generate Report" to view sales data</td></tr>';
        }

        // Destroy existing charts
        Object.values(this.charts).forEach(chart => {
            if (chart) chart.destroy();
        });
        this.charts = {};
    }

    updateDateInputs() {
        const dateRange = document.getElementById('reportDateRange').value;
        const customInputs = document.getElementById('customDateInputs');
        const startDate = document.getElementById('startDate');
        const endDate = document.getElementById('endDate');
        
        if (dateRange === 'custom') {
            customInputs.style.display = 'flex';
        } else {
            customInputs.style.display = 'none';
            this.setDateRangeValues(dateRange, startDate, endDate);
        }
    }

    setDateRangeValues(range, startDateInput, endDateInput) {
        const today = new Date();
        let endDate = new Date(today);
        let startDate = new Date(today);

        switch (range) {
            case 'today':
                startDate = new Date(today);
                break;
            case 'yesterday':
                startDate = new Date(today.getTime() - 24 * 60 * 60 * 1000);
                endDate.setTime(startDate.getTime());
                break;
            case 'this_week':
                const dayOfWeek = today.getDay();
                startDate = new Date(today.getTime() - dayOfWeek * 24 * 60 * 60 * 1000);
                break;
            case 'last_week':
                const lastWeekEnd = new Date(today.getTime() - today.getDay() * 24 * 60 * 60 * 1000 - 24 * 60 * 60 * 1000);
                const lastWeekStart = new Date(lastWeekEnd.getTime() - 6 * 24 * 60 * 60 * 1000);
                startDate = lastWeekStart;
                endDate.setTime(lastWeekEnd.getTime());
                break;
            case 'this_month':
                startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                break;
            case 'last_month':
                startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                endDate.setTime(new Date(today.getFullYear(), today.getMonth(), 0).getTime());
                break;
            case 'this_year':
                startDate = new Date(today.getFullYear(), 0, 1);
                endDate = new Date(today.getFullYear(), 11, 31);
                break;
        }

        startDateInput.value = startDate.toISOString().split('T')[0];
        endDateInput.value = endDate.toISOString().split('T')[0];
    }

    validateDateRange() {
        const startDate = new Date(document.getElementById('startDate').value);
        const endDate = new Date(document.getElementById('endDate').value);
        
        if (startDate > endDate) {
            this.toast.error('Start date cannot be after end date');
            return false;
        }
        return true;
    }

    async generateReport() {
        if (!this.validateDateRange()) return;

        try {
            const filters = this.getReportFilters();
            const reportData = await this.fetchReportData(filters);
            
            this.currentReportData = reportData;
            this.updateSummaryCards(reportData);
            this.renderCharts(reportData);
            this.populateReportTable(reportData);
            
            this.toast.success('Report generated successfully');
        } catch (error) {
            console.error('Error generating report:', error);
            this.toast.error('Failed to generate report');
        }
    }

    getReportFilters() {
        const dateRange = document.getElementById('reportDateRange').value;
        let startDate, endDate;

        if (dateRange === 'custom') {
            startDate = document.getElementById('startDate').value;
            endDate = document.getElementById('endDate').value;
        } else {
            const startInput = document.getElementById('startDate');
            const endInput = document.getElementById('endDate');
            startDate = startInput.value;
            endDate = endInput.value;
        }

        return {
            startDate,
            endDate,
            category: document.getElementById('reportCategory').value,
            paymentMethod: document.getElementById('reportPaymentMethod').value
        };
    }

    async fetchReportData(filters) {
        // Import and use the CSRF service
        const { csrfService } = await import('../modules/csrf-module.js');
        await csrfService.ensure();
        const csrfToken = csrfService.getToken();

        const formData = new FormData();
        formData.append('action', 'generate_report');
        formData.append('csrf_token', csrfToken);
        formData.append('startDate', filters.startDate);
        formData.append('endDate', filters.endDate);
        formData.append('category', filters.category);
        formData.append('paymentMethod', filters.paymentMethod);

        const response = await this.apiClient.request('sales_report.php', {
            method: 'POST',
            body: formData,
            headers: {} // Remove Content-Type to let browser set it for FormData
        });

        if (!response.success) {
            throw new Error(response.message || 'Failed to fetch report data');
        }

        return response.data;
    }

    updateSummaryCards(data) {
        const elements = {
            totalSales: document.getElementById('reportTotalSales'),
            totalTransactions: document.getElementById('reportTotalTransactions'),
            avgTransaction: document.getElementById('reportAvgTransaction'),
            topProduct: document.getElementById('reportTopProduct'),
            salesChange: document.getElementById('salesChange'),
            transactionsChange: document.getElementById('transactionsChange'),
            avgChange: document.getElementById('avgChange'),
            topProductSales: document.getElementById('topProductSales')
        };

        if (elements.totalSales) elements.totalSales.textContent = `₱${data.summary.totalSales.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
        if (elements.totalTransactions) elements.totalTransactions.textContent = data.summary.totalTransactions.toLocaleString();
        if (elements.avgTransaction) elements.avgTransaction.textContent = `₱${data.summary.avgTransaction.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
        if (elements.topProduct) elements.topProduct.textContent = data.summary.topProduct.name || '-';
        
        // Update change indicators
        if (elements.salesChange) {
            const change = data.summary.salesChange || 0;
            elements.salesChange.textContent = `${change >= 0 ? '+' : ''}${change.toFixed(1)}%`;
            elements.salesChange.className = `summary-change ${change >= 0 ? 'positive' : 'negative'}`;
        }
        
        if (elements.transactionsChange) {
            const change = data.summary.transactionsChange || 0;
            elements.transactionsChange.textContent = `${change >= 0 ? '+' : ''}${change.toFixed(1)}%`;
            elements.transactionsChange.className = `summary-change ${change >= 0 ? 'positive' : 'negative'}`;
        }
        
        if (elements.avgChange) {
            const change = data.summary.avgChange || 0;
            elements.avgChange.textContent = `${change >= 0 ? '+' : ''}${change.toFixed(1)}%`;
            elements.avgChange.className = `summary-change ${change >= 0 ? 'positive' : 'negative'}`;
        }
        
        if (elements.topProductSales) {
            elements.topProductSales.textContent = `${data.summary.topProduct.quantity || 0} sold`;
        }
    }

    renderCharts(data) {
        this.renderSalesTrendChart(data.charts.salesTrend);
        this.renderCategoryChart(data.charts.categoryBreakdown);
        this.renderPaymentMethodChart(data.charts.paymentMethods);
        this.renderHourlySalesChart(data.charts.hourlySales);
        this.renderTopProductsChart(data.charts.topProducts);
    }

    renderSalesTrendChart(trendData) {
        const ctx = document.getElementById('salesTrendChart');
        if (!ctx) return;

        if (this.charts.salesTrend) {
            this.charts.salesTrend.destroy();
        }

        // Create gradient
        const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(99, 102, 241, 0.3)');
        gradient.addColorStop(0.5, 'rgba(99, 102, 241, 0.15)');
        gradient.addColorStop(1, 'rgba(99, 102, 241, 0.02)');

        this.charts.salesTrend = new Chart(ctx, {
            type: 'line',
            data: {
                labels: trendData.labels,
                datasets: [{
                    label: 'Sales (₱)',
                    data: trendData.data,
                    borderColor: '#6366f1',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#6366f1',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 3,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    pointHoverBackgroundColor: '#4f46e5',
                    pointHoverBorderColor: '#ffffff',
                    pointHoverBorderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#6366f1',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            title: function(context) {
                                return context[0].label;
                            },
                            label: function(context) {
                                return 'Sales: ₱' + context.parsed.y.toLocaleString('en-PH', {minimumFractionDigits: 2});
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6b7280',
                            font: {
                                size: 12,
                                weight: '500'
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(107, 114, 128, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#6b7280',
                            font: {
                                size: 12,
                                weight: '500'
                            },
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            },
                            padding: 10
                        }
                    }
                },
                elements: {
                    line: {
                        borderJoinStyle: 'round'
                    }
                }
            }
        });
    }

    renderCategoryChart(categoryData) {
        const ctx = document.getElementById('categoryChart');
        if (!ctx) return;

        if (this.charts.category) {
            this.charts.category.destroy();
        }

        this.charts.category = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: categoryData.labels,
                datasets: [{
                    data: categoryData.data,
                    backgroundColor: [
                        '#3b82f6',
                        '#ef4444',
                        '#10b981',
                        '#f59e0b',
                        '#8b5cf6',
                        '#06b6d4'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    renderPaymentMethodChart(paymentData) {
        const ctx = document.getElementById('paymentMethodChart');
        if (!ctx) return;

        if (this.charts.paymentMethod) {
            this.charts.paymentMethod.destroy();
        }

        this.charts.paymentMethod = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: paymentData.labels,
                datasets: [{
                    data: paymentData.data,
                    backgroundColor: [
                        '#10b981',
                        '#3b82f6',
                        '#f59e0b'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    renderHourlySalesChart(hourlyData) {
        const ctx = document.getElementById('hourlySalesChart');
        if (!ctx) return;

        if (this.charts.hourlySales) {
            this.charts.hourlySales.destroy();
        }

        this.charts.hourlySales = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: hourlyData.labels,
                datasets: [{
                    label: 'Sales (₱)',
                    data: hourlyData.data,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: '#3b82f6',
                    borderWidth: 1
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
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    renderTopProductsChart(topProductsData) {
        const ctx = document.getElementById('topProductsReportChart');
        if (!ctx) return;

        if (this.charts.topProducts) {
            this.charts.topProducts.destroy();
        }

        this.charts.topProducts = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: topProductsData.labels,
                datasets: [{
                    label: 'Quantity Sold',
                    data: topProductsData.data,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: '#10b981',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    populateReportTable(data) {
        const tableBody = document.getElementById('reportTableBody');
        if (!tableBody) return;

        if (!data.transactions || data.transactions.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem;">No transactions found for the selected period</td></tr>';
            return;
        }

        const rows = data.transactions.map(transaction => {
            const date = new Date(transaction.created_at);
            const formattedDate = date.toLocaleDateString('en-PH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
            const formattedTime = date.toLocaleTimeString('en-PH', {
                hour: '2-digit',
                minute: '2-digit'
            });

            return `
                <tr>
                    <td>${transaction.transaction_id}</td>
                    <td>
                        <div>${formattedDate}</div>
                        <div style="font-size: 0.8em; color: #666;">${formattedTime}</div>
                    </td>
                    <td>
                        <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                            ${transaction.items_count} item(s)
                        </div>
                    </td>
                    <td>
                        <span class="payment-method-badge ${transaction.payment_method}">
                            ${transaction.payment_method.toUpperCase()}
                        </span>
                    </td>
                    <td>₱${parseFloat(transaction.total_amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                    <td>${transaction.cashier_name || 'N/A'}</td>
                    <td>
                        <button class="btn-small btn-secondary" onclick="viewTransactionDetails('${transaction.transaction_id}')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        tableBody.innerHTML = rows;
    }

    filterReportTable() {
        const searchTerm = document.getElementById('reportTableSearch').value.toLowerCase();
        const tableRows = document.querySelectorAll('#reportTableBody tr');

        tableRows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    }

    sortReportTable() {
        const sortBy = document.getElementById('reportTableSort').value;
        const tableBody = document.getElementById('reportTableBody');
        const rows = Array.from(tableBody.querySelectorAll('tr'));

        rows.sort((a, b) => {
            let aVal, bVal;

            switch (sortBy) {
                case 'date_desc':
                case 'date_asc':
                    aVal = new Date(a.cells[1].textContent.trim());
                    bVal = new Date(b.cells[1].textContent.trim());
                    break;
                case 'amount_desc':
                case 'amount_asc':
                    aVal = parseFloat(a.cells[4].textContent.replace('₱', '').replace(/,/g, ''));
                    bVal = parseFloat(b.cells[4].textContent.replace('₱', '').replace(/,/g, ''));
                    break;
                default:
                    return 0;
            }

            if (sortBy.includes('_desc')) {
                return bVal > aVal ? 1 : -1;
            } else {
                return aVal > bVal ? 1 : -1;
            }
        });

        rows.forEach(row => tableBody.appendChild(row));
    }

    async exportReport() {
        if (!this.currentReportData) {
            this.toast.warning('Please generate a report first');
            return;
        }

        try {
            const filters = this.getReportFilters();
            
            // Import and use the CSRF service
            const { csrfService } = await import('../modules/csrf-module.js');
            await csrfService.ensure();
            const csrfToken = csrfService.getToken();

            const formData = new FormData();
            formData.append('action', 'export_report');
            formData.append('csrf_token', csrfToken);
            formData.append('startDate', filters.startDate);
            formData.append('endDate', filters.endDate);
            formData.append('category', filters.category);
            formData.append('paymentMethod', filters.paymentMethod);

            const response = await this.apiClient.request('sales_report.php', {
                method: 'POST',
                body: formData,
                headers: {} // Remove Content-Type to let browser set it for FormData
            });

            if (response.success) {
                // Create download link
                const link = document.createElement('a');
                link.href = response.data.downloadUrl;
                link.download = response.data.filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                this.toast.success('Report exported successfully');
            } else {
                throw new Error(response.message);
            }
        } catch (error) {
            console.error('Error exporting report:', error);
            this.toast.error('Failed to export report');
        }
    }

    async loadCategories() {
        try {
            const response = await this.apiClient.request('inventory.php', {
                method: 'GET',
                headers: {}
            });

            if (response.success && response.data) {
                this.populateCategoryDropdown(response.data);
            }
        } catch (error) {
            console.error('Error loading categories:', error);
            // Keep default categories if API fails
        }
    }

    populateCategoryDropdown(categories) {
        const categorySelect = document.getElementById('reportCategory');
        if (!categorySelect) return;

        // Keep the "All Categories" option
        const allOption = categorySelect.querySelector('option[value="all"]');
        categorySelect.innerHTML = '';
        if (allOption) {
            categorySelect.appendChild(allOption);
        } else {
            const defaultOption = document.createElement('option');
            defaultOption.value = 'all';
            defaultOption.textContent = 'All Categories';
            categorySelect.appendChild(defaultOption);
        }

        // Add categories from database
        const uniqueCategories = [...new Set(categories.map(item => item.category).filter(cat => cat))];
        uniqueCategories.forEach(category => {
            const option = document.createElement('option');
            option.value = category;
            option.textContent = category.charAt(0).toUpperCase() + category.slice(1);
            categorySelect.appendChild(option);
        });
    }

    destroy() {
        // Clean up charts
        Object.values(this.charts).forEach(chart => {
            if (chart) chart.destroy();
        });
        this.charts = {};
    }
}

window.viewTransactionDetails = async function(transactionId) {
    try {
        // Show loading state
        if (window.ToastManager) {
            window.ToastManager.show('Loading transaction details...', 'info');
        }

        // Fetch transaction details from API
        const response = await fetch(`api/sales.php?transaction_id=${transactionId}`, {
            method: 'GET',
            credentials: 'include'
        });

        const result = await response.json();

        if (result.success && result.data) {
            showTransactionModal(result.data);
        } else {
            throw new Error(result.error || 'Failed to fetch transaction details');
        }
    } catch (error) {
        console.error('Error fetching transaction details:', error);
        if (window.ToastManager) {
            window.ToastManager.show('Failed to load transaction details', 'error');
        }
    }
};

function showTransactionModal(transaction) {
    // Create modal HTML
    const modalHTML = `
        <div id="transactionModal" class="modal-overlay" onclick="closeTransactionModal(event)">
            <div class="modal-content" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <h3>Transaction Details</h3>
                    <button class="modal-close" onclick="closeTransactionModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="transaction-info">
                        <div class="info-row">
                            <label>Transaction ID:</label>
                            <span>${transaction.transaction_id}</span>
                        </div>
                        <div class="info-row">
                            <label>Date & Time:</label>
                            <span>${new Date(transaction.created_at).toLocaleString('en-PH')}</span>
                        </div>
                        <div class="info-row">
                            <label>Customer:</label>
                            <span>${transaction.customer_name || 'Walk-in Customer'}</span>
                        </div>
                        <div class="info-row">
                            <label>Cashier:</label>
                            <span>${transaction.cashier_name || 'System User'}</span>
                        </div>
                        <div class="info-row">
                            <label>Payment Method:</label>
                            <span class="payment-method-badge ${transaction.payment_method}">
                                ${transaction.payment_method.toUpperCase()}
                            </span>
                        </div>
                    </div>
                    
                    <div class="transaction-items">
                        <h4>Items Purchased</h4>
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${transaction.items ? transaction.items.map(item => `
                                    <tr>
                                        <td>${item.product_name}</td>
                                        <td>${item.quantity}</td>
                                        <td>₱${parseFloat(item.unit_price).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                                        <td>₱${parseFloat(item.total_price).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                                    </tr>
                                `).join('') : `
                                    <tr>
                                        <td colspan="4">${transaction.products || 'No items available'}</td>
                                    </tr>
                                `}
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="transaction-totals">
                        <div class="total-row">
                            <label>Subtotal:</label>
                            <span>₱${(parseFloat(transaction.total_amount) - parseFloat(transaction.tax_amount || 0)).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                        </div>
                        <div class="total-row">
                            <label>Tax:</label>
                            <span>₱${parseFloat(transaction.tax_amount || 0).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                        </div>
                        <div class="total-row total-final">
                            <label>Total Amount:</label>
                            <span>₱${parseFloat(transaction.total_amount).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                        </div>
                        ${transaction.amount_received ? `
                            <div class="total-row">
                                <label>Amount Received:</label>
                                <span>₱${parseFloat(transaction.amount_received).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                            </div>
                            <div class="total-row">
                                <label>Change:</label>
                                <span>₱${parseFloat(transaction.change_amount || 0).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                            </div>
                        ` : ''}
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeTransactionModal()">Close</button>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    const existingModal = document.getElementById('transactionModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to page
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Add modal styles if not already present
    if (!document.getElementById('transactionModalStyles')) {
        const styles = `
            <style id="transactionModalStyles">
                .modal-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.5);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 1000;
                }
                .modal-content {
                    background: white;
                    border-radius: 8px;
                    width: 90%;
                    max-width: 600px;
                    max-height: 90vh;
                    overflow-y: auto;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                }
                .modal-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 1rem 1.5rem;
                    border-bottom: 1px solid #e5e7eb;
                }
                .modal-header h3 {
                    margin: 0;
                    color: #1f2937;
                }
                .modal-close {
                    background: none;
                    border: none;
                    font-size: 1.5rem;
                    cursor: pointer;
                    color: #6b7280;
                    padding: 0;
                    width: 30px;
                    height: 30px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .modal-close:hover {
                    color: #374151;
                }
                .modal-body {
                    padding: 1.5rem;
                }
                .transaction-info {
                    margin-bottom: 2rem;
                }
                .info-row {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 0.5rem 0;
                    border-bottom: 1px solid #f3f4f6;
                }
                .info-row label {
                    font-weight: 600;
                    color: #374151;
                }
                .transaction-items {
                    margin-bottom: 2rem;
                }
                .transaction-items h4 {
                    margin-bottom: 1rem;
                    color: #1f2937;
                }
                .items-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 1rem;
                }
                .items-table th,
                .items-table td {
                    padding: 0.75rem;
                    text-align: left;
                    border-bottom: 1px solid #e5e7eb;
                }
                .items-table th {
                    background-color: #f9fafb;
                    font-weight: 600;
                    color: #374151;
                }
                .transaction-totals {
                    border-top: 2px solid #e5e7eb;
                    padding-top: 1rem;
                }
                .total-row {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 0.5rem 0;
                }
                .total-row label {
                    font-weight: 500;
                    color: #374151;
                }
                .total-final {
                    border-top: 1px solid #e5e7eb;
                    margin-top: 0.5rem;
                    padding-top: 1rem;
                    font-weight: 600;
                    font-size: 1.1rem;
                }
                .modal-footer {
                    display: flex;
                    justify-content: flex-end;
                    gap: 1rem;
                    padding: 1rem 1.5rem;
                    border-top: 1px solid #e5e7eb;
                    background-color: #f9fafb;
                }
                .payment-method-badge {
                    padding: 0.25rem 0.5rem;
                    border-radius: 4px;
                    font-size: 0.75rem;
                    font-weight: 600;
                }
                .payment-method-badge.cash {
                    background-color: #dcfce7;
                    color: #166534;
                }
                .payment-method-badge.card {
                    background-color: #dbeafe;
                    color: #1e40af;
                }
                .payment-method-badge.gcash {
                    background-color: #dbeafe;
                    color: #1d4ed8;
                }
            </style>
        `;
        document.head.insertAdjacentHTML('beforeend', styles);
    }
}

window.closeTransactionModal = function(event) {
    if (event && event.target !== event.currentTarget) return;
    
    const modal = document.getElementById('transactionModal');
    if (modal) {
        modal.remove();
    }
};

window.printTransactionReceipt = function(transactionId) {
    // This will reuse the existing print functionality from POS
    if (window.ToastManager) {
        window.ToastManager.show('Print functionality coming soon', 'info');
    }
};

// Global functions are defined in admin-dashboard.js

export default SalesReportModule;
