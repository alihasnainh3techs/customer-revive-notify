document.addEventListener('DOMContentLoaded', function () {
    // 1. Daily Message Volume Chart (Line Chart)
    const ctxVolume = document.getElementById('volumeChart').getContext('2d');
    new Chart(ctxVolume, {
        type: 'line',
        data: {
            labels: ['Oct 20', 'Oct 21', 'Oct 22', 'Oct 23', 'Oct 24', 'Oct 25', 'Oct 26'],
            datasets: [{
                label: 'Messages Sent',
                data: [400, 550, 480, 800, 1100, 950, 1200],
                borderColor: '#008060',
                backgroundColor: 'rgba(0, 128, 96, 0.1)',
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
            }
        }
    });

    // 2. Campaign Type Distribution (Doughnut Chart)
    const ctxType = document.getElementById('typeChart').getContext('2d');
    new Chart(ctxType, {
        type: 'doughnut',
        data: {
            labels: ['Discount', 'Notification'],
            datasets: [{
                data: [65, 35],
                backgroundColor: ['#008060', '#47c1bf'],
                hoverOffset: 4
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
});