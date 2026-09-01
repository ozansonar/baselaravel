@extends('layouts.admin')

@section('title', 'Profil Düzenle')

@section('content')

    <!-- ==================== COVER & PROFILE HEADER ==================== -->
    <div class="prf-cover" data-aos="fade-down" data-aos-duration="400">
        <div class="prf-cover-bg"></div>
        <div class="prf-cover-overlay"></div>
    </div>

    <div class="prf-header" data-aos="fade-up" data-aos-delay="100">
        <div class="prf-avatar-wrap">
            <div class="prf-avatar" id="avatarDisplay">
                @if($user->avatar)
                    <img src="{{ upload_url($user->avatar) }}" alt="{{ $user->full_name }}" class="prf-avatar-img">
                @else
                    <span>{{ mb_strtoupper(mb_substr($user->first_name, 0, 1) . mb_substr($user->last_name, 0, 1)) }}</span>
                @endif
                <div class="prf-avatar-status"></div>
            </div>
        </div>

        <div class="prf-header-info">
            <div class="prf-name-row">
                <div>
                    <h2>{{ $user->full_name }}</h2>
                    <p class="prf-role">
                        <i class="bi bi-shield-fill-check"></i>
                        {{ $user->roles->pluck('name')->implode(', ') ?: 'Kullanıcı' }}
                    </p>
                </div>
            </div>

            @if($user->bio)
                <p class="prf-bio">{{ $user->bio }}</p>
            @endif

            <div class="prf-meta">
                @if($user->location)
                    <span><i class="bi bi-geo-alt"></i> {{ $user->location }}</span>
                @endif
                <span><i class="bi bi-envelope"></i> {{ $user->email }}</span>
                @if($user->phone)
                    <span><i class="bi bi-phone"></i> {{ $user->phone }}</span>
                @endif
                <span><i class="bi bi-calendar3"></i> {{ $user->created_at->translatedFormat('F Y') }}'den beri üye</span>
            </div>
        </div>
    </div>

    <!-- ==================== EDIT FORM ==================== -->
    <form method="POST" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data" id="profileForm" data-validate novalidate>
        @csrf
        @method('PUT')

        <div class="row g-4 mt-2">
            <!-- Sol Kolon: Profil Bilgileri -->
            <div class="col-lg-8" data-aos="fade-up" data-aos-delay="150">
                <div class="prf-card">
                    <div class="prf-card-title"><i class="bi bi-pencil-square"></i> Profil Bilgileri</div>
                    <div class="prf-edit-form">
                        <div class="stg-row">
                            <div class="stg-field stg-half">
                                <label class="stg-label" for="first_name">Ad</label>
                                <input type="text" class="stg-input @error('first_name') is-invalid @enderror"
                                       id="first_name" name="first_name" data-validation-engine="validate[required,maxSize[100]]"
                                       value="{{ old('first_name', $user->first_name) }}">
                                @error('first_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="stg-field stg-half">
                                <label class="stg-label" for="last_name">Soyad</label>
                                <input type="text" class="stg-input @error('last_name') is-invalid @enderror"
                                       id="last_name" name="last_name" data-validation-engine="validate[required,maxSize[100]]"
                                       value="{{ old('last_name', $user->last_name) }}">
                                @error('last_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="stg-field">
                            <label class="stg-label" for="email">E-posta</label>
                            <input type="text" class="stg-input @error('email') is-invalid @enderror"
                                   id="email" name="email" data-validation-engine="validate[required,custom[email],maxSize[191]]"
                                   value="{{ old('email', $user->email) }}">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="stg-field">
                            <label class="stg-label" for="phone">Telefon</label>
                            <input type="text" class="stg-input @error('phone') is-invalid @enderror"
                                   id="phone" name="phone" data-validation-engine="validate[custom[phone],maxSize[20]]"
                                   value="{{ old('phone', $user->phone) }}">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="stg-field">
                            <label class="stg-label" for="bio">Biyografi</label>
                            <textarea class="stg-textarea @error('bio') is-invalid @enderror"
                                      id="bio" name="bio" data-validation-engine="validate[maxSize[1000]]" rows="3">{{ old('bio', $user->bio) }}</textarea>
                            @error('bio')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="stg-field">
                            <label class="stg-label" for="location">Konum</label>
                            <input type="text" class="stg-input @error('location') is-invalid @enderror"
                                   id="location" name="location" data-validation-engine="validate[maxSize[100]]"
                                   value="{{ old('location', $user->location) }}"
                                   placeholder="Örn: İstanbul, Türkiye">
                            @error('location')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Şifre Değiştirme -->
                <div class="prf-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="prf-card-title"><i class="bi bi-shield-lock"></i> Şifre Değiştir</div>
                    <div class="prf-edit-form">
                        <div class="stg-row">
                            <div class="stg-field">
                                <label class="stg-label" for="current_password">Mevcut Şifre</label>
                                <input type="password" class="stg-input @error('current_password') is-invalid @enderror"
                                       id="current_password" name="current_password"
                                       data-validation-engine="validate[condRequired[password]]"
                                       placeholder="Şifrenizi değiştirmek için gerekli"
                                       autocomplete="current-password">
                                @error('current_password')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="stg-row">
                            <div class="stg-field stg-half">
                                <label class="stg-label" for="password">Yeni Şifre</label>
                                <input type="password" class="stg-input @error('password') is-invalid @enderror"
                                       id="password" name="password" data-validation-engine="validate[minSize[8]]"
                                       placeholder="Değiştirmek istemiyorsanız boş bırakın"
                                       autocomplete="new-password">
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="stg-hint">En az 8 karakter</div>
                            </div>
                            <div class="stg-field stg-half">
                                <label class="stg-label" for="password_confirmation">Şifre Tekrarı</label>
                                <input type="password" class="stg-input"
                                       id="password_confirmation" name="password_confirmation" data-validation-engine="validate[condRequired[password],equals[password]]"
                                       placeholder="Yeni şifreyi tekrar giriniz"
                                       autocomplete="new-password">
                            </div>
                        </div>
                        <div id="passwordStrength" class="d-none">
                            <div class="d-flex align-items-center gap-2 mt-1">
                                <div class="prg-bar prg-sm flex-grow-1">
                                    <div class="prg-fill" id="strengthBar"></div>
                                </div>
                                <small id="strengthText" class="text-muted"></small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2" data-aos="fade-up" data-aos-delay="250">
                    <button type="submit" class="stg-save-btn" id="saveBtn">
                        <i class="bi bi-check-lg"></i> Değişiklikleri Kaydet
                    </button>
                    <a href="{{ route('admin.dashboard') }}" class="prf-btn prf-btn-ghost">İptal</a>
                </div>
            </div>

            <!-- Sağ Kolon: Avatar -->
            <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                <div class="prf-card">
                    <div class="prf-card-title"><i class="bi bi-person-bounding-box"></i> Profil Fotoğrafı</div>
                    <div class="prf-edit-form">
                        <div class="prf-avatar-upload-area" id="avatarUploadArea">
                            <div class="prf-avatar-preview" id="avatarPreview">
                                @if($user->avatar)
                                    <img src="{{ upload_url($user->avatar) }}" alt="{{ $user->full_name }}" id="avatarImg">
                                @else
                                    <div class="prf-avatar-placeholder" id="avatarPlaceholder">
                                        <i class="bi bi-camera"></i>
                                        <span>Fotoğraf Yükle</span>
                                    </div>
                                @endif
                            </div>
                            <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/webp" class="d-none"
                                   data-validation-engine="validate[funcCall[FormValidation.rules.imageFile]]"
                                   data-max-size="1" data-accept="image/jpeg,image/png,image/webp">
                            <button type="button" class="prf-btn prf-btn-primary w-100 mt-3" data-click-target="avatarInput">
                                <i class="bi bi-upload"></i> Fotoğraf Seç
                            </button>
                            @if($user->avatar)
                                <label class="d-flex align-items-center gap-2 mt-2" id="removeAvatarLabel">
                                    <input type="checkbox" name="remove_avatar" data-fv-ignore value="1" id="removeAvatarCheck">
                                    <small class="text-muted">Mevcut fotoğrafı kaldır</small>
                                </label>
                            @endif
                            @error('avatar')
                                <div class="text-danger mt-2 fld-error-text">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="stg-hint mt-2">JPG, PNG veya WebP. Maksimum 1 MB.</div>
                    </div>
                </div>

                <!-- Hesap Bilgileri -->
                <div class="prf-card" data-aos="fade-up" data-aos-delay="250">
                    <div class="prf-card-title"><i class="bi bi-info-circle"></i> Hesap Bilgileri</div>
                    <div class="prf-about-list">
                        <div class="prf-about-item">
                            <i class="bi bi-person-badge"></i>
                            <div>
                                <small>Rol</small>
                                <span>{{ $user->roles->pluck('name')->implode(', ') ?: 'Kullanıcı' }}</span>
                            </div>
                        </div>
                        <div class="prf-about-item">
                            <i class="bi bi-calendar-check"></i>
                            <div>
                                <small>Kayıt Tarihi</small>
                                <span>{{ $user->created_at->translatedFormat('d F Y') }}</span>
                            </div>
                        </div>
                        <div class="prf-about-item">
                            <i class="bi bi-clock-history"></i>
                            <div>
                                <small>Son Güncelleme</small>
                                <span>{{ $user->updated_at->translatedFormat('d F Y, H:i') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/admin/js/profile.js') }}"></script>
@endpush
