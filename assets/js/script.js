/**
 * @package Modern Food Menu
 * @author Lineweb.gr - Andrew Matia
 * @copyright 2025 Lineweb.gr - Andrew Matia
 */
function snaporderEscapeHtml(value) {
	return String(value === undefined || value === null ? '' : value).replace(/[&<>'"]/g, function (character) {
		return {'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'}[character];
	});
}

function snaporderSafeUrl(value) {
	try {
		var url = new URL(String(value || ''), window.location.origin);
		return url.protocol === 'http:' || url.protocol === 'https:' ? url.href : '';
	} catch (error) {
		return '';
	}
}

jQuery(document).ready(function ($) {
	// Category Navigation State
	var currentParentCategory = 0; // 0 = root level

	// Helper: Get children of a category
	function getCategoryChildren(parentId) {
		if (!window.mfmCategories) return [];
		return window.mfmCategories.filter(cat => cat.parent == parentId);
	}

	// Helper: Get all descendant category IDs (recursive)
	function getAllDescendantIds(categoryId) {
		var descendants = [];
		var children = getCategoryChildren(categoryId);
		children.forEach(child => {
			descendants.push(child.id);
			descendants = descendants.concat(getAllDescendantIds(child.id));
		});
		return descendants;
	}

	// Helper: Get category by ID
	function getCategoryById(id) {
		if (!window.mfmCategories) return null;
		return window.mfmCategories.find(cat => cat.id == id);
	}

	// Render category navigation
	function renderCategoryNav() {
		var nav = $('#mfm-category-nav');
		nav.empty();

		if (currentParentCategory === 0) {
			// Root level: Show "All" + top-level categories
			var allBtn = $('<button>')
				.addClass('mfm-filter-btn active whitespace-nowrap px-6 py-3 rounded-lg mfm-bg-primary text-white text-base font-bold mfm-shadow-primary transition-all transform hover:scale-105')
				.attr('data-filter', '*')
				.attr('data-id', '0')
				.text('All');
			nav.append(allBtn);

			var topLevel = getCategoryChildren(0);
			topLevel.forEach(cat => {
				// Skip categories with empty names
				if (!cat.name || cat.name.trim() === '') {
					return;
				}
				var btn = $('<button>')
					.addClass('mfm-filter-btn whitespace-nowrap px-6 py-3 rounded-lg bg-white text-gray-600 border border-gray-100 text-base font-bold shadow-sm hover:shadow-md hover:bg-gray-50 transition-all transform hover:scale-105')
					.attr('data-filter', '.cat-' + cat.slug)
					.attr('data-id', cat.id)
					.attr('data-has-children', getCategoryChildren(cat.id).length > 0 ? '1' : '0')
					.text(cat.name);
				nav.append(btn);
			});
		} else {
			// Sub-category level: Show "Back" + children
			var backBtn = $('<button>')
				.addClass('mfm-filter-btn whitespace-nowrap px-6 py-3 rounded-lg mfm-bg-primary text-white text-base font-bold mfm-shadow-primary transition-all transform hover:scale-105')
				.attr('data-action', 'back')
				.html('<i data-lucide="arrow-left" class="w-4 h-4 inline-block mr-1"></i> Back');
			nav.append(backBtn);

			var children = getCategoryChildren(currentParentCategory);
			children.forEach(cat => {
				// Skip categories with empty names
				if (!cat.name || cat.name.trim() === '') {
					return;
				}
				var btn = $('<button>')
					.addClass('mfm-filter-btn whitespace-nowrap px-6 py-3 rounded-lg bg-white text-gray-600 border border-gray-100 text-base font-bold shadow-sm hover:shadow-md hover:bg-gray-50 transition-all transform hover:scale-105')
					.attr('data-filter', '.cat-' + cat.slug)
					.attr('data-id', cat.id)
					.attr('data-has-children', getCategoryChildren(cat.id).length > 0 ? '1' : '0')
					.text(cat.name);
				nav.append(btn);
			});

			// Re-initialize lucide icons for the back button
			if (typeof lucide !== 'undefined') {
				lucide.createIcons();
			}
		}
	}

	// Filter items by category
	function filterByCategory(categoryId, categorySlug) {
		if (categoryId == 0) {
			// Show all
			$('.mfm-category-list').show();
			$('.mfm-recommended-section').fadeIn();
		} else {
			// Get all descendant IDs
			var descendantIds = getAllDescendantIds(categoryId);
			var allIds = [categoryId].concat(descendantIds);

			// Build selector for this category and all descendants
			var selectors = allIds.map(id => {
				var cat = getCategoryById(id);
				return cat ? '.cat-' + cat.slug : '';
			}).filter(s => s).join(', ');

			$('.mfm-category-list').hide();
			if (selectors) {
				$(selectors).show();
			}
			$('.mfm-recommended-section').hide();
		}
	}

	// Handle category button clicks
	$(document).on('click', '.mfm-filter-btn', function () {
		var $btn = $(this);
		var action = $btn.attr('data-action');
		var catId = parseInt($btn.attr('data-id'));
		var hasChildren = $btn.attr('data-has-children') === '1';
		var filterValue = $btn.attr('data-filter');

		if (action === 'back') {
			// Go back to parent level
			var currentCat = getCategoryById(currentParentCategory);
			currentParentCategory = currentCat ? currentCat.parent : 0;
			renderCategoryNav();

			// Filter to parent or all
			if (currentParentCategory === 0) {
				filterByCategory(0);
			} else {
				var parentCat = getCategoryById(currentParentCategory);
				filterByCategory(currentParentCategory, parentCat.slug);
			}
		} else if (hasChildren) {
			// Navigate into sub-categories
			currentParentCategory = catId;
			renderCategoryNav();

			// Track Category View
			if (catId !== 0) {
				MFMApp.recordView('category_view', catId);
			}

			// Filter to this category and descendants
			var cat = getCategoryById(catId);
			filterByCategory(catId, cat.slug);
		} else {
			// Leaf category - just filter
			$('.mfm-filter-btn').removeClass('active');
			$('.mfm-filter-btn').removeClass('mfm-bg-primary text-white mfm-shadow-primary').addClass('bg-white text-gray-600 border border-gray-100');
			$btn.addClass('active');
			$btn.removeClass('bg-white text-gray-600 border border-gray-100').addClass('mfm-bg-primary text-white mfm-shadow-primary');

			// Track Category View
			if (catId && catId !== 0) {
				MFMApp.recordView('category_view', catId);
			}

			if (filterValue === '*') {
				filterByCategory(0);
			} else {
				var cat = getCategoryById(catId);
				filterByCategory(catId, cat.slug);
			}
		}
	});

	// Initialize category navigation
	if (window.mfmCategories) {
		renderCategoryNav();
	}

	// Initialize Stripe if enabled
	if (typeof Stripe !== 'undefined' && mfm_vars.stripe_key) {
		window.stripe = Stripe(mfm_vars.stripe_key);
		window.elements = window.stripe.elements();
		window.cardElement = window.elements.create('card', {
			style: {
				base: {
					fontSize: '16px',
					color: '#32325d',
					'::placeholder': {
						color: '#aab7c4'
					}
				},
				invalid: {
					color: '#fa755a'
				}
			}
		});
		if (document.getElementById('stripe-card-element')) {
			window.cardElement.mount('#stripe-card-element');
			window.cardElement.on('change', function (event) {
				var errorDiv = document.getElementById('stripe-card-errors');
				if (event.error) {
					errorDiv.textContent = event.error.message;
				} else {
					errorDiv.textContent = '';
				}
			});
		}
	}
});

