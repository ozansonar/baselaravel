<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Department;
use App\Enums\PermissionKey;
use App\Enums\Gender;
use App\Mail\ResetPasswordMail;
use App\Mail\VerifyEmailMail;
use App\Services\MailService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'birth_date',
        'gender',
        'location',
        'department',
        'bio',
        'avatar',
        'is_active',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'gender' => Gender::class,
            'department' => Department::class,
            'password' => 'hashed',
            'is_active' => 'boolean',
            // Şifreli sütunlar: veritabanı dökümü ele geçse bile anahtar da
            // kurtarma kodları da uygulama anahtarı olmadan okunamıyor.
            'two_factor_secret'         => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at'   => 'datetime',
        ];
    }

    // ── Accessors ──

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    // ── Relationships ──

    /**
     * @return BelongsToMany<Role, $this>
     */
    /** canAccessPanel() için istek içi hatırlatıcı; sütun değil. */
    private ?bool $panelAccess = null;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * @return HasMany<AdminNotification, $this>
     */
    public function adminNotifications(): HasMany
    {
        return $this->hasMany(AdminNotification::class);
    }

    // ── Helpers ──

    /**
     * İki adımlı doğrulama kurulmuş ve doğrulanmış mı?
     *
     * Yalnız anahtarın varlığına bakmak yetmiyor: kurulumu yarıda bırakan
     * kişide anahtar var ama çalışan bir kimlik doğrulayıcı yok, ve ondan kod
     * istemek onu hesabından kilitlerdi.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Reads through the roles relation so repeated authorization checks within
     * a single request hit the database once instead of once per call.
     */
    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    /**
     * @param array<int, string> $slugs
     */
    public function hasAnyRole(array $slugs): bool
    {
        return $this->roles->whereIn('slug', $slugs)->isNotEmpty();
    }

    /**
     * True when any of the user's roles carries the given permission.
     *
     * Reads through the relations so a request that runs many authorization
     * checks still loads roles and permissions once.
     */
    public function hasPermission(PermissionKey|string $permission): bool
    {
        $key = $permission instanceof PermissionKey ? $permission->value : $permission;

        return $this->roles
            ->loadMissing('permissions')
            ->contains(fn (Role $role): bool => $role->permissions->contains('key', $key));
    }

    /**
     * Kullanıcının panele girebilecek bir rolü var mı?
     *
     * Aynı soru ön yüzde üç yerde soruluyordu (başlıktaki menü iki kez,
     * düzendeki analitik koşulu bir kez) ve her biri ayrı bir exists sorgusu
     * atıyordu. Cevap istek boyunca değişmiyor; ilk sorguda saklanıyor.
     */
    public function canAccessPanel(): bool
    {
        return $this->panelAccess ??= $this->roles()->whereHas('permissions')->exists();
    }

    /**
     * Send the verification link through the project's own mail pipeline so it
     * is logged and uses the editable template like every other mail.
     */
    public function sendEmailVerificationNotification(): void
    {
        $verificationUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())],
        );

        try {
            app(MailService::class)->queue($this->email, new VerifyEmailMail($this, $verificationUrl));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Doğrulama maili kuyruğa eklenemedi', [
                'user_id' => $this->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send the password reset notification using custom mail template.
     */
    public function sendPasswordResetNotification($token): void
    {
        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $this->email,
        ], false));

        try {
            app(MailService::class)->queue($this->email, new ResetPasswordMail($resetUrl));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Şifre sıfırlama maili kuyruğa eklenemedi', [
                'user_id' => $this->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
