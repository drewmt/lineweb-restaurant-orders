jQuery(document).ready(function ($) {
    // Click card to open modal
    $(document).on('click', '.mfm-order-card', function () {
        const orderId = $(this).data('order-id');
        openOrderModal(orderId);
    });

    // List view button click
    $(document).on('click', '.mfm-view-order-btn', function (e) {
        e.preventDefault();
        e.stopPropagation(); // Prevent row click if we add one
        const orderId = $(this).data('order-id');
        openOrderModal(orderId);
    });

    // Close modal when clicking outside
    $(document).on('click', '#mfm-order-modal', function (e) {
        if (e.target === this) {
            closeOrderModal();
        }
    });

    // Auto-refresh every 30 seconds
    if ($('.mfm-orders-wrap').length) {
        setInterval(function () {
            // Only refresh if modal is not open
            if ($('#mfm-order-modal').css('display') === 'none') {
                location.reload();
            }
        }, 30000);
    }
});

function openOrderModal(orderId) {
    const modal = document.getElementById('mfm-order-modal');
    const content = document.getElementById('mfm-modal-content');

    modal.style.display = 'flex';
    content.innerHTML = '<p class="mfm-loading">Loading...</p>';

    // Fetch order details via AJAX
    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mfm_get_order_details&order_id=' + orderId + '&nonce=' + mfm_admin_vars.nonce
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.innerHTML = data.data.html;
            } else {
                content.innerHTML = '<p class="mfm-error">Error loading order details</p>';
            }
        })
        .catch(err => {
            content.textContent = 'Error loading order details.';
        });
}

function closeOrderModal() {
    document.getElementById('mfm-order-modal').style.display = 'none';
}

function updateOrderStatus(orderId, newStatus) {
    if (!confirm('Are you sure you want to change the order status to ' + newStatus + '?')) {
        return;
    }

    // Update via AJAX
    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mfm_update_order_status&order_id=' + orderId + '&status=' + newStatus + '&nonce=' + mfm_admin_vars.nonce
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Close modal and reload page to show updated status
                closeOrderModal();
                location.reload();
            } else {
                alert('Error updating status: ' + (data.data.message || 'Unknown error'));
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
        });
}


function deleteOrder(orderId) {
    if (!confirm('Are you sure you want to delete this order? This cannot be undone.')) {
        return;
    }

    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mfm_delete_order&order_id=' + orderId + '&nonce=' + mfm_admin_vars.nonce
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeOrderModal();
                location.reload();
            } else {
                alert('Error deleting order: ' + (data.data.message || 'Unknown error'));
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
        });
}

