<?php

namespace App\Providers;

use App\Contracts\FileCreators\BankTransferFileInterface;
use App\Repositories\BankTransfer\EloquentBankTransferRepository;
use Illuminate\Support\ServiceProvider;

class BackendServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(BankTransferFileInterface::class, EloquentBankTransferRepository::class);
    }
}
