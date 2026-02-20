<?php

namespace App\Providers;

use App\Parsers\BanquePopulaireParser;
use App\Parsers\CsvParserFactory;
use App\Parsers\RevolutParser;
use App\Events\CategoryCorrected;
use App\Events\ImportCompleted;
use App\Listeners\CategorizeImportedTransactions;
use App\Listeners\UpdateMerchantRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CsvParserFactory::class, fn () => new CsvParserFactory([
            BanquePopulaireParser::class,
            RevolutParser::class,
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Event::listen(ImportCompleted::class, CategorizeImportedTransactions::class);
        Event::listen(CategoryCorrected::class, UpdateMerchantRule::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