// App View Logic
var MFMApp = {
	currentItem: null,
	currentQty: 1,
	currentExtras: [],





	recordView: function (type, id) {
		if (!type || !id) {
			return;
		}
		var data = new FormData();
		data.append('action', 'mfm_track_view');
		data.append('nonce', mfm_vars.nonce);
		data.append('item_id', id);

		fetch(mfm_vars.ajax_url, {
			method: 'POST',
			body: data
		})
			.then(response => {
				return response.json();
			})
			.then(data => {
				// Success
			})
			.catch(err => {
				console.error('MFMApp.recordView: Error', err);
			});
	},

	showFeaturedPage: function () {
		// Hide main content wrapper
		var mainContent = document.getElementById('mfm-main-content');
		if (mainContent) {
			mainContent.style.display = 'none';
		}

		// Show featured page
		var featuredPage = document.getElementById('mfm-featured-page');
		if (featuredPage) {
			featuredPage.classList.remove('hidden');
			featuredPage.style.display = 'block';
		}

		// Scroll to top
		window.scrollTo(0, 0);

		// Re-initialize Lucide icons
		if (typeof lucide !== 'undefined') {
			lucide.createIcons();
		}
	},

	hideFeaturedPage: function () {
		// Hide featured page
		var featuredPage = document.getElementById('mfm-featured-page');
		if (featuredPage) {
			featuredPage.classList.add('hidden');
			featuredPage.style.display = 'none';
		}

		// Show main content wrapper
		var mainContent = document.getElementById('mfm-main-content');
		if (mainContent) {
			mainContent.style.display = 'block';
		}

		// Scroll to top
		window.scrollTo(0, 0);
	},

	openItem: function (data) {
		// Track Product View
		this.recordView('product_view', data.id);

		this.currentItem = data;
		this.currentQty = 1;
		this.currentExtras = [];

		var modal = document.getElementById('mfm-modal');
		var content = modal.querySelector('.mfm-modal-content');

		// Populate data
		modal.querySelector('.mfm-modal-title').textContent = data.title;
		// Description HTML is filtered with wp_kses_post() before it reaches this data attribute.
		modal.querySelector('.mfm-modal-desc').innerHTML = data.description;
		modal.querySelector('.mfm-modal-qty').textContent = '1';

		// Variant Initialization
		this.currentVariant = null;

		// Nutritional Info
		var calEl = modal.querySelector('.mfm-modal-calories');
		var calWrap = modal.querySelector('.mfm-modal-calories-wrap');
		if (data.calories) {
			calEl.textContent = data.calories + ' kcal';
			calWrap.classList.remove('hidden');
		} else {
			calEl.textContent = '';
			calWrap.classList.add('hidden');
		}

		// Insert Meta Container (Variants) AFTER Description/Nutrition
		var metaContainer = modal.querySelector('.mfm-modal-meta');
		if (!metaContainer) {
			metaContainer = document.createElement('div');
			metaContainer.className = 'mfm-modal-meta mb-6 space-y-6';
			// Insert before the Extras wrapper
			var extrasWrapper = modal.querySelector('.mfm-modal-extras-wrapper');
			extrasWrapper.parentNode.insertBefore(metaContainer, extrasWrapper);
		}
		metaContainer.innerHTML = '';

		// Render Variants
		if (data.variants && Array.isArray(data.variants) && data.variants.length > 0) {
			var variantHtml = `
                <div>
                    <h4 class="font-bold text-gray-900 mb-2 text-sm">Select Variant (Optional)</h4>
                    <div class="flex overflow-x-auto pb-2 gap-2 -mx-1 px-1 no-scrollbar">
            `;
			data.variants.forEach((variant, index) => {
				var isSelected = false;
				variantHtml += `
                    <button type="button" 
                            class="mfm-variant-btn flex-shrink-0 px-4 py-2.5 rounded-lg border-2 transition-all text-center flex items-center gap-2 whitespace-nowrap ${isSelected ? 'mfm-border-primary mfm-bg-primary-50' : 'border-gray-200 bg-white hover:border-gray-300'}" 
                            data-variant-index="${index}" 
                            onclick="MFMApp.updateVariant(${index})">
						<span class="font-medium text-sm ${isSelected ? 'text-gray-900' : 'text-gray-700'}">${snaporderEscapeHtml(variant.name)}</span>
						${variant.price > 0 ? `<span class="text-xs font-bold ${isSelected ? 'mfm-text-primary' : 'text-gray-500'}">+${snaporderEscapeHtml(variant.price)}${snaporderEscapeHtml(window.mfmCurrencySymbol || '€')}</span>` : ''}
                    </button>
                `;
			});
			variantHtml += `</div></div>`;
			metaContainer.insertAdjacentHTML('beforeend', variantHtml);
		}

		var algEl = modal.querySelector('.mfm-modal-allergens');
		if (data.allergens) {
			algEl.querySelector('span').textContent = 'Contains: ' + data.allergens;
			algEl.classList.remove('hidden');
		} else {
			algEl.classList.add('hidden');
		}

		var dietEl = modal.querySelector('.mfm-modal-dietary');
		dietEl.innerHTML = '';
		if (data.dietary && Array.isArray(data.dietary)) {
			if (data.dietary.includes('vegetarian') || data.dietary.includes('vegan')) {
				dietEl.innerHTML += `
	<div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center" title="Vegetarian/Vegan">
		<i data-lucide="leaf" class="w-3.5 h-3.5 text-green-600"></i>
                    </div>
	`;
			}
			if (data.dietary.includes('spicy')) {
				dietEl.innerHTML += `
	<div class="w-6 h-6 rounded-full bg-red-100 flex items-center justify-center" title="Spicy">
		<i data-lucide="flame" class="w-3.5 h-3.5 text-red-600"></i>
                    </div>
	`;
			}
			if (data.dietary.includes('gluten_free')) {
				dietEl.innerHTML += `
	<div class="w-6 h-6 rounded-full bg-yellow-100 flex items-center justify-center" title="Gluten Free">
		<i data-lucide="wheat-off" class="w-3.5 h-3.5 text-yellow-600"></i>
                    </div>
	`;
			}
			lucide.createIcons();
		}

		this.updatePriceDisplay();

		var modalImageUrl = snaporderSafeUrl(data.image);
		if (modalImageUrl) {
			modal.querySelector('.mfm-modal-image').style.backgroundImage = 'url("' + modalImageUrl + '")';
			modal.querySelector('.mfm-modal-image').style.display = 'block';
		} else {
			modal.querySelector('.mfm-modal-image').style.display = 'none';
		}

		// Extras
		var extrasWrapper = modal.querySelector('.mfm-modal-extras-wrapper');
		var extrasContainer = modal.querySelector('.mfm-modal-extras');
		extrasContainer.innerHTML = '';

		if (data.extras && data.extras.length > 0) {
			extrasWrapper.classList.remove('hidden');
			var self = this;
			data.extras.forEach(function (extra, index) {
				var html = `
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-xl border border-gray-100">
						<div class="flex items-center gap-3">
							<span class="font-medium text-gray-700">${snaporderEscapeHtml(extra.name)}</span>
							<span class="font-bold text-gray-900">+${snaporderEscapeHtml(extra.price)}${snaporderEscapeHtml(window.mfmCurrencySymbol || '€')}</span>
						</div>
						<div class="flex items-center gap-3 bg-gray-100 rounded-lg p-1">
							<button class="w-6 h-6 flex items-center justify-center font-bold text-gray-600" onclick="MFMApp.updateExtraQty(${index}, -1)">-</button>
							<span class="text-sm font-bold w-4 text-center" id="extra-qty-${index}">0</span>
							<button class="w-6 h-6 flex items-center justify-center font-bold mfm-text-primary" onclick="MFMApp.updateExtraQty(${index}, 1)">+</button>
						</div>
					</div>
	`;
				extrasContainer.insertAdjacentHTML('beforeend', html);
			});
		} else {
			extrasWrapper.classList.add('hidden');
		}

		modal.classList.remove('hidden');
		setTimeout(() => {
			content.classList.remove('translate-y-full');
		}, 10);
		document.body.style.overflow = 'hidden';
	},

	closeModal: function () {
		var modal = document.getElementById('mfm-modal');
		var content = modal.querySelector('.mfm-modal-content');
		content.classList.add('translate-y-full');
		setTimeout(() => {
			modal.classList.add('hidden');
			document.body.style.overflow = '';
		}, 300);
	},

	incrementQty: function () {
		this.currentQty = Math.min(this.currentQty + 1, 99);
		document.querySelector('.mfm-modal-qty').textContent = this.currentQty;
		this.updatePriceDisplay();
	},

	decrementQty: function () {
		if (this.currentQty > 1) {
			this.currentQty--;
			document.querySelector('.mfm-modal-qty').textContent = this.currentQty;
			this.updatePriceDisplay();
		}
	},

	updateExtraQty: function (extraIndex, change) {
		var extraData = this.currentItem.extras[extraIndex];
		if (!extraData) return;

		var existingExtra = this.currentExtras.find(e => e.index === extraIndex);

		if (existingExtra) {
			existingExtra.qty = Math.min(existingExtra.qty + change, 20);
			if (existingExtra.qty <= 0) {
				this.currentExtras = this.currentExtras.filter(e => e.index !== extraIndex);
			}
		} else if (change > 0) {
			this.currentExtras.push({
				index: extraIndex,
				name: extraData.name,
				price: parseFloat(extraData.price),
				qty: 1
			});
		}

		// Update the displayed quantity for this specific extra
		var extraQtySpan = document.getElementById(`extra-qty-${extraIndex}`);
		if (extraQtySpan) {
			var updatedExtra = this.currentExtras.find(e => e.index === extraIndex);
			extraQtySpan.textContent = updatedExtra ? updatedExtra.qty : 0;
		}

		this.updatePriceDisplay();
	},

	updateVariant: function (index) {
		// Toggle variant selection - allow deselection
		var clickedVariant = this.currentItem.variants[index];

		// If clicking the same variant, deselect it
		if (this.currentVariant && this.currentVariant.index === index) {
			this.currentVariant = null;
		} else {
			this.currentVariant = Object.assign({index: index}, clickedVariant);
		}

		// Update button styles
		var buttons = document.querySelectorAll('.mfm-variant-btn');
		buttons.forEach((btn, i) => {
			if (i === index && this.currentVariant) {
				// Selected state
				btn.classList.remove('border-gray-200', 'bg-white', 'hover:border-gray-300');
				btn.classList.add('mfm-border-primary', 'mfm-bg-primary-50');
				btn.querySelector('span:first-child').classList.remove('text-gray-700');
				btn.querySelector('span:first-child').classList.add('text-gray-900');
				var priceSpan = btn.querySelector('span:last-child');
				if (priceSpan && priceSpan !== btn.querySelector('span:first-child')) {
					priceSpan.classList.remove('text-gray-500');
					priceSpan.classList.add('mfm-text-primary');
				}
			} else {
				// Unselected state
				btn.classList.remove('mfm-border-primary', 'mfm-bg-primary-50');
				btn.classList.add('border-gray-200', 'bg-white', 'hover:border-gray-300');
				btn.querySelector('span:first-child').classList.remove('text-gray-900');
				btn.querySelector('span:first-child').classList.add('text-gray-700');
				var priceSpan = btn.querySelector('span:last-child');
				if (priceSpan && priceSpan !== btn.querySelector('span:first-child')) {
					priceSpan.classList.remove('mfm-text-primary');
					priceSpan.classList.add('text-gray-500');
				}
			}
		});

		this.updatePriceDisplay();
	},

	updatePriceDisplay: function () {
		var basePrice = parseFloat(this.currentItem.price);
		var extrasTotal = this.currentExtras.reduce((sum, extra) => sum + (extra.price * extra.qty), 0);

		var variantPrice = (this.currentVariant && this.currentVariant.price) ? parseFloat(this.currentVariant.price) : 0;

		var total = (basePrice + extrasTotal + variantPrice) * this.currentQty;

		var formatted = total.toFixed(2) + (window.mfmCurrencySymbol || '€');
		document.querySelector('.mfm-modal-price').textContent = formatted;
		document.querySelector('.mfm-modal-price-display').textContent = formatted;
	},

	addToCart: function () {
		var basePrice = parseFloat(this.currentItem.price);
		var extrasTotal = this.currentExtras.reduce((sum, extra) => sum + (extra.price * extra.qty), 0);
		var variantPrice = (this.currentVariant && this.currentVariant.price) ? parseFloat(this.currentVariant.price) : 0;

		var unitPrice = basePrice + extrasTotal + variantPrice;

		// Get product notes
		var productNotes = document.getElementById('mfm-product-notes').value.trim();

		var cartItem = {
			id: this.currentItem.id,
			title: this.currentItem.title,
			image: this.currentItem.image,
			price: unitPrice,
			qty: this.currentQty,
			extras: this.currentExtras,
			variant: this.currentVariant,
			notes: productNotes
		};

		MFMCart.addItem(cartItem);
		this.closeModal();

		// Show feedback
		// alert('Added to cart!'); // Or a nicer toast notification
	},

	closeModal: function () {
		document.getElementById('mfm-modal').classList.add('hidden');
		document.body.style.overflow = '';
		// Clear notes field for next product
		document.getElementById('mfm-product-notes').value = '';
	}
};

