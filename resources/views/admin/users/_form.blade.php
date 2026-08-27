{{-- Shared form partial for create & edit --}}
@php
    $isEdit = isset($user);
    $u = $isEdit ? $user : null;
@endphp

<div class="stg-layout">

    <!-- Left Navigation -->
    <div class="stg-nav" data-aos="fade-right" data-aos-delay="100">
        <a href="#section-avatar" class="stg-nav-item active" onclick="scrollToSection('section-avatar', this)">
            <i class="bi bi-camera"></i> Profil Fotoğrafı
        </a>
        <a href="#section-personal" class="stg-nav-item" onclick="scrollToSection('section-personal', this)">
            <i class="bi bi-person"></i> Kişisel Bilgiler
        </a>
        <a href="#section-account" class="stg-nav-item" onclick="scrollToSection('section-account', this)">
            <i class="bi bi-key"></i> Hesap Bilgileri
        </a>
        <a href="#section-role" class="stg-nav-item" onclick="scrollToSection('section-role', this)">
            <i class="bi bi-shield"></i> Rol & Yetki
        </a>
    </div>

    <!-- Form Content -->
    <div class="stg-content">

        <!-- ==================== SECTION 1: AVATAR ==================== -->
        <div class="card-dark mb-4" id="section-avatar" data-aos="fade-up" data-aos-delay="0">
            <div class="card-header-custom">
                <div class="form-section-header mb-0">
                    <div class="form-section-icon"><i class="bi bi-camera-fill"></i></div>
                    <div>
                        <h6 class="mb-0">Profil Fotoğrafı</h6>
                        <small class="text-muted">Kullanıcının profil resmini yükleyin veya değiştirin</small>
                    </div>
                </div>
            </div>
            <div class="card-body-custom">
                <div class="uf-avatar-section">
                    <div class="uf-avatar-preview" id="avatarPreview">
                        @if($isEdit && $u->avatar)
                            <img src="{{ upload_url($u->avatar, 'thumb') }}" alt="{{ $u->full_name }}" id="avatarImg">
                        @else
                            <img src="https://ui-avatars.com/api/?name={{ urlencode($isEdit ? $u->full_name : 'Yeni Kullanıcı') }}&background=14b8a6&color=fff&size=120" alt="{{ $isEdit ? $u->full_name : 'Yeni Kullanıcı' }}" id="avatarImg">
                        @endif
                        <div class="uf-avatar-overlay" onclick="document.getElementById('avatarInput').click()">
                            <i class="bi bi-camera-fill"></i>
                            <span>Değiştir</span>
                        </div>
                    </div>
                    <div class="uf-avatar-info">
                        <h6 id="avatarUserName">{{ $isEdit ? $u->full_name : 'Yeni Kullanıcı' }}</h6>
                        <p class="text-muted mb-2 fs-13">PNG, JPG veya WebP formatında, maksimum 1MB boyutunda bir fotoğraf yükleyin.</p>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn-glass" onclick="document.getElementById('avatarInput').click()">
                                <i class="bi bi-upload me-1"></i> Fotoğraf Yükle
                            </button>
                            <button type="button" class="btn-teal btn-danger-gradient {{ ($isEdit && $u->avatar) ? '' : 'd-none' }}" onclick="removeAvatar()" id="removeAvatarBtn">
                                <i class="bi bi-trash me-1"></i> Kaldır
                            </button>
                        </div>
                        <input type="file" id="avatarInput" name="avatar" accept="image/png,image/jpeg,image/webp" hidden onchange="previewAvatar(this)" data-validation-engine="validate[funcCall[FormValidation.rules.imageFile]]" data-max-size="2" data-accept="image/jpeg,image/png,image/webp">
                        @if($isEdit && $u->avatar)
                            <input type="hidden" name="remove_avatar" data-fv-ignore id="removeAvatarFlag" value="0">
                        @endif
                    </div>
                </div>
                @error('avatar')
                    <div class="text-neon-red fs-13 mt-2"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>
                @enderror
            </div>
        </div>


        <!-- ==================== SECTION 2: PERSONAL INFO ==================== -->
        <div class="card-dark mb-4" id="section-personal" data-aos="fade-up" data-aos-delay="50">
            <div class="card-header-custom">
                <div class="form-section-header mb-0">
                    <div class="form-section-icon"><i class="bi bi-person-fill"></i></div>
                    <div>
                        <h6 class="mb-0">Kişisel Bilgiler</h6>
                        <small class="text-muted">Kullanıcının temel kişisel bilgilerini girin</small>
                    </div>
                </div>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="stg-label">Ad <span class="text-neon-red">*</span></label>
                        <input type="text" class="stg-input @error('first_name') is-invalid @enderror" name="first_name" data-validation-engine="validate[required,custom[letters],maxSize[50]]" data-fv-mask="letters" id="firstName"
                               placeholder="Kullanıcının adı" value="{{ old('first_name', $u?->first_name) }}">
                        @error('first_name')
                            <div class="text-neon-red fs-13 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="stg-label">Soyad <span class="text-neon-red">*</span></label>
                        <input type="text" class="stg-input @error('last_name') is-invalid @enderror" name="last_name" data-validation-engine="validate[required,custom[letters],maxSize[50]]" data-fv-mask="letters" id="lastName"
                               placeholder="Kullanıcının soyadı" value="{{ old('last_name', $u?->last_name) }}">
                        @error('last_name')
                            <div class="text-neon-red fs-13 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="stg-label">E-posta Adresi <span class="text-neon-red">*</span></label>
                        <div class="stg-input-group">
                            <span class="stg-input-prefix"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="stg-input @error('email') is-invalid @enderror" name="email" data-validation-engine="validate[required,custom[email],maxSize[255]]" id="email"
                                   placeholder="ornek@mail.com" value="{{ old('email', $u?->email) }}">
                        </div>
                        @error('email')
                            <div class="text-neon-red fs-13 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="stg-label">Telefon</label>
                        <div class="stg-input-group">
                            <span class="stg-input-prefix"><i class="bi bi-telephone"></i></span>
                            <input type="tel" class="stg-input @error('phone') is-invalid @enderror" name="phone" data-validation-engine="validate[custom[phone],maxSize[20]]" id="phone"
                                   placeholder="+90 5XX XXX XX XX" value="{{ old('phone', $u?->phone) }}">
                        </div>
                        @error('phone')
                            <div class="text-neon-red fs-13 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="stg-label">Doğum Tarihi</label>
                        <input type="date" class="stg-input @error('birth_date') is-invalid @enderror" name="birth_date" id="birthDate"
                               value="{{ old('birth_date', $u?->birth_date?->format('Y-m-d')) }}" data-validation-engine="validate[custom[date],past[now]]">
                        @error('birth_date')
                            <div class="text-neon-red fs-13 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="stg-label">Cinsiyet</label>
                        <select class="stg-select @error('gender') is-invalid @enderror" name="gender" data-fv-ignore id="gender">
                            <option value="">Seçiniz</option>
                            @foreach(\App\Enums\Gender::cases() as $gender)
                                <option value="{{ $gender->value }}" {{ old('gender', $u?->gender?->value) === $gender->value ? 'selected' : '' }}>
                                    {{ $gender->label() }}
                                </option>
                            @endforeach
                        </select>
                        @error('gender')
                            <div class="text-neon-red fs-13 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="stg-label">Konum</label>
                        <div class="stg-input-group">
                            <span class="stg-input-prefix"><i class="bi bi-geo-alt"></i></span>
                            <input type="text" class="stg-input @error('location') is-invalid @enderror" name="location" data-validation-engine="validate[maxSize[100]]" id="location"
                                   placeholder="Şehir, Ülke" value="{{ old('location', $u?->location) }}">
                        </div>
                        @error('location')
                            <div class="text-neon-red fs-13 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="stg-label">Departman</label>
                        <select class="stg-select @error('department') is-invalid @enderror" name="department" data-fv-ignore id="department">
                            <option value="">Seçiniz</option>
                            @foreach(\App\Enums\Department::cases() as $dept)
                                <option value="{{ $dept->value }}" {{ old('department', $u?->department?->value) === $dept->value ? 'selected' : '' }}>
                                    {{ $dept->label() }}
                                </option>
                            @endforeach
                        </select>
                        @error('department')
                            <div class="text-neon-red fs-13 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12">
                        <label class="stg-label">Biyografi</label>
                        <textarea class="stg-textarea @error('bio') is-invalid @enderror" name="bio" data-validation-engine="validate[maxSize[500]]" id="bio" rows="3"
                                  placeholder="Kullanıcı hakkında kısa bilgi...">{{ old('bio', $u?->bio) }}</textarea>
                        <small class="text-muted">Maksimum 500 karakter</small>
                        @error('bio')
                            <div class="text-neon-red fs-13 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>


        <!-- ==================== SECTION 3: ACCOUNT INFO ==================== -->
        <div class="card-dark mb-4" id="section-account" data-aos="fade-up" data-aos-delay="50">
            <div class="card-header-custom">
                <div class="form-section-header mb-0">
                    <div class="form-section-icon"><i class="bi bi-key-fill"></i></div>
                    <div>
                        <h6 class="mb-0">Hesap Bilgileri</h6>
                        <small class="text-muted">Giriş ve güvenlik ile ilgili ayarlar</small>
                    </div>
                </div>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="stg-label">Şifre @if(!$isEdit)<span class="text-neon-red">*</span>@endif</label>
                        <div class="stg-input-group">
                            <span class="stg-input-prefix"><i class="bi bi-lock"></i></span>
                            <input type="password" class="stg-input @error('password') is-invalid @enderror" name="password" id="password"
                                   placeholder="Güçlü bir şifre girin"
                                   data-validation-engine="validate[{{ $isEdit ? '' : 'required,' }}minSize[8]]">
                            <button type="button" class="stg-input-prefix btn-unstyled" onclick="togglePassword('password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="uf-password-strength mt-2 d-none" id="passwordStrength">
                            <div class="uf-strength-bars">
                                <div class="uf-strength-bar"></div>
                                <div class="uf-strength-bar"></div>
                                <div class="uf-strength-bar"></div>
                                <div class="uf-strength-bar"></div>
                            </div>
                            <small id="strengthText">Şifre gücü</small>
                        </div>
                        @error('password')
                            <div class="text-neon-red fs-13 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="stg-label">Şifre Tekrar @if(!$isEdit)<span class="text-neon-red">*</span>@endif</label>
                        <div class="stg-input-group">
                            <span class="stg-input-prefix"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" class="stg-input" name="password_confirmation" id="passwordConfirm"
                                   placeholder="Şifreyi tekrar girin"
                                   data-validation-engine="validate[{{ $isEdit ? '' : 'required,' }}equals[password]]">
                            <button type="button" class="stg-input-prefix btn-unstyled" onclick="togglePassword('passwordConfirm', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    @if($isEdit)
                    <div class="col-12">
                        <div class="uf-info-box">
                            <i class="bi bi-info-circle-fill text-neon-blue"></i>
                            <span>Şifre alanlarını boş bırakırsanız mevcut şifre korunur. Yalnızca değiştirmek istiyorsanız doldurun.</span>
                        </div>
                    </div>
                    @endif
                    <div class="col-md-6">
                        <label class="stg-label">Durum <span class="text-neon-red">*</span></label>
                        <select class="stg-select @error('is_active') is-invalid @enderror" name="is_active" data-fv-ignore id="userStatus">
                            <option value="1" {{ old('is_active', $u?->is_active ?? 1) == 1 ? 'selected' : '' }}>Aktif</option>
                            <option value="0" {{ old('is_active', $u?->is_active ?? 1) == 0 ? 'selected' : '' }}>Pasif</option>
                        </select>
                        @error('is_active')
                            <div class="text-neon-red fs-13 mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>


        <!-- ==================== SECTION 4: ROLE ==================== -->
        <div class="card-dark mb-4" id="section-role" data-aos="fade-up" data-aos-delay="50">
            <div class="card-header-custom">
                <div class="form-section-header mb-0">
                    <div class="form-section-icon"><i class="bi bi-shield-fill-check"></i></div>
                    <div>
                        <h6 class="mb-0">Rol & Yetki</h6>
                        <small class="text-muted">Kullanıcının rolünü belirleyin</small>
                    </div>
                </div>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="stg-label">Roller</label>
                        @error('roles')
                            <div class="text-neon-red fs-13 mb-2"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>
                        @enderror
                        <div class="uf-role-cards">
                            @php
                                $userRoleIds = $isEdit ? $u->roles->pluck('id')->toArray() : [];
                                $oldRoles = old('roles', $userRoleIds);
                            @endphp
                            @foreach($roles as $role)
                                @php
                                    $iconMap = [
                                        'admin'     => 'bi-shield-fill',
                                        'editor'    => 'bi-pencil-fill',
                                        'moderator' => 'bi-shield-fill-check',
                                        'user'      => 'bi-person-fill',
                                        'viewer'    => 'bi-eye-fill',
                                    ];
                                    $accentMap = [
                                        'admin'     => 'accent-teal',
                                        'editor'    => 'accent-purple',
                                        'moderator' => 'accent-blue',
                                        'user'      => 'accent-green',
                                        'viewer'    => 'accent-pink',
                                    ];
                                    $icon = $iconMap[$role->slug] ?? 'bi-person-fill';
                                    $accent = $accentMap[$role->slug] ?? 'accent-teal';
                                    $isChecked = in_array($role->id, $oldRoles);
                                @endphp
                                <label class="uf-role-card {{ $isChecked ? 'active' : '' }}" data-role="{{ $role->slug }}">
                                    <input type="checkbox" name="roles[]" value="{{ $role->id }}" class="d-none uf-role-checkbox"
                                           {{ $isChecked ? 'checked' : '' }} data-fv-ignore>
                                    <div class="uf-role-card-icon {{ $accent }}"><i class="bi {{ $icon }}"></i></div>
                                    <div class="uf-role-card-info">
                                        <strong>{{ $role->name }}</strong>
                                        <small>{{ $role->description ?? '' }}</small>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- ==================== BOTTOM ACTIONS ==================== -->
        <div class="uf-bottom-actions" data-aos="fade-up">
            <div class="d-flex gap-2">
                <a href="{{ route('admin.users.index') }}" class="btn-glass"><i class="bi bi-x-lg me-1"></i> İptal</a>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn-teal">
                    <i class="bi bi-check2 me-1"></i>
                    {{ $isEdit ? 'Değişiklikleri Kaydet' : 'Kullanıcı Oluştur' }}
                </button>
            </div>
        </div>


    </div><!-- /stg-content -->
</div><!-- /stg-layout -->
