<?php

namespace Tests\Feature;

use App\Events\ImportCompleted;
use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\MerchantRule;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_store_with_valid_csv_creates_import_and_transactions(): void
    {
        $this->seed(CategorySeeder::class);
        Event::fake([ImportCompleted::class]);
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());

        $response = $this->actingAs($user)
            ->post(route('imports.store'), ['csv_file' => $file]);

        $response->assertRedirect(route('home'));

        $this->assertDatabaseHas('imports', [
            'file_name' => 'releve.csv',
            'bank' => 'Banque Populaire',
            'status' => 'completed',
        ]);

        $this->assertDatabaseCount('transactions', 3);
    }

    public function test_store_auto_creates_account_when_none_exists(): void
    {
        $this->seed(CategorySeeder::class);
        Event::fake([ImportCompleted::class]);
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());

        $this->actingAs($user)
            ->post(route('imports.store'), ['csv_file' => $file]);

        $this->assertDatabaseHas('accounts', [
            'bank' => 'Banque Populaire',
            'type' => 'personal',
            'name' => 'Banque Populaire Personal',
        ]);
    }

    public function test_store_uses_existing_account_when_one_matches(): void
    {
        $this->seed(CategorySeeder::class);
        Event::fake([ImportCompleted::class]);
        $user = User::factory()->create();

        $existingAccount = Account::create([
            'bank' => 'Banque Populaire',
            'type' => 'personal',
            'name' => 'My BP Account',
        ]);

        $file = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());

        $this->actingAs($user)
            ->post(route('imports.store'), ['csv_file' => $file]);

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
        $file = UploadedFile::fake()->createWithContent('random.csv', "col1,col2,col3\nfoo,bar,baz");

        $response = $this->actingAs($user)
            ->post(route('imports.store'), ['csv_file' => $file]);

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
            ->post(route('imports.store'), ['csv_file' => $file]);

        Event::assertDispatched(ImportCompleted::class);
    }

    public function test_store_with_professional_account_type(): void
    {
        $this->seed(CategorySeeder::class);
        Event::fake([ImportCompleted::class]);
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());

        $this->actingAs($user)
            ->post(route('imports.store'), [
                'csv_file' => $file,
                'account_type' => 'professional',
            ]);

        $this->assertDatabaseHas('accounts', [
            'bank' => 'Banque Populaire',
            'type' => 'professional',
            'name' => 'Banque Populaire Professional',
        ]);
    }

    public function test_store_without_account_type_defaults_to_personal(): void
    {
        $this->seed(CategorySeeder::class);
        Event::fake([ImportCompleted::class]);
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());

        $this->actingAs($user)
            ->post(route('imports.store'), ['csv_file' => $file]);

        $this->assertDatabaseHas('accounts', [
            'bank' => 'Banque Populaire',
            'type' => 'personal',
        ]);
    }

    public function test_store_with_invalid_account_type_returns_validation_error(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());

        $response = $this->actingAs($user)
            ->post(route('imports.store'), [
                'csv_file' => $file,
                'account_type' => 'invalid',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['account_type']);
    }

    public function test_store_with_duplicate_csv_imports_zero_new_transactions(): void
    {
        $this->seed(CategorySeeder::class);
        Event::fake([ImportCompleted::class]);
        $user = User::factory()->create();

        // First import
        $file1 = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());
        $this->actingAs($user)
            ->post(route('imports.store'), ['csv_file' => $file1]);

        $this->assertDatabaseCount('transactions', 3);

        // Second import with same content
        $file2 = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());
        $response = $this->actingAs($user)
            ->post(route('imports.store'), ['csv_file' => $file2]);

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
        $account = Account::factory()->personal()->create();
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
        $account = Account::factory()->personal()->create();
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
        $account = Account::factory()->personal()->create();
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
        $account = Account::factory()->personal()->create();
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
}
