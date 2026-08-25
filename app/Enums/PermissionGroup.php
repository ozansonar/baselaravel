<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Sections the permission matrix is grouped by in the roles screen.
 */
enum PermissionGroup: string
{
    case Content       = 'content';
    case Media         = 'media';
    case Communication = 'communication';
    case System        = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Content       => 'İçerik',
            self::Media         => 'Medya & Dosya',
            self::Communication => 'İletişim',
            self::System        => 'Sistem',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Content       => 'bi-file-earmark-text-fill',
            self::Media         => 'bi-folder-fill',
            self::Communication => 'bi-chat-dots-fill',
            self::System        => 'bi-gear-fill',
        };
    }
}
