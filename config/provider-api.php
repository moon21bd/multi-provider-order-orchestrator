<?php

return [
    'API_KEY_SANDBOX' => '5e73adee-79fc-452e-aa46-3fb8aa8d52f6',
    'API_SECRET_SANDBOX' => '2684f5c5-fd5f-4120-ad91-03f282d8d146',
    'API_KEY_PRODUCTION' => '5e73adee-79fc-452e-aa46-3fb8aa8d52f6',
    'API_SECRET_PRODUCTION' => '2684f5c5-fd5f-4120-ad91-03f282d8d146',
    'BASEURL_SANDBOX' => 'https://preprod-dvs-api.foxtrot.com/v1',
    'BASEURL_PRODUCTION' => 'https://prod-dvs-api.foxtrot.com/v1',
    'API_TRANSACTION' => '/async/transactions',
    'API_TRANSACTION_STATUS' => '/transactions/',

    /*
     * Foxtrot callbacks (https://developer.provider.example/reference/callbacks).
     *
     * Foxtrot POSTs a JSON transaction object to the `callback_url` supplied on
     * each POST /async/transactions request when a transaction reaches
     * COMPLETED or DECLINED (REVERSED on rare manual reversals).
     *
     * Foxtrot does NOT sign its callbacks and provides no shared-secret/token
     * mechanism, so the callback is treated purely as a TRIGGER: the delivered
     * status is always re-verified against the Foxtrot API before a transaction
     * is moved to a terminal state.
     *
     * The callback host is environment-based (prod vs stage) and can be
     * overridden with FOXTROT_CALLBACK_BASE_URL when the public host differs from
     * the default (e.g. behind a proxy/provider).
     */
    'CALLBACK_BASE_URL' => env(
        'FOXTROT_CALLBACK_BASE_URL',
        env('APP_ENV') === 'production'
            ? 'https://api.example.com'
            : 'https://api.example.com'
    ),
];
