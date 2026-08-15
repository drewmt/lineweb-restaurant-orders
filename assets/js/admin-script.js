jQuery(document).ready(function ($) {
    // Click card to open modal
    $(document).on('click', '.snaporder-order-card', function () {
        const orderId = $(this).data('order-id');
        openOrderModal(orderId);
    });

    // List view button click
    $(document).on('click', '.snaporder-view-order-btn', function (e) {
        e.preventDefault();
        e.stopPropagation(); // Prevent row click if we add one
        const orderId = $(this).data('order-id');
        openOrderModal(orderId);
    });

    // Close modal when clicking outside
    $(document).on('click', '#snaporder-order-modal', function (e) {
        if (e.target === this) {
            closeOrderModal();
        }
    });

    // Auto-refresh every 30 seconds
    if ($('.snaporder-orders-wrap').length) {
        setInterval(function () {
            // Only refresh if modal is not open
            if ($('#snaporder-order-modal').css('display') === 'none') {
                location.reload();
            }
        }, 30000);
    }
});

function openOrderModal(orderId) {
    const modal = document.getElementById('snaporder-order-modal');
    const content = document.getElementById('snaporder-modal-content');

    modal.style.display = 'flex';
    content.innerHTML = '<p class="snaporder-loading">Loading...</p>';

    // Fetch order details via AJAX
    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=snaporder_get_order_details&order_id=' + orderId + '&nonce=' + snaporder_admin_vars.nonce
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.innerHTML = data.data.html;
            } else {
                content.innerHTML = '<p class="snaporder-error">Error loading order details</p>';
            }
        })
        .catch(err => {
            content.textContent = 'Error loading order details.';
        });
}

function closeOrderModal() {
    document.getElementById('snaporder-order-modal').style.display = 'none';
}

function updateOrderStatus(orderId, newStatus) {
    if (!confirm('Are you sure you want to change the order status to ' + newStatus + '?')) {
        return;
    }

    // Update via AJAX
    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=snaporder_update_order_status&order_id=' + orderId + '&status=' + newStatus + '&nonce=' + snaporder_admin_vars.nonce
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
        body: 'action=snaporder_delete_order&order_id=' + orderId + '&nonce=' + snaporder_admin_vars.nonce
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

