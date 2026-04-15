document.addEventListener('DOMContentLoaded', () => {
    // --- Selectors ---
    const campaignTypeSelect = document.querySelector('[name="campaign_type"]');
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
    const customValidityField = document.querySelector('[name="custom_validity"]');
    const monthlyValidityField = document.querySelector('[name="monthly_validity"]');

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
        document.querySelector('[name="discount_code"]').value = result;
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

    // --- State Management ---
    let selectedProducts = [];

    // --- Selectors ---
    const selectBtn = document.getElementById('select-products-btn');
    const listContainer = document.getElementById('product-list-container');
    const summaryText = document.getElementById('selection-summary');

    /**
     * Updates the UI based on the current state
     */
    function renderProducts() {
        listContainer.innerHTML = '';

        // 1. Update the Summary Count
        const productCount = selectedProducts.length;
        if (productCount === 0) {
            summaryText.textContent = 'No products selected yet';
        } else {
            summaryText.textContent = `${productCount} product${productCount > 1 ? 's' : ''} selected`;
        }

        // 2. Build the list
        selectedProducts.forEach((product) => {
            // A product might have multiple variants selected. 
            // We'll create a row for each selected variant to be specific.
            product.variants.forEach((variant) => {
                const row = document.createElement('s-clickable');
                row.setAttribute('border', 'base');
                row.setAttribute('borderStyle', 'solid none none none');
                row.setAttribute('paddingInline', 'base');
                row.setAttribute('paddingBlock', 'small');

                // Fallback image logic
                const imgSrc = product.images?.[0]?.originalSrc || 'https://cdn.shopify.com/s/assets/no-image-2048-5e88c1b20e087fb7bbe9a3771824e743c244f437e4f8ba93bbf7b11b53f7824c_small.gif';

                // Format title: Show "Product Title" or "Product Title - Variant Title"
                const displayTitle = variant.title === 'Default Title'
                    ? product.title
                    : `${product.title} - ${variant.title}`;

                row.innerHTML = `
                <s-grid gridTemplateColumns="auto 1fr auto" gap="base" alignItems="center">
                    <s-thumbnail size="small" src="${imgSrc}" alt="${displayTitle}"></s-thumbnail>
                    <s-stack>
                        <s-heading size="small">${displayTitle}</s-heading>
                        <s-text color="subdued">${variant.sku || 'No SKU'}</s-text>
                    </s-stack>
                    <s-button 
                        variant="tertiary" 
                        icon="x" 
                        data-variant-id="${variant.id}"
                        accessibilityLabel="Remove ${displayTitle}">
                    </s-button>
                </s-grid>
            `;

                // Handle Removal
                const removeBtn = row.querySelector(`[data-variant-id="${variant.id}"]`);
                removeBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    removeVariant(variant.id);
                });

                listContainer.appendChild(row);
            });
        });
    }

    /**
     * Removes a specific variant from the selection
     */
    function removeVariant(variantId) {
        selectedProducts = selectedProducts.map(product => {
            return {
                ...product,
                variants: product.variants.filter(v => v.id !== variantId)
            };
        }).filter(product => product.variants.length > 0); // Remove product if it has no variants left

        renderProducts();
    }

    /**
     * Handle Selection
     */
    selectBtn.addEventListener('click', async () => {
        const selected = await shopify.resourcePicker({
            type: 'product',
            multiple: true,
            action: 'select',
            // This ensures the picker remembers what you already chose
            selectionIds: selectedProducts.map(p => ({
                id: p.id,
                variants: p.variants.map(v => ({ id: v.id }))
            }))
        });

        if (selected) {
            selectedProducts = selected;
            renderProducts();

            console.log("Selected Products: ", selectedProducts);
        }
    });

    // --- State ---
    const filters = {
        tags: [],
        purchasedProducts: [],
        totalSpent: {
            from: null,
            to: null
        },
        lastOrderDate: {
            from: null,
            to: null
        },
    };

    const purchasedBtn = document.getElementById('choose-purchased-btn');
    const countDisplay = document.getElementById('purchased-products-count');
    const spentFrom = document.getElementById('filter-spent-from');
    const spentTo = document.getElementById('filter-spent-to');
    const dateFrom = document.getElementById('filter-date-from');
    const dateTo = document.getElementById('filter-date-to');

    /**
 * Syncs the component value to the filter object
 * Sets to null if the value is an empty string
 */
    function syncFilter(element, category, key) {
        const val = element.value ? element.value.trim() : "";
        filters[category][key] = val !== "" ? val : null;
    }

    /**
 * Updates only the count text in the UI
 */
    function updatePurchasedCountUI() {
        const count = filters.purchasedProducts.length;

        if (count === 0) {
            countDisplay.textContent = 'No products selected yet';
        } else {
            countDisplay.textContent = `${count} product${count > 1 ? 's' : ''} selected`;
        }
    }

    /**
     * Handle "Products Purchased" Selection
     */
    purchasedBtn.addEventListener('click', async () => {
        const selected = await shopify.resourcePicker({
            type: 'product',
            multiple: true,
            action: 'select',
            // Pass existing selection so the picker shows them as checked
            selectionIds: filters.purchasedProducts.map(p => ({
                id: p.id,
                variants: p.variants.map(v => ({ id: v.id }))
            }))
        });

        if (selected) {
            // Update the filters object with the full resource data
            filters.purchasedProducts = selected;

            // Reflect the change in the UI count
            updatePurchasedCountUI();

            console.log('Updated Purchased Products Filter:', filters.purchasedProducts);
        }
    });

    // --- Selectors ---
    const tagInput = document.getElementById('tag-input-field');
    const chipContainer = document.getElementById('chip-container');

    /**
     * Renders the chips based on the filters.tags array
     */
    function renderTags() {
        // Clear container
        chipContainer.innerHTML = '';

        filters.tags.forEach((tag) => {
            const chip = document.createElement('s-clickable-chip');
            chip.setAttribute('removable', '');
            chip.textContent = tag;

            // Polaris/App Bridge components fire a 'remove' event 
            // when the 'x' on the chip is clicked
            chip.addEventListener('remove', () => {
                handleRemoveTag(tag);
            });

            chipContainer.appendChild(chip);
        });
    }

    /**
     * Adds a unique tag to the array and clears the input
     */
    function handleAddTag(value) {
        const cleanTag = value.trim();

        // 1. Validation: Not empty and not a duplicate
        if (cleanTag !== '' && !filters.tags.includes(cleanTag)) {
            filters.tags.push(cleanTag);
            renderTags();
        }

        // 2. Clear the input field after processing
        tagInput.value = '';
    }

    /**
     * Removes a tag from the array
     */
    function handleRemoveTag(tagToRemove) {
        filters.tags = filters.tags.filter(tag => tag !== tagToRemove);
        renderTags();
    }

    // --- Event Listeners ---

    // Handle Enter Key
    tagInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            handleAddTag(tagInput.value);
        }
    });

    // Handle Blur (Coming out of the input)
    tagInput.addEventListener('blur', () => {
        handleAddTag(tagInput.value);
    });

    // Amount Listeners
    spentFrom.addEventListener('change', () => syncFilter(spentFrom, 'totalSpent', 'from'));
    spentTo.addEventListener('change', () => syncFilter(spentTo, 'totalSpent', 'to'));

    // Date Listeners
    dateFrom.addEventListener('change', () => syncFilter(dateFrom, 'lastOrderDate', 'from'));
    dateTo.addEventListener('change', () => syncFilter(dateTo, 'lastOrderDate', 'to'));

    // --- Apply Filters Button ---
    const applyBtn = document.getElementById('apply-filters-btn');

    applyBtn.addEventListener('click', async () => {
        console.log('Final Filters Applied:', filters);
    });
});