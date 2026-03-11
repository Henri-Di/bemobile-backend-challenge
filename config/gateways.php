<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default gateway code
    |--------------------------------------------------------------------------
    |
    | Logical identifier used by the payment layer when a preferred gateway
    | must be resolved from configuration.
    |
    */
    'default' => env('PAYMENT_GATEWAY_DEFAULT', 'gateway_1'),

    /*
    |--------------------------------------------------------------------------
    | HTTP client settings
    |--------------------------------------------------------------------------
    */
    'http' => [
        'timeout' => max(1, (int) env('PAYMENT_HTTP_TIMEOUT', 10)),
        'connect_timeout' => max(1, (int) env('PAYMENT_HTTP_CONNECT_TIMEOUT', 5)),
        'retry_times' => max(0, (int) env('PAYMENT_HTTP_RETRY_TIMES', 2)),
        'retry_sleep' => max(0, (int) env('PAYMENT_HTTP_RETRY_SLEEP', 200)),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway One
    |--------------------------------------------------------------------------
    |
    | Notes:
    | - Uses login endpoint to obtain bearer token
    | - Charges via /transactions
    | - Refunds/chargebacks via /transactions/{id}/charge_back
    |
    */
    'gateway_1' => [
        'code' => 'gateway_1',
        'name' => 'Gateway One',
        'enabled' => filter_var(env('PAYMENT_GATEWAY_1_ENABLED', true), FILTER_VALIDATE_BOOL),
        'base_url' => rtrim((string) env('GATEWAY1_BASE_URL', 'http://localhost:3001'), '/'),

        'auth' => [
            'strategy' => 'login_bearer',
            'email' => trim((string) env('GATEWAY1_LOGIN_EMAIL', 'dev@betalent.tech')),
            'token' => trim((string) env('GATEWAY1_LOGIN_TOKEN', 'FEC9BB078BF338F464F96B48089EB498')),
        ],

        'endpoints' => [
            'login' => '/login',
            'transactions' => '/transactions',
            'refund' => '/transactions/{id}/charge_back',
        ],

        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],

        'payload_map' => [
            'amount' => 'amount',
            'name' => 'name',
            'email' => 'email',
            'card_number' => 'cardNumber',
            'cvv' => 'cvv',
        ],

        'response_map' => [
            'external_id' => 'id',
            'status' => 'status',
            'message' => 'message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway Two
    |--------------------------------------------------------------------------
    |
    | Notes:
    | - Uses static auth headers
    | - Charges via /transacoes
    | - Refunds via /transacoes/reembolso
    |
    */
    'gateway_2' => [
        'code' => 'gateway_2',
        'name' => 'Gateway Two',
        'enabled' => filter_var(env('PAYMENT_GATEWAY_2_ENABLED', true), FILTER_VALIDATE_BOOL),
        'base_url' => rtrim((string) env('GATEWAY2_BASE_URL', 'http://localhost:3002'), '/'),

        'auth' => [
            'strategy' => 'static_headers',
            'token' => trim((string) env('GATEWAY2_AUTH_TOKEN', 'tk_f2198cc671b5289fa856')),
            'secret' => trim((string) env('GATEWAY2_AUTH_SECRET', '3d15e8ed6131446ea7e3456728b1211f')),
        ],

        'endpoints' => [
            'transactions' => '/transacoes',
            'refund' => '/transacoes/reembolso',
        ],

        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Gateway-Auth-Token' => trim((string) env('GATEWAY2_AUTH_TOKEN', 'tk_f2198cc671b5289fa856')),
            'Gateway-Auth-Secret' => trim((string) env('GATEWAY2_AUTH_SECRET', '3d15e8ed6131446ea7e3456728b1211f')),
        ],

        'payload_map' => [
            'amount' => 'valor',
            'name' => 'nome',
            'email' => 'email',
            'card_number' => 'numeroCartao',
            'cvv' => 'cvv',
        ],

        'response_map' => [
            'external_id' => 'id',
            'status' => 'status',
            'message' => 'mensagem',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Internal status normalization
    |--------------------------------------------------------------------------
    |
    | Optional central map for adapters/services to normalize provider-specific
    | statuses into local transaction/refund statuses.
    |
    */
    'status_map' => [
        'approved' => 'paid',
        'paid' => 'paid',
        'success' => 'paid',
        'processed' => 'paid',
        'pending' => 'pending',
        'processing' => 'pending',
        'failed' => 'failed',
        'error' => 'failed',
        'denied' => 'failed',
        'refunded' => 'refunded',
        'refund' => 'refunded',
        'chargeback' => 'refunded',
    ],

];