// assets/js/analytics.js
// Environmental Reporting System - Analytics Functions

let categoryChart, statusChart, trendsChart, barangayChart;

// Initialize all charts
async function initAnalytics() {
    showLoading('Loading analytics data...');
    
    try {
        await Promise.all([
            loadCategoryChart(),
            loadStatusChart(),
            loadTrendsChart(),
            loadBarangayChart(),
            loadMetrics(),
            loadInsights()
        ]);
    } catch (error) {
        console.error('Failed to load analytics:', error);
        showNotification('Failed to load analytics data', 'error');
    } finally {
        hideLoading();
    }
}

// Load Category Chart
async function loadCategoryChart() {
    const response = await fetch('../../controllers/AnalyticsController.php?action=category_stats');
    const data = await response.json();
    
    const ctx = document.getElementById('categoryChart').getContext('2d');
    
    if (categoryChart) categoryChart.destroy();
    
    categoryChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(item => item.name),
            datasets: [{
                label: 'Number of Reports',
                data: data.map(item => item.count),
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'top' },
                tooltip: { callbacks: { label: (ctx) => `${ctx.raw} reports` } }
            },
            scales: { y: { beginAtZero: true, title: { display: true, text: 'Number of Reports' } } }
        }
    });
}

// Load Status Chart
async function loadStatusChart() {
    const response = await fetch('../../controllers/AnalyticsController.php?action=status_stats');
    const data = await response.json();
    
    const ctx = document.getElementById('statusChart').getContext('2d');
    
    if (statusChart) statusChart.destroy();
    
    const colors = {
        pending: '#f59e0b',
        verified: '#3b82f6',
        in_progress: '#8b5cf6',
        resolved: '#10b981',
        rejected: '#ef4444'
    };
    
    statusChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.map(item => item.status.toUpperCase()),
            datasets: [{
                data: data.map(item => item.count),
                backgroundColor: data.map(item => colors[item.status] || '#6b7280'),
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${ctx.raw} reports (${((ctx.raw / data.reduce((a,b) => a + b.count, 0)) * 100).toFixed(1)}%)` } }
            }
        }
    });
}

// Load Trends Chart
async function loadTrendsChart() {
    const response = await fetch('../../controllers/AnalyticsController.php?action=monthly_trends');
    const data = await response.json();
    
    const ctx = document.getElementById('trendsChart').getContext('2d');
    
    if (trendsChart) trendsChart.destroy();
    
    trendsChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(item => item.month),
            datasets: [{
                label: 'Reports per Month',
                data: data.map(item => item.count),
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: 'rgb(75, 192, 192)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                tooltip: { callbacks: { label: (ctx) => `${ctx.raw} reports` } }
            },
            scales: { y: { beginAtZero: true, title: { display: true, text: 'Number of Reports' } } }
        }
    });
}

// Load Barangay Chart
async function loadBarangayChart() {
    const response = await fetch('../../controllers/AnalyticsController.php?action=barangay_stats');
    const data = await response.json();
    
    const ctx = document.getElementById('barangayChart').getContext('2d');
    
    if (barangayChart) barangayChart.destroy();
    
    barangayChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: data.map(item => item.name),
            datasets: [{
                data: data.map(item => item.count),
                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#FF6384'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'right' },
                tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${ctx.raw} reports` } }
            }
        }
    });
}

// Load Key Metrics
async function loadMetrics() {
    const response = await fetch('../../controllers/AnalyticsController.php?action=metrics');
    const data = await response.json();
    
    document.getElementById('totalReports').textContent = data.total_reports;
    document.getElementById('pendingReports').textContent = data.pending_reports;
    document.getElementById('resolvedReports').textContent = data.resolved_reports;
    document.getElementById('avgResolution').textContent = `${data.avg_resolution_days} days`;
    document.getElementById('totalUsers').textContent = data.total_users;
    
    const resolutionRate = data.total_reports > 0 ? ((data.resolved_reports / data.total_reports) * 100).toFixed(1) : 0;
    document.getElementById('resolutionRate').textContent = `${resolutionRate}%`;
}

// Load Decision Insights
async function loadInsights() {
    const response = await fetch('../../controllers/AnalyticsController.php?action=insights');
    const insights = await response.json();
    
    const container = document.getElementById('insightsContainer');
    if (!container) return;
    
    container.innerHTML = '';
    
    insights.forEach(insight => {
        const bgColor = insight.type === 'warning' ? 'bg-yellow-500' : insight.type === 'urgent' ? 'bg-red-500' : 'bg-blue-500';
        const icon = insight.type === 'warning' ? 'fa-exclamation-triangle' : insight.type === 'urgent' ? 'fa-bell' : 'fa-lightbulb';
        
        const div = document.createElement('div');
        div.className = `${bgColor} bg-opacity-20 rounded-lg p-4`;
        div.innerHTML = `
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold text-gray-900">${insight.type === 'recommendation' ? '💡 Recommendation' : (insight.type === 'urgent' ? '🚨 Urgent' : '⚠️ Alert')}</p>
                    <p class="text-gray-700 text-sm">${insight.message}</p>
                </div>
                <i class="fas ${icon} text-2xl ${bgColor.replace('bg', 'text')}"></i>
            </div>
        `;
        container.appendChild(div);
    });
}

// Export charts as image
function exportChart(chartId, format = 'png') {
    const canvas = document.getElementById(chartId);
    if (!canvas) return;
    
    const link = document.createElement('a');
    link.download = `${chartId}.${format}`;
    link.href = canvas.toDataURL(`image/${format}`);
    link.click();
    
    showNotification('Chart exported successfully!', 'success');
}

// Refresh all data
function refreshAnalytics() {
    initAnalytics();
    showNotification('Analytics data refreshed', 'success');
}

// Auto-refresh every 5 minutes
if (document.getElementById('categoryChart')) {
    setInterval(refreshAnalytics, 300000);
}