<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Food',          'slug' => 'food',          'color' => '#A3E635'],
            ['name' => 'Restaurants',   'slug' => 'restaurants',   'color' => '#FBBF24'],
            ['name' => 'Transport',     'slug' => 'transport',     'color' => '#60A5FA'],
            ['name' => 'Housing',       'slug' => 'housing',       'color' => '#2DD4BF'],
            ['name' => 'Entertainment', 'slug' => 'entertainment', 'color' => '#C084FC'],
            ['name' => 'Shopping',      'slug' => 'shopping',      'color' => '#F472B6'],
            ['name' => 'Health',        'slug' => 'health',        'color' => '#34D399'],
            ['name' => 'Subscriptions', 'slug' => 'subscriptions', 'color' => '#FB7185'],
            ['name' => 'Education',     'slug' => 'education',     'color' => '#38BDF8'],
            ['name' => 'Travel',        'slug' => 'travel',        'color' => '#818CF8'],
            ['name' => 'Insurance',     'slug' => 'insurance',     'color' => '#94A3B8'],
            ['name' => 'Income',        'slug' => 'income',        'color' => '#10B981'],
            ['name' => 'Professional',  'slug' => 'professional',  'color' => '#EAB308'],
            ['name' => 'Other',         'slug' => 'other',         'color' => '#A1A1AA'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                array_merge($category, ['icon' => null, 'parent_id' => null])
            );
        }
    }
}
