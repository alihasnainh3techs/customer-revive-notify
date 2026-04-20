const serviceField = document.querySelector('[name="service"]');

const smtpHost = document.querySelector('[name="smtp_host"]');
const port = document.querySelector('[name="port"]');
const securityType = document.querySelector('[name="security_type"]');
const username = document.querySelector('[name="username"]');
const password = document.querySelector('[name="password"]');
const customFromEmail = document.querySelector('[name="custom_from_email"]');

const smtpFields = [smtpHost, port, securityType, username, password];

function getWrapper(el) {
    return el?.closest(
        's-text-field, s-number-field, s-select, s-password-field'
    );
}

function toggleFields() {
    const isCustom = serviceField.value === 'custom';

    smtpFields.forEach(field => {
        const wrapper = getWrapper(field);
        if (!wrapper) return;

        wrapper.style.display = isCustom ? '' : 'none';
        field.required = isCustom;

        if (!isCustom) {
            field.error = '';
        }
    });

    const emailWrapper = getWrapper(customFromEmail);
    if (emailWrapper) {
        emailWrapper.style.display = '';
    }
}

const fieldErrorMap = {
    service: document.querySelector('[name="service"]'),
    status: document.querySelector('[name="status"]'),
    smtp_host: document.querySelector('[name="smtp_host"]'),
    port: document.querySelector('[name="port"]'),
    security_type: document.querySelector('[name="security_type"]'),
    username: document.querySelector('[name="username"]'),
    password: document.querySelector('[name="password"]'),
    custom_from_email: document.querySelector('[name="custom_from_email"]'),
};

function clearAllErrors() {
    Object.values(fieldErrorMap).forEach(el => {
        if (el) el.error = '';
    });
}

function applyValidationErrors(errors = {}) {
    Object.entries(errors).forEach(([name, messages]) => {
        const field = document.querySelector(`[name="${name}"]`);
        if (field) {
            field.error = Array.isArray(messages)
                ? messages[0]
                : messages;
        }
    });
}

async function handleSubmit(e) {
    e.preventDefault();
    clearAllErrors();

    try {
        shopify.loading(true);

        const form = e.target;

        const formData = new FormData(form);
        const configId = formData.get('id');

        let url = '/settings/smtp';
        let method = 'POST';

        // Update mode
        if (configId) {
            url = `/settings/smtp/${configId}`;
            formData.append('_method', 'PUT');
        }

        const response = await fetch(url, {
            method,
            headers: {
                'X-CSRF-TOKEN': document.querySelector(
                    'meta[name="csrf-token"]'
                ).content,
            },
            body: formData,
        });

        const data = await response.json();

        if (response.ok) {
            shopify.toast.show(
                data.message || 'SMTP configuration saved successfully.',
                { duration: 3000 }
            );

            window.location.reload();
        } else if (response.status === 422) {
            applyValidationErrors(data.errors || {});

            shopify.toast.show('Please fix the errors and try again.', {
                duration: 4000,
                isError: true,
            });

            return;
        } else {
            throw new Error(data.message || 'Server error.');
        }
    } catch (error) {
        console.error(error);

        shopify.toast.show(
            'Network error. Please check your connection.',
            {
                duration: 4000,
                isError: true,
            }
        );
    } finally {
        shopify.loading(false);
    }
}

serviceField.addEventListener('change', toggleFields);
toggleFields();