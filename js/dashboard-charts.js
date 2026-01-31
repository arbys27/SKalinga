// Dashboard analytics charts using Chart.js
// This file assumes Chart.js is loaded via CDN in the HTML

document.addEventListener('DOMContentLoaded', function () {
    // Youth Registration Trend
    if (document.getElementById('youthTrendChart')) {
        new Chart(document.getElementById('youthTrendChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Registrations',
                    data: [120, 135, 150, 170, 200, 220, 210, 230, 250, 270, 300, 320],
                    borderColor: '#228B57',
                    backgroundColor: 'rgba(34,139,87,0.08)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '#228B57',
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Events Pie Chart
    if (document.getElementById('eventsPieChart')) {
        new Chart(document.getElementById('eventsPieChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Sports', 'Seminars', 'Community', 'Health', 'Others'],
                datasets: [{
                    data: [8, 6, 5, 4, 5],
                    backgroundColor: ['#5DADE2', '#FF6B9D', '#228B57', '#A8E6CF', '#F5F7F8'],
                    borderWidth: 2,
                }]
            },
            options: {
                plugins: { legend: { position: 'bottom' } },
                cutout: '70%',
            }
        });
    }

    // Resources Bar Chart
    if (document.getElementById('resourcesBarChart')) {
        new Chart(document.getElementById('resourcesBarChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Chairs', 'Tents', 'Sound', 'Lights', 'Sports'],
                datasets: [{
                    label: 'Borrowed',
                    data: [40, 30, 25, 20, 41],
                    backgroundColor: '#228B57',
                    borderRadius: 8,
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Health Consults
    if (document.getElementById('healthLineChart')) {
        new Chart(document.getElementById('healthLineChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Consults',
                    data: [2, 3, 4, 5, 6, 7, 8, 7, 6, 8, 10, 13],
                    borderColor: '#5DADE2',
                    backgroundColor: 'rgba(93,173,226,0.08)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '#5DADE2',
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
});
