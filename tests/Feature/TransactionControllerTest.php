<?php

namespace Tests\Feature;

use App\Events\CategoryCorrected;
use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
    }

    public function test_can_update_transaction_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $oldCategory = Category::where('slug', 'food')->first();
        $newCategory = Category::where('slug', 'restaurants')->first();
        $import = Import::factory()->completed()->create();

        $transaction = Transaction::factory()->create([
            'category_id' => $oldCategory->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'date' => now(),
            'amount_cents' => -5000,
        ]);

        $response = $this->actingAs($user)
            ->patch("/transactions/{$transaction->id}", [
                'category_id' => $newCategory->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'category_id' => $newCategory->id,
        ]);
    }

    public function test_update_requires_valid_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $category = Category::where('slug', 'food')->first();

        $transaction = Transaction::factory()->create([
            'category_id' => $category->id,
            'account_id' => $account->id,
        ]);

        $response = $this->actingAs($user)
            ->patch("/transactions/{$transaction->id}", [
                'category_id' => 99999,
            ]);

        $response->assertSessionHasErrors('category_id');
    }

    public function test_update_requires_authentication(): void
    {
        $account = Account::factory()->personal()->create();
        $category = Category::where('slug', 'food')->first();

        $transaction = Transaction::factory()->create([
            'category_id' => $category->id,
            'account_id' => $account->id,
        ]);

        $response = $this->patch("/transactions/{$transaction->id}", [
            'category_id' => $category->id,
        ]);

        $response->assertRedirect('/login');
    }

    public function test_update_nonexistent_transaction_returns_404(): void
    {
        $user = User::factory()->create();
        $category = Category::where('slug', 'food')->first();

        $response = $this->actingAs($user)
            ->patch('/transactions/99999', [
                'category_id' => $category->id,
            ]);

        $response->assertNotFound();
    }

    public function test_can_re_correct_transaction_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $food = Category::where('slug', 'food')->first();
        $restaurants = Category::where('slug', 'restaurants')->first();
        $transport = Category::where('slug', 'transport')->first();

        $transaction = Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $account->id,
        ]);

        // First correction
        $this->actingAs($user)
            ->patch("/transactions/{$transaction->id}", ['category_id' => $restaurants->id])
            ->assertRedirect();

        // Second correction (re-correct)
        $this->actingAs($user)
            ->patch("/transactions/{$transaction->id}", ['category_id' => $transport->id])
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'category_id' => $transport->id,
        ]);
    }

    public function test_update_returns_redirect_back(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $category = Category::where('slug', 'food')->first();
        $newCategory = Category::where('slug', 'transport')->first();

        $transaction = Transaction::factory()->create([
            'category_id' => $category->id,
            'account_id' => $account->id,
        ]);

        $response = $this->actingAs($user)
            ->from("/categories/{$category->id}")
            ->patch("/transactions/{$transaction->id}", [
                'category_id' => $newCategory->id,
            ]);

        $response->assertRedirect("/categories/{$category->id}");
    }

    public function test_category_id_is_required(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $category = Category::where('slug', 'food')->first();

        $transaction = Transaction::factory()->create([
            'category_id' => $category->id,
            'account_id' => $account->id,
        ]);

        $response = $this->actingAs($user)
            ->patch("/transactions/{$transaction->id}", []);

        $response->assertSessionHasErrors('category_id');
    }

    public function test_category_corrected_event_dispatches_on_update(): void
    {
        Event::fake([CategoryCorrected::class]);

        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $food = Category::where('slug', 'food')->first();
        $restaurants = Category::where('slug', 'restaurants')->first();

        $transaction = Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $account->id,
            'description' => 'CARREFOUR CITY PARIS',
        ]);

        $this->actingAs($user)
            ->patch("/transactions/{$transaction->id}", [
                'category_id' => $restaurants->id,
            ]);

        Event::assertDispatched(CategoryCorrected::class, function ($event) use ($transaction, $food, $restaurants) {
            return $event->transaction->id === $transaction->id
                && $event->oldCategoryId === $food->id
                && $event->newCategoryId === $restaurants->id;
        });
    }

    public function test_category_corrected_event_not_dispatched_when_same_category(): void
    {
        Event::fake([CategoryCorrected::class]);

        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $food = Category::where('slug', 'food')->first();

        $transaction = Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $account->id,
        ]);

        $this->actingAs($user)
            ->patch("/transactions/{$transaction->id}", [
                'category_id' => $food->id,
            ]);

        Event::assertNotDispatched(CategoryCorrected::class);
    }
}
