<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_id'      => Menu::factory(),
            'parent_id'    => null,
            'label'        => fake()->words(2, true),
            'icon'         => null,
            'link_type'    => 'url',
            'route_name'   => null,
            'route_params' => null,
            'url'          => '/' . fake()->slug(2),
            'target'       => '_self',
            'display_type' => 'link',
            'sort_order'   => fake()->numberBetween(0, 20),
            'is_active'    => true,
        ];
    }
}
