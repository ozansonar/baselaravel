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

    /**
     * Check if role has admin panel access.
     */
    public function hasAdminAccess(): bool
    {
        return in_array($this, [self::Admin, self::Editor, self::Moderator], true);
    }
}
