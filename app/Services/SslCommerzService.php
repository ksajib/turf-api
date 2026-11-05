<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SslCommerzService
{
    public function initiate(array $payload): array
    {
        $config = config('services.sslcommerz');
        if (empty($config['store_id']) || empty($config['store_password'])) {
            throw new RuntimeException('SSLCommerz credentials are not configured.');
        }

        $response = Http::asForm()->timeout(30)->post($config['initiation_url'], [
            'store_id' => $config['store_id'],
            'store_passwd' => $config['store_password'],
            'total_amount' => number_format((float) $payload['total_amount'], 2, '.', ''),
            'currency' => 'BDT',
            'tran_id' => $payload['tran_id'],
            'success_url' => $payload['success_url'],
            'fail_url' => $payload['fail_url'],
            'cancel_url' => $payload['cancel_url'],
            'ipn_url' => $payload['ipn_url'],
            'cus_name' => $payload['cus_name'],
            'cus_email' => $payload['cus_email'],
            'cus_phone' => $payload['cus_phone'],
            'cus_add1' => $payload['cus_add1'] ?? 'Bangladesh',
            'cus_city' => $payload['cus_city'] ?? 'Dhaka',
            'shipping_method' => 'NO',
            'product_name' => $payload['product_name'],
            'product_category' => 'Turf booking',
            'product_profile' => 'general',
            'value_a' => $payload['booking_id'],
        ]);

        if (! $response->successful()) throw new RuntimeException('SSLCommerz did not accept the payment request.');
        $result = $response->json();
        if (($result['status'] ?? null) !== 'SUCCESS' || empty($result['GatewayPageURL'])) throw new RuntimeException($result['failedreason'] ?? 'SSLCommerz payment session could not be created.');
        return $result;
    }

    public function validate(string $validationId): array
    {
        $config = config('services.sslcommerz');
        $response = Http::timeout(30)->get($config['validation_url'], ['val_id' => $validationId, 'store_id' => $config['store_id'], 'store_passwd' => $config['store_password'], 'format' => 'json']);
        if (! $response->successful()) throw new RuntimeException('SSLCommerz validation request failed.');
        return $response->json();
    }
}
