{{-- Hero Section --}}
<section class="hero-section" id="home">
    <div class="hero-bg-elements">
        <div class="floating-leaf"><i class="fa-solid fa-leaf"></i></div>
        <div class="floating-leaf"><i class="fa-solid fa-seedling"></i></div>
        <div class="floating-leaf"><i class="fa-solid fa-leaf"></i></div>
        <div class="floating-leaf"><i class="fa-solid fa-seedling"></i></div>
        <div class="floating-leaf"><i class="fa-solid fa-leaf"></i></div>
    </div>
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="hero-content">
                    <div class="hero-badge">
                        <i class="fa-solid fa-location-dot"></i>
                        Çorum • Büyük Palabıyık Köyü • %100 Doğal
                    </div>
                    <h1 class="hero-title">
                        Çorum Köyünden Sofraya <span>Taze</span> Lezzetler
                    </h1>
                    <p class="hero-description">
                        1975'ten bu yana <strong>Çorum'un Büyük Palabıyık Köyü'nde</strong> ailemizin çiftliğinden doğal süt, köy peyniri, tereyağı ve organik köy ürünlerini sevgiyle üretip sofralarınıza ulaştırıyoruz. Hiçbir katkı maddesi yok — sadece İç Anadolu'nun bereketli toprağının tadı.
                    </p>
                    <div class="hero-buttons">
                        <a href="{{ route('products.index', $categories->first()?->slug ?? 'sut') }}" class="btn btn-custom">
                            <i class="fa-solid fa-cheese me-2"></i>Ürünleri Keşfet
                        </a>
                        <a href="{{ route('pages.show', 'hakkimizda') }}" class="btn btn-outline-custom">
                            <i class="fa-solid fa-play me-2"></i>Hikayemizi İzle
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="farm-scene">
                    <div class="farm-illustration">
                        {{-- Sun with glow --}}
                        <div class="sun"></div>

                        {{-- Clouds --}}
                        <div class="cloud cloud-1"></div>
                        <div class="cloud cloud-2"></div>
                        <div class="cloud cloud-3"></div>

                        {{-- Flying Birds --}}
                        <div class="bird bird-1" aria-hidden="true">🐦</div>
                        <div class="bird bird-2" aria-hidden="true">🐦</div>
                        <div class="bird bird-3" aria-hidden="true">🐦</div>

                        {{-- Butterflies --}}
                        <div class="butterfly butterfly-1" aria-hidden="true">🦋</div>
                        <div class="butterfly butterfly-2" aria-hidden="true">🦋</div>

                        {{-- Dust particles --}}
                        <div class="dust-particle dust-1"></div>
                        <div class="dust-particle dust-2"></div>
                        <div class="dust-particle dust-3"></div>
                        <div class="dust-particle dust-4"></div>

                        {{-- Barn SVG --}}
                        <svg class="barn-svg" viewBox="0 0 200 180" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <rect x="30" y="80" width="140" height="100" fill="#8B4513"/>
                            <rect x="35" y="85" width="130" height="90" fill="#A0522D"/>
                            <polygon points="20,80 100,20 180,80" fill="#6B3E26"/>
                            <polygon points="25,80 100,25 175,80" fill="#8B4513"/>
                            <rect x="70" y="110" width="60" height="70" fill="#5D3A1A"/>
                            <rect x="75" y="115" width="25" height="60" fill="#4A2E15"/>
                            <rect x="100" y="115" width="25" height="60" fill="#4A2E15"/>
                            <circle cx="95" cy="145" r="4" fill="#D4A84B"/>
                            <circle cx="105" cy="145" r="4" fill="#D4A84B"/>
                            <rect x="45" y="100" width="20" height="25" fill="#87CEEB"/>
                            <rect x="135" y="100" width="20" height="25" fill="#87CEEB"/>
                            <line x1="55" y1="100" x2="55" y2="125" stroke="#5D3A1A" stroke-width="2"/>
                            <line x1="45" y1="112" x2="65" y2="112" stroke="#5D3A1A" stroke-width="2"/>
                            <line x1="145" y1="100" x2="145" y2="125" stroke="#5D3A1A" stroke-width="2"/>
                            <line x1="135" y1="112" x2="155" y2="112" stroke="#5D3A1A" stroke-width="2"/>
                            <circle cx="100" cy="55" r="15" fill="#87CEEB"/>
                            <circle cx="100" cy="55" r="12" fill="#B0E0E6"/>
                            <line x1="100" y1="40" x2="100" y2="70" stroke="#5D3A1A" stroke-width="2"/>
                            <line x1="85" y1="55" x2="115" y2="55" stroke="#5D3A1A" stroke-width="2"/>
                            <rect x="175" y="60" width="25" height="120" fill="#808080"/>
                            <ellipse cx="187.5" cy="60" rx="12.5" ry="8" fill="#A0A0A0"/>
                            <ellipse cx="187.5" cy="55" rx="12.5" ry="8" fill="#696969"/>
                        </svg>

                        {{-- Animated Cows --}}
                        <div class="cow-container">
                            <svg class="cow-svg" viewBox="0 0 200 180" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                {{-- Tail --}}
                                <path d="M30 70 Q10 55 15 40 Q18 35 22 38" stroke="#2d2d2d" stroke-width="3" fill="none" stroke-linecap="round"/>
                                <ellipse cx="15" cy="38" rx="5" ry="4" fill="#2d2d2d"/>
                                {{-- Body --}}
                                <ellipse cx="95" cy="85" rx="55" ry="40" fill="white" stroke="#e8e8e8" stroke-width="1"/>
                                {{-- Black patches on body --}}
                                <ellipse cx="75" cy="75" rx="18" ry="14" fill="#2d2d2d" transform="rotate(-15 75 75)"/>
                                <ellipse cx="110" cy="90" rx="15" ry="12" fill="#2d2d2d" transform="rotate(10 110 90)"/>
                                <ellipse cx="60" cy="95" rx="12" ry="10" fill="#2d2d2d" transform="rotate(-5 60 95)"/>
                                {{-- Legs --}}
                                <rect x="55" y="115" width="14" height="35" rx="7" fill="white" stroke="#e8e8e8" stroke-width="1"/>
                                <rect x="78" y="118" width="14" height="35" rx="7" fill="white" stroke="#e8e8e8" stroke-width="1"/>
                                <rect x="105" y="118" width="14" height="35" rx="7" fill="white" stroke="#e8e8e8" stroke-width="1"/>
                                <rect x="128" y="115" width="14" height="35" rx="7" fill="white" stroke="#e8e8e8" stroke-width="1"/>
                                {{-- Hooves --}}
                                <ellipse cx="62" cy="152" rx="8" ry="5" fill="#5d4037"/>
                                <ellipse cx="85" cy="155" rx="8" ry="5" fill="#5d4037"/>
                                <ellipse cx="112" cy="155" rx="8" ry="5" fill="#5d4037"/>
                                <ellipse cx="135" cy="152" rx="8" ry="5" fill="#5d4037"/>
                                {{-- Bell --}}
                                <rect x="88" y="58" width="18" height="4" rx="2" fill="#8B4513"/>
                                <circle cx="97" cy="68" r="8" fill="#d4a84b"/>
                                <circle cx="97" cy="68" r="5" fill="#c49a3b"/>
                                <circle cx="97" cy="71" r="2" fill="#8B4513"/>
                                {{-- Head --}}
                                <circle cx="155" cy="55" r="35" fill="white" stroke="#e8e8e8" stroke-width="1"/>
                                {{-- Black patch on head --}}
                                <ellipse cx="145" cy="40" rx="14" ry="11" fill="#2d2d2d" transform="rotate(-20 145 40)"/>
                                {{-- Ears --}}
                                <ellipse cx="125" cy="28" rx="14" ry="8" fill="white" stroke="#e8e8e8" stroke-width="1" transform="rotate(-30 125 28)"/>
                                <ellipse cx="125" cy="28" rx="9" ry="5" fill="#FFB6C1" transform="rotate(-30 125 28)"/>
                                <ellipse cx="185" cy="28" rx="14" ry="8" fill="white" stroke="#e8e8e8" stroke-width="1" transform="rotate(30 185 28)"/>
                                <ellipse cx="185" cy="28" rx="9" ry="5" fill="#FFB6C1" transform="rotate(30 185 28)"/>
                                {{-- Horns --}}
                                <path d="M135 20 Q130 5 138 2" stroke="#d4a84b" stroke-width="5" fill="none" stroke-linecap="round"/>
                                <path d="M175 20 Q180 5 172 2" stroke="#d4a84b" stroke-width="5" fill="none" stroke-linecap="round"/>
                                {{-- Eyes --}}
                                <circle cx="142" cy="50" r="8" fill="white"/>
                                <circle cx="168" cy="50" r="8" fill="white"/>
                                <circle cx="144" cy="51" r="5" fill="#2d2d2d"/>
                                <circle cx="170" cy="51" r="5" fill="#2d2d2d"/>
                                <circle cx="146" cy="49" r="2" fill="white"/>
                                <circle cx="172" cy="49" r="2" fill="white"/>
                                {{-- Snout --}}
                                <ellipse cx="155" cy="68" rx="16" ry="11" fill="#FFB6C1"/>
                                <ellipse cx="149" cy="68" rx="4" ry="3" fill="#E091A0"/>
                                <ellipse cx="161" cy="68" rx="4" ry="3" fill="#E091A0"/>
                                {{-- Smile --}}
                                <path d="M148 74 Q155 80 162 74" stroke="#D47B8A" stroke-width="2" fill="none" stroke-linecap="round"/>
                                {{-- Udder --}}
                                <ellipse cx="95" cy="122" rx="14" ry="8" fill="#FFB6C1"/>
                            </svg>
                        </div>
                        <div class="cow-container-2">
                            <svg class="cow-svg" viewBox="0 0 200 180" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                {{-- Tail --}}
                                <path d="M30 70 Q10 55 15 40 Q18 35 22 38" stroke="#6B3E26" stroke-width="3" fill="none" stroke-linecap="round"/>
                                <ellipse cx="15" cy="38" rx="5" ry="4" fill="#6B3E26"/>
                                {{-- Body --}}
                                <ellipse cx="95" cy="85" rx="55" ry="40" fill="#f5e6d0" stroke="#e8dcc8" stroke-width="1"/>
                                {{-- Brown patches on body --}}
                                <ellipse cx="80" cy="78" rx="20" ry="15" fill="#8B4513" transform="rotate(-10 80 78)"/>
                                <ellipse cx="115" cy="88" rx="16" ry="13" fill="#A0522D" transform="rotate(15 115 88)"/>
                                {{-- Legs --}}
                                <rect x="55" y="115" width="14" height="35" rx="7" fill="#f5e6d0" stroke="#e8dcc8" stroke-width="1"/>
                                <rect x="78" y="118" width="14" height="35" rx="7" fill="#f5e6d0" stroke="#e8dcc8" stroke-width="1"/>
                                <rect x="105" y="118" width="14" height="35" rx="7" fill="#f5e6d0" stroke="#e8dcc8" stroke-width="1"/>
                                <rect x="128" y="115" width="14" height="35" rx="7" fill="#f5e6d0" stroke="#e8dcc8" stroke-width="1"/>
                                {{-- Hooves --}}
                                <ellipse cx="62" cy="152" rx="8" ry="5" fill="#5d4037"/>
                                <ellipse cx="85" cy="155" rx="8" ry="5" fill="#5d4037"/>
                                <ellipse cx="112" cy="155" rx="8" ry="5" fill="#5d4037"/>
                                <ellipse cx="135" cy="152" rx="8" ry="5" fill="#5d4037"/>
                                {{-- Bell --}}
                                <rect x="88" y="58" width="18" height="4" rx="2" fill="#8B4513"/>
                                <circle cx="97" cy="68" r="8" fill="#d4a84b"/>
                                <circle cx="97" cy="68" r="5" fill="#c49a3b"/>
                                <circle cx="97" cy="71" r="2" fill="#8B4513"/>
                                {{-- Head --}}
                                <circle cx="155" cy="55" r="35" fill="#f5e6d0" stroke="#e8dcc8" stroke-width="1"/>
                                {{-- Brown patch on head --}}
                                <ellipse cx="165" cy="42" rx="15" ry="12" fill="#8B4513" transform="rotate(15 165 42)"/>
                                {{-- Ears --}}
                                <ellipse cx="125" cy="28" rx="14" ry="8" fill="#f5e6d0" stroke="#e8dcc8" stroke-width="1" transform="rotate(-30 125 28)"/>
                                <ellipse cx="125" cy="28" rx="9" ry="5" fill="#FFB6C1" transform="rotate(-30 125 28)"/>
                                <ellipse cx="185" cy="28" rx="14" ry="8" fill="#f5e6d0" stroke="#e8dcc8" stroke-width="1" transform="rotate(30 185 28)"/>
                                <ellipse cx="185" cy="28" rx="9" ry="5" fill="#FFB6C1" transform="rotate(30 185 28)"/>
                                {{-- Horns --}}
                                <path d="M135 20 Q130 5 138 2" stroke="#d4a84b" stroke-width="5" fill="none" stroke-linecap="round"/>
                                <path d="M175 20 Q180 5 172 2" stroke="#d4a84b" stroke-width="5" fill="none" stroke-linecap="round"/>
                                {{-- Eyes --}}
                                <circle cx="142" cy="50" r="8" fill="white"/>
                                <circle cx="168" cy="50" r="8" fill="white"/>
                                <circle cx="144" cy="51" r="5" fill="#2d2d2d"/>
                                <circle cx="170" cy="51" r="5" fill="#2d2d2d"/>
                                <circle cx="146" cy="49" r="2" fill="white"/>
                                <circle cx="172" cy="49" r="2" fill="white"/>
                                {{-- Snout --}}
                                <ellipse cx="155" cy="68" rx="16" ry="11" fill="#FFB6C1"/>
                                <ellipse cx="149" cy="68" rx="4" ry="3" fill="#E091A0"/>
                                <ellipse cx="161" cy="68" rx="4" ry="3" fill="#E091A0"/>
                                {{-- Smile --}}
                                <path d="M148 74 Q155 80 162 74" stroke="#D47B8A" stroke-width="2" fill="none" stroke-linecap="round"/>
                            </svg>
                        </div>

                        {{-- Chickens --}}
                        <div class="chicken-container chicken-1">
                            <svg class="chicken-svg" viewBox="0 0 50 50" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <ellipse cx="25" cy="32" rx="15" ry="12" fill="#D2691E"/>
                                <circle cx="38" cy="22" r="8" fill="#D2691E"/>
                                <path d="M38 14 Q35 10 38 8 Q41 10 38 14" fill="#FF4444"/>
                                <polygon points="46,22 52,24 46,26" fill="#FFA500"/>
                                <circle cx="40" cy="20" r="2" fill="#2d2d2d"/>
                                <ellipse cx="42" cy="28" rx="2" ry="3" fill="#FF4444"/>
                                <line x1="20" y1="44" x2="20" y2="50" stroke="#FFA500" stroke-width="2"/>
                                <line x1="30" y1="44" x2="30" y2="50" stroke="#FFA500" stroke-width="2"/>
                                <path d="M10 28 Q5 20 8 15" stroke="#8B4513" stroke-width="3" fill="none"/>
                                <path d="M10 30 Q3 25 5 18" stroke="#8B4513" stroke-width="3" fill="none"/>
                            </svg>
                        </div>
                        <div class="chicken-container chicken-2">
                            <svg class="chicken-svg" viewBox="0 0 50 50" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <ellipse cx="25" cy="32" rx="15" ry="12" fill="#F5DEB3"/>
                                <circle cx="38" cy="22" r="8" fill="#F5DEB3"/>
                                <path d="M38 14 Q35 10 38 8 Q41 10 38 14" fill="#FF4444"/>
                                <polygon points="46,22 52,24 46,26" fill="#FFA500"/>
                                <circle cx="40" cy="20" r="2" fill="#2d2d2d"/>
                                <ellipse cx="42" cy="28" rx="2" ry="3" fill="#FF4444"/>
                                <line x1="20" y1="44" x2="20" y2="50" stroke="#FFA500" stroke-width="2"/>
                                <line x1="30" y1="44" x2="30" y2="50" stroke="#FFA500" stroke-width="2"/>
                                <path d="M10 28 Q5 20 8 15" stroke="#D2691E" stroke-width="3" fill="none"/>
                            </svg>
                        </div>
                        <div class="chicken-container chicken-3">
                            <svg class="chicken-svg" viewBox="0 0 50 50" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <ellipse cx="25" cy="32" rx="15" ry="12" fill="#DEB887"/>
                                <circle cx="38" cy="22" r="8" fill="#DEB887"/>
                                <path d="M38 14 Q35 10 38 8 Q41 10 38 14" fill="#FF4444"/>
                                <polygon points="46,22 52,24 46,26" fill="#FFA500"/>
                                <circle cx="40" cy="20" r="2" fill="#2d2d2d"/>
                                <ellipse cx="42" cy="28" rx="2" ry="3" fill="#FF4444"/>
                                <line x1="20" y1="44" x2="20" y2="50" stroke="#FFA500" stroke-width="2"/>
                                <line x1="30" y1="44" x2="30" y2="50" stroke="#FFA500" stroke-width="2"/>
                                <path d="M10 28 Q5 20 8 15" stroke="#A0522D" stroke-width="3" fill="none"/>
                            </svg>
                        </div>

                        {{-- Flowers --}}
                        <div class="flower flower-1">
                            <svg viewBox="0 0 30 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <line x1="15" y1="20" x2="15" y2="40" stroke="#228B22" stroke-width="2"/>
                                <circle cx="15" cy="12" r="8" fill="#FF69B4"/>
                                <circle cx="15" cy="12" r="4" fill="#FFD700"/>
                            </svg>
                        </div>
                        <div class="flower flower-2">
                            <svg viewBox="0 0 30 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <line x1="15" y1="20" x2="15" y2="40" stroke="#228B22" stroke-width="2"/>
                                <circle cx="15" cy="12" r="8" fill="#FF6347"/>
                                <circle cx="15" cy="12" r="4" fill="#FFD700"/>
                            </svg>
                        </div>
                        <div class="flower flower-3">
                            <svg viewBox="0 0 30 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <line x1="15" y1="20" x2="15" y2="40" stroke="#228B22" stroke-width="2"/>
                                <circle cx="15" cy="12" r="8" fill="#9370DB"/>
                                <circle cx="15" cy="12" r="4" fill="#FFD700"/>
                            </svg>
                        </div>
                        <div class="flower flower-4">
                            <svg viewBox="0 0 30 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <line x1="15" y1="20" x2="15" y2="40" stroke="#228B22" stroke-width="2"/>
                                <circle cx="15" cy="12" r="8" fill="#87CEEB"/>
                                <circle cx="15" cy="12" r="4" fill="#FFD700"/>
                            </svg>
                        </div>

                        {{-- Grass --}}
                        <div class="grass-row"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var grassRow = document.querySelector('.grass-row');
    if (grassRow) {
        for (var i = 0; i < 80; i++) {
            var blade = document.createElement('div');
            blade.className = 'grass-blade';
            grassRow.appendChild(blade);
        }
    }
});
</script>
