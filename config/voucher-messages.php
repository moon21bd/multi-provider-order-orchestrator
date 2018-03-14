<?php

return [
    '-32700' => [
        'ErrorName' => 'Parse error',
        'description' => 'Invalid JSON was received by the server',
    ],
    '-32600' => [
        'ErrorName' => 'Invalid Request',
        'description' => 'The JSON sent is not a valid Request object'
    ],
    '-32601' => [
        'ErrorName' => 'Method not found',
        'description' => 'The method does not exist / is not available',
    ],
    '-32602 ' => [
        'ErrorName' => 'Invalid params',
        'description' => 'Invalid method parameter(s).',
    ],
    '-32603 ' => [
        'ErrorName' => 'Internal error',
        'description' => 'Internal JSON-RPC error.',
    ],
    '30101' => [
        'ErrorName' => 'Unauthorized Access',
        'description' => 'Error in authorization',
    ],
    '30102 ' => [
        'ErrorName' => 'Order not found',
        'description' => 'Order not found',
    ],
    '30103' => [
        'ErrorName' => 'Order Failed',
        'description' => 'Order Failed',
    ],
    '30104 ' => [
        'ErrorName' => 'Fulfilment error',
        'description' => 'Fulfilment error',
    ],
    '30105' => [
        'ErrorName' => 'Invalid SKU',
        'description' => 'The SKU does not exist',
    ],
    '30106' => [
        'ErrorName' => 'Unauthorized SKU',
        'description' => 'The client is not authorised to process orders of the requested SKU',
    ],
    '30107' => [
        'ErrorName' => 'Invalid User Account',
        'description' => 'The submitted user account was not found.',
    ],
    '30108' => [
        'ErrorName' => 'User account validation error ',
        'description' => 'Unable to validate this user account. May be temporary, can be retried.',
    ],
    '30109' => [
        'ErrorName' => 'Duplicate request ID',
        'description' => 'Duplicate request ID',
    ],
    '30110' => [
        'ErrorName' => 'Unauthorised Order Size',
        'description' => ' The number of vouchers on the order exceeds the allowed limit',
    ],
    '30111' => [
        'ErrorName' => ' User account ineligible for SKU',
        'description' => ' The user id is not eligible to purchase the selected item, e.g. already has an active subscription of which only one can be active at a time,',
    ],
    '30112' => [
        'ErrorName' => ' Voucher out of stock ',
        'description' => 'Occurs when there are no more voucher codes available in the inventory service.',
    ],
    '30113' => [
        'ErrorName' => ' Invalid Price',
        'description' => 'Occurs when the Price data sent in the validate, top up, or placeOrder request does not match the price data in our liskSku response.',
    ],
    '30114' => [
        'ErrorName' => 'User Country Not Eligible for Product',
        'description' => 'The player’s game account country is not eligible for products in this country.(Riot Games)',
    ],
    '30199' => [
        'ErrorName' => 'Unknown Error',
        'description' => 'Unknown Error',
    ],
    'FAILED' => [
        'ErrorName' => 'Order Expired',
        'description' => 'Order Expired',
    ],
    '' => [
        'ErrorName' => 'AccessDeniedException',
        'description' => 'User is not authorized to access this resource with an explicit deny',
    ],
];
