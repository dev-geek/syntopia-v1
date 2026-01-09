<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirstPromoterService
{
    private $apiKey;
    private $accountId;
    private $saleApiUrl = 'https://v2.firstpromoter.com/api/v2/track/sale';

    public function __construct()
    {
        $this->apiKey = config('payment.firstpromoter.api_key');
        $this->accountId = config('payment.firstpromoter.account_id');
    }

    public function trackSale(array $data): ?array
    {
        if (!$this->apiKey || !$this->accountId) {
            Log::warning('FirstPromoter: API key or Account ID not configured');
            return null;
        }

        if (empty($data['event_id'])) {
            Log::error('FirstPromoter: Missing required field: event_id', ['data' => $data]);
            return null;
        }

        if (empty($data['amount'])) {
            Log::error('FirstPromoter: Missing required field: amount', ['data' => $data]);
            return null;
        }

        if (empty($data['email']) && empty($data['uid'])) {
            Log::error('FirstPromoter: Missing required field: email or uid must be provided', ['data' => $data]);
            return null;
        }

        $amount = $this->convertAmountToCents($data['amount'], $data['currency'] ?? 'USD');

        if ($amount <= 0) {
            Log::error('FirstPromoter: Invalid amount (must be greater than 0)', [
                'original_amount' => $data['amount'],
                'converted_amount' => $amount,
                'currency' => $data['currency'] ?? 'USD'
            ]);
            return null;
        }

        $payload = [
            'event_id' => $data['event_id'],
            'amount' => $amount,
        ];

        if (!empty($data['email'])) {
            $payload['email'] = $data['email'];
        }

        if (!empty($data['uid'])) {
            $payload['uid'] = $data['uid'];
        }

        if (!empty($data['quantity'])) {
            $payload['quantity'] = $data['quantity'];
        }

        if (!empty($data['plan'])) {
            $payload['plan'] = $data['plan'];
        }

        if (!empty($data['currency']) && $data['currency'] !== config('payment.firstpromoter.default_currency', 'USD')) {
            $payload['currency'] = $data['currency'];
        }

        if (!empty($data['mrr'])) {
            $mrrAmount = $this->convertAmountToCents($data['mrr'], $data['currency'] ?? 'USD');
            $payload['mrr'] = (string) $mrrAmount;
        }

        if (!empty($data['promo_code'])) {
            $payload['promo_code'] = $data['promo_code'];
        }

        if (!empty($data['tid'])) {
            $payload['tid'] = request()->cookie('_fprom_tid');
        }

        if (!empty($data['ref_id'])) {
            $payload['ref_id'] = request()->cookie('_fprom_ref');
        }

        if (isset($data['skip_email_notification'])) {
            $payload['skip_email_notification'] = $data['skip_email_notification'];
        }

        try {

            dd($payload,$this->apiKey,$this->accountId);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Account-ID' => $this->accountId,
                'Content-Type' => 'application/json',
            ])->post($this->saleApiUrl, $payload); dd($response->json());

            $statusCode = $response->status();
            $responseBody = $response->json() ?? [];
            $errorMessage = $responseBody['message'] ?? $response->body();

            if ($response->successful()) {
                $logData = [
                    'sale_id' => $responseBody['id'] ?? null,
                    'sale_amount' => $responseBody['sale_amount'] ?? null,
                    'referral_id' => $responseBody['referral']['id'] ?? null,
                    'referral_email' => $responseBody['referral']['email'] ?? null,
                    'commissions_count' => count($responseBody['commissions'] ?? []),
                ];

                if (!empty($responseBody['commissions'])) {
                    $logData['commissions'] = array_map(function ($commission) {
                        return [
                            'id' => $commission['id'] ?? null,
                            'status' => $commission['status'] ?? null,
                            'amount' => $commission['amount'] ?? null,
                            'promoter_email' => $commission['promoter_campaign']['promoter']['email'] ?? null,
                            'campaign_name' => $commission['promoter_campaign']['campaign']['name'] ?? null,
                        ];
                    }, $responseBody['commissions']);
                }

                Log::info('FirstPromoter: Sale tracked successfully', $logData);

                return $responseBody;
            } elseif ($statusCode === 409) {
                Log::info('FirstPromoter: Duplicate sale detected', [
                    'status_code' => $statusCode,
                    'event_id' => $payload['event_id'],
                    'message' => $errorMessage,
                ]);
                return ['duplicate' => true, 'message' => $errorMessage];
            } elseif ($statusCode === 404) {
                $logData = [
                    'status_code' => $statusCode,
                    'event_id' => $payload['event_id'],
                    'message' => $errorMessage,
                    'code' => $responseBody['code'] ?? null
                ];

                if (isset($payload['email'])) {
                    $logData['email'] = $payload['email'];
                }
                if (isset($payload['uid'])) {
                    $logData['uid'] = $payload['uid'];
                }

                Log::warning('FirstPromoter: Sale not found (404)', $logData);

                return null;
            } elseif ($statusCode === 400) {
                Log::error('FirstPromoter: Validation error (400) - Invalid request data', [
                    'status_code' => $statusCode,
                    'message' => $errorMessage,
                    'payload' => $payload,
                    'response_body' => $responseBody
                ]);
                return null;
            } else {
                Log::error('FirstPromoter: Failed to track sale', [
                    'status_code' => $statusCode,
                    'message' => $errorMessage,
                    'response_body' => $responseBody,
                    'payload' => $payload
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('FirstPromoter: Exception while tracking sale', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload
            ]);
            return null;
        }
    }

    private function convertAmountToCents(float $amount, string $currency): int
    {
        if ($amount <= 0) {
            return 0;
        }

        $zeroDecimalCurrencies = ['JPY', 'KRW', 'CLP', 'VND', 'UGX', 'VUV', 'XAF', 'XOF', 'XPF'];

        if (in_array(strtoupper($currency), $zeroDecimalCurrencies)) {
            return (int) round($amount);
        }

        return (int) round($amount * 100);
    }
}

