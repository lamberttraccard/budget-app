<?php

namespace Tests\Feature;

use App\Events\ImportCompleted;
use App\Jobs\CategorizeTransactionChunk;
use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\MerchantRule;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function bpCsvContent(): string
    {
        return mb_convert_encoding(
            "Compte;Date de comptabilisation;Date opération;Libellé;Référence;Date valeur;Montant\r\n"
            ."00012345678;01/02/2026;01/02/2026;CARREFOUR CITY;REF001;01/02/2026;-42,50\r\n"
            ."00012345678;05/02/2026;05/02/2026;SNCF INTERNET;REF002;05/02/2026;-85,00\r\n"
            .'00012345678;10/02/2026;10/02/2026;VIREMENT SALAIRE;REF003;10/02/2026;3200,00',
            'ISO-8859-15',
            'UTF-8',
        );
    }

    private function revolutCsvContent(): string
    {
        return "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n"
            ."CARD_PAYMENT,Current,2026-02-01 10:00:00,2026-02-01 10:00:00,Amazon,-25.99,0.00,EUR,COMPLETED,1000.00\n"
            .'CARD_PAYMENT,Current,2026-02-15 14:30:00,2026-02-15 14:30:00,Netflix,-13.99,0.00,EUR,COMPLETED,986.01';
    }

    public function test_preview_with_valid_bp_csv_flashes_import_preview(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());

        $response = $this->actingAs($user)
            ->post(route('imports.preview'), ['csv_file' => $file]);

        $response->assertRedirect();
        $response->assertSessionHas('importPreview', function (array $preview) {
            return $preview['bank'] === 'Banque Populaire'
                && $preview['transactionCount'] === 3
                && $preview['dateFrom'] === '2026-02-01'
                && $preview['dateTo'] === '2026-02-10'
                && $preview['fileName'] === 'releve.csv';
        });
    }

    public function test_preview_with_valid_revolut_csv_returns_correct_bank_name(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('revolut.csv', $this->revolutCsvContent());

        $response = $this->actingAs($user)
            ->post(route('imports.preview'), ['csv_file' => $file]);

        $response->assertRedirect();
        $response->assertSessionHas('importPreview', function (array $preview) {
            return $preview['bank'] === 'Revolut'
                && $preview['transactionCount'] === 2;
        });
    }

    public function test_preview_with_unrecognized_csv_returns_validation_error(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('random.csv', "col1,col2,col3\nfoo,bar,baz");

        $response = $this->actingAs($user)
            ->post(route('imports.preview'), ['csv_file' => $file]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['csv_file']);
    }

    public function test_store_with_new_account_creates_import_and_transactions(): void
    {
        $this->seed(CategorySeeder::class);
        Event::fake([ImportCompleted::class]);
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());

        $response = $this->actingAs($user)
            ->post(route('imports.store'), [
                'csv_file' => $file,
                'new_account_name' => 'BP Personal',
            ]);

        $response->assertRedirect(route('home'));

        $this->assertDatabaseHas('imports', [
            'file_name' => 'releve.csv',
            'bank' => 'Banque Populaire',
            'status' => 'completed',
        ]);

        $this->assertDatabaseCount('transactions', 3);

        $this->assertDatabaseHas('accounts', [
            'bank' => 'Banque Populaire',
            'name' => 'BP Personal',
        ]);
    }

    public function test_store_with_existing_account(): void
    {
        $this->seed(CategorySeeder::class);
        Event::fake([ImportCompleted::class]);
        $user = User::factory()->create();

        $existingAccount = Account::create([
            'bank' => 'Banque Populaire',
            'name' => 'My BP Account',
        ]);

        $file = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());

        $this->actingAs($user)
            ->post(route('imports.store'), [
                'csv_file' => $file,
                'account_id' => $existingAccount->id,
            ]);

        // Should not create a new account
        $this->assertDatabaseCount('accounts', 1);

        // Transactions should be linked to the existing account
        $this->assertTrue(
            $existingAccount->transactions()->exists(),
            'Transactions should be linked to the existing account',
        );
    }

    public function test_store_with_unrecognized_csv_returns_validation_error(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create();
        $file = UploadedFile::fake()->createWithContent('random.csv', "col1,col2,col3\nfoo,bar,baz");

        $response = $this->actingAs($user)
            ->post(route('imports.store'), ['csv_file' => $file, 'account_id' => $account->id]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['csv_file']);
    }

    public function test_preview_requires_authentication(): void
    {
        $file = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());

        $this->post(route('imports.preview'), ['csv_file' => $file])
            ->assertRedirect(route('login'));
    }

    public function test_store_requires_authentication(): void
    {
        $file = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());

        $this->post(route('imports.store'), ['csv_file' => $file])
            ->assertRedirect(route('login'));
    }

    public function test_store_fires_import_completed_event(): void
    {
        $this->seed(CategorySeeder::class);
        Event::fake([ImportCompleted::class]);
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());

        $this->actingAs($user)
            ->post(route('imports.store'), [
                'csv_file' => $file,
                'new_account_name' => 'BP Personal',
            ]);

        Event::assertDispatched(ImportCompleted::class);
    }

    public function test_store_creates_new_account_with_detected_bank(): void
    {
        $this->seed(CategorySeeder::class);
        Event::fake([ImportCompleted::class]);
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());

        $this->actingAs($user)
            ->post(route('imports.store'), [
                'csv_file' => $file,
                'new_account_name' => 'BP Pro',
            ]);

        $this->assertDatabaseHas('accounts', [
            'bank' => 'Banque Populaire',
            'name' => 'BP Pro',
        ]);
    }

    public function test_store_without_account_returns_validation_error(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());

        $response = $this->actingAs($user)
            ->post(route('imports.store'), ['csv_file' => $file]);

        $response->assertRedirect();
        $response->assertSessionHasErrors();
    }

    public function test_store_with_nonexistent_account_id_returns_validation_error(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());

        $response = $this->actingAs($user)
            ->post(route('imports.store'), [
                'csv_file' => $file,
                'account_id' => 99999,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['account_id']);
    }

    public function test_store_with_duplicate_csv_imports_zero_new_transactions(): void
    {
        $this->seed(CategorySeeder::class);
        Event::fake([ImportCompleted::class]);
        $user = User::factory()->create();

        // First import (creates account)
        $file1 = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());
        $this->actingAs($user)
            ->post(route('imports.store'), [
                'csv_file' => $file1,
                'new_account_name' => 'BP Personal',
            ]);

        $this->assertDatabaseCount('transactions', 3);
        $account = Account::first();

        // Second import with same content, using existing account
        $file2 = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());
        $response = $this->actingAs($user)
            ->post(route('imports.store'), [
                'csv_file' => $file2,
                'account_id' => $account->id,
            ]);

        $response->assertRedirect(route('home'));

        // Should still be 3 transactions (no duplicates)
        $this->assertDatabaseCount('transactions', 3);
    }

    public function test_dashboard_passes_import_preview_as_inertia_prop(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());

        // POST preview to flash session data
        $this->actingAs($user)
            ->post(route('imports.preview'), ['csv_file' => $file]);

        // GET dashboard should include importPreview prop from flashed session
        $response = $this->actingAs($user)
            ->get(route('home'));

        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('importPreview')
            ->where('importPreview.bank', 'Banque Populaire')
            ->where('importPreview.transactionCount', 3)
        );
    }

    public function test_authenticated_user_can_delete_import(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create();
        $import = Import::factory()->completed()->create();
        Transaction::factory()->count(5)->create([
            'import_id' => $import->id,
            'account_id' => $account->id,
        ]);

        $this->actingAs($user)
            ->delete("/imports/{$import->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('imports', ['id' => $import->id]);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_cascade_deletes_all_linked_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create();
        $import = Import::factory()->completed()->create();
        $otherImport = Import::factory()->completed()->create();

        Transaction::factory()->count(3)->create([
            'import_id' => $import->id,
            'account_id' => $account->id,
        ]);
        Transaction::factory()->count(2)->create([
            'import_id' => $otherImport->id,
            'account_id' => $account->id,
        ]);

        $this->actingAs($user)
            ->delete("/imports/{$import->id}");

        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseHas('imports', ['id' => $otherImport->id]);
    }

    public function test_merchant_rules_preserved_after_import_deletion(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->create();
        $food = Category::where('slug', 'food')->first();

        MerchantRule::create([
            'merchant_pattern' => 'CARREFOUR CITY',
            'category_id' => $food->id,
        ]);

        $import = Import::factory()->completed()->create();
        Transaction::factory()->create([
            'import_id' => $import->id,
            'account_id' => $account->id,
            'description' => 'CARREFOUR CITY',
            'category_id' => $food->id,
        ]);

        $this->actingAs($user)
            ->delete("/imports/{$import->id}");

        $this->assertDatabaseHas('merchant_rules', [
            'merchant_pattern' => 'CARREFOUR CITY',
            'category_id' => $food->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_delete_import(): void
    {
        $import = Import::factory()->completed()->create();

        $this->delete("/imports/{$import->id}")
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('imports', ['id' => $import->id]);
    }

    public function test_dashboard_includes_imports_in_props(): void
    {
        $user = User::factory()->create();
        Import::factory()->completed()->create([
            'bank' => 'Banque Populaire',
            'transaction_count' => 42,
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->loadDeferredProps('imports', fn (Assert $page) => $page
                    ->has('imports', 1)
                    ->where('imports.0.bank', 'Banque Populaire')
                    ->where('imports.0.transactionCount', 42)
                )
            );
    }

    public function test_deleting_import_updates_available_months(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->create();
        $food = Category::where('slug', 'food')->first();

        $import = Import::factory()->completed()->create();
        Transaction::factory()->create([
            'import_id' => $import->id,
            'account_id' => $account->id,
            'category_id' => $food->id,
            'date' => '2026-01-15',
            'amount_cents' => -5000,
        ]);

        // January should be in available months
        $this->actingAs($user)
            ->get(route('home', ['month' => '2026-01']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('availableMonths', 1)
                ->where('availableMonths.0', '2026-01')
            );

        // Delete import
        $this->actingAs($user)
            ->delete("/imports/{$import->id}");

        // January should no longer be available
        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('availableMonths', 0)
            );
    }

    public function test_deleting_nonexistent_import_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete('/imports/99999')
            ->assertNotFound();
    }

    public function test_dashboard_excludes_pending_imports_from_props(): void
    {
        $user = User::factory()->create();
        Import::factory()->completed()->create(['bank' => 'Banque Populaire']);
        Import::factory()->pending()->create(['bank' => 'Revolut']);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->loadDeferredProps('imports', fn (Assert $page) => $page
                    ->has('imports', 1)
                    ->where('imports.0.bank', 'Banque Populaire')
                )
            );
    }

    public function test_dashboard_includes_categorizing_and_categorized_imports(): void
    {
        $user = User::factory()->create();
        Import::factory()->completed()->create(['bank' => 'Banque Populaire']);
        Import::factory()->categorizing()->create(['bank' => 'Revolut']);
        Import::factory()->categorized()->create(['bank' => 'N26']);
        Import::factory()->pending()->create(['bank' => 'Boursorama']);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->loadDeferredProps('imports', fn (Assert $page) => $page
                    ->has('imports', 3)
                )
            );
    }

    public function test_status_endpoint_returns_import_status_without_batch(): void
    {
        $user = User::factory()->create();
        $import = Import::factory()->completed()->create();

        $response = $this->actingAs($user)
            ->get(route('imports.status', $import));

        $response->assertOk()
            ->assertJson(['status' => 'completed']);

        $this->assertArrayNotHasKey('progress', $response->json());
    }

    public function test_status_endpoint_returns_batch_progress(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create();
        $import = Import::factory()->completed()->create();

        Transaction::factory()->count(3)->create([
            'import_id' => $import->id,
            'account_id' => $account->id,
            'category_id' => null,
        ]);

        // Create a real batch so we can test the endpoint
        $batch = Bus::batch([
            new CategorizeTransactionChunk([1]),
        ])->allowFailures()->dispatch();

        $import->update([
            'batch_id' => $batch->id,
            'status' => 'categorizing',
        ]);

        $response = $this->actingAs($user)
            ->get(route('imports.status', $import));

        $response->assertOk()
            ->assertJsonStructure(['status', 'progress', 'processedJobs', 'totalJobs', 'failedJobs', 'finishedAt']);
    }

    public function test_status_endpoint_requires_authentication(): void
    {
        $import = Import::factory()->completed()->create();

        $this->get(route('imports.status', $import))
            ->assertRedirect(route('login'));
    }

    public function test_recategorize_dispatches_new_batch(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create();
        $import = Import::factory()->categorized()->create();

        Transaction::factory()->count(3)->create([
            'import_id' => $import->id,
            'account_id' => $account->id,
            'category_id' => null,
        ]);

        Bus::fake([CategorizeTransactionChunk::class]);

        $response = $this->actingAs($user)
            ->post(route('imports.recategorize', $import));

        $response->assertRedirect();

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 1;
        });
    }

    public function test_recategorize_requires_authentication(): void
    {
        $import = Import::factory()->categorized()->create();

        $this->post(route('imports.recategorize', $import))
            ->assertRedirect(route('login'));
    }

    public function test_destroy_cancels_active_batch(): void
    {
        $user = User::factory()->create();

        // Create a real batch
        $batch = Bus::batch([
            new CategorizeTransactionChunk([1]),
        ])->allowFailures()->dispatch();

        $import = Import::factory()->categorizing()->create([
            'batch_id' => $batch->id,
        ]);

        $this->actingAs($user)
            ->delete("/imports/{$import->id}");

        $this->assertDatabaseMissing('imports', ['id' => $import->id]);

        // Verify batch was cancelled
        $updatedBatch = Bus::findBatch($batch->id);
        $this->assertTrue($updatedBatch->cancelled());
    }

    public function test_imports_include_status_and_uncategorized_count(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create();
        $import = Import::factory()->categorized()->create([
            'bank' => 'Banque Populaire',
            'transaction_count' => 10,
        ]);

        Transaction::factory()->count(3)->create([
            'import_id' => $import->id,
            'account_id' => $account->id,
            'category_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->loadDeferredProps('imports', fn (Assert $page) => $page
                    ->has('imports', 1)
                    ->where('imports.0.status', 'categorized')
                    ->where('imports.0.uncategorizedCount', 3)
                )
            );
    }
}
