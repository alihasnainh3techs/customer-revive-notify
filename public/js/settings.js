
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

    const savedTab = localStorage.getItem('activeSettingsTab') || 'templates';
    activateTab(savedTab);

    buttons.forEach(btn => {
        btn.addEventListener('click', function () {
            activateTab(this.dataset.target);
        });
    });

    const variables = [
        { name: '{{ first_name }}' },
        { name: '{{ last_name }}' },
        { name: '{{ email }}' },
        { name: '{{ phone }}' },
        { name: '{{ shop_name }}' },
        { name: '{{ shop_url }}' },
        { name: '{{ shop_email }}' },
        { name: '{{ discount_code }}' },
        { name: '{{ discount_amount }}' },
        { name: '{{ discount_expiry }}' },
        { name: '{{ discount_link }}' },
        { name: '{{ last_order_date }}' },
        { name: '{{ total_spent }}' },
    ];

    const container = document.getElementById('variables-container');

    if (container) {
        container.innerHTML = variables.map(v => `
            <s-box padding="small-100" border="small-100 base solid" borderRadius="small-100" background="transparent">
                <s-stack direction="inline" alignItems="center" justifyContent="space-between" gap="small-200">
                    <s-text type="strong">${v.name}</s-text>
                    <s-button 
                        variant="secondary" 
                        icon="clipboard" 
                        accessibilityLabel="Copy ${v.name} variable"
                        onclick="copyVariable('${v.name}', this)">
                    </s-button>
                </s-stack>
            </s-box>
        `).join('');
    }

    const statusSwitch = document.getElementById('status-switch');
    const statusBadge = document.getElementById('status-badge');

    statusSwitch.addEventListener('change', () => {
        const isChecked = statusSwitch.checked;
        statusBadge.textContent = isChecked ? 'Active' : 'Inactive';
        statusBadge.setAttribute('tone', isChecked ? 'success' : 'critical');
        statusSwitch.value = isChecked ? '1' : '0';
    });

    // ───────────────────────────────────────────
    // Form Submission
    // ───────────────────────────────────────────
    const createTemplateForm = document.getElementById('create-template-form');
    const createTemplateModal = document.getElementById('create-template-modal');
    const createTemplateBtnSubmit = document.getElementById('create-template-btn-submit');
    const createTemplateBtnClose = document.getElementById('create-template-btn-close');

    const deleteTemplateForm = document.getElementById('delete-template-form');
    const deleteTemplateModal = document.getElementById('delete-template-modal');
    const confirmDeleteTemplateModal = document.getElementById('confirm-delete-template-modal');
    const cancelDeleteTemplateModal = document.getElementById('cancel-delete-template-modal');

    // Fields
    const nameField = createTemplateModal.querySelector('[name="template_name"]');
    const subjectField = createTemplateModal.querySelector('[name="subject"]');
    const bodyField = createTemplateModal.querySelector('[name="body"]');

    const idField = deleteTemplateModal.querySelector('[name="id"]');

    // Map API error keys → field elements
    const fieldMap = {
        name: nameField,
        subject: subjectField,
        body: bodyField,
    };

    function setLoading(loading) {
        if (loading) {
            createTemplateBtnSubmit.setAttribute('loading', '');
            createTemplateBtnSubmit.setAttribute('disabled', '');
            createTemplateBtnClose.setAttribute('disabled', '');
        } else {
            createTemplateBtnSubmit.removeAttribute('loading');
            createTemplateBtnSubmit.removeAttribute('disabled');
            createTemplateBtnClose.removeAttribute('disabled');
        }
    }

    function clearErrors() {
        Object.values(fieldMap).forEach(el => {
            if (el) el.removeAttribute('error');
        });
    }

    function showFieldErrors(errors) {
        Object.entries(errors).forEach(([key, messages]) => {
            const el = fieldMap[key];
            if (el) el.setAttribute('error', messages[0]);
        });
    }

    function resetForm() {
        nameField.value = '';
        subjectField.value = '';
        bodyField.value = '';

        // Reset type to email
        selectType('email');

        // Reset status to active
        statusSwitch.checked = true;
        statusSwitch.value = '1';
        statusBadge.textContent = 'Active';
        statusBadge.setAttribute('tone', 'success');

        clearErrors();
    }

    createTemplateModal.addEventListener("afterhide", resetForm);

    createTemplateForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors();
        setLoading(true);

        try {
            const response = await fetch('/templates', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    name: nameField.value,
                    type: currentType,
                    subject: subjectField.value,
                    body: bodyField.value,
                    status: statusSwitch.value === '1',
                }),
            });

            const data = await response.json();
            if (response.ok) {
                // Success
                shopify.toast.show('Template created successfully.', { duration: 3000 });
                resetForm();
                createTemplateModal.hideOverlay();

                window.location.reload();
            } else if (response.status === 422 && data.errors) {
                // Field validation errors
                showFieldErrors(data.errors);
            } else {
                // Generic error
                shopify.toast.show(data.message || 'Something went wrong. Please try again.', {
                    duration: 3000,
                    isError: true,
                });
            }
        } catch (err) {
            console.error("Error: ", err);
            shopify.toast.show('Network error. Please check your connection.', {
                duration: 3000,
                isError: true,
            });
        } finally {
            setLoading(false);
        }
    });

    deleteTemplateForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        confirmDeleteTemplateModal.setAttribute('loading', '');
        confirmDeleteTemplateModal.setAttribute('disabled', '');
        cancelDeleteTemplateModal.setAttribute('disabled', '');

        try {
            const response = await fetch(`/templates/${idField.value}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                }
            });

            const data = await response.json();
            if (response.ok) {
                // Success
                shopify.toast.show('Template deleted successfully.', { duration: 3000 });
                deleteTemplateModal.hideOverlay();

                window.location.reload();
            } else {
                // Generic error
                shopify.toast.show(data.message || 'Something went wrong. Please try again.', {
                    duration: 3000,
                    isError: true,
                });
            }
        } catch (err) {
            console.error("Error: ", err);
            shopify.toast.show('Network error. Please check your connection.', {
                duration: 3000,
                isError: true,
            });
        } finally {
            confirmDeleteTemplateModal.removeAttribute('loading', '');
            confirmDeleteTemplateModal.removeAttribute('disabled', '');
            cancelDeleteTemplateModal.removeAttribute('disabled', '');
        }
    });
});

// ───────────────────────────────────────────
// Globals
// ───────────────────────────────────────────
let currentType = 'email';

function selectType(type) {
    currentType = type;

    const btnEmail = document.getElementById('btn-email');
    const btnMessage = document.getElementById('btn-message');
    const subjectWrap = document.getElementById('subject-field');

    if (type === 'email') {
        btnEmail.setAttribute('background', 'subdued');
        btnMessage.setAttribute('background', 'primary-subdued');
        subjectWrap.style.display = 'block';
    } else {
        btnMessage.setAttribute('background', 'subdued');
        btnEmail.setAttribute('background', 'primary-subdued');
        subjectWrap.style.display = 'none';
    }
}

function copyVariable(variable, btn) {
    if (!navigator.clipboard) return;

    navigator.clipboard.writeText(variable).then(() => {
        const originalIcon = btn.getAttribute('icon');
        btn.setAttribute('icon', 'clipboard-check');
        setTimeout(() => btn.setAttribute('icon', originalIcon), 1500);
    }).catch(err => console.error('Failed to copy', err));
}

function selectTemplate(id, name, type) {
    if (type === "delete") {
        const modalDeleteTemplateName = document.getElementById("modal-delete-template-name");
        modalDeleteTemplateName.innerHTML = `Are you sure you want to delete "${name}"?`;

        const deleteTemplateModal = document.getElementById('delete-template-modal');
        const idField = deleteTemplateModal.querySelector('[name="id"]');
        idField.value = id;
    }
}