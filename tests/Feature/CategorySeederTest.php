<?php

namespace Tests\Feature;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_all_categories(): void
    {
        $this->seed(CategorySeeder::class);

        $this->assertSame(13, Category::whereNull('parent_id')->count());
        $this->assertSame(8, Category::whereNotNull('parent_id')->count());
        $this->assertDatabaseCount('categories', 21);
    }

    public function test_all_top_level_categories_have_null_parent(): void
    {
        $this->seed(CategorySeeder::class);

        $this->assertSame(13, Category::whereNull('parent_id')->count());
    }

    public function test_food_category_has_correct_color(): void
    {
        $this->seed(CategorySeeder::class);

        $food = Category::where('slug', 'food')->first();

        $this->assertNotNull($food);
        $this->assertSame('#A3E635', $food->color);
        $this->assertSame('Food', $food->name);
    }

    public function test_other_category_has_correct_color(): void
    {
        $this->seed(CategorySeeder::class);

        $other = Category::where('slug', 'other')->first();

        $this->assertNotNull($other);
        $this->assertSame('#A1A1AA', $other->color);
    }

    public function test_income_category_has_correct_color(): void
    {
        $this->seed(CategorySeeder::class);

        $income = Category::where('slug', 'income')->first();

        $this->assertNotNull($income);
        $this->assertSame('#10B981', $income->color);
    }

    public function test_all_expected_slugs_exist(): void
    {
        $this->seed(CategorySeeder::class);

        $expectedSlugs = [
            'food', 'restaurants', 'transport', 'housing', 'entertainment',
            'shopping', 'health', 'education', 'travel',
            'insurance', 'income', 'professional', 'other',
        ];

        foreach ($expectedSlugs as $slug) {
            $this->assertDatabaseHas('categories', ['slug' => $slug]);
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CategorySeeder::class);

        $this->assertDatabaseCount('categories', 21);
    }

    public function test_subcategories_have_correct_parent(): void
    {
        $this->seed(CategorySeeder::class);

        $transport = Category::where('slug', 'transport')->first();
        $fuel = Category::where('slug', 'transport-fuel')->first();

        $this->assertNotNull($fuel);
        $this->assertSame($transport->id, $fuel->parent_id);
    }

    public function test_subcategories_inherit_parent_color(): void
    {
        $this->seed(CategorySeeder::class);

        $transport = Category::where('slug', 'transport')->first();
        $fuel = Category::where('slug', 'transport-fuel')->first();

        $this->assertNotNull($fuel);
        $this->assertSame($transport->color, $fuel->color);
    }

    public function test_subcategories_have_null_icon(): void
    {
        $this->seed(CategorySeeder::class);

        $subcategoriesWithIcons = Category::whereNotNull('parent_id')
            ->whereNotNull('icon')
            ->count();

        $this->assertSame(0, $subcategoriesWithIcons);
    }

    public function test_food_category_has_correct_icon(): void
    {
        $this->seed(CategorySeeder::class);

        $food = Category::where('slug', 'food')->first();

        $this->assertNotNull($food);
        $this->assertSame('apple', $food->icon);
    }

    public function test_all_categories_have_icons(): void
    {
        $this->seed(CategorySeeder::class);

        $categoriesWithoutIcons = Category::whereNull('parent_id')
            ->whereNull('icon')
            ->count();

        $this->assertSame(0, $categoriesWithoutIcons);
    }
}
