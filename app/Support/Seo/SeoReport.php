<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Enums\SeoLevel;

/**
 * Bir içeriğin denetim sonucu.
 *
 * Bulguların yanında bir de skor taşıyor. Skor bir not değil, bir **sıralama
 * aracı**: yüz içerikten hangisine önce bakılacağını söylüyor. Bu ayrım
 * önemli — not olsaydı 100 hedeflenir ve kurallar not almak için esnetilirdi.
 */
final readonly class SeoReport
{
    /**
     * @param list<SeoIssue> $issues Seviyeye göre sıralı
     */
    private function __construct(
        public array $issues,
        public int $score,
    ) {}

    /**
     * @param list<SeoIssue> $issues
     */
    public static function fromIssues(array $issues): self
    {
        usort(
            $issues,
            static fn (SeoIssue $a, SeoIssue $b): int => [$a->level->weight(), $a->code]
                <=> [$b->level->weight(), $b->code],
        );

        $penalty = array_sum(array_map(
            static fn (SeoIssue $issue): int => $issue->level->penalty(),
            $issues,
        ));

        return new self($issues, max(0, 100 - $penalty));
    }

    /** @return list<SeoIssue> */
    public function ofLevel(SeoLevel $level): array
    {
        return array_values(array_filter(
            $this->issues,
            static fn (SeoIssue $issue): bool => $issue->level === $level,
        ));
    }

    public function count(SeoLevel $level): int
    {
        return count($this->ofLevel($level));
    }

    public function isClean(): bool
    {
        return $this->issues === [];
    }

    /**
     * Skorun okunabilir karşılığı — listede renk ve sıralama için.
     */
    public function grade(): string
    {
        return match (true) {
            $this->score >= 90 => 'good',
            $this->score >= 70 => 'fair',
            default            => 'poor',
        };
    }

    /**
     * @return array{score: int, grade: string, counts: array<string, int>, issues: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'score'  => $this->score,
            'grade'  => $this->grade(),
            'counts' => [
                SeoLevel::Error->value   => $this->count(SeoLevel::Error),
                SeoLevel::Warning->value => $this->count(SeoLevel::Warning),
                SeoLevel::Info->value    => $this->count(SeoLevel::Info),
            ],
            'issues' => array_map(
                static fn (SeoIssue $issue): array => $issue->toArray(),
                $this->issues,
            ),
        ];
    }
}
