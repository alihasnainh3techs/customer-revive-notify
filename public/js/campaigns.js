document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('campaigns-table');
    const { currentPage, lastPage } = window.__pagination;

    // ── Helper: navigate with updated search params ──────────────────────
    function navigate(page) {
        const params = new URLSearchParams(window.location.search);

        if (page !== undefined) {
            page > 1 ? params.set('page', page) : params.delete('page');
        }

        window.location.href = `${window.location.pathname}?${params.toString()}`;
    }

    // ── Pagination events ─────────────────────────────────────────────────
    table.addEventListener('nextpage', () => {
        if (currentPage < lastPage) navigate(currentPage + 1);
    });

    table.addEventListener('previouspage', () => {
        if (currentPage > 1) navigate(currentPage - 1);
    });
});

const deleteCampaignForm = document.getElementById('delete-campaign-form');
const deleteCampaignModal = document.getElementById('delete-campaign-modal');
const confirmDeleteCampaignModal = document.getElementById('confirm-delete-campaign-modal');
const cancelDeleteCampaignModal = document.getElementById('cancel-delete-campaign-modal');

const idField = deleteCampaignModal.querySelector('[name="id"]');

deleteCampaignForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    confirmDeleteCampaignModal.setAttribute('loading', '');
    confirmDeleteCampaignModal.setAttribute('disabled', '');
    cancelDeleteCampaignModal.setAttribute('disabled', '');

    try {
        const response = await fetch(`/campaigns/${idField.value}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            }
        });

        const data = await response.json();
        if (response.ok) {
            // Success
            shopify.toast.show('Campaign deleted successfully.', { duration: 3000 });
            deleteCampaignModal.hideOverlay();

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
        confirmDeleteCampaignModal.removeAttribute('loading', '');
        confirmDeleteCampaignModal.removeAttribute('disabled', '');
        cancelDeleteCampaignModal.removeAttribute('disabled', '');
    }
});

function selectCampaign(campaign) {
    const data = JSON.parse(campaign);

    const modalDeleteCampaignName = document.getElementById("modal-delete-campaign-name");
    modalDeleteCampaignName.innerHTML = `Are you sure you want to delete "${data.campaign_name}"?`;

    const deleteCampaignModal = document.getElementById('delete-campaign-modal');
    const idField = deleteCampaignModal.querySelector('[name="id"]');
    idField.value = data.id;
}