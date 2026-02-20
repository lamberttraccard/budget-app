<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\MerchantRule;
use App\Models\Transaction;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_children_relationship(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $this->assertCount(1, $parent->children);
        $this->assertTrue($parent->children->first()->is($child));
    }

    public function test_category_parent_relationship(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $this->assertNotNull($child->parent);
        $this->assertTrue($child->parent->is($parent));
    }

    public function test_top_level_category_has_null_parent(): void
    {
        $category = Category::factory()->create(['parent_id' => null]);

        $this->assertNull($category->parent);
    }

    public function test_transaction_belongs_to_category(): void
    {
        $category = Category::factory()->create();
        $account = Account::factory()->create();
        $transaction = Transaction::factory()->create([
            'category_id' => $category->id,
            'account_id' => $account->id,
        ]);

        $this->assertTrue($transaction->category->is($category));
    }

    public function test_transaction_belongs_to_account(): void
    {
        $account = Account::factory()->create();
        $transaction = Transaction::factory()->create(['account_id' => $account->id]);

        $this->assertTrue($transaction->account->is($account));
    }

    public function test_transaction_belongs_to_import(): void
    {
        $import = Import::factory()->create();
        $account = Account::factory()->create();
        $transaction = Transaction::factory()->create([
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $this->assertTrue($transaction->import->is($import));
    }

    public function test_transaction_import_can_be_null(): void
    {
        $account = Account::factory()->create();
        $transaction = Transaction::factory()->create([
            'account_id' => $account->id,
            'import_id' => null,
        ]);

        $this->assertNull($transaction->import);
    }

    public function test_account_has_many_transactions(): void
    {
        $account = Account::factory()->create();
        Transaction::factory()->count(3)->create(['account_id' => $account->id]);

        $this->assertCount(3, $account->transactions);
    }

    public function test_category_has_many_transactions(): void
    {
        $category = Category::factory()->create();
        $account = Account::factory()->create();
        Transaction::factory()->count(2)->create([
            'category_id' => $category->id,
            'account_id' => $account->id,
        ]);

        $this->assertCount(2, $category->transactions);
    }

    public function test_import_has_many_transactions(): void
    {
        $import = Import::factory()->create();
        $account = Account::factory()->create();
        Transaction::factory()->count(4)->create([
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $this->assertCount(4, $import->transactions);
    }

    public function test_merchant_rule_belongs_to_category(): void
    {
        $category = Category::factory()->create();
        $rule = MerchantRule::factory()->create(['category_id' => $category->id]);

        $this->assertTrue($rule->category->is($category));
    }

    public function test_deleting_account_cascades_to_transactions(): void
    {
        $account = Account::factory()->create();
        Transaction::factory()->count(3)->create(['account_id' => $account->id]);

        $this->assertDatabaseCount('transactions', 3);

        $account->delete();

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_deleting_import_cascades_to_transactions(): void
    {
        $import = Import::factory()->create();
        $account = Account::factory()->create();
        Transaction::factory()->count(2)->create([
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $this->assertDatabaseCount('transactions', 2);

        $import->delete();

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_deleting_category_nullifies_transaction_category_id(): void
    {
        $category = Category::factory()->create();
        $account = Account::factory()->create();
        $transaction = Transaction::factory()->create([
            'category_id' => $category->id,
            'account_id' => $account->id,
        ]);

        $category->delete();

        $this->assertNull($transaction->fresh()->category_id);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'category_id' => null]);
    }
}
