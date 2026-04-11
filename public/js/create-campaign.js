document.addEventListener('DOMContentLoaded', () => {
    // --- Selectors ---
    const campaignTypeSelect = document.querySelector('[name="campaignType"]');
    const scheduleChoiceList = document.querySelector('#campaign-schedule-mode');

    // Sections to toggle based on Campaign Type
    const discountSections = [
        document.getElementById('section-discount-code'),
        document.getElementById('section-discounted-products'),
        document.getElementById('section-discount-rules')
    ];

    // Scheduling Stacks
    const monthlyStack = document.getElementById('stack-monthly');
    const customStack = document.getElementById('stack-custom');
    const customValidityField = document.querySelector('[name="customValidity"]');
    const monthlyValidityField = document.querySelector('[name="monthlyValidity"]');

    // --- Task 01 & 04: Campaign Type Logic ---
    const handleCampaignTypeChange = () => {
        const isOther = campaignTypeSelect.value === 'other';

        // Hide/Show Discount Sections
        discountSections.forEach(section => {
            if (section) section.style.display = isOther ? 'none' : 'block';
        });

        // Task 04: Hide Discount validity in Custom schedule if "Other"
        if (customValidityField) {
            customValidityField.style.display = isOther ? 'none' : 'block';
        }
        if (monthlyValidityField) {
            monthlyValidityField.style.display = isOther ? 'none' : 'block';
        }
    };

    // --- Task 02: Generate Discount Code ---
    window.generateCode = () => {
        const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        let result = 'SALE-';
        for (let i = 0; i < 8; i++) {
            result += characters.charAt(Math.floor(Math.random() * characters.length));
        }
        document.querySelector('[name="discountCode"]').value = result;
    };

    // Attach generate listener to the button
    const genBtn = document.querySelector('#generateCodeBtn');
    if (genBtn && genBtn.textContent.includes('Generate')) {
        genBtn.addEventListener('click', (e) => {
            e.preventDefault();
            generateCode();
        });
    }

    // --- Task 03: Schedule Logic ---
    const handleScheduleChange = () => {

        const selectedMode = scheduleChoiceList.values[0]; // 'monthly' or 'custom'

        if (selectedMode === 'monthly') {
            monthlyStack.style.display = 'block';
            customStack.style.display = 'none';
        } else {
            monthlyStack.style.display = 'none';
            customStack.style.display = 'block';
        }
    };

    // --- Event Listeners ---
    campaignTypeSelect.addEventListener('change', handleCampaignTypeChange);

    // Choice lists often use custom events, but standard 'change' usually works
    scheduleChoiceList.addEventListener('change', handleScheduleChange);

    // --- Initialization ---
    // Run on load to set default states
    handleCampaignTypeChange();
    handleScheduleChange();
});