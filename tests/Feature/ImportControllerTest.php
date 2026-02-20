<?php

namespace Tests\Feature;

use App\Events\ImportCompleted;
use App\Models\Account;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
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
            ."00012345678;10/02/2026;10/02/2026;VIREMENT SALAIRE;REF003;10/02/2026;3200,00",
            'ISO-8859-15',
            'UTF-8',
        );
    }

    private function revolutCsvContent(): string
    {
        return "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n"
            ."CARD_PAYMENT,Current,2026-02-01 10:00:00,2026-02-01 10:00:00,Amazon,-25.99,0.00,EUR,COMPLETED,1000.00\n"
            ."CARD_PAYMENT,Current,2026-02-15 14:30:00,2026-02-15 14:30:00,Netflix,-13.99,0.00,EUR,COMPLETED,986.01";
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

        $response->assertRedirect(route('dashboard'));

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

        $response->assertRedirect(route('dashboard'));

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
            ->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('importPreview')
            ->where('importPreview.bank', 'Banque Populaire')
            ->where('importPreview.transactionCount', 3)
        );
    }
}
