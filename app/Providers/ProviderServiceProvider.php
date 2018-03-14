<?php

namespace App\Providers;

use App\Http\Controllers\EchoTopUpApiController;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use App\Integrations\FoxtrotProviderHandler;
use App\Integrations\Interfaces\ProviderHandler;

class ProviderServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(ProviderHandler::class, function ($app) {
            $provider = $this->getProviderFromRequest($app);

            switch ($provider) {
                case 'ECHO':
                    return $app->make(EchoTopUpApiController::class);
                case 'FOXTROT':
                    return $app->make(FoxtrotProviderHandler::class);
                default:
                    return null;
            }
        });
    }

    private function getProviderFromRequest($app)
    {
        $request = $app['request'];
        $provider = $request->query('provider') ?? $request->input('provider');

        if (strpos($provider, '?') !== false) { // some partners append query state to the provider param
            list($provider,$status) = explode("?", $provider);
        }

        Log::info('PROVIDER | ' . $provider);

        if (!$provider) {
            return null;
        }

        return strtoupper($provider);
    }
}