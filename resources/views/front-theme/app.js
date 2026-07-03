/* =============================================
   ORHAN BABA'NIN ÇİFTLİĞİ - Ortak JavaScript
   ============================================= */

// Güvenli JSON parse - XSS ve crash önleme
function safeJsonParse(key, fallback) {
    try {
        const data = localStorage.getItem(key);
        if (!data) return fallback;
        return JSON.parse(data);
    } catch (e) {
        console.warn('JSON parse hatası (' + key + '):', e);
        return fallback;
    }
}

// Güvenli HTML escape - XSS önleme
function escapeHtml(text) {
    if (typeof text !== 'string') return '';
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Navbar scroll efekti
function initNavbarScroll() {
    window.addEventListener('scroll', function() {
        var navbar = document.querySelector('.navbar-custom');
        var scrollTop = document.getElementById('scrollTop');

        if (navbar) {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        }

        if (scrollTop) {
            if (window.scrollY > 500) {
                scrollTop.classList.add('visible');
            } else {
                scrollTop.classList.remove('visible');
            }
        }
    });
}

// Scroll to top
function initScrollToTop() {
    var scrollBtn = document.getElementById('scrollTop');
    if (scrollBtn) {
        scrollBtn.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
}

// Sepet sayısını güncelle (navbar)
function updateCartCount() {
    var cart = safeJsonParse('cart', []);
    var totalItems = cart.reduce(function(sum, item) { return sum + (item.quantity || 0); }, 0);
    var countEls = document.querySelectorAll('#cartCount, .cart-count-badge');
    countEls.forEach(function(el) {
        el.textContent = totalItems;
    });
}

// Animate on scroll (Intersection Observer)
function initAnimateOnScroll() {
    var observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('animated');
            }
        });
    }, observerOptions);

    document.querySelectorAll('.animate-on-scroll').forEach(function(el) {
        observer.observe(el);
    });
}

// Sayfa yüklendiğinde başlat
document.addEventListener('DOMContentLoaded', function() {
    initNavbarScroll();
    initScrollToTop();
    updateCartCount();
    initAnimateOnScroll();
});
