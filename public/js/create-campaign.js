(function () {
    // 1. Select elements
    const spentFrom = document.getElementById('filter-spent-from');
    const spentTo = document.getElementById('filter-spent-to');
    const dateFrom = document.getElementById('filter-date-from');
    const dateTo = document.getElementById('filter-date-to');
    const applyBtn = document.getElementById('apply-filters-btn');
    const errorBanner = document.getElementById('error-banner');

    const priceRegex = /^(0|[1-9]\d*)(\.\d{1,2})?$/;

    const validate = () => {
        let isValid = true;
        let bannerMessages = [];

        // --- 1. Individual Field Format Validation (Regex) ---
        const valFrom = spentFrom.value.trim();
        const valTo = spentTo.value.trim();

        if (valFrom && !priceRegex.test(valFrom)) {
            spentFrom.setAttribute('error', 'Enter a valid amount (e.g. 10.00)');
            isValid = false;
        } else {
            spentFrom.removeAttribute('error');
        }

        if (valTo && !priceRegex.test(valTo)) {
            spentTo.setAttribute('error', 'Enter a valid amount (e.g. 100.00)');
            isValid = false;
        } else {
            spentTo.removeAttribute('error');
        }

        // --- 2. Logical Range Validation (Amount) ---
        // Only compare if both formats are valid and present
        if (valFrom && valTo && !isNaN(valFrom) && !isNaN(valTo)) {
            if (parseFloat(valFrom) > parseFloat(valTo)) {
                bannerMessages.push("The minimum amount (From) cannot be greater than the maximum amount (To).");
                isValid = false;
            }
        }

        // --- 3. Logical Range Validation (Date) ---
        const dFrom = dateFrom.value;
        const dTo = dateTo.value;

        if (dFrom && dTo) {
            if (new Date(dFrom) > new Date(dTo)) {
                bannerMessages.push("The start date (From) must be earlier than or equal to the end date (To).");
                isValid = false;
            }
        }

        // --- 4. UI Updates (Banner & Button) ---
        if (bannerMessages.length > 0) {
            errorBanner.setAttribute('tone', 'critical');
            // We use s-stack inside the banner for clean spacing if multiple errors exist
            errorBanner.innerHTML = `<s-stack direction="block" gap="tight">
                ${bannerMessages.map(msg => `<s-text variant="bodyMd" as="p">${msg}</s-text>`).join('')}
            </s-stack>`;
            errorBanner.style.display = 'block';
        } else {
            errorBanner.style.display = 'none';
            errorBanner.innerHTML = '';
        }

        // Disable/Enable Apply Button
        if (isValid) {
            applyBtn.removeAttribute('disabled');
        } else {
            applyBtn.setAttribute('disabled', 'true');
        }
    };

    // Attach Listeners
    spentFrom.addEventListener('input', validate);
    spentTo.addEventListener('input', validate);

    // Dates use 'change' because users pick them from a calendar
    dateFrom.addEventListener('change', validate);
    dateTo.addEventListener('change', validate);

    // Run once on load to ensure initial button state is correct
    validate();
})();

// --- Selectors ---
const campaignTypeSelect = document.querySelector('[name="campaign_type"]');
const scheduleChoiceList = document.querySelector('#campaign-schedule-mode');
// --- Discount Type Selectors ---
const discountTypeChoiceList = document.querySelector('#discount-type-choice-list');
const stackPercentage = document.getElementById('stack-percentage');
const stackFixed = document.getElementById('stack-fixed');
const stackShipping = document.getElementById('stack-shipping');

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

// --- Discount Type Visibility Logic ---
const handleDiscountTypeChange = () => {
    const selectedType = discountTypeChoiceList.values[0];

    // Hide all first
    stackPercentage.style.display = 'none';
    stackFixed.style.display = 'none';
    stackShipping.style.display = 'none';

    // Show the selected one
    if (selectedType === 'percentage_discount') {
        stackPercentage.style.display = 'block';
    } else if (selectedType === 'fixed_amount_discount') {
        stackFixed.style.display = 'block';
    } else if (selectedType === 'shipping_discount') {
        stackShipping.style.display = 'block';
    }
};

// Attach the listener
if (discountTypeChoiceList) {
    discountTypeChoiceList.addEventListener('change', () => {
        handleDiscountTypeChange();
        updateDiscountSummary(); // Update summary immediately when changed
    });
}

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

    shopify.toast.show('Filters applied.', { duration: 3000 });
});

