<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Moderator = 'moderator';
    case User = 'user';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin     => 'Yönetici',
            self::Editor    => 'Editör',
            self::Moderator => 'Moderatör',
            self::User      => 'Kullanıcı',
            self::Viewer    => 'İzleyici',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Admin     => 'danger',
            self::Editor    => 'warning',
            self::Moderator => 'info',
            self::User      => 'success',
            self::Viewer    => 'secondary',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin     => 'Tam yetkili sistem yöneticisi',
            self::Editor    => 'İçerik yönetimi yetkisi',
            self::Moderator => 'Mesaj ve yorum yönetimi yetkisi',
            self::User      => 'Kayıtlı site kullanıcısı',
            self::Viewer    => 'Sadece görüntüleme yetkisi',
        };
    }

    /**
     * Check if role has admin panel access.
     */
    public function hasAdminAccess(): bool
    {
        return in_array($this, [self::Admin, self::Editor, self::Moderator], true);
    }

    /**
     * Slugs of the roles AdminMiddleware lets into the panel.
     *
     * @return array<int, string>
     */
    public static function adminPanelSlugs(): array
    {
        return array_values(array_map(
            static fn (self $role): string => $role->value,
            array_filter(self::cases(), static fn (self $role): bool => $role->hasAdminAccess()),
        ));
    }
}
