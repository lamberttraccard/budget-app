<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Food',          'slug' => 'food',          'color' => '#A3E635', 'icon' => 'apple'],
            ['name' => 'Restaurants',   'slug' => 'restaurants',   'color' => '#FBBF24', 'icon' => 'utensils'],
            ['name' => 'Transport',     'slug' => 'transport',     'color' => '#60A5FA', 'icon' => 'car'],
            ['name' => 'Housing',       'slug' => 'housing',       'color' => '#2DD4BF', 'icon' => 'house'],
            ['name' => 'Entertainment', 'slug' => 'entertainment', 'color' => '#C084FC', 'icon' => 'film'],
            ['name' => 'Shopping',      'slug' => 'shopping',      'color' => '#F472B6', 'icon' => 'shopping-bag'],
            ['name' => 'Health',        'slug' => 'health',        'color' => '#34D399', 'icon' => 'heart-pulse'],
            ['name' => 'Subscriptions', 'slug' => 'subscriptions', 'color' => '#FB7185', 'icon' => 'credit-card'],
            ['name' => 'Education',     'slug' => 'education',     'color' => '#38BDF8', 'icon' => 'graduation-cap'],
            ['name' => 'Travel',        'slug' => 'travel',        'color' => '#818CF8', 'icon' => 'plane'],
            ['name' => 'Insurance',     'slug' => 'insurance',     'color' => '#94A3B8', 'icon' => 'shield'],
            ['name' => 'Income',        'slug' => 'income',        'color' => '#10B981', 'icon' => 'wallet'],
            ['name' => 'Professional',  'slug' => 'professional',  'color' => '#EAB308', 'icon' => 'briefcase'],
            ['name' => 'Other',         'slug' => 'other',         'color' => '#A1A1AA', 'icon' => 'ellipsis'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                array_merge($category, ['parent_id' => null])
            );
        }
    }
}
