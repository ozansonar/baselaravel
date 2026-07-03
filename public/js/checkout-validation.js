/**
 * Checkout page: validation, phone masking, city/district dynamic loading,
 * saved address selection, coupon apply, double submit prevention.
 * All validation errors are shown via global result modal (no inline messages).
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var scriptTag = document.querySelector('script[data-coupon-url]');
    var couponUrl = scriptTag ? scriptTag.getAttribute('data-coupon-url') : '';
    var districtsBaseUrl = scriptTag ? scriptTag.getAttribute('data-districts-url') : '';
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────
    var NAME_REGEX = /^[a-zA-ZçÇğĞıİöÖşŞüÜ\s]+$/;
    var PHONE_DIGITS_ONLY = /[^0-9]/g;
    var EMAIL_REGEX = /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/;

    function showErrorModal(errors) {
        var html = '<ul class="text-start mb-0" style="list-style:none;padding:0">';
        errors.forEach(function (msg) {
            html += '<li class="mb-1"><i class="fa-solid fa-circle-exclamation text-danger me-1"></i> ' + msg + '</li>';
        });
        html += '</ul>';
        window.showResultModal('error', html, 'Eksik veya Hatalı Bilgiler');
    }

    // ──────────────────────────────────────────────
    // 1. Name fields — allow only letters + spaces
    // ──────────────────────────────────────────────
    document.querySelectorAll('[data-validate="name"]').forEach(function (input) {
        input.addEventListener('input', function () {
            var cleaned = this.value.replace(/[^a-zA-ZçÇğĞıİöÖşŞüÜ\s]/g, '');
            if (cleaned !== this.value) {
                this.value = cleaned;
            }
        });
    });

    // ──────────────────────────────────────────────
    // 2. Phone masking — format: 0555 123 45 67
    // ──────────────────────────────────────────────
    document.querySelectorAll('[data-validate="phone"]').forEach(function (input) {
        function formatPhone(digits) {
            if (digits.length <= 4) return digits;
            if (digits.length <= 7) return digits.slice(0, 4) + ' ' + digits.slice(4);
            if (digits.length <= 9) return digits.slice(0, 4) + ' ' + digits.slice(4, 7) + ' ' + digits.slice(7);
            return digits.slice(0, 4) + ' ' + digits.slice(4, 7) + ' ' + digits.slice(7, 9) + ' ' + digits.slice(9, 11);
        }

        input.addEventListener('input', function () {
            var digits = this.value.replace(PHONE_DIGITS_ONLY, '').slice(0, 11);
            this.value = formatPhone(digits);
        });

        // Format existing value on load
        if (input.value) {
            var digits = input.value.replace(PHONE_DIGITS_ONLY, '').slice(0, 11);
            input.value = formatPhone(digits);
        }
    });

    // ──────────────────────────────────────────────
    // 3. Zip code — digits only, max 5
    // ──────────────────────────────────────────────
    document.querySelectorAll('[data-validate="zipcode"]').forEach(function (input) {
        input.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 5);
        });
    });

    // ──────────────────────────────────────────────
    // 4. City & District dynamic select
    // ──────────────────────────────────────────────
    var citySelect = document.getElementById('shipping_city');
    var districtSelect = document.getElementById('shipping_district');

    function loadDistricts(cityName, selectedDistrict) {
        if (!cityName) {
            districtSelect.innerHTML = '<option value="">Önce il seçiniz</option>';
            districtSelect.disabled = true;
            return;
        }

        districtSelect.innerHTML = '<option value="">Yükleniyor...</option>';
        districtSelect.disabled = true;

        fetch(districtsBaseUrl + '/' + encodeURIComponent(cityName) + '/ilceler', {
            headers: { 'Accept': 'application/json' }
        })
        .then(function (r) { return r.json(); })
        .then(function (districts) {
            districtSelect.innerHTML = '<option value="">İlçe seçiniz</option>';
            districts.forEach(function (name) {
                var opt = document.createElement('option');
                opt.value = name;
                opt.textContent = name;
                if (selectedDistrict && name === selectedDistrict) {
                    opt.selected = true;
                }
                districtSelect.appendChild(opt);
            });
            districtSelect.disabled = false;
        })
        .catch(function () {
            districtSelect.innerHTML = '<option value="">İlçeler yüklenemedi</option>';
            districtSelect.disabled = false;
        });
    }

    if (citySelect) {
        citySelect.addEventListener('change', function () {
            loadDistricts(this.value, null);
        });

        // Load districts for pre-selected city (old value or default address)
        if (citySelect.value) {
            var oldDistrict = districtSelect ? districtSelect.getAttribute('data-old') : null;
            loadDistricts(citySelect.value, oldDistrict);
        }
    }

    // ──────────────────────────────────────────────
    // 5. Address card selection (saved addresses)
    // ──────────────────────────────────────────────
    document.querySelectorAll('.checkout-address-card').forEach(function (card) {
        card.addEventListener('click', function () {
            document.querySelectorAll('.checkout-address-card').forEach(function (c) {
                c.classList.remove('active');
            });
            this.classList.add('active');

            var data = JSON.parse(this.dataset.address);

            // Set name
            var nameInput = document.getElementById('shipping_name');
            nameInput.value = data.full_name;

            // Set phone with formatting
            var phoneInput = document.getElementById('shipping_phone');
            var phoneDigits = (data.phone || '').replace(PHONE_DIGITS_ONLY, '').slice(0, 11);
            if (phoneDigits.length === 11) {
                phoneInput.value = phoneDigits.slice(0, 4) + ' ' + phoneDigits.slice(4, 7) + ' ' + phoneDigits.slice(7, 9) + ' ' + phoneDigits.slice(9, 11);
            } else {
                phoneInput.value = data.phone || '';
            }

            // Set city select
            if (citySelect) {
                citySelect.value = data.city || '';
                loadDistricts(data.city, data.district);
            }

            // Set zip code
            var zipInput = document.getElementById('shipping_zip_code');
            zipInput.value = data.zip_code || '';

            // Set address
            var addressInput = document.getElementById('shipping_address');
            addressInput.value = data.address_line || '';
        });
    });

    // ──────────────────────────────────────────────
    // 6. Apply coupon (AJAX)
    // ──────────────────────────────────────────────
    var applyCouponBtn = document.getElementById('applyCouponBtn');
    var couponInput = document.getElementById('couponInput');
    var couponHidden = document.getElementById('couponCodeHidden');
    var couponMessage = document.getElementById('couponMessage');
    var discountRow = document.getElementById('discountRow');
    var checkoutShippingEl = document.getElementById('checkoutShipping');

    if (applyCouponBtn) {
        applyCouponBtn.addEventListener('click', function () {
            var code = couponInput.value.trim();
            if (!code) return;

            applyCouponBtn.disabled = true;
            applyCouponBtn.textContent = '...';

            fetch(couponUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ code: code })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                applyCouponBtn.disabled = false;
                applyCouponBtn.textContent = 'Uygula';

                if (data.success) {
                    couponMessage.className = 'small mt-2 text-success';
                    couponMessage.textContent = data.message;
                    couponHidden.value = code;
                    discountRow.classList.remove('d-none');
                    document.getElementById('checkoutDiscount').textContent = '-' + data.discount + ' ₺';
                    document.getElementById('checkoutTotal').textContent = data.total + ' ₺';

                    if (checkoutShippingEl) {
                        if (data.shipping_raw > 0) {
                            checkoutShippingEl.innerHTML = data.shipping + ' ₺';
                            checkoutShippingEl.classList.remove('text-success');
                        } else {
                            checkoutShippingEl.innerHTML = 'Ücretsiz';
                            checkoutShippingEl.classList.add('text-success');
                        }
                    }
                } else {
                    couponMessage.className = 'small mt-2 text-danger';
                    couponMessage.textContent = data.message;
                    couponHidden.value = '';
                    discountRow.classList.add('d-none');
                }
            })
            .catch(function () {
                applyCouponBtn.disabled = false;
                applyCouponBtn.textContent = 'Uygula';
                window.showResultModal('error', 'Kupon kodu uygulanırken bir hata oluştu.', 'Hata');
            });
        });
    }

    // ──────────────────────────────────────────────
    // 7. Form submit validation + double submit prevention
    //    All errors collected and shown in a single modal
    // ──────────────────────────────────────────────
    var form = document.getElementById('checkoutForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            var errors = [];

            // Validate all name fields
            form.querySelectorAll('[data-validate="name"]').forEach(function (input) {
                var val = input.value.trim();
                var label = input.closest('.col-md-6, .col-12')?.querySelector('.form-label')?.textContent?.replace(' *', '').trim() || 'Ad Soyad';
                if (!val) {
                    errors.push(label + ' zorunludur.');
                } else if (val.length < 3) {
                    errors.push(label + ' en az 3 karakter olmalıdır.');
                } else if (!NAME_REGEX.test(val)) {
                    errors.push(label + ' sadece harf ve boşluk içerebilir.');
                }
            });

            // Validate phone
            form.querySelectorAll('[data-validate="phone"]').forEach(function (input) {
                var digits = input.value.replace(PHONE_DIGITS_ONLY, '');
                if (!digits) {
                    errors.push('Telefon numarası zorunludur.');
                } else if (digits.length !== 11 || digits.slice(0, 2) !== '05') {
                    errors.push('Telefon numarası 05 ile başlamalı ve 11 haneli olmalıdır.');
                }
            });

            // Validate email
            form.querySelectorAll('[data-validate="email"]').forEach(function (input) {
                var val = input.value.trim();
                if (!val) {
                    errors.push('E-posta adresi zorunludur.');
                } else if (!EMAIL_REGEX.test(val)) {
                    errors.push('Geçerli bir e-posta adresi giriniz.');
                }
            });

            // Validate city
            if (citySelect && !citySelect.value) {
                errors.push('İl seçiniz.');
            }

            // Validate district
            if (districtSelect && !districtSelect.value) {
                errors.push('İlçe seçiniz.');
            }

            // Validate address
            var addressField = document.getElementById('shipping_address');
            if (addressField && !addressField.value.trim()) {
                errors.push('Teslimat adresi zorunludur.');
            }

            // Validate zip code if filled
            form.querySelectorAll('[data-validate="zipcode"]').forEach(function (input) {
                var val = input.value.trim();
                if (val && val.length !== 5) {
                    errors.push('Posta kodu 5 haneli olmalıdır.');
                }
            });

            // Validate reCAPTCHA if present
            var recaptchaWidget = form.querySelector('.g-recaptcha');
            if (recaptchaWidget) {
                var recaptchaResponse = form.querySelector('[name="g-recaptcha-response"]');
                if (!recaptchaResponse || !recaptchaResponse.value) {
                    errors.push('Lütfen robot olmadığınızı doğrulayın.');
                }
            }

            if (errors.length > 0) {
                e.preventDefault();
                showErrorModal(errors);
                return;
            }

            // Prevent double submit
            var btn = document.getElementById('placeOrderBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Sipariş oluşturuluyor...';
        });
    }
});