// Cart Logic
var MFMCart = {
	items: [],
	selectedPayment: mfm_vars.default_payment || '',
	deliveryType: 'delivery',
	tipAmount: 0,
	pendingRequestId: '',

	init: function () {
		var stored = localStorage.getItem('mfm_cart');
		if (stored) {
			try {
				var parsed = JSON.parse(stored);
				this.items = this.normalizeStoredItems(parsed);
			} catch (error) {
				this.items = [];
				localStorage.removeItem('mfm_cart');
			}
		}
		this.updateUI();
	},

	addItem: function (item) {
		if (this.items.length >= 50) {
			window.alert(mfm_vars.strings.order_error);
			return;
		}
		this.items.push(item);
		this.pendingRequestId = '';
		this.save();
		this.updateUI();
	},

	removeItem: function (index) {
		this.items.splice(index, 1);
		this.pendingRequestId = '';
		this.save();
		this.updateUI();
		this.renderCartItems(); // Re-render if modal is open
	},

	updateQty: function (index, change) {
		if (!this.items[index]) {
			return;
		}
		var newQty = Math.min(this.items[index].qty + change, 99);
		if (newQty > 0) {
			this.items[index].qty = newQty;
			this.pendingRequestId = '';
			this.save();
			this.updateUI();
			this.renderCartItems();
		}
	},

	save: function () {
		localStorage.setItem('mfm_cart', JSON.stringify(this.items));
	},

	restore: function () {
		var stored = localStorage.getItem('mfm_cart');
		if (stored) {
			try {
				var parsed = JSON.parse(stored);
				this.items = this.normalizeStoredItems(parsed);
			} catch (error) {
				this.items = [];
			}
		}
	},

	normalizeStoredItems: function (items) {
		if (!Array.isArray(items)) {
			return [];
		}

		return items.slice(0, 50).filter(function (item) {
			return item && typeof item === 'object' && Number.isInteger(Number(item.id)) && Number(item.id) > 0;
		}).map(function (item) {
			var quantity = Math.max(1, Math.min(99, Number.parseInt(item.qty, 10) || 1));
			var price = Number.parseFloat(item.price);
			var extras = Array.isArray(item.extras) ? item.extras.slice(0, 50).filter(function (extra) {
				return extra && Number.isInteger(Number(extra.index));
			}).map(function (extra) {
				return {
					index: Number(extra.index),
					name: String(extra.name || ''),
					price: Math.max(0, Number.parseFloat(extra.price) || 0),
					qty: Math.max(1, Math.min(20, Number.parseInt(extra.qty, 10) || 1))
				};
			}) : [];
			var variant = item.variant && Number.isInteger(Number(item.variant.index)) ? {
				index: Number(item.variant.index),
				name: String(item.variant.name || ''),
				price: Math.max(0, Number.parseFloat(item.variant.price) || 0)
			} : null;

			return {
				id: Number(item.id),
				title: String(item.title || ''),
				image: String(item.image || ''),
				price: Number.isFinite(price) && price >= 0 ? price : 0,
				qty: quantity,
				extras: extras,
				variant: variant,
				notes: String(item.notes || '').slice(0, 300)
			};
		});
	},

	getTotal: function () {
		var subtotal = this.items.reduce((sum, item) => {
			var price = parseFloat(item.price) || 0;
			return sum + (price * item.qty);
		}, 0);
		return subtotal + (this.tipAmount || 0);
	},

	getSubtotal: function () {
		return this.items.reduce((sum, item) => {
			var price = parseFloat(item.price) || 0;
			return sum + (price * item.qty);
		}, 0);
	},

	setTip: function (percent) {
		var subtotal = this.getSubtotal();
		this.tipAmount = subtotal * percent;

		// UI Updates
		jQuery('#custom-tip-wrap').addClass('hidden');
		jQuery('.tip-btn').removeClass('border-orange-500 text-orange-500 bg-orange-50').addClass('border-gray-200');

		// Highlight logic (approximate since we store amount not percent)
		// Actually, let's just highlight the clicked button if passed, but simpler to just re-calc
		// We can find the button by percent text or just rely on the amount match?
		// Let's rely on simple class toggling by the caller or passed "this"?
		// For now, simple implementation:
		var btnIndex = -1;
		if (percent === 0.05) btnIndex = 0;
		if (percent === 0.10) btnIndex = 1;
		if (percent === 0.15) btnIndex = 2;

		if (btnIndex > -1) {
			jQuery('.tip-btn').eq(btnIndex).addClass('border-orange-500 text-orange-500 bg-orange-50').removeClass('border-gray-200');
		}

		this.updateTipUI();
	},

	toggleCustomTip: function () {
		jQuery('#custom-tip-wrap').toggleClass('hidden');
		this.tipAmount = 0;
		jQuery('.tip-btn').removeClass('border-orange-500 text-orange-500 bg-orange-50').addClass('border-gray-200');
		jQuery('.tip-btn').last().toggleClass('border-orange-500 text-orange-500 bg-orange-50 border-gray-200');

		if (!jQuery('#custom-tip-wrap').hasClass('hidden')) {
			jQuery('#custom-tip-input').focus().val('');
		}
		this.updateTipUI();
	},

	setCustomTip: function (val) {
		this.tipAmount = Math.min(Math.max(parseFloat(val) || 0, 0), this.getSubtotal());
		this.updateTipUI();
	},

	updateTipUI: function () {
		// Update Hidden Input for Form Submission
		jQuery('#tip-amount-input').val(this.tipAmount.toFixed(2));

		// Update Display
		if (this.tipAmount > 0) {
			jQuery('#tip-display-row').removeClass('hidden');
			jQuery('#tip-display-amount').text(mfm_vars.currency + this.tipAmount.toFixed(2));
		} else {
			jQuery('#tip-display-row').addClass('hidden');
		}

		this.updateUI(); // Updates total button
	},

	getCount: function () {
		return this.items.reduce((sum, item) => sum + item.qty, 0);
	},

	updateUI: function () {
		var total = this.getTotal().toFixed(2);
		var count = this.getCount();

		// Update Bottom Bar
		var bar = document.getElementById('mfm-bottom-bar');
		var barCount = document.getElementById('mfm-bar-count');
		var barTotal = document.getElementById('mfm-bar-total');

		if (barCount) barCount.textContent = count;
		if (barTotal) barTotal.textContent = total + (window.mfmCurrencySymbol || '€');

		// Update Top Icon
		var topCount = document.querySelector('.mfm-cart-count');
		if (topCount) topCount.textContent = count;
		var topBadge = document.querySelector('.cart-btn span');
		if (topBadge) topBadge.textContent = count;

		if (bar) {
			if (count > 0) {
				bar.classList.remove('hidden');
			} else {
				bar.classList.add('hidden');
			}
		}
	},

	openCart: function () {
		var modal = document.getElementById('mfm-cart-modal');
		var content = modal.querySelector('.mfm-cart-content');

		this.renderCartItems();
		document.getElementById('mfm-cart-total').textContent = this.getTotal().toFixed(2) + (window.mfmCurrencySymbol || '€');

		modal.classList.remove('hidden');
		setTimeout(() => {
			content.classList.remove('translate-y-full');
		}, 10);
		document.body.style.overflow = 'hidden';
	},

	closeCart: function () {
		var modal = document.getElementById('mfm-cart-modal');
		var content = modal.querySelector('.mfm-cart-content');
		content.classList.add('translate-y-full');
		setTimeout(() => {
			modal.classList.add('hidden');
			document.body.style.overflow = '';
		}, 300);
	},

	renderCartItems: function () {
		var container = document.getElementById('mfm-cart-items');
		container.innerHTML = '';

		if (this.items.length === 0) {
			container.innerHTML = `
                <div class="text-center text-gray-500 py-10">
					<i data-lucide="shopping-basket" class="w-16 h-16 mx-auto mb-4 text-gray-300"></i>
				<p>${snaporderEscapeHtml(mfm_vars.strings.empty_cart)}</p>
				</div>
	`;
			lucide.createIcons();
			return;
		}

		this.items.forEach((item, index) => {
			var extrasHtml = item.extras.map(e => `<span class="text-sm text-gray-500 block"> + ${snaporderEscapeHtml(e.name)}${e.qty > 1 ? ' (x' + Number(e.qty) + ')' : ''}</span>`).join('');

			var variantHtml = item.variant ? `<span class="text-sm text-gray-500 block"> Variant: ${snaporderEscapeHtml(item.variant.name)}</span>` : '';
			var imageUrl = snaporderSafeUrl(item.image);

			var html = `
                <div class="flex gap-4 items-center">
					${imageUrl ? `<img src="${snaporderEscapeHtml(imageUrl)}" alt="" class="w-16 h-16 bg-gray-100 rounded-lg object-cover flex-none">` : '<div class="w-16 h-16 bg-gray-100 rounded-lg flex-none"></div>'}
					<div class="flex-1">
						<h4 class="font-bold text-gray-900 text-base">${snaporderEscapeHtml(item.title)}</h4>
                        ${variantHtml}
						${extrasHtml}
						<div class="flex justify-between items-center mt-1">
							<span class="text-base text-gray-500">x${item.qty}</span>
							<span class="font-bold mfm-text-primary text-base">${(item.price * item.qty).toFixed(2)}${window.mfmCurrencySymbol || '€'}</span>
						</div>
					</div>
					<button class="text-gray-400 hover:text-red-500" onclick="MFMCart.removeItem(${index})">
						<i data-lucide="trash-2" class="w-5 h-5"></i>
					</button>
				</div>
	`;
			container.insertAdjacentHTML('beforeend', html);
		});
		lucide.createIcons();
	},

	openCheckout: function () {
		if (!this.items.length) {
			window.alert(mfm_vars.strings.empty_cart);
			return;
		}
		this.closeCart();
		var modal = document.getElementById('mfm-checkout-modal');
		modal.classList.remove('hidden');
	},

	closeCheckout: function () {
		var modal = document.getElementById('mfm-checkout-modal');
		modal.classList.add('hidden');
	},

	selectPayment: function (method, element) {
		if (this.selectedPayment !== method) {
			this.pendingRequestId = '';
		}
		// UI Update
		jQuery('.payment-option').removeClass('selected mfm-border-primary mfm-bg-primary-50').addClass('border-gray-200');

		// Reset icons/text
		jQuery('.payment-option').each(function () {
			const $icon = jQuery(this).find('svg, i');
			$icon.removeClass('mfm-text-primary').addClass('text-gray-400');
			jQuery(this).find('span').removeClass('text-gray-900').addClass('text-gray-500');
		});

		if (element) {
			var $el = jQuery(element);
			$el.removeClass('border-gray-200').addClass('selected mfm-border-primary mfm-bg-primary-50');
			$el.find('svg, i').removeClass('text-gray-400').addClass('mfm-text-primary');
			$el.find('span').removeClass('text-gray-500').addClass('text-gray-900');
			// Update Radio
			$el.find('input[type="radio"]').prop('checked', true);
		}

		this.selectedPayment = method;

		// Stripe UI
		if (method === 'stripe') {
			jQuery('#stripe-card-element').show();
		} else {
			jQuery('#stripe-card-element').hide();
		}
	},

	submitOrder: function (form) {
		if (!this.items.length || !this.selectedPayment) {
			window.alert(mfm_vars.strings.order_error);
			return;
		}

		var formData = new FormData(form);
		var canonicalSelections = this.items.map(function (item) {
			return {
				id: Number(item.id),
				qty: Number(item.qty),
				variant_index: item.variant && Number.isInteger(item.variant.index) ? item.variant.index : null,
				extras: Array.isArray(item.extras) ? item.extras.filter(function (extra) {
					return Number.isInteger(extra.index);
				}).map(function (extra) {
					return {index: extra.index, qty: Number(extra.qty)};
				}) : [],
				notes: item.notes || ''
			};
		});

		if (!this.pendingRequestId) {
			try {
				this.pendingRequestId = this.createRequestId();
			} catch (error) {
				window.alert(mfm_vars.strings.order_error);
				return;
			}
		}

		formData.append('action', 'mfm_submit_order');
		formData.append('nonce', mfm_vars.nonce);
		formData.append('cart', JSON.stringify(canonicalSelections));
		formData.append('request_id', this.pendingRequestId);
		formData.append('deliveryType', this.deliveryType);
		// Ensure payment comes from the selection logic
		if (this.selectedPayment) {
			formData.set('payment', this.selectedPayment);
		}

		// Explicitly capture table number if visible
		if (this.deliveryType === 'dinein') {
			var tableInput = form.querySelector('input[name="table_number"]');
			if (tableInput) {
				formData.set('table_number', tableInput.value);
			}
		}

		var self = this;
		var $btn = jQuery(form).find('button[type="submit"]');
		var originalText = $btn.text();
		$btn.text('Processing...').prop('disabled', true);

		var finishOrder = function (data) {
			self.items = [];
			self.pendingRequestId = '';
			self.tipAmount = 0;
			self.save();
			self.updateUI();
			self.closeCheckout();
			form.reset();
			if (data.token) {
				localStorage.setItem('mfm_order_token_' + data.order_id, data.token);
			}
			window.location.href = window.location.pathname + '?order_id=' + encodeURIComponent(data.order_id);
		};

		var restoreButton = function () {
			$btn.text(originalText).prop('disabled', false);
		};

		var confirmOrderPayment = function (orderData, paymentIntent) {
			jQuery.post(mfm_vars.ajax_url, {
				action: 'mfm_confirm_stripe_payment',
				nonce: mfm_vars.nonce,
				order_id: orderData.order_id,
				token: orderData.token,
				payment_intent: paymentIntent.id
			}).done(function (response) {
				if (response.success) {
					finishOrder(response.data);
					return;
				}
				jQuery('#stripe-card-errors').text(response.data.message || mfm_vars.strings.payment_error);
				restoreButton();
			}).fail(function (xhr) {
				var message = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : mfm_vars.strings.payment_error;
				jQuery('#stripe-card-errors').text(message);
				restoreButton();
			});
		};

		var processServerResponse = function (response) {
			if (!response.success) {
				window.alert(response.data.message || mfm_vars.strings.order_error);
				restoreButton();
				return;
			}

			if (!response.data.payment_required) {
				finishOrder(response.data);
				return;
			}

			if (!window.stripe || !window.cardElement || !response.data.client_secret) {
				jQuery('#stripe-card-errors').text(mfm_vars.strings.payment_error);
				restoreButton();
				return;
			}

			window.stripe.confirmCardPayment(response.data.client_secret, {
				payment_method: {card: window.cardElement}
			}).then(function (result) {
				if (result.error) {
					jQuery('#stripe-card-errors').text(result.error.message || mfm_vars.strings.payment_error);
					restoreButton();
					return;
				}
				confirmOrderPayment(response.data, result.paymentIntent);
			});
		};

		var sendRequest = function () {
			jQuery.ajax({
				url: mfm_vars.ajax_url,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: processServerResponse,
				error: function (xhr) {
					var message = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : mfm_vars.strings.order_error;
					window.alert(message);
					restoreButton();
				}
			});
		};

		sendRequest();
	},

	createRequestId: function () {
		if (window.crypto && typeof window.crypto.randomUUID === 'function') {
			return window.crypto.randomUUID();
		}
		if (!window.crypto || typeof window.crypto.getRandomValues !== 'function') {
			throw new Error('Secure random generation is unavailable.');
		}

		var bytes = window.crypto.getRandomValues(new Uint8Array(16));
		bytes[6] = (bytes[6] & 0x0f) | 0x40;
		bytes[8] = (bytes[8] & 0x3f) | 0x80;
		var hex = Array.from(bytes, function (byte) {
			return byte.toString(16).padStart(2, '0');
		}).join('');

		return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
	},

	setDeliveryType: function (type) {
		if (this.deliveryType !== type) {
			this.pendingRequestId = '';
		}
		this.deliveryType = type; // Store selected type
		var btnDelivery = document.getElementById('btn-delivery');
		var btnPickup = document.getElementById('btn-pickup');
		var btnDineIn = document.getElementById('btn-dinein');

		var deliveryFields = document.getElementById('delivery-fields');
		var dineinFields = document.getElementById('dinein-fields');
		var contactFields = document.getElementById('contact-info-fields');

		// Reset Buttons
		[btnDelivery, btnPickup, btnDineIn].forEach(btn => {
			if (btn) {
				btn.classList.remove('bg-white', 'shadow-sm', 'text-gray-900');
				btn.classList.add('text-gray-500');
			}
		});

		// Activate Selected Button
		var activeBtn = type === 'delivery' ? btnDelivery : (type === 'pickup' ? btnPickup : btnDineIn);
		if (activeBtn) {
			activeBtn.classList.add('bg-white', 'shadow-sm', 'text-gray-900');
			activeBtn.classList.remove('text-gray-500');
		}

		// Field Visibility
		if (type === 'delivery') {
			if (deliveryFields) deliveryFields.classList.remove('hidden');
			if (dineinFields) dineinFields.classList.add('hidden');
			if (contactFields) contactFields.classList.remove('hidden');

			// Required logic
			if (deliveryFields) deliveryFields.querySelectorAll('input').forEach(i => i.required = true);
			if (dineinFields) dineinFields.querySelectorAll('input').forEach(i => i.required = false);
			if (contactFields) contactFields.querySelectorAll('input').forEach(i => i.required = true);

		} else if (type === 'dinein') {
			if (deliveryFields) deliveryFields.classList.add('hidden');
			if (dineinFields) dineinFields.classList.remove('hidden');
			if (contactFields) contactFields.classList.add('hidden');

			// Required logic
			if (deliveryFields) deliveryFields.querySelectorAll('input').forEach(i => i.required = false);
			if (dineinFields) dineinFields.querySelectorAll('input').forEach(i => i.required = true);
			if (contactFields) contactFields.querySelectorAll('input').forEach(i => i.required = false);

		} else {
			// Pickup
			if (deliveryFields) deliveryFields.classList.add('hidden');
			if (dineinFields) dineinFields.classList.add('hidden');
			if (contactFields) contactFields.classList.remove('hidden');

			// Required logic
			if (deliveryFields) deliveryFields.querySelectorAll('input').forEach(i => i.required = false);
			if (dineinFields) dineinFields.querySelectorAll('input').forEach(i => i.required = false);
			if (contactFields) contactFields.querySelectorAll('input').forEach(i => i.required = true);
		}
	}
};