// --- Summary Section Dynamic Updates ---
const summaryStatus = document.getElementById('summary-status');
const summaryType = document.getElementById('summary-type');
const summaryTargeting = document.getElementById('summary-targeting');
const customersHeading = document.getElementById('customers-heading');
const customerFiltersSummary = document.getElementById('customer-filters-summary');
const summaryDiscounts = document.getElementById('summary-discounts');

// 1. Campaign Status & Type
const campaignStatusSelect = document.querySelector('[name="campaign_status"]');
// campaignTypeSelect is already defined in your file

function updateStatusAndTypeSummary() {
    // Update Status
    if (campaignStatusSelect) {
        const selectedOption = campaignStatusSelect.value;
        summaryStatus.textContent = selectedOption === '0' ? 'Inactive' : 'Active';
    }

    // Update Type
    if (campaignTypeSelect) {
        const selectedOption = campaignTypeSelect.value;
        summaryType.textContent = selectedOption === 'other' ? 'Other' : 'Discount';
    }
}

campaignStatusSelect.addEventListener('change', updateStatusAndTypeSummary);
campaignTypeSelect.addEventListener('change', updateStatusAndTypeSummary);

// 2. Targeting Summary
// This function reads the global `filters` object (from your existing code) and updates the text.
function updateTargetingSummary() {
    if (!filters) return;

    const hasSpentFilter = filters.totalSpent?.from !== null || filters.totalSpent?.to !== null;
    const hasDateFilter = filters.lastOrderDate?.from !== null || filters.lastOrderDate?.to !== null;
    const hasProductFilter = filters.purchasedProducts?.length > 0;
    const hasTagFilter = filters.tags?.length > 0;

    const activeFilters = [];
    if (hasSpentFilter) activeFilters.push('spend');
    if (hasDateFilter) activeFilters.push('recency');
    if (hasProductFilter) activeFilters.push('products');
    if (hasTagFilter) activeFilters.push('tags');

    if (activeFilters.length === 0) {
        summaryTargeting.textContent = 'All customers';
        customerFiltersSummary.textContent = '(No filters applied)';
        customersHeading.textContent = 'All customers';
    } else {
        summaryTargeting.textContent = `Based on ${activeFilters.join(', ')}`;
        customerFiltersSummary.textContent = `Based on ${activeFilters.join(', ')}`;
        customersHeading.textContent = `Filtered customers`;
    }
}

// Update targeting summary whenever the "Apply filters" button is clicked.
// We hook into the existing button's event listener.
const originalApplyBtnHandler = applyBtn?.onclick;
if (applyBtn) {
    applyBtn.addEventListener('click', () => {
        // Wait a tiny bit for the main handler to update the filters object
        setTimeout(updateTargetingSummary, 50);
    });
}

// 3. Discount Rules Summary
function updateDiscountSummary() {
    const selectedType = discountTypeChoiceList.values[0];
    const typeLabels = {
        'percentage_discount': 'Percentage',
        'fixed_amount_discount': 'Fixed amount',
        'shipping_discount': 'Shipping'
    };

    summaryDiscounts.textContent = typeLabels[selectedType] || 'None';
}

// --- Initialize Summary on Page Load ---
// Call all update functions once at the start to set the initial correct values.
handleDiscountTypeChange();
updateStatusAndTypeSummary();
updateTargetingSummary();
updateDiscountSummary();

function handleReset() {
    // --- Reset State Objects ---

    // 1. Reset selected products array
    selectedProducts = [];

    // 2. Reset filters object to initial state
    filters.tags = [];
    filters.purchasedProducts = [];
    filters.totalSpent = {
        from: null,
        to: null
    };
    filters.lastOrderDate = {
        from: null,
        to: null
    };

    // --- Re-render UI Components ---

    // 3. Re-render product list and summary
    renderProducts();

    // 4. Re-render tags/chips in the modal
    renderTags();

    // 5. Reset purchased products count display
    updatePurchasedCountUI();

    // 6. Reset discount sections visibility based on campaign type
    handleCampaignTypeChange();

    // 7. Reset schedule visibility
    handleScheduleChange();

    // 8. Update all summary sections to reflect reset state
    updateStatusAndTypeSummary();
    updateTargetingSummary();
    updateDiscountSummary();

    if (discountTypeChoiceList) {
        discountTypeChoiceList.values = ['percentage_discount'];
        handleDiscountTypeChange();
    }
}

