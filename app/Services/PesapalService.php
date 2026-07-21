<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PesapalService
{
    private string $baseUrl;

    public function __construct()
    {
        $environment = config(
            'services.pesapal.environment',
            'sandbox'
        );

        $this->baseUrl = $environment === 'live'
            ? config('services.pesapal.live_url')
            : config('services.pesapal.sandbox_url');
    }

    public function requestToken(): string
    {
        $consumerKey = trim(
            (string) config(
                'services.pesapal.consumer_key'
            )
        );

        $consumerSecret = trim(
            (string) config(
                'services.pesapal.consumer_secret'
            )
        );

        if (
            $consumerKey === ''
            || $consumerSecret === ''
        ) {
            throw new RuntimeException(
                'PesaPal credentials are not configured.'
            );
        }

        $response = Http::acceptJson()
            ->asJson()
            ->timeout(30)
            ->post(
                $this->baseUrl
                    . '/api/Auth/RequestToken',
                [
                    'consumer_key' => $consumerKey,
                    'consumer_secret' => $consumerSecret,
                ]
            );

        if (!$response->successful()) {
            throw new RuntimeException(
                $this->errorMessage(
                    $response->json(),
                    'PesaPal authentication failed.'
                )
            );
        }

        $token = trim(
            (string) $response->json('token')
        );

        if ($token === '') {
            throw new RuntimeException(
                'PesaPal did not return an access token.'
            );
        }

        return $token;
    }

    public function registerIpn(
        string $ipnUrl
    ): array {
        $response = $this
            ->authenticatedRequest()
            ->post(
                $this->baseUrl
                    . '/api/URLSetup/RegisterIPN',
                [
                    'url' => $ipnUrl,
                    'ipn_notification_type' => 'GET',
                ]
            );

        if (!$response->successful()) {
            throw new RuntimeException(
                $this->errorMessage(
                    $response->json(),
                    'PesaPal IPN registration failed.'
                )
            );
        }

        return $response->json();
    }

    public function submitInvoice(
        Invoice $invoice,
        array $customer
    ): array {
        $ipnId = trim(
            (string) config(
                'services.pesapal.ipn_id'
            )
        );

        if ($ipnId === '') {
            throw new RuntimeException(
                'PESAPAL_IPN_ID is not configured.'
            );
        }

        $merchantReference =
            'ASH-INVOICE-'
            . $invoice->id
            . '-'
            . now()->format('YmdHis');

        $callbackUrl = trim(
            (string) config(
                'services.pesapal.callback_url'
            )
        );

        $cancellationUrl = trim(
            (string) config(
                'services.pesapal.cancellation_url'
            )
        );

        $payload = [
            'id' => $merchantReference,
            'currency' =>
                $invoice->currency ?: 'RWF',
            'amount' =>
                round((float) $invoice->amount, 2),
            'description' =>
                'Property invoice for '
                . $invoice->property_name,
            'callback_url' => $callbackUrl,
            'redirect_mode' => 'TOP_WINDOW',
            'notification_id' => $ipnId,
            'billing_address' => [
                'email_address' =>
                    $customer['email'],
                'phone_number' =>
                    $customer['phone'] ?? '',
                'country_code' => 'RW',
                'first_name' =>
                    $customer['first_name']
                        ?? 'Property',
                'middle_name' => '',
                'last_name' =>
                    $customer['last_name']
                        ?? 'Manager',
                'line_1' =>
                    $customer['address'] ?? 'Kigali',
                'line_2' => '',
                'city' => 'Kigali',
                'state' => '',
                'postal_code' => '',
                'zip_code' => '',
            ],
        ];

        if ($cancellationUrl !== '') {
            $payload['cancellation_url'] =
                $cancellationUrl;
        }

        $response = $this
            ->authenticatedRequest()
            ->post(
                $this->baseUrl
                    . '/api/Transactions/SubmitOrderRequest',
                $payload
            );

        if (!$response->successful()) {
            throw new RuntimeException(
                $this->errorMessage(
                    $response->json(),
                    'PesaPal payment request failed.'
                )
            );
        }

        $result = $response->json();

        if (empty($result['redirect_url'])) {
            throw new RuntimeException(
                'PesaPal did not return a redirect URL.'
            );
        }

        return $result;
    }

    public function transactionStatus(
        string $orderTrackingId
    ): array {
        $response = $this
            ->authenticatedRequest()
            ->get(
                $this->baseUrl
                    . '/api/Transactions/GetTransactionStatus',
                [
                    'orderTrackingId' =>
                        $orderTrackingId,
                ]
            );

        if (!$response->successful()) {
            throw new RuntimeException(
                $this->errorMessage(
                    $response->json(),
                    'Could not verify PesaPal transaction.'
                )
            );
        }

        return $response->json();
    }

    private function authenticatedRequest():
        PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken(
                $this->requestToken()
            )
            ->timeout(30);
    }

    private function errorMessage(
        mixed $payload,
        string $fallback
    ): string {
        if (!is_array($payload)) {
            return $fallback;
        }

        return (string) (
            data_get($payload, 'error.message')
            ?: data_get($payload, 'message')
            ?: $fallback
        );
    }
}
