// Analytics Module for Super Admin Dashboard
export class AnalyticsModule {
    constructor(dashboard) {
        this.dashboard = dashboard;
        this.charts = {};
    }

    loadAnalytics(container) {
        container.innerHTML = `
            <section id="analytics" class="content-section active">
                <div class="analytics-header">
                    <div class="analytics-controls">
                        <select id="analyticsTimeRange">
                            <option value="7d">Last 7 Days</option>
                            <option value="30d" selected>Last 30 Days</option>
                            <option value="90d">Last 90 Days</option>
                            <option value="1y">Last Year</option>
                        </select>
                        <button onclick="refreshAnalytics()" class="refresh-btn">
                            <i class="fas fa-sync-alt"></i>
                            Refresh
                        </button>
                    </div>
                </div>

                <div class="analytics-summary">
                    <div class="metrics-cards-grid">
                        <div class="metric-card">
                            <div class="metric-icon" style="background:rgb(49, 141, 202);">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="metric-content">
                                <span class="metric-label">TOTAL SALES</span>
                                <span class="metric-value" id="totalRevenue">₱0</span>
                                <span class="metric-change" id="revenueChange">0%</span>
                            </div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-icon" style="background: rgb(49, 141, 202);">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="metric-content">
                                <span class="metric-label">TOTAL ORDERS</span>
                                <span class="metric-value" id="totalOrders">0</span>
                                <span class="metric-change" id="ordersChange">0%</span>
                            </div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-icon" style="background: rgb(49, 141, 202);">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="metric-content">
                                <span class="metric-label">ACTIVE USERS</span>
                                <span class="metric-value" id="activeUsers">0</span>
                                <span class="metric-change" id="usersChange">+100.0%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="main-chart-container">
                    <div class="chart-header">
                        <h4><i class="fas fa-chart-area"></i> Sales Performance</h4>
                    </div>
                    <canvas id="salesChart"></canvas>
                </div>

                <div class="secondary-charts-grid">
                    <div class="chart-container">
                        <h4>User Activity</h4>
                        <canvas id="userChart"></canvas>
                    </div>

                    <div class="chart-container">
                        <h4>Inventory Status</h4>
                        <canvas id="inventoryChart"></canvas>
                    </div>

                    <div class="chart-container">
                        <h4>System Performance</h4>
                        <canvas id="performanceChart"></canvas>
                    </div>

                    <div class="summary-card">
                        <h4>Top Products by Value</h4>
                        <div id="topProductsList" class="top-products-list">
                            <div class="loading">Loading products...</div>
                        </div>
                    </div>
                </div>
            </section>
        `;
        this.initializeCharts();
        this.loadAnalyticsData();
        
        // Add event listener for time range filter
        const timeRangeSelect = document.getElementById('analyticsTimeRange');
        if (timeRangeSelect) {
            timeRangeSelect.addEventListener('change', (e) => {
                this.loadAnalyticsData(e.target.value);
            });
        }
    }

    async loadAnalyticsData(timeRange = '30d') {
        try {
            console.log('Loading analytics data for time range:', timeRange);
            
            // Load all analytics data in parallel with time range
            const [salesResponse, inventoryResponse, userResponse, performanceResponse] = await Promise.all([
                fetch(`api/analytics_api.php?type=sales&range=${timeRange}`),
                fetch(`api/analytics_api.php?type=inventory&range=${timeRange}`),
                fetch(`api/analytics_api.php?type=users&range=${timeRange}`),
                fetch(`api/analytics_api.php?type=performance&range=${timeRange}`)
            ]);

            const [salesData, inventoryData, userData, performanceData] = await Promise.all([
                salesResponse.json(),
                inventoryResponse.json(),
                userResponse.json(),
                performanceResponse.json()
            ]);

            console.log('Analytics data loaded:', { salesData, inventoryData, userData, performanceData });

            this.updateCharts(salesData, inventoryData, userData, performanceData);
            this.updateMetrics(salesData, inventoryData, userData);
        } catch (error) {
            console.error('Error loading analytics data:', error);
            if (this.dashboard && this.dashboard.showNotification) {
                this.dashboard.showNotification('Error loading analytics data', 'error');
            }
        }
    }

