<?php

namespace App\Providers;

use App\Support\ArabicLoremProvider;
use Faker\Factory as FakerFactory;
use Faker\Generator as FakerGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerArabicFaker();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register a faker instance configured with an Arabic text provider.
     */
    protected function registerArabicFaker(): void
    {
        $locale = config('app.faker_locale', 'en_US');

        $this->app->singleton(FakerGenerator::class.':'.$locale, function () use ($locale) {
            $faker = FakerFactory::create($locale);
            $faker->addProvider(new ArabicLoremProvider($faker));
            $faker->seed();
            $faker->unique(true);

            return $faker;
        });

        $this->app->singleton(FakerGenerator::class, function () use ($locale) {
            return app(FakerGenerator::class.':'.$locale);
        });
    }
}
