<?php

declare(strict_types=1);

namespace App\Enums;

enum AiLogSource: string
{
    case ProductFill    = 'product_fill';
    case BlogFill       = 'blog_fill';
    case BlogScheduled  = 'blog_scheduled';
    case PageFill       = 'page_fill';
    case CityFill       = 'city_fill';
    case TopicCluster   = 'topic_cluster';

    public function label(): string
    {
        return match ($this) {
            self::ProductFill   => 'Ürün Doldurma',
            self::BlogFill      => 'Blog Doldurma',
            self::BlogScheduled => 'Otomatik Blog',
            self::PageFill      => 'Sayfa Doldurma',
            self::CityFill      => 'Şehir Sayfası Doldurma',
            self::TopicCluster  => 'Topic Cluster Üretimi',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::ProductFill   => 'ord-status-pending',
            self::BlogFill      => 'ord-status-sent',
            self::BlogScheduled => 'ord-status-processing',
            self::PageFill      => 'ord-status-pending',
            self::CityFill      => 'ord-status-sent',
            self::TopicCluster  => 'ord-status-processing',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ProductFill   => 'bi-box-seam',
            self::BlogFill      => 'bi-pencil-square',
            self::BlogScheduled => 'bi-clock-history',
            self::PageFill      => 'bi-file-earmark-text',
            self::CityFill      => 'bi-geo-alt',
            self::TopicCluster  => 'bi-diagram-3',
        };
    }
}
