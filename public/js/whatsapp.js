document.addEventListener('DOMContentLoaded', () => {
    // ─────────────────────────────────────────────────────────
    // DOM Elements
    // ─────────────────────────────────────────────────────────
    const addDeviceBtn = document.getElementById('add-device-btn');
    const addDeviceModal = document.getElementById('add-device-modal');
    const deviceNameInput = document.getElementById('device-name-input');
    const qrImage = document.getElementById('qr-image');
    const deviceFormStep = document.getElementById('device-form-step');
    const qrStep = document.getElementById('qr-step');

    // Single Unified Button
    const submitBtn = document.getElementById('submit-device-btn');
    const cancelBtn = document.getElementById('cancel-add-device');

    const deleteDeviceModal = document.getElementById('delete-device-modal');
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    const cancelDeleteBtn = document.getElementById('cancel-delete-btn');
    const deleteDeviceNameSpan = document.getElementById('delete-device-name');
    const deviceIdField = document.getElementById('device-id');

    // ─────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────
    function showToast(message, isError = false) {
        if (typeof shopify !== 'undefined' && shopify.toast) {
            shopify.toast.show(message, { duration: 3000, isError });
        } else {
            alert(message);
        }
    }

    function setButtonLoading(btn, loading) {
        if (loading) {
            btn.setAttribute('loading', '');
            btn.setAttribute('disabled', '');
        } else {
            btn.removeAttribute('loading');
            btn.removeAttribute('disabled');
        }
    }

    function clearError(input) {
        input.removeAttribute('error');
    }

    function showError(input, message) {
        input.setAttribute('error', message);
    }

    // ─────────────────────────────────────────────────────────
    // Reset add device modal
    // ─────────────────────────────────────────────────────────
    function resetAddDeviceModal() {
        deviceNameInput.value = '';
        clearError(deviceNameInput);
        deviceFormStep.style.display = 'block';
        qrStep.style.display = 'none';
        qrImage.src = '';

        // Reset button text
        submitBtn.textContent = 'Next';
        setButtonLoading(submitBtn, false);
    }

    if (addDeviceBtn) {
        addDeviceBtn.addEventListener('click', () => {
            resetAddDeviceModal();
            addDeviceModal.showOverlay();
        });
    }

    // ─────────────────────────────────────────────────────────
    // Unified Submit Logic (Next then Save)
    // ─────────────────────────────────────────────────────────
    submitBtn.addEventListener('click', async () => {
        // STEP 1: If we are on the form, act as "Next"
        if (deviceFormStep.style.display !== 'none') {
            const name = deviceNameInput.value.trim();
            if (!name) {
                showError(deviceNameInput, 'Device name is required');
                return;
            }
            clearError(deviceNameInput);

            setButtonLoading(submitBtn, true);

            try {
                const response = await fetch('/settings/whatsapp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ name }),
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    qrImage.src = data.qr || 'data:image/svg+xml,...';

                    // Switch UI steps
                    deviceFormStep.style.display = 'none';
                    qrStep.style.display = 'block';

                    // Update button to act as "Save"
                    submitBtn.textContent = 'Save';
                    setButtonLoading(submitBtn, false);

                    showToast(data.message || 'Device created. Scan QR code with WhatsApp.');
                } else {
                    if (response.status === 422 && data.errors && data.errors.name) {
                        showError(deviceNameInput, data.errors.name[0]);
                    } else {
                        showToast(data.message || 'Failed to create device', true);
                    }
                    setButtonLoading(submitBtn, false);
                }
            } catch (err) {
                console.error(err);
                showToast('Network error. Please try again.', true);
                setButtonLoading(submitBtn, false);
            }
        }
        // STEP 2: If we are on the QR step, act as "Save" (Reload)
        else {
            setButtonLoading(submitBtn, true);
            addDeviceModal.hideOverlay();
            window.location.reload();
        }
    });

    cancelBtn.addEventListener('click', () => {
        addDeviceModal.hideOverlay();
        resetAddDeviceModal();
    });

    // ─────────────────────────────────────────────────────────
    // Delete device logic
    // ─────────────────────────────────────────────────────────
    function attachDeleteListeners() {
        document.querySelectorAll('.delete-device-btn').forEach(btn => {
            btn.removeEventListener('click', handleDeleteClick);
            btn.addEventListener('click', handleDeleteClick);
        });
    }

    function handleDeleteClick(e) {
        const deviceId = e.currentTarget.getAttribute('data-device-id');
        const deviceName = e.currentTarget.getAttribute('data-device-name');
        if (deleteDeviceNameSpan) deleteDeviceNameSpan.textContent = `Are you sure you want to delete "${deviceName}"?`;
        if (deviceIdField) deviceIdField.value = deviceId;
        deleteDeviceModal.showOverlay();
    }

    attachDeleteListeners();

    confirmDeleteBtn.addEventListener('click', async () => {
        const deviceId = deviceIdField.value;
        if (!deviceId) return;

        setButtonLoading(confirmDeleteBtn, true);
        cancelDeleteBtn.setAttribute('disabled', '');

        try {
            const response = await fetch(`/settings/whatsapp/${deviceId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });
            const data = await response.json();

            if (response.ok && data.success) {
                showToast(data.message || 'Device deleted successfully');
                deleteDeviceModal.hideOverlay();
                window.location.reload();
            } else {
                showToast(data.message || 'Failed to delete device', true);
                setButtonLoading(confirmDeleteBtn, false);
                cancelDeleteBtn.removeAttribute('disabled');
            }
        } catch (err) {
            console.error(err);
            showToast('Network error', true);
            setButtonLoading(confirmDeleteBtn, false);
            cancelDeleteBtn.removeAttribute('disabled');
        }
    });

    cancelDeleteBtn.addEventListener('click', () => {
        deleteDeviceModal.hideOverlay();
    });

    const whatsappSwitch = document.getElementById('enable-whatsapp-switch');

    if (whatsappSwitch) {
        whatsappSwitch.addEventListener('change', async (event) => {
            // Get the current state (true if checked, false if not)
            const deviceId = event.target.getAttribute('data-device-id');
            const enable_whatsapp = event.currentTarget.checked;

            try {
                const response = await fetch(`/settings/whatsapp/${deviceId}/toggle-notifications`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        // Match the key expected by your Controller validator
                        enable_whatsapp,
                    }),
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    // Shopify Toast for success
                    if (typeof shopify !== 'undefined' && shopify.toast) {
                        shopify.toast.show(data.message, { duration: 3000 });
                    } else {
                        console.log('Success:', data.message);
                    }
                } else {
                    throw new Error(data.message || 'Failed to update settings');
                }
            } catch (error) {
                console.error('Error toggling WhatsApp:', error);

                // Revert the switch visually if the database update failed
                if (isChecked) {
                    whatsappSwitch.removeAttribute('checked');
                } else {
                    whatsappSwitch.setAttribute('checked', '');
                }

                // Shopify Toast for error
                if (typeof shopify !== 'undefined' && shopify.toast) {
                    shopify.toast.show(error.message, { isError: true, duration: 3000 });
                }
            }
        });
    }
});