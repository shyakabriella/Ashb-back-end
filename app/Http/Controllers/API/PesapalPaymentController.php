<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\PesapalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class PesapalPaymentController extends Controller
{
    public function __construct(
        private PesapalService $pesapal
    ) {
    }

    public function initialize(
        Request $request,
        Invoice $invoice
    ): JsonResponse {
        $validator = Validator::make(
            $request->all(),
            [
                'payer_name' =>
                    'required|string|max:150',
                'payer_email' =>
                    'required|email|max:255',
                'phone' =>
                    'nullable|string|max:30',
            ]
        );

        if ($validator->fails()) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Please check the payment details.',
                    'errors' =>
                        $validator->errors(),
                ],
                422
            );
        }

        if (
            strtolower(
                (string) $invoice->payment_status
            ) === 'paid'
        ) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'This invoice has already been paid.',
                ],
                422
            );
        }

        $data = $validator->validated();

        $names = preg_split(
            '/\s+/',
            trim($data['payer_name']),
            2
        );

        try {
            $result = $this->pesapal->submitInvoice(
                $invoice,
                [
                    'email' =>
                        $data['payer_email'],
                    'phone' =>
                        $data['phone'] ?? '',
                    'first_name' =>
                        $names[0] ?? 'Property',
                    'last_name' =>
                        $names[1] ?? 'Manager',
                    'address' =>
                        optional(
                            $invoice->property
                        )->address
                            ?: 'Kigali',
                ]
            );

            $metadata = is_array($invoice->metadata)
                ? $invoice->metadata
                : [];

            $metadata['pesapal'] = [
                'merchant_reference' =>
                    $result['merchant_reference']
                        ?? null,
                'order_tracking_id' =>
                    $result['order_tracking_id']
                        ?? null,
                'redirect_url' =>
                    $result['redirect_url']
                        ?? null,
                'initialized_at' =>
                    now()->toIso8601String(),
            ];

            $invoice->forceFill([
                'metadata' => $metadata,
            ])->save();

            return response()->json([
                'success' => true,
                'message' =>
                    'PesaPal checkout created.',
                'data' => [
                    'checkout_url' =>
                        $result['redirect_url'],
                    'order_tracking_id' =>
                        $result['order_tracking_id']
                            ?? null,
                    'merchant_reference' =>
                        $result['merchant_reference']
                            ?? null,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error(
                'PesaPal initialization failed.',
                [
                    'invoice_id' =>
                        $invoice->id,
                    'error' =>
                        $exception->getMessage(),
                ]
            );

            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        $exception->getMessage(),
                ],
                500
            );
        }
    }

    public function callback(
        Request $request
    ): RedirectResponse {
        $trackingId = trim(
            (string) $request->query(
                'OrderTrackingId'
            )
        );

        $merchantReference = trim(
            (string) $request->query(
                'OrderMerchantReference'
            )
        );

        if ($trackingId !== '') {
            $this->synchronizePayment(
                $trackingId,
                $merchantReference
            );
        }

        return redirect()->away(
            rtrim(
                (string) env(
                    'APP_FRONTEND_URL',
                    'https://www.d.ashbhub.com'
                ),
                '/'
            )
            . '/payment-result?tracking_id='
            . urlencode($trackingId)
        );
    }

    public function ipn(
        Request $request
    ): JsonResponse {
        $trackingId = trim(
            (string) (
                $request->input('OrderTrackingId')
                ?: $request->query(
                    'OrderTrackingId'
                )
            )
        );

        $merchantReference = trim(
            (string) (
                $request->input(
                    'OrderMerchantReference'
                )
                ?: $request->query(
                    'OrderMerchantReference'
                )
            )
        );

        if ($trackingId !== '') {
            $this->synchronizePayment(
                $trackingId,
                $merchantReference
            );
        }

        return response()->json([
            'status' => '200',
            'message' => 'IPN processed.',
        ]);
    }

    public function registerIpn():
        JsonResponse {
        try {
            $result = $this->pesapal->registerIpn(
                (string) config(
                    'services.pesapal.ipn_url'
                )
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (Throwable $exception) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        $exception->getMessage(),
                ],
                500
            );
        }
    }

    private function synchronizePayment(
        string $trackingId,
        string $merchantReference
    ): void {
        try {
            $status = $this
                ->pesapal
                ->transactionStatus(
                    $trackingId
                );

            $invoice = Invoice::query()
                ->where(
                    'metadata->pesapal->merchant_reference',
                    $merchantReference
                )
                ->orWhere(
                    'metadata->pesapal->order_tracking_id',
                    $trackingId
                )
                ->first();

            if (!$invoice) {
                return;
            }

            $description = strtoupper(
                (string) (
                    $status[
                        'payment_status_description'
                    ] ?? ''
                )
            );

            $metadata = is_array($invoice->metadata)
                ? $invoice->metadata
                : [];

            $metadata['pesapal']['status'] =
                $status;

            $metadata['pesapal']['verified_at'] =
                now()->toIso8601String();

            $updates = [
                'metadata' => $metadata,
            ];

            if ($description === 'COMPLETED') {
                $updates['payment_status'] =
                    Invoice::PAYMENT_STATUS_PAID;
            }

            $invoice->forceFill($updates)->save();
        } catch (Throwable $exception) {
            Log::error(
                'PesaPal payment synchronization failed.',
                [
                    'order_tracking_id' =>
                        $trackingId,
                    'merchant_reference' =>
                        $merchantReference,
                    'error' =>
                        $exception->getMessage(),
                ]
            );
        }
    }
}
