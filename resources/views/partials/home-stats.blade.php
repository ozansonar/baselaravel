{{-- Stats Section --}}
<section class="stats-section" aria-label="İstatistikler">
    <div class="container">
        <div class="row g-4">
            <div class="col-6 col-lg-3">
                <div class="stats-card animate-on-scroll">
                    <div class="stats-card__icon">
                        <i class="fa-solid fa-calendar-alt"></i>
                    </div>
                    <div class="stats-card__number" data-counter="50" data-suffix="+">0+</div>
                    <div class="stats-card__label">Yıllık Deneyim</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stats-card animate-on-scroll">
                    <div class="stats-card__icon">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="stats-card__number" data-counter="15" data-suffix="K+">0K+</div>
                    <div class="stats-card__label">Mutlu Müşteri</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stats-card animate-on-scroll">
                    <div class="stats-card__icon">
                        <i class="fa-solid fa-cow"></i>
                    </div>
                    <div class="stats-card__number" data-counter="200" data-suffix="+">0+</div>
                    <div class="stats-card__label">Sağlıklı Hayvan</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stats-card animate-on-scroll">
                    <div class="stats-card__icon">
                        <i class="fa-solid fa-truck"></i>
                    </div>
                    <div class="stats-card__number" data-counter="81" data-suffix="">0</div>
                    <div class="stats-card__label">İle Teslimat</div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var counters = document.querySelectorAll('[data-counter]');
    var animated = false;

    function animateCounters() {
        if (animated) return;
        animated = true;

        counters.forEach(function (el) {
            var target = parseInt(el.getAttribute('data-counter'), 10);
            var suffix = el.getAttribute('data-suffix') || '';
            var duration = 2000;
            var start = 0;
            var startTime = null;

            function step(timestamp) {
                if (!startTime) startTime = timestamp;
                var progress = Math.min((timestamp - startTime) / duration, 1);
                var eased = 1 - Math.pow(1 - progress, 3);
                var current = Math.floor(eased * target);
                el.textContent = current + suffix;

                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    el.textContent = target + suffix;
                }
            }

            requestAnimationFrame(step);
        });
    }

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    animateCounters();
                    observer.disconnect();
                }
            });
        }, { threshold: 0.3 });

        var section = document.querySelector('.stats-section');
        if (section) observer.observe(section);
    } else {
        animateCounters();
    }
});
</script>
