<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Timezones offered in the settings screen.
 *
 * A short curated list rather than the full IANA database, which would be
 * unusable in a dropdown. Add a case when a project needs another one.
 */
enum AppTimezone: string
{
    case EuropeIstanbul     = 'Europe/Istanbul';
    case EuropeLondon       = 'Europe/London';
    case EuropeBerlin       = 'Europe/Berlin';
    case EuropeMoscow       = 'Europe/Moscow';
    case AmericaNewYork     = 'America/New_York';
    case AmericaChicago     = 'America/Chicago';
    case AmericaDenver      = 'America/Denver';
    case AmericaLosAngeles  = 'America/Los_Angeles';
    case AsiaDubai          = 'Asia/Dubai';
    case AsiaKolkata        = 'Asia/Kolkata';
    case AsiaShanghai       = 'Asia/Shanghai';
    case AsiaTokyo          = 'Asia/Tokyo';
    case AustraliaSydney    = 'Australia/Sydney';
    case PacificAuckland    = 'Pacific/Auckland';

    /**
     * Identifier plus its current UTC offset, e.g. "Europe/Istanbul (UTC+3)".
     */
    public function label(): string
    {
        return $this->value . ' (' . $this->utcOffset() . ')';
    }

    /**
     * Read from PHP's timezone database so the offset follows daylight saving
     * instead of drifting out of date in a hardcoded string.
     */
    public function utcOffset(): string
    {
        $offset = (new \DateTimeZone($this->value))
            ->getOffset(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        $hours = intdiv(abs($offset), 3600);
        $minutes = intdiv(abs($offset) % 3600, 60);
        $sign = $offset < 0 ? '-' : '+';

        return $minutes === 0
            ? sprintf('UTC%s%d', $sign, $hours)
            : sprintf('UTC%s%d:%02d', $sign, $hours, $minutes);
    }

    public static function default(): self
    {
        return self::EuropeIstanbul;
    }
}