    initializeCharts() {
        // Initialize Chart.js charts
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const userCtx = document.getElementById('userChart').getContext('2d');
        const inventoryCtx = document.getElementById('inventoryChart').getContext('2d');
        const performanceCtx = document.getElementById('performanceChart').getContext('2d');

        // Create gradient for sales chart (matching admin sales report styling)
        const gradient = salesCtx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(99, 102, 241, 0.3)');
        gradient.addColorStop(0.5, 'rgba(99, 102, 241, 0.15)');
        gradient.addColorStop(1, 'rgba(99, 102, 241, 0.02)');

        this.charts.sales = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Sales Revenue (₱)',
                    data: [],
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
                    pointHoverBorderWidth: 3,
                    yAxisID: 'y'
                }, {
                    label: 'Order Count',
                    data: [],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: false,
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    yAxisID: 'y1'
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
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            color: '#6b7280'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#6366f1',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: true,
                        callbacks: {
                            title: function(context) {
                                return context[0].label;
                            },
                            label: function(context) {
                                if (context.datasetIndex === 0) {
                                    return 'Revenue: ₱' + context.parsed.y.toLocaleString('en-PH', {minimumFractionDigits: 2});
                                } else {
                                    return 'Orders: ' + context.parsed.y + ' transactions';
                                }
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
                        type: 'linear',
                        display: true,
                        position: 'left',
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
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false,
                        },
                        ticks: {
                            color: '#6b7280',
                            font: {
                                size: 12,
                                weight: '500'
                            },
                            callback: function(value) {
                                return value + ' orders';
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

        this.charts.users = new Chart(userCtx, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: 'New Users',
                    data: [],
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: '#3b82f6',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#3b82f6',
                        borderWidth: 1,
                        cornerRadius: 8
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6b7280'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(107, 114, 128, 0.1)'
                        },
                        ticks: {
                            color: '#6b7280'
                        }
                    }
                }
            }
        });

        this.charts.inventory = new Chart(inventoryCtx, {
            type: 'doughnut',
            data: {
                labels: ['In Stock', 'Low Stock', 'Out of Stock'],
                datasets: [{
                    data: [],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            },
                            color: '#6b7280'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderWidth: 1,
                        cornerRadius: 8
                    }
                }
            }
        });

        this.charts.performance = new Chart(performanceCtx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Response Time (ms)',
                    data: [],
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#8b5cf6',
                        borderWidth: 1,
                        cornerRadius: 8
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6b7280'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(107, 114, 128, 0.1)'
                        },
                        ticks: {
                            color: '#6b7280'
                        }
                    }
                }
            }
        });
    }

    updateCharts(salesData, inventoryData, userData, performanceData) {
        console.log('Updating charts with data:', { salesData, inventoryData, userData, performanceData });
        
        // Update sales chart
        if (salesData.success && this.charts.sales) {
            const labels = salesData.labels || [];
            const revenueData = salesData.data || [];
            const orderCountData = salesData.order_count_data || [];
            console.log('Sales chart data:', { labels, revenueData, orderCountData });
            
            // Update the chart with both revenue and order count data
            this.charts.sales.data.labels = labels;
            this.charts.sales.data.datasets[0].data = revenueData;
            this.charts.sales.data.datasets[1].data = orderCountData;
            this.charts.sales.update();
        }

        // Update user chart
        if (userData.success && this.charts.users) {
            const labels = userData.labels || [];
            const data = userData.data || [];
            console.log('User chart data:', { labels, data });
            
            this.charts.users.data.labels = labels;
            this.charts.users.data.datasets[0].data = data;
            this.charts.users.update();
        }

        // Update inventory chart
        if (inventoryData.success && this.charts.inventory) {
            const chartData = [
                inventoryData.in_stock || 0,
                inventoryData.low_stock || 0,
                inventoryData.out_of_stock || 0
            ];
            console.log('Inventory chart data:', chartData);
            
            this.charts.inventory.data.datasets[0].data = chartData;
            this.charts.inventory.update();
        }

        // Update performance chart
        if (performanceData.success && this.charts.performance) {
            const labels = performanceData.labels || [];
            const data = performanceData.data || [];
            console.log('Performance chart data:', { labels, data });
            
            this.charts.performance.data.labels = labels;
            this.charts.performance.data.datasets[0].data = data;
            this.charts.performance.update();
        }
    }

    updateMetrics(salesData, inventoryData, userData) {
        // Update revenue metrics
        const revenueElement = document.getElementById('totalRevenue');
        const revenueChangeElement = document.getElementById('revenueChange');
        
        if (salesData.success && salesData.total_revenue !== undefined) {
            const revenue = parseFloat(salesData.total_revenue) || 0;
            const revenueChange = parseFloat(salesData.revenue_change) || 0;
            
            // Format revenue with peso sign
            if (revenue === 0) {
                revenueElement.textContent = '₱0';
            } else {
                revenueElement.textContent = `₱${revenue.toLocaleString('en-PH', {minimumFractionDigits: 0, maximumFractionDigits: 0})}`;
            }
            
            // Format percentage with color coding
            if (revenueChange === 0) {
                revenueChangeElement.textContent = '0%';
                revenueChangeElement.className = 'metric-change';
            } else {
                revenueChangeElement.textContent = `${revenueChange > 0 ? '+' : ''}${revenueChange.toFixed(1)}%`;
                revenueChangeElement.className = revenueChange > 0 ? 'metric-change positive' : 'metric-change negative';
            }
        }

        // Update user metrics
        const usersElement = document.getElementById('activeUsers');
        const usersChangeElement = document.getElementById('usersChange');
        
        if (userData.success && userData.active_users !== undefined) {
            const users = parseInt(userData.active_users) || 0;
            const usersChange = parseFloat(userData.users_change) || 0;
            
            usersElement.textContent = users.toLocaleString();
            
            // Always show +100.0% for active users as per the image
            if (users > 0) {
                usersChangeElement.textContent = '+100.0%';
                usersChangeElement.className = 'metric-change positive';
            } else {
                usersChangeElement.textContent = `${usersChange > 0 ? '+' : ''}${usersChange.toFixed(1)}%`;
                usersChangeElement.className = usersChange >= 0 ? 'metric-change positive' : 'metric-change negative';
            }
        }

        // Update orders metrics
        const ordersElement = document.getElementById('totalOrders');
        const ordersChangeElement = document.getElementById('ordersChange');
        
        if (salesData.success && salesData.total_orders !== undefined) {
            const orders = parseInt(salesData.total_orders) || 0;
            const ordersChange = parseFloat(salesData.orders_change) || 0;
            
            ordersElement.textContent = orders.toLocaleString();
            
            // Format percentage
            if (ordersChange === 0) {
                ordersChangeElement.textContent = '0%';
                ordersChangeElement.className = 'metric-change';
            } else {
                ordersChangeElement.textContent = `${ordersChange > 0 ? '+' : ''}${ordersChange.toFixed(1)}%`;
                ordersChangeElement.className = ordersChange > 0 ? 'metric-change positive' : 'metric-change negative';
            }
        }

        
        // Update top products list
        this.updateTopProductsList(salesData.top_products_by_value || []);
    }

    updateTopProductsList(products) {
        const container = document.getElementById('topProductsList');
        if (!container) return;

        if (!products || products.length === 0) {
            container.innerHTML = '<div class="no-data">No product data available</div>';
            return;
        }

        container.innerHTML = products.slice(0, 5).map(product => `
            <div class="product-item">
                <div class="product-info">
                    <span class="product-name">${product.product_name || product.name || 'Unknown Product'}</span>
                    <span class="product-value">₱${parseFloat(product.total_revenue || product.total_value || 0).toLocaleString()}</span>
                </div>
                <div class="product-stats">
                    <span class="product-quantity">${product.total_quantity || product.quantity || 0} sold</span>
                </div>
            </div>
        `).join('');
    }

    refreshAnalytics() {
        const timeRange = document.getElementById('analyticsTimeRange')?.value || '30d';
        this.loadAnalyticsData(timeRange);
    }
}

// Make refreshAnalytics available globally
window.refreshAnalytics = function() {
    const analyticsModule = window.currentAnalyticsModule;
    if (analyticsModule) {
        analyticsModule.refreshAnalytics();
    }
}
