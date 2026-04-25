document.addEventListener('DOMContentLoaded', function () {
    const data = window.DashboardData;

    // 1. Daily Message Volume
    const ctxVolume = document.getElementById('volumeChart').getContext('2d');
    new Chart(ctxVolume, {
        type: 'line',
        data: {
            labels: data.volumeLabels,
            datasets: [{
                label: 'Messages Sent',
                data: data.volumeData,
                borderColor: '#008060',
                backgroundColor: 'rgba(0, 128, 96, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });

    // 2. Campaign Type Distribution
    const ctxType = document.getElementById('typeChart').getContext('2d');
    new Chart(ctxType, {
        type: 'doughnut',
        data: {
            labels: data.distLabels,
            datasets: [{
                data: data.distData,
                backgroundColor: ['#008060', '#47c1bf', '#5c6ac4', '#9c6ade'],
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });
});