// Initialize
jQuery(document).ready(function ($) {
	// Initialize Cart
	MFMCart.init();

	$(document).on('click keydown', '.mfm-open-item', function (event) {
		if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
			return;
		}
		event.preventDefault();
		try {
			MFMApp.openItem(JSON.parse($(this).attr('data-mfm-item')));
		} catch (error) {
			window.alert(mfm_vars.strings.order_error);
		}
	});

	$(document).on('click keydown', '.payment-option', function (event) {
		if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
			return;
		}
		event.preventDefault();
		MFMCart.selectPayment($(this).attr('data-payment-method'), this);
	});

	$(document).on('click', '.mfm-shortcode-cat-pill', function () {
		var $button = $(this);
		var $menu = $button.closest('.mfm-shortcode-menu');
		var filter = $button.attr('data-filter');
		$menu.find('.mfm-shortcode-cat-pill').removeClass('active');
		$button.addClass('active');
		$menu.find('.mfm-shortcode-item').each(function () {
			var categories = String($(this).attr('data-categories') || '').split(' ');
			$(this).toggle(filter === 'all' || categories.includes(filter));
		});
	});

	// Open cart from top icon
	$('.cart-btn').on('click', function (e) {
		e.preventDefault();
		MFMCart.openCart();
	});

	// Handle Checkout Submission
	$('#mfm-checkout-form').on('submit', function (e) {
		e.preventDefault();
		MFMCart.submitOrder(this);
	});

	// Live Order Tracking Polling
	const $statusContainer = $('#order-status-container');
	if ($statusContainer.length) {
		const orderId = $statusContainer.data('order-id');
		const orderToken = localStorage.getItem('mfm_order_token_' + orderId) || '';
		let currentStatus = $statusContainer.data('current-status');
		let pollingTimer = null;

		if (!orderToken) {
			$('#live-status-text').text(mfm_vars.strings.invalid_link);
			return;
		}

		function checkStatus() {
			$.post(mfm_vars.ajax_url, {
				action: 'mfm_check_status',
				nonce: mfm_vars.nonce,
				order_id: orderId,
				// Token authenticates the poll — prevents order status enumeration.
				token: orderToken
			}, function (response) {
				if (!response.success) {
					$('#live-status-text').text(mfm_vars.strings.invalid_link);
					if (pollingTimer) {
						window.clearInterval(pollingTimer);
					}
					return;
				}

				if (response.data.status !== currentStatus) {
					currentStatus = response.data.status;
					updateStatusUI(currentStatus);
				}
				renderOrderDetails(response.data);
			});
		}

		function renderOrderDetails(data) {
			if (!Array.isArray(data.items)) {
				return;
			}
			var html = data.items.map(function (item) {
				return `<div class="flex justify-between text-sm"><div><span class="font-bold text-gray-900">${Number(item.qty)}x</span> <span class="text-gray-700">${snaporderEscapeHtml(item.title)}</span></div><span class="font-medium text-gray-900">${snaporderEscapeHtml(item.line_total)}${snaporderEscapeHtml(data.currency)}</span></div>`;
			}).join('');
			$('#mfm-order-detail-items').html(html);
			$('#mfm-order-detail-total').text(data.total + data.currency);
			$('#mfm-order-details').removeClass('hidden');
		}

		function updateStatusUI(status) {
			// Update Text
			$('#live-status-text').text(status);

			// Update Timeline
			let passed = true;
			$('.status-step').each(function () {
				const stepKey = $(this).data('step');
				const $dot = $(this).find('div');
				const $text = $(this).find('p');

				const isActive = (stepKey === status);
				if (isActive) passed = false;

				if (passed || isActive) {
					$dot.removeClass('bg-gray-200 border-gray-200').addClass('mfm-bg-primary mfm-border-primary');
					$text.removeClass('text-gray-400').addClass('text-gray-900');
				} else {
					$dot.removeClass('mfm-bg-primary mfm-border-primary').addClass('bg-gray-200 border-gray-200');
					$text.removeClass('text-gray-900').addClass('text-gray-400');
				}
			});
		}

		checkStatus();
		pollingTimer = window.setInterval(checkStatus, 5000);
	}

	// Search Logic
	const searchInput = document.getElementById('mfm-search-input');
	if (searchInput) {
		searchInput.addEventListener('keyup', function () {
			var val = this.value.toLowerCase();
			var items = document.querySelectorAll('.mfm-list-card');
			var sections = document.querySelectorAll('.mfm-category-list');

			items.forEach(function (item) {
				var title = item.querySelector('h4').textContent.toLowerCase();
				if (title.includes(val)) {
					item.classList.remove('hidden');
					item.classList.add('flex');
				} else {
					item.classList.add('hidden');
					item.classList.remove('flex');
				}
			});

			// Hide empty sections
			sections.forEach(function (section) {
				var hasVisible = false;
				section.querySelectorAll('.mfm-list-card').forEach(function (item) {
					if (!item.classList.contains('hidden')) hasVisible = true;
				});

				if (hasVisible) {
					section.classList.remove('hidden');
				} else {
					section.classList.add('hidden');
				}
			});
		});
	}
});
