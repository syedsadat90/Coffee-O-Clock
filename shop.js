'use strict';

const menuCards = Array.from(document.querySelectorAll('[data-menu-card]'));
const searchInput = document.querySelector('#menu-search');
const filterButtons = Array.from(document.querySelectorAll('[data-menu-filter]'));
const resultCount = document.querySelector('#menu-result-count');
const emptyFilterMessage = document.querySelector('#menu-empty-filter');
const itemCount = document.querySelector('#cart-item-count');
const subtotalNote = document.querySelector('#cart-subtotal-note');
const cartTotal = document.querySelector('#cart-total');
const clearButton = document.querySelector('#clear-order');
const orderForm = document.querySelector('form[action="actions/place-order.php"]');
const submitButton = orderForm?.querySelector('button[type="submit"]');

let activeCategory = 'all';

function updateMenuVisibility() {
    const searchTerm = searchInput.value.trim().toLocaleLowerCase();
    let visibleItems = 0;

    for (const card of menuCards) {
        const matchesCategory = activeCategory === 'all'
            || card.dataset.menuCategory.trim().toLocaleLowerCase() === activeCategory;
        const searchableText = `${card.dataset.menuName} ${card.dataset.menuCategory}`.toLocaleLowerCase();
        const matchesSearch = searchTerm === '' || searchableText.includes(searchTerm);
        card.hidden = !matchesCategory || !matchesSearch;
        if (!card.hidden) visibleItems += 1;
    }

    resultCount.textContent = searchTerm === '' && activeCategory === 'all'
        ? `${visibleItems} available`
        : `${visibleItems} of ${menuCards.length} items`;
    emptyFilterMessage.hidden = visibleItems !== 0;
}

function updateOrderSummary() {
    let quantityTotal = 0;
    let subtotalCents = 0;

    for (const card of menuCards) {
        const quantityInput = card.querySelector('input[type="number"]');
        const quantity = Number.parseInt(quantityInput.value, 10);
        if (!Number.isInteger(quantity) || quantity <= 0) continue;

        const unitPrice = Number.parseFloat(card.querySelector('[data-unit-price]').dataset.unitPrice);
        quantityTotal += quantity;
        subtotalCents += Math.round(unitPrice * 100) * quantity;
    }

    const formattedTotal = new Intl.NumberFormat('en-BD', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(subtotalCents / 100);

    itemCount.textContent = `${quantityTotal} ${quantityTotal === 1 ? 'item' : 'items'} selected`;
    subtotalNote.textContent = quantityTotal === 0
        ? 'Choose items to start your order'
        : 'Ready when you are';
    cartTotal.textContent = `৳${formattedTotal}`;
    clearButton.disabled = quantityTotal === 0;
    submitButton.disabled = quantityTotal === 0;
}

for (const button of filterButtons) {
    button.addEventListener('click', () => {
        activeCategory = button.dataset.menuFilter.trim().toLocaleLowerCase();
        for (const filterButton of filterButtons) {
            const isActive = filterButton === button;
            filterButton.classList.toggle('is-active', isActive);
            filterButton.setAttribute('aria-pressed', String(isActive));
        }
        updateMenuVisibility();
    });
}

searchInput.addEventListener('input', updateMenuVisibility);

for (const quantityInput of document.querySelectorAll('.shop-item-order input[type="number"]')) {
    quantityInput.addEventListener('input', updateOrderSummary);
    quantityInput.addEventListener('change', updateOrderSummary);
}

clearButton.addEventListener('click', () => {
    for (const quantityInput of document.querySelectorAll('.shop-item-order input[type="number"]')) {
        quantityInput.value = '0';
    }
    updateOrderSummary();
});

updateMenuVisibility();
updateOrderSummary();
