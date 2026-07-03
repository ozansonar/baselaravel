<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ContentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class UpdatePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'title'            => ['required', 'string', 'max:255'],
            'slug'             => ['nullable', 'string', 'max:255', 'unique:pages,slug,' . $this->route('page')?->id],
            'content'          => ['required', 'string', 'max:100000'],
            'excerpt'          => ['nullable', 'string', 'max:500'],
            'image'            => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status'           => ['nullable', new Enum(ContentStatus::class)],
            'sort_order'       => ['nullable', 'integer', 'min:0'],
            'meta_title'       => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'published_at'     => ['nullable', 'date'],
        ];

        // Sections validation (for structured pages like "hakkimizda")
        if ($this->has('sections')) {
            $rules = array_merge($rules, [
                // Story
                'sections.story.tag'             => ['nullable', 'string', 'max:100'],
                'sections.story.title'           => ['nullable', 'string', 'max:255'],
                'sections.story.paragraphs'      => ['nullable', 'string', 'max:5000'],
                'sections.story.highlight'       => ['nullable', 'string', 'max:500'],
                'sections.story.experience_year' => ['nullable', 'string', 'max:20'],
                'sections.story.closing'         => ['nullable', 'string', 'max:1000'],

                // Values
                'sections.values'               => ['nullable', 'array', 'max:8'],
                'sections.values.*.icon'        => ['required_with:sections.values', 'string', 'max:100'],
                'sections.values.*.title'       => ['required_with:sections.values', 'string', 'max:100'],
                'sections.values.*.description' => ['required_with:sections.values', 'string', 'max:500'],

                // Timeline
                'sections.timeline'               => ['nullable', 'array', 'max:20'],
                'sections.timeline.*.year'        => ['required_with:sections.timeline', 'string', 'max:10'],
                'sections.timeline.*.title'       => ['required_with:sections.timeline', 'string', 'max:100'],
                'sections.timeline.*.description' => ['required_with:sections.timeline', 'string', 'max:500'],

                // Stats
                'sections.stats'              => ['nullable', 'array', 'max:8'],
                'sections.stats.*.number'     => ['required_with:sections.stats', 'integer', 'min:0'],
                'sections.stats.*.suffix'     => ['nullable', 'string', 'max:10'],
                'sections.stats.*.label'      => ['required_with:sections.stats', 'string', 'max:100'],
                'sections.stats.*.format'     => ['nullable', 'string', 'in:,K'],

                // Team
                'sections.team'                => ['nullable', 'array', 'max:12'],
                'sections.team.*.name'         => ['required_with:sections.team', 'string', 'max:100'],
                'sections.team.*.role'         => ['required_with:sections.team', 'string', 'max:100'],
                'sections.team.*.description'  => ['nullable', 'string', 'max:500'],
                'sections.team.*.photo_file'   => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

                // CTA
                'sections.cta.title'       => ['nullable', 'string', 'max:255'],
                'sections.cta.description' => ['nullable', 'string', 'max:500'],
            ]);
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required'       => 'Sayfa başlığı zorunludur.',
            'title.max'            => 'Sayfa başlığı en fazla 255 karakter olabilir.',
            'content.required'     => 'Sayfa içeriği zorunludur.',
            'image.image'          => 'Dosya bir görsel olmalıdır.',
            'image.mimes'          => 'Görsel JPG, PNG veya WebP formatında olmalıdır.',
            'image.max'            => 'Görsel en fazla 2 MB olabilir.',
            'meta_title.max'       => 'Meta başlık en fazla 70 karakter olabilir.',
            'meta_description.max' => 'Meta açıklama en fazla 160 karakter olabilir.',
        ];
    }
}
