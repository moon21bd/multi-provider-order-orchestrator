<?php

namespace App\Integrations\Interfaces;

use Illuminate\Http\Request;

interface ProviderHandler
{
    public function parseRequest(Request $request);

    public function processWebhook(array $data);

}