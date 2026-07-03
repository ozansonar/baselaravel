{{-- About Section --}}
<section class="about-section" id="about" aria-labelledby="about-title">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="about-image-container animate-on-scroll">
                    <div class="about-image-main">
                        <svg class="about-farm-svg" viewBox="0 0 300 250" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <!-- Rolling Hills -->
                            <ellipse cx="150" cy="230" rx="180" ry="40" fill="#7cb342"/>
                            <ellipse cx="80" cy="210" rx="100" ry="30" fill="#8bc34a"/>
                            <ellipse cx="250" cy="220" rx="80" ry="25" fill="#9ccc65"/>
                            <!-- Farmer -->
                            <circle cx="150" cy="120" r="25" fill="#ffcc80"/>
                            <ellipse cx="150" cy="180" rx="30" ry="40" fill="#5d4037"/>
                            <rect x="135" y="95" width="30" height="10" fill="#8d6e63"/>
                            <ellipse cx="150" cy="95" rx="18" ry="8" fill="#8d6e63"/>
                            <!-- Farmer Face -->
                            <circle cx="143" cy="118" r="3" fill="#5d4037"/>
                            <circle cx="157" cy="118" r="3" fill="#5d4037"/>
                            <path d="M145 130 Q150 135 155 130" stroke="#5d4037" stroke-width="2" fill="none"/>
                            <!-- Pitchfork -->
                            <line x1="190" y1="100" x2="190" y2="200" stroke="#8d6e63" stroke-width="4"/>
                            <path d="M180 100 L190 90 L200 100" stroke="#8d6e63" stroke-width="4" fill="none"/>
                            <line x1="185" y1="95" x2="185" y2="110" stroke="#8d6e63" stroke-width="3"/>
                            <line x1="195" y1="95" x2="195" y2="110" stroke="#8d6e63" stroke-width="3"/>
                            <!-- Small Animals -->
                            <ellipse cx="60" cy="195" rx="15" ry="10" fill="white"/>
                            <circle cx="70" cy="190" r="6" fill="white"/>
                            <ellipse cx="250" cy="200" rx="12" ry="8" fill="#D2691E"/>
                            <circle cx="258" cy="196" r="5" fill="#D2691E"/>
                            <!-- Trees -->
                            <rect x="30" y="150" width="8" height="40" fill="#8d6e63"/>
                            <circle cx="34" cy="140" r="20" fill="#4a7c43"/>
                            <rect x="270" y="160" width="6" height="30" fill="#8d6e63"/>
                            <circle cx="273" cy="150" r="15" fill="#4a7c43"/>
                        </svg>
                    </div>
                    <div class="about-floating-card about-floating-card--card-1">
                        <div class="about-floating-card__icon">
                            <i class="fa-solid fa-medal"></i>
                        </div>
                        <div>
                            <div class="about-floating-card__text">Kalite Garantisi</div>
                            <div class="about-floating-card__subtext">ISO 22000 Sertifikalı</div>
                        </div>
                    </div>
                    <div class="about-floating-card about-floating-card--card-2">
                        <div class="about-floating-card__icon">
                            <i class="fa-solid fa-leaf"></i>
                        </div>
                        <div>
                            <div class="about-floating-card__text">%100 Organik</div>
                            <div class="about-floating-card__subtext">Doğal Üretim</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="about-content animate-on-scroll">
                    <span class="section-badge"><i class="fa-solid fa-heart"></i> Hikayemiz</span>
                    <h2 class="about-content__title" id="about-title">Çorum'dan 50 Yıldır Aynı Sevgiyle, Aynı Lezzetle</h2>
                    <p class="about-content__text">
                        1975 yılında Orhan dedemizin <strong>Çorum'un Büyük Palabıyık Köyü'nde</strong> kurduğu bu çiftlik, üç nesil boyunca aynı geleneksel değerlerle üretim yapmaya devam ediyor. İç Anadolu'nun bereketli topraklarında, doğayla uyum içinde yaşıyor ve üretiyoruz.
                    </p>
                    <p class="about-content__text">
                        Hayvanlarımız Çorum köylerinin geniş meralarında serbest dolaşır, tamamen organik yemlerle beslenir. Her ürünümüz, atalarımızdan öğrendiğimiz geleneksel yöntemlerle, sabırla ve özenle hazırlanır.
                    </p>
                    <div class="about-features">
                        <div class="about-features__item">
                            <div class="about-features__icon">
                                <i class="fa-solid fa-check"></i>
                            </div>
                            <span class="about-features__text">Katkı maddesi içermez</span>
                        </div>
                        <div class="about-features__item">
                            <div class="about-features__icon">
                                <i class="fa-solid fa-check"></i>
                            </div>
                            <span class="about-features__text">Günlük taze üretim</span>
                        </div>
                        <div class="about-features__item">
                            <div class="about-features__icon">
                                <i class="fa-solid fa-check"></i>
                            </div>
                            <span class="about-features__text">Soğuk zincir teslimat</span>
                        </div>
                        <div class="about-features__item">
                            <div class="about-features__icon">
                                <i class="fa-solid fa-check"></i>
                            </div>
                            <span class="about-features__text">Aile işletmesi güvencesi</span>
                        </div>
                    </div>
                    <a href="{{ route('pages.show', 'hakkimizda') }}" class="btn-custom">
                        <i class="fa-solid fa-info-circle me-2"></i>Daha Fazla Bilgi
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
