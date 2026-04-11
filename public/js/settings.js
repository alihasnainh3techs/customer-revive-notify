document.addEventListener('DOMContentLoaded', () => {
    const buttons = document.querySelectorAll('.toggle-tabs-btn');
    const panels = document.querySelectorAll('.tab-content');
    const input = document.getElementById('active-tab-input');

    function activateTab(target) {
        input.value = target;
        localStorage.setItem('activeSettingsTab', target);

        buttons.forEach(b => {
            b.setAttribute('variant', b.dataset.target === target ? 'secondary' : 'tertiary');
        });

        panels.forEach(panel => {
            panel.style.display = panel.id === target ? 'flex' : 'none';
        });
    }

    // Restore last active tab, falling back to 'templates'
    const savedTab = localStorage.getItem('activeSettingsTab') || 'templates';
    activateTab(savedTab);

    buttons.forEach(btn => {
        btn.addEventListener('click', function () {
            activateTab(this.dataset.target);
        });
    });
});