const fieldErrorMap = {
    campaign_name: document.querySelector('[name="campaign_name"]'),
    discount_code: document.querySelector('[name="discount_code"]'),
    custom_start_date: document.querySelector('[name="custom_start_date"]'),
    message_template: document.querySelector('[name="message_template"]'),
    email_template: document.querySelector('[name="email_template"]'),
    percentage_value: document.querySelector('[name="percentage_value"]'),
    percentage_min_subtotal: document.querySelector('[name="percentage_min_subtotal"]'),
    fixed_value: document.querySelector('[name="fixed_value"]'),
    fixed_min_subtotal: document.querySelector('[name="fixed_min_subtotal"]'),
    shipping_discount_amount: document.querySelector('[name="shipping_discount_amount"]'),
    shipping_min_subtotal: document.querySelector('[name="shipping_min_subtotal"]'),
    total_spent_from: document.querySelector('[name="total_spent_from"]'),
    total_spent_to: document.querySelector('[name="total_spent_to"]'),
    last_order_from: document.querySelector('[name="last_order_from"]'),
    last_order_to: document.querySelector('[name="last_order_to"]'),
};

function clearAllErrors() {
    Object.values(fieldErrorMap).forEach(el => {
        if (el) el.error = '';
    });
}

function applyValidationErrors(errors) {
    let globalErrorHandled = false;

    Object.entries(errors).forEach(([key, messages]) => {
        const el = fieldErrorMap[key];
        if (el) {
            el.error = Array.isArray(messages) ? messages[0] : messages;
        }
    });

    // discount_rules has no single field — surface as toast
    if (errors.discount_rules) {
        const msg = Array.isArray(errors.discount_rules)
            ? errors.discount_rules[0]
            : errors.discount_rules;
        shopify.toast.show(msg, { duration: 5000, isError: true });
        globalErrorHandled = true;
    }

    return globalErrorHandled;
}

async function handleSubmit(e) {
    e.preventDefault();
    clearAllErrors();

    try {
        shopify.loading(true);

        const form = e.target;
        const formData = new FormData(form);

        // Map the full objects to just an array of Variant GIDs
        const selectedVariantIds = selectedProducts.flatMap(product =>
            product.variants.map(variant => variant.id)
        );

        const purchasedVariantIds = filters.purchasedProducts.flatMap(product =>
            product.variants.map(variant => variant.id)
        );

        // Append in-memory state that has no real form inputs
        formData.append('selected_products', JSON.stringify(selectedVariantIds));
        formData.append('purchased_products', JSON.stringify(purchasedVariantIds));
        formData.set('order_tags', filters.tags.join(','));

        const response = await fetch('/campaigns', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: formData,
        });

        const data = await response.json();

        if (response.ok) {
            shopify.toast.show(data.message || 'Campaign created successfully.', {
                duration: 3000,
            });

            window.location.href = '/campaigns' + window.location.search;
        } else if (response.status === 422) {
            const isGlobalErrorShown = applyValidationErrors(data.errors ?? {});
            if (!isGlobalErrorShown) {
                shopify.toast.show('Please fix the errors and try again.', {
                    duration: 4000,
                    isError: true,
                });
            }
        } else {
            throw new Error(data.message || 'Server error.');
        }

    } catch (err) {
        console.error("Error: ", err);
        shopify.toast.show('Network error. Please check your connection.', {
            duration: 3000,
            isError: true,
        });
    } finally {
        shopify.loading(false);
    }
}

document.getElementById('message-template-select').addEventListener('change', function (e) {
    const select = e.target;                     // <s-select> element
    const value = select.value;                  // selected option’s value
    const hiddenInput = document.getElementById('message_template_source');

    // Reset when the placeholder is selected (empty value)
    if (!value) {
        hiddenInput.value = '';
        return;
    }

    // Find the selected <s-option> inside the select's light DOM
    // (Shoelace options are slotted, so querySelector works on the host)
    const option = select.querySelector(`s-option[value="${CSS.escape(value)}"]`);
    if (!option) return;

    // Walk up to the <s-option-group> and read its data-group
    const group = option.closest('s-option-group');
    if (group) {
        hiddenInput.value = group.dataset.group;   // "texnity" or "app"
    }
});

document.getElementById('email-template-select').addEventListener('change', function (e) {
    const select = e.target;                     // <s-select> element
    const value = select.value;                  // selected option’s value
    const hiddenInput = document.getElementById('email_template_source');

    // Reset when the placeholder is selected (empty value)
    if (!value) {
        hiddenInput.value = '';
        return;
    }

    // Find the selected <s-option> inside the select's light DOM
    // (Shoelace options are slotted, so querySelector works on the host)
    const option = select.querySelector(`s-option[value="${CSS.escape(value)}"]`);
    if (!option) return;

    // Walk up to the <s-option-group> and read its data-group
    const group = option.closest('s-option-group');
    if (group) {
        hiddenInput.value = group.dataset.group;   // "texnity" or "app"
    }
});