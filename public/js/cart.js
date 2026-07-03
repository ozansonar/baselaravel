'use strict';

document.addEventListener('DOMContentLoaded', function () {
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    var shippingConfig = window.SHIPPING_CONFIG || { freeLimit: 500, fee: 39.90, minOrder: 100 };
    var FREE_SHIPPING_THRESHOLD = shippingConfig.freeLimit;
    var SHIPPING_COST = shippingConfig.fee;
    var couponApplied = null;
    var couponDiscount = 0;

    // AJAX helper
    function cartFetch(url, method, body) {
        return fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: body ? JSON.stringify(body) : undefined
        }).then(function (r) { return r.json(); });
    }

    // Format number as Turkish currency
    function formatPrice(value) {
        return parseFloat(value).toLocaleString('tr-TR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // Update navbar cart badge
    function updateNavbarCart(count) {
        var badge = document.getElementById('cartBadge');
        if (badge) {
            badge.textContent = count;
            badge.classList.toggle('d-none', count === 0);
        }
        var badgeMobile = document.getElementById('cartBadgeMobile');
        if (badgeMobile) {
            badgeMobile.textContent = count;
            badgeMobile.classList.toggle('d-none', count === 0);
        }
    }

    // Recalculate summary from DOM data
    function recalcSummary() {
        var items = document.querySelectorAll('.cart-item');
        var subtotal = 0;
        var itemCount = 0;

        items.forEach(function (item) {
            var price = parseFloat(item.dataset.price);
            var qtyEl = item.querySelector('.cart-qty-value');
            var qty = parseInt(qtyEl.textContent, 10);
            subtotal += price * qty;
            itemCount += qty;
        });

        var isFreeShipping = subtotal >= FREE_SHIPPING_THRESHOLD;
        var shipping = isFreeShipping ? 0 : SHIPPING_COST;

        // Coupon discount
        couponDiscount = 0;
        if (couponApplied) {
            couponDiscount = Math.round(subtotal * 0.1);
        }

        var total = subtotal + shipping - couponDiscount;

        // Update subtotal
        var subtotalEl = document.getElementById('summarySubtotal');
        if (subtotalEl) subtotalEl.textContent = formatPrice(subtotal) + '₺';

        // Update shipping
        var shippingEl = document.getElementById('summaryShipping');
        if (shippingEl) {
            if (isFreeShipping) {
                shippingEl.textContent = 'Ücretsiz';
                shippingEl.classList.add('text-success');
            } else {
                shippingEl.textContent = formatPrice(shipping) + '₺';
                shippingEl.classList.remove('text-success');
            }
        }

        // Update discount
        var discountRow = document.getElementById('discountRow');
        var discountEl = document.getElementById('summaryDiscount');
        if (discountRow && discountEl) {
            if (couponApplied) {
                discountRow.classList.remove('d-none');
                discountEl.textContent = '-' + formatPrice(couponDiscount) + '₺';
            } else {
                discountRow.classList.add('d-none');
            }
        }

        // Update total
        var totalEl = document.getElementById('summaryTotal');
        if (totalEl) totalEl.textContent = formatPrice(total) + '₺';

        // Update item count
        var itemCountEl = document.getElementById('itemCount');
        if (itemCountEl) itemCountEl.textContent = '(' + items.length + ' ürün)';

        // Update shipping progress
        updateShippingProgress(subtotal);

        // Update WhatsApp link
        updateWhatsAppLink(subtotal, shipping, total);

        // Update min order alert & checkout button
        updateMinOrderStatus(subtotal);

        return { subtotal: subtotal, shipping: shipping, total: total, count: itemCount };
    }

    // Update free shipping progress bar
    function updateShippingProgress(subtotal) {
        var progressEl = document.getElementById('shippingProgress');
        if (!progressEl) return;

        var remaining = FREE_SHIPPING_THRESHOLD - subtotal;
        var progress = Math.min((subtotal / FREE_SHIPPING_THRESHOLD) * 100, 100);

        if (remaining > 0) {
            progressEl.innerHTML =
                '<div class="shipping-progress-text">' +
                '    <span>Ücretsiz kargo için</span>' +
                '    <strong>' + Math.ceil(remaining) + '₺ kaldı</strong>' +
                '</div>' +
                '<div class="shipping-progress-bar">' +
                '    <div class="shipping-progress-fill" style="width: ' + progress + '%;"></div>' +
                '</div>';
        } else {
            progressEl.innerHTML =
                '<div class="shipping-progress-success">' +
                '    <i class="fa-solid fa-check-circle"></i> Ücretsiz kargo kazandınız!' +
                '</div>' +
                '<div class="shipping-progress-bar">' +
                '    <div class="shipping-progress-fill" style="width: 100%;"></div>' +
                '</div>';
        }
    }

    // Update WhatsApp link with current cart data
    function updateWhatsAppLink(subtotal, shipping, total) {
        var whatsappBtn = document.getElementById('whatsappBtn');
        if (!whatsappBtn) return;

        var phone = whatsappBtn.dataset.phone;
        var items = document.querySelectorAll('.cart-item');

        if (items.length === 0) {
            whatsappBtn.classList.add('disabled');
            whatsappBtn.href = '#';
            return;
        }

        whatsappBtn.classList.remove('disabled');

        var msg = 'Merhaba, aşağıdaki ürünleri sipariş vermek istiyorum:%0A%0A';

        items.forEach(function (item) {
            var name = item.querySelector('.cart-item-name a').textContent.trim();
            var price = parseFloat(item.dataset.price);
            var qty = parseInt(item.querySelector('.cart-qty-value').textContent, 10);
            var lineTotal = price * qty;
            msg += '• ' + name + ' — ' + qty + ' adet x ' + formatPrice(price) + '₺ = ' + formatPrice(lineTotal) + '₺%0A';
        });

        msg += '%0A—————————————%0A';
        msg += 'Ara Toplam: ' + formatPrice(subtotal) + '₺%0A';

        if (couponApplied) {
            msg += 'İndirim (' + couponApplied + '): -' + formatPrice(couponDiscount) + '₺%0A';
        }

        var isFreeShipping = subtotal >= FREE_SHIPPING_THRESHOLD;
        msg += 'Kargo: ' + (isFreeShipping ? 'Ücretsiz' : formatPrice(shipping) + '₺') + '%0A';
        msg += 'Toplam: ' + formatPrice(total) + '₺%0A%0A';
        msg += 'Ödeme ve teslimat detayları için bilgi rica ederim.';

        whatsappBtn.href = 'https://wa.me/' + phone + '?text=' + msg;
    }

    // Update minimum order status
    function updateMinOrderStatus(subtotal) {
        var minOrder = shippingConfig.minOrder;
        var alertEl = document.getElementById('minOrderAlert');
        var checkoutBtn = document.getElementById('checkoutBtn');
        var belowMin = subtotal < minOrder;

        if (belowMin) {
            var remaining = (minOrder - subtotal).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            var minFormatted = minOrder.toLocaleString('tr-TR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

            if (!alertEl) {
                alertEl = document.createElement('div');
                alertEl.id = 'minOrderAlert';
                alertEl.className = 'alert alert-warning small mb-3';
                var summaryTotal = document.querySelector('.summary-total');
                if (summaryTotal) {
                    summaryTotal.insertAdjacentElement('afterend', alertEl);
                }
            }
            alertEl.innerHTML = '<i class="fa-solid fa-circle-info me-1"></i> Minimum sipariş tutarı <strong>' + minFormatted + '₺</strong>\'dir. <strong>' + remaining + '₺</strong> daha eklemeniz gerekmektedir.';
            alertEl.classList.remove('d-none');

            if (checkoutBtn) {
                checkoutBtn.classList.add('disabled');
                checkoutBtn.setAttribute('aria-disabled', 'true');
            }
        } else {
            if (alertEl) {
                alertEl.classList.add('d-none');
            }
            if (checkoutBtn) {
                checkoutBtn.classList.remove('disabled');
                checkoutBtn.removeAttribute('aria-disabled');
            }
        }
    }

    // Update item display after quantity change
    function updateItemDisplay(productId, qty) {
        var item = document.getElementById('cartItem-' + productId);
        if (!item) return;

        var price = parseFloat(item.dataset.price);
        var qtyEl = document.getElementById('qtyValue-' + productId);
        var unitPriceEl = document.getElementById('unitPrice-' + productId);
        var totalEl = document.getElementById('itemTotal-' + productId);
        var minusBtn = item.querySelector('.cart-qty-minus');

        if (qtyEl) qtyEl.textContent = qty;
        if (unitPriceEl) unitPriceEl.textContent = qty + ' x ' + formatPrice(price) + ' ₺';
        if (totalEl) totalEl.textContent = formatPrice(price * qty) + ' ₺';
        if (minusBtn) minusBtn.disabled = qty <= 1;
    }

    // Quantity minus buttons
    document.querySelectorAll('.cart-qty-minus').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (this.disabled) return;
            var productId = parseInt(this.dataset.productId, 10);
            var qtyEl = document.getElementById('qtyValue-' + productId);
            var qty = parseInt(qtyEl.textContent, 10);
            if (qty <= 1) return;

            var newQty = qty - 1;
            updateItemDisplay(productId, newQty);
            recalcSummary();

            cartFetch('/sepet/guncelle', 'PATCH', {
                product_id: productId,
                quantity: newQty
            }).then(function (data) {
                if (!data.success) return;
                updateNavbarCart(data.cart_count);
                if (data.cart_count === 0) location.reload();
            });
        });
    });

    // Quantity plus buttons
    document.querySelectorAll('.cart-qty-plus').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var productId = parseInt(this.dataset.productId, 10);
            var qtyEl = document.getElementById('qtyValue-' + productId);
            var qty = parseInt(qtyEl.textContent, 10);

            var newQty = qty + 1;
            updateItemDisplay(productId, newQty);
            recalcSummary();

            cartFetch('/sepet/guncelle', 'PATCH', {
                product_id: productId,
                quantity: newQty
            }).then(function (data) {
                if (!data.success) return;
                updateNavbarCart(data.cart_count);
            });
        });
    });

    // Remove item buttons
    document.querySelectorAll('.cart-remove-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var productId = parseInt(this.dataset.productId, 10);
            var item = document.getElementById('cartItem-' + productId);

            if (item) {
                item.classList.add('removing');
            }

            setTimeout(function () {
                cartFetch('/sepet/kaldir', 'DELETE', {
                    product_id: productId
                }).then(function (data) {
                    if (!data.success) return;

                    if (item) item.remove();
                    updateNavbarCart(data.cart_count);

                    if (data.is_empty) {
                        location.reload();
                    } else {
                        recalcSummary();
                    }
                });
            }, 400);
        });
    });

    // Clear cart button
    var clearBtn = document.getElementById('clearCartBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            showConfirmModal({
                type: 'danger',
                title: 'Sepeti Temizle',
                message: 'Sepetinizdeki <strong>tüm ürünler</strong> silinecektir.<br>Bu işlem geri alınamaz.',
                confirmText: 'Evet, Temizle',
                cancelText: 'Vazgeç',
                onConfirm: function () {
                    cartFetch('/sepet/temizle', 'DELETE').then(function (data) {
                        if (data.success) {
                            updateNavbarCart(0);
                            location.reload();
                        }
                    });
                }
            });
        });
    }

    // Coupon - Apply
    var applyCouponBtn = document.getElementById('applyCouponBtn');
    if (applyCouponBtn) {
        applyCouponBtn.addEventListener('click', function () {
            var input = document.getElementById('couponInput');
            var code = input.value.trim().toUpperCase();

            if (!code) {
                input.classList.add('animate-shake');
                setTimeout(function () { input.classList.remove('animate-shake'); }, 500);
                return;
            }

            // Valid coupon codes
            if (code === 'HOSGELDIN10' || code === 'INDIRIM10') {
                couponApplied = code;
                document.getElementById('couponApplied').classList.remove('d-none');
                document.getElementById('appliedCouponCode').textContent = code;
                document.getElementById('couponInputGroup').classList.add('d-none');
                input.value = '';
                recalcSummary();
            } else {
                input.classList.add('animate-shake');
                setTimeout(function () { input.classList.remove('animate-shake'); }, 500);
            }
        });

        // Enter key support
        var couponInput = document.getElementById('couponInput');
        if (couponInput) {
            couponInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applyCouponBtn.click();
                }
            });
        }
    }

    // Coupon - Remove
    var removeCouponBtn = document.getElementById('removeCouponBtn');
    if (removeCouponBtn) {
        removeCouponBtn.addEventListener('click', function () {
            couponApplied = null;
            couponDiscount = 0;
            document.getElementById('couponApplied').classList.add('d-none');
            document.getElementById('couponInputGroup').classList.remove('d-none');
            recalcSummary();
        });
    }

    // Initial WhatsApp link setup
    recalcSummary();
});
