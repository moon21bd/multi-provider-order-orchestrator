<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Three groups, and the grouping is the point:
|
|  1. Public  - token issuance only.
|  2. Client  - everything a paying API consumer calls. Guarded by auth,
|               throttled, and wrapped in `provider.trace` so every client
|               request produces a full lifecycle trace.
|  3. Vendor  - the per-partner integration endpoints the queue worker calls
|               internally, plus partner status callbacks and the single
|               inbound webhook. Deliberately NOT behind `auth:api`: the
|               worker is inside the trust boundary and partners cannot
|               present our client tokens. They are IP-restricted instead.
|
| Only representative vendor endpoints are kept here; the original carries
| the same three shapes repeated per partner.
*/

$router->post('access/token', 'AccessTokenController@createAccessToken');

/*
 * Client-facing API.
 */
$router->group(['middleware' => ['auth:api', 'throttle:600000', 'provider.trace']], function () use ($router) {
    $router->get('get/operators/{country}', 'MetaDataController@getOperators');
    $router->get('get/all/country', 'MetaDataController@getAllCountry');

    $router->post('order/request', 'OrderRequestController@getOrderRequest');
    $router->post('order/request/status', 'OrderRequestController@getOrderRequestStatus');
});

/*
 * Operational endpoints used by the worker and by support tooling.
 * Requeue/resend exist because a transaction can legitimately be stuck when a
 * partner never calls back -- replaying one by reference is safer than
 * mutating its status by hand.
 */
$router->group(['middleware' => ['throttle:600000']], function () use ($router) {
    $router->get('requeue/{transactionid}', 'OrderRequestController@requeueByTransactionId');
    $router->get('resend/{transactionid}', 'OrderRequestController@resendByTransactionId');
    $router->get('update/balance/info', 'MetaDataController@updateBalanceInfo');
    $router->get('worker/status/{id}', 'MetaDataController@saveWorkerStatus');
});

/*
 * Vendor integration endpoints.
 *
 * Each partner exposes the same three operations under its own prefix --
 * submit, status, balance -- so the queue worker can dispatch purely from the
 * `route` string stored on the transaction, with no switch on partner code.
 */
$router->group(['middleware' => ['throttle:600000', 'ip.checker']], function () use ($router) {
    // Synchronous partner.
    $router->post('echo/order/request', 'EchoTopUpApiController@sendOrderRequest');
    $router->post('echo/order/status', 'EchoTopUpApiController@getOrderStatus');
    $router->post('echo/balance/enquiry', 'EchoTopUpApiController@balanceEnquiry');

    // Asynchronous partner: submits, then calls back.
    $router->post('foxtrot/order/request', 'FoxtrotNewOrderVendorController@sendOrderRequest');
    $router->post('foxtrot/order/status', 'FoxtrotNewOrderVendorController@getOrderStatus');
});

/*
 * Single inbound webhook for every partner that calls back. The `provider`
 * query parameter selects the handler; see App\Providers\ProviderServiceProvider.
 */
$router->post('webhook', 'WebhookController@handle');
$router->get('webhook', 'WebhookController@handle');
