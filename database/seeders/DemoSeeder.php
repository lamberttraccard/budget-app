<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\MerchantRule;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    private array $categories = [];

    /** March 2025 → February 2026 (12 months) */
    private const MONTHS = [
        '2025-03', '2025-04', '2025-05', '2025-06', '2025-07', '2025-08',
        '2025-09', '2025-10', '2025-11', '2025-12', '2026-01', '2026-02',
    ];

    public function run(): void
    {
        $this->call(CategorySeeder::class);
        $this->categories = Category::all()->keyBy('slug')->toArray();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $accounts = $this->createAccounts();
        $this->createMerchantRules();

        $this->seedBanquePopulaire($accounts['bp']);
        $this->seedRevolutPersonal($accounts['revolut']);
        $this->seedRevolutBusiness($accounts['business']);
    }

    private function createAccounts(): array
    {
        return [
            'bp' => Account::factory()->personal()->create([
                'name' => 'Compte Courant',
                'bank' => 'Banque Populaire',
            ]),
            'revolut' => Account::factory()->personal()->create([
                'name' => 'Revolut Personal',
                'bank' => 'Revolut',
            ]),
            'business' => Account::factory()->professional()->create([
                'name' => 'Revolut Business',
                'bank' => 'Revolut',
            ]),
        ];
    }

    private function createMerchantRules(): void
    {
        $rules = [
            'CARREFOUR' => 'food',       'LECLERC' => 'food',
            'LIDL' => 'food',            'PICARD' => 'food',
            'FRANPRIX' => 'food',        'MCDONALD' => 'restaurants',
            'DELIVEROO' => 'restaurants', 'UBER EATS' => 'restaurants',
            'RATP' => 'transport',       'SNCF' => 'transport',
            'UBER TRIP' => 'transport',  'TOTAL ENERGIES' => 'transport',
            'LOYER' => 'housing',        'EDF' => 'housing',
            'ENGIE' => 'housing',        'FREE MOBILE' => 'subscriptions',
            'NETFLIX' => 'subscriptions', 'SPOTIFY' => 'subscriptions',
            'AMAZON PRIME' => 'subscriptions', 'CANAL+' => 'entertainment',
            'FNAC' => 'shopping',        'AMAZON' => 'shopping',
            'ZARA' => 'shopping',        'PHARMACIE' => 'health',
            'DOCTOLIB' => 'health',      'MAIF' => 'insurance',
            'ALLIANZ' => 'insurance',    'SALARIAL' => 'income',
            'FREELANCE' => 'income',
        ];

        foreach ($rules as $pattern => $slug) {
            MerchantRule::factory()->create([
                'merchant_pattern' => $pattern,
                'category_id' => $this->cat($slug),
            ]);
        }
    }

    // ── Banque Populaire: 1 import per month ──────────────────────────

    private function seedBanquePopulaire(Account $account): void
    {
        foreach (self::MONTHS as $month) {
            $fileName = 'releve_' . str_replace('-', '_', $month) . '.csv';
            $txnData = $this->bpMonth($month);
            $this->createImportWithTransactions($fileName, 'Banque Populaire', $account, $txnData);
        }
    }

    private function bpMonth(string $month): array
    {
        $d = fn (int $day) => "{$month}-" . str_pad($day, 2, '0', STR_PAD_LEFT);
        $v = $this->variation($month);

        $base = [
            // ── Income ──
            [$d(1),  285000,  'VIR SALARIAL ACME SAS', 'income'],
            // ── Fixed charges ──
            [$d(3),  -85000,  'PRLV LOYER NEXITY', 'housing'],
            [$d(5),  -12500 + $v * 30, 'PRLV EDF ELECTRICITE', 'housing'],
            [$d(5),  -4500,   'PRLV FREE MOBILE', 'subscriptions'],
            [$d(6),  -9800,   'PRLV MAIF ASSURANCE AUTO', 'insurance'],
            [$d(1),  -7590,   'PRLV RATP NAVIGO', 'transport'],
            [$d(9),  -1399,   'CB NETFLIX.COM', 'subscriptions'],
            [$d(9),  -999,    'CB SPOTIFY PREMIUM', 'subscriptions'],
            [$d(20), -2199,   'PRLV CANAL+ CINEPACK', 'entertainment'],
            // ── Groceries ──
            [$d(7),  -6532 - $v * 15, 'CB CARREFOUR MARKET', 'food'],
            [$d(10), -4298 + $v * 12, 'CB LIDL PARIS 11', 'food'],
            [$d(14), -7845 - $v * 20, 'CB LECLERC DRIVE', 'food'],
            [$d(18), -3210 + $v * 8,  'CB PICARD SURGELES', 'food'],
            [$d(22), -5123 - $v * 10, 'CB FRANPRIX', 'food'],
            [$d(27), -8910 + $v * 18, 'CB CARREFOUR CITY', 'food'],
            // ── Transport ──
            [$d(12), -3500 - $v * 5, 'CB TOTAL ENERGIES SP98', 'transport'],
            // ── Restaurants ──
            [$d(8),  -1890 + $v * 3,  'CB MCDONALD\'S PARIS', 'restaurants'],
            [$d(15), -3420 - $v * 7,  'CB DELIVEROO FR', 'restaurants'],
            [$d(21), -4560 + $v * 10, 'CB LE PETIT BISTROT', null],
            // ── Shopping / Health ──
            [$d(11), -4990 + $v * 12, 'CB FNAC CHATELET', 'shopping'],
            [$d(16), -2500,           'CB PHARMACIE DU CENTRE', 'health'],
        ];

        $seasonal = match ($month) {
            '2025-03' => [
                [$d(15), -25000, 'IMPOTS.GOUV ACOMPTE IR', null],
                [$d(20), -6500,  'CB JARDILAND PRINTEMPS', 'shopping'],
            ],
            '2025-04' => [
                [$d(12), -3800, 'CB TRUFFAUT PLANTES', 'shopping'],
                [$d(21), -5500, 'CB DECATHLON RUNNING', 'shopping'],
            ],
            '2025-05' => [
                [$d(1),  -8500, 'CB FLEURISTE FETE MERES', 'shopping'],
                [$d(15), -25000, 'IMPOTS.GOUV ACOMPTE IR', null],
                [$d(25), -4200, 'CB RESTAURANT ANNIVERSAIRE', 'restaurants'],
            ],
            '2025-06' => [
                [$d(10), -35000, 'CB SNCF BILLETS VACANCES', 'travel'],
                [$d(20), -12000, 'CB DECATHLON EQUIPEMENT ETE', 'shopping'],
            ],
            '2025-07' => [
                [$d(1),  -95000, 'CB AIRBNB LOCATION VACANCES', 'travel'],
                [$d(5),  -15000, 'CB PEAGE AUTOROUTE A7', 'transport'],
                [$d(8),  -8500,  'CB RESTAURANT PLAGE', 'restaurants'],
                [$d(12), -6200,  'CB SUPERMARCHE VACANCES', 'food'],
                [$d(15), -4500,  'CB ACTIVITE NAUTIQUE', 'entertainment'],
            ],
            '2025-08' => [
                [$d(3),  -7800,  'CB RESTAURANT VACANCES SUD', 'restaurants'],
                [$d(10), -5500,  'CB MARCHE PROVENCAL', 'food'],
                [$d(20), -15000, 'CB PEAGE RETOUR AUTOROUTE', 'transport'],
                [$d(25), -3200,  'CB STATION SERVICE RETOUR', 'transport'],
            ],
            '2025-09' => [
                [$d(2),  -15000, 'CB FNAC FOURNITURES RENTREE', 'shopping'],
                [$d(2),  -8500,  'CB CULTURA LIVRES RENTREE', 'education'],
                [$d(5),  -25000, 'IMPOTS.GOUV ACOMPTE IR', null],
                [$d(15), -6800,  'CB UNIQLO GARDE ROBE', 'shopping'],
            ],
            '2025-10' => [
                [$d(25), -3500, 'CB HALLOWEEN DEGUISEMENT', 'entertainment'],
            ],
            '2025-11' => [
                [$d(25), -18000, 'CB AMAZON BLACK FRIDAY', 'shopping'],
                [$d(26), -12000, 'CB FNAC BLACK FRIDAY', 'shopping'],
                [$d(15), -25000, 'IMPOTS.GOUV ACOMPTE IR', null],
            ],
            '2025-12' => [
                [$d(15), -15000, 'CB FNAC CADEAUX NOEL', 'shopping'],
                [$d(18), -12000, 'CB GALERIES LAFAYETTE NOEL', 'shopping'],
                [$d(20), -8500,  'CB AMAZON CADEAUX NOEL', 'shopping'],
                [$d(24), -9500,  'CB TRAITEUR REVEILLON', 'food'],
                [$d(31), -12000, 'CB RESTAURANT REVEILLON', 'restaurants'],
                [$d(22), 50000,  'VIR PRIME NOEL ACME SAS', 'income'],
            ],
            '2026-01' => [
                [$d(2),  -35000, 'CB SNCF VOYAGE BILLET', 'travel'],
                [$d(10), -4500,  'CB BOOKING.COM HOTEL', 'travel'],
                [$d(15), -8900,  'CB SOLDES ZARA', 'shopping'],
                [$d(16), -6500,  'CB SOLDES FNAC', 'shopping'],
            ],
            '2026-02' => [
                [$d(14), -8500, 'CB RESTAURANT ST VALENTIN', 'restaurants'],
                [$d(14), -4500, 'CB FLEURISTE ST VALENTIN', 'shopping'],
            ],
            default => [],
        };

        return $this->toSequence([...$base, ...$seasonal]);
    }

    // ── Revolut Personal: bi-monthly imports ──────────────────────────

    private function seedRevolutPersonal(Account $account): void
    {
        foreach (array_chunk(self::MONTHS, 2) as $pair) {
            $txnData = array_merge(
                ...array_map(fn ($m) => $this->revolutPersonalMonth($m), $pair),
            );
            $label = str_replace('-', '_', $pair[0]) . '-' . str_replace('-', '_', end($pair));
            $this->createImportWithTransactions("transactions_{$label}.csv", 'Revolut', $account, $txnData);
        }
    }

    private function revolutPersonalMonth(string $month): array
    {
        $d = fn (int $day) => "{$month}-" . str_pad($day, 2, '0', STR_PAD_LEFT);
        $v = $this->variation($month);

        $base = [
            [$d(2),  50000,           'Transfer from Compte Courant', null],
            [$d(5),  -2300 + $v * 5,  'UBER EATS PARIS', 'restaurants'],
            [$d(12), -1850 - $v * 3,  'DELIVEROO FR', 'restaurants'],
            [$d(19), -3200 + $v * 8,  'LA BRASSERIE PARISIENNE', null],
            [$d(8),  -3999 + $v * 10, 'AMAZON.FR MARKETPLACE', 'shopping'],
            [$d(15), -5990 - $v * 12, 'ZARA FRANCE', 'shopping'],
            [$d(10), -1245 + $v * 2,  'UBER TRIP PARIS', 'transport'],
            [$d(22), -870,            'UBER TRIP PARIS 2', 'transport'],
            [$d(7),  -699,            'AMAZON PRIME VIDEO', 'subscriptions'],
            [$d(17), -5000,           'DOCTOLIB CONSULTATION', 'health'],
            [$d(25), -1500 - $v * 4,  'VINTED ACHAT', null],
            [$d(28), -2100 + $v * 6,  'LEBONCOIN PAIEMENT', null],
        ];

        $seasonal = match ($month) {
            '2025-06' => [[$d(15), -12000, 'AIRBNB WEEKEND BRETAGNE', 'travel']],
            '2025-07' => [
                [$d(10), -8000, 'CB SOUVENIR BOUTIQUE', 'shopping'],
                [$d(18), -5500, 'UBER EATS VACANCES', 'restaurants'],
            ],
            '2025-08' => [
                [$d(5),  -15000, 'AIRBNB WEEKEND NICE', 'travel'],
                [$d(22), -3500,  'UBER TRIP AEROPORT', 'transport'],
            ],
            '2025-09' => [[$d(3), -9500, 'AMAZON RENTREE TECH', 'shopping']],
            '2025-11' => [[$d(25), -15000, 'AMAZON BLACK FRIDAY TECH', 'shopping']],
            '2025-12' => [
                [$d(20), -8900, 'AIRBNB WEEKEND LYON', 'travel'],
                [$d(23), -6500, 'AMAZON CADEAUX', 'shopping'],
            ],
            '2026-01' => [[$d(5), -7500, 'SOLDES AMAZON FR', 'shopping']],
            default => [],
        };

        return $this->toSequence([...$base, ...$seasonal]);
    }

    // ── Revolut Business: bi-monthly imports ──────────────────────────

    private function seedRevolutBusiness(Account $account): void
    {
        foreach (array_chunk(self::MONTHS, 2) as $pair) {
            $txnData = array_merge(
                ...array_map(fn ($m) => $this->revolutBusinessMonth($m), $pair),
            );
            $label = str_replace('-', '_', $pair[0]) . '-' . str_replace('-', '_', end($pair));
            $this->createImportWithTransactions("business_{$label}.csv", 'Revolut', $account, $txnData);
        }
    }

    private function revolutBusinessMonth(string $month): array
    {
        $d = fn (int $day) => "{$month}-" . str_pad($day, 2, '0', STR_PAD_LEFT);
        $v = $this->variation($month);

        $base = [
            [$d(5),  350000 + $v * 500, 'VIR FREELANCE CLIENT ALPHA', 'income'],
            [$d(18), 280000 - $v * 300, 'VIR FREELANCE CLIENT BETA', 'income'],
            [$d(3),  -4999,  'GITHUB SUBSCRIPTION', 'professional'],
            [$d(3),  -2999,  'FIGMA PROFESSIONAL', 'professional'],
            [$d(3),  -1099,  'NOTION WORKSPACE', 'professional'],
            [$d(7),  -5000,  'OVH CLOUD HOSTING', 'professional'],
            [$d(10), -8900,  'COWORKING SPACE PARIS', 'professional'],
            [$d(15), -12000, 'ALLIANZ RC PRO', 'insurance'],
            [$d(9),  -4500 + $v * 8, 'RESTAURANT CLIENT MEETING', null],
            [$d(20), -3800 - $v * 5, 'DEJEUNER AFFAIRES', null],
            [$d(12), -2500,  'SNCF VOYAGE PROFESSIONNEL', 'transport'],
            [$d(22), -15000, 'FORMATION LARAVEL MASTERCLASS', 'education'],
        ];

        $seasonal = match ($month) {
            '2025-03' => [[$d(25), -45000, 'EXPERT COMPTABLE BILAN 2024', 'professional']],
            '2025-05' => [[$d(10), 450000, 'VIR FREELANCE CLIENT GAMMA', 'income']],
            '2025-06' => [
                [$d(8),  -25000, 'CONFERENCE LARACON EU BILLET', 'education'],
                [$d(9),  -18000, 'HOTEL LARACON EU', 'travel'],
            ],
            '2025-07' => [[$d(15), -3500, 'RESTAURANT CLIENT VISIO', null]],
            '2025-09' => [
                [$d(5),  -35000, 'MACBOOK RENOUVELLEMENT', 'professional'],
                [$d(10), 520000, 'VIR FREELANCE GROS PROJET', 'income'],
            ],
            '2025-10' => [[$d(15), -8000, 'CONFERENCE FORUM PHP', 'education']],
            '2025-11' => [[$d(20), -12000, 'BLACK FRIDAY LICENCES JETBRAINS', 'professional']],
            '2025-12' => [
                [$d(10), 180000, 'VIR FREELANCE BONUS FIN ANNEE', 'income'],
                [$d(28), -8000,  'CADEAU CLIENTS FIN ANNEE', null],
            ],
            '2026-01' => [
                [$d(8),  -55000, 'EXPERT COMPTABLE CLOTURE', 'professional'],
                [$d(20), -9000,  'URSSAF COTISATIONS T1', null],
            ],
            '2026-02' => [[$d(5), 380000, 'VIR FREELANCE NOUVEAU CLIENT', 'income']],
            default => [],
        };

        return $this->toSequence([...$base, ...$seasonal]);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /** Convert raw tuples [date, amount, description, category] to factory sequence data. */
    private function toSequence(array $rows): array
    {
        return array_map(fn (array $row) => [
            'date' => $row[0],
            'amount_cents' => $row[1],
            'description' => $row[2],
            'category_id' => $row[3] ? $this->cat($row[3]) : null,
        ], $rows);
    }

    /** Create a completed import and its transactions using factories. */
    private function createImportWithTransactions(string $fileName, string $bank, Account $account, array $txnData): void
    {
        $dates = array_column($txnData, 'date');
        sort($dates);

        $import = Import::factory()->completed()->create([
            'file_name' => $fileName,
            'bank' => $bank,
            'transaction_count' => count($txnData),
            'date_from' => $dates[0],
            'date_to' => end($dates),
        ]);

        Transaction::factory()
            ->for($account)
            ->for($import)
            ->count(count($txnData))
            ->sequence(...$txnData)
            ->create();
    }

    /** Deterministic per-month variation for realistic amount differences. */
    private function variation(string $month): int
    {
        return crc32($month) % 100;
    }

    private function cat(string $slug): int
    {
        return $this->categories[$slug]['id'];
    }
}
