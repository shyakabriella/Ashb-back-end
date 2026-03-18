<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContractController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 15);
        $perPage = $perPage > 100 ? 100 : max($perPage, 1);

        $contracts = Contract::query()
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Contracts fetched successfully.',
            'data' => collect($contracts->items())->map(fn (Contract $contract) => $this->transformContract($contract)),
            'meta' => [
                'current_page' => $contracts->currentPage(),
                'last_page' => $contracts->lastPage(),
                'per_page' => $contracts->perPage(),
                'total' => $contracts->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules(false));

        $contract = Contract::create(
            $this->mapRequestToDatabase($validated)
        );

        return response()->json([
            'success' => true,
            'message' => 'Contract created successfully.',
            'data' => $this->transformContract($contract),
        ], 201);
    }

    public function show(Contract $contract): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Contract fetched successfully.',
            'data' => $this->transformContract($contract),
        ]);
    }

    public function update(Request $request, Contract $contract): JsonResponse
    {
        $validated = $request->validate($this->rules(true));

        $contract->update(
            $this->mapRequestToDatabase($validated, true)
        );

        $contract->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Contract updated successfully.',
            'data' => $this->transformContract($contract),
        ]);
    }

    public function destroy(Contract $contract): JsonResponse
    {
        $contract->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contract deleted successfully.',
        ]);
    }

    protected function rules(bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return [
            'agreementDate' => [$required, 'date'],
            'effectiveDate' => [$required, 'date'],

            'clientName' => [$required, 'string', 'max:255'],
            'clientTin' => ['nullable', 'string', 'max:100'],
            'hotelName' => [$required, 'string', 'max:255'],
            'websiteName' => ['nullable', 'string', 'max:255'],

            'discountPercent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'standardMonthlyFee' => ['nullable', 'numeric', 'min:0'],
            'discountedMonthlyFee' => ['nullable', 'numeric', 'min:0'],
            'postDiscountMonthlyFee' => ['nullable', 'numeric', 'min:0'],

            'providerRepresentativeName' => ['nullable', 'string', 'max:255'],
            'providerSignatureText' => ['nullable', 'string', 'max:255'],
            'providerSignedDate' => ['nullable', 'date'],

            'clientRepresentativeName' => ['nullable', 'string', 'max:255'],
            'clientSignatureText' => ['nullable', 'string', 'max:255'],
            'clientSignedDate' => ['nullable', 'date'],

            'kpiRecipient' => ['nullable', 'string', 'max:255'],

            'billingCycle' => ['nullable', 'string', Rule::in(['monthly', 'quarterly'])],
            'invoiceDay' => ['nullable', 'integer', 'min:1', 'max:31'],
            'isActive' => ['nullable', 'boolean'],

            'pdfPath' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function mapRequestToDatabase(array $data, bool $partial = false): array
    {
        $fieldMap = [
            'agreementDate' => 'agreement_date',
            'effectiveDate' => 'effective_date',

            'clientName' => 'client_name',
            'clientTin' => 'client_tin',
            'hotelName' => 'hotel_name',
            'websiteName' => 'website_name',

            'discountPercent' => 'discount_percent',
            'standardMonthlyFee' => 'standard_monthly_fee',
            'discountedMonthlyFee' => 'discounted_monthly_fee',
            'postDiscountMonthlyFee' => 'post_discount_monthly_fee',

            'providerRepresentativeName' => 'provider_representative_name',
            'providerSignatureText' => 'provider_signature_text',
            'providerSignedDate' => 'provider_signed_date',

            'clientRepresentativeName' => 'client_representative_name',
            'clientSignatureText' => 'client_signature_text',
            'clientSignedDate' => 'client_signed_date',

            'kpiRecipient' => 'kpi_recipient',

            'billingCycle' => 'billing_cycle',
            'invoiceDay' => 'invoice_day',
            'isActive' => 'is_active',

            'pdfPath' => 'pdf_path',
        ];

        $mapped = [];

        foreach ($fieldMap as $requestKey => $dbKey) {
            if ($partial) {
                if (array_key_exists($requestKey, $data)) {
                    $mapped[$dbKey] = $data[$requestKey];
                }
            } else {
                $mapped[$dbKey] = $data[$requestKey] ?? null;
            }
        }

        if (!array_key_exists('billing_cycle', $mapped) || empty($mapped['billing_cycle'])) {
            $mapped['billing_cycle'] = 'monthly';
        }

        if (!array_key_exists('invoice_day', $mapped) || empty($mapped['invoice_day'])) {
            $mapped['invoice_day'] = 1;
        }

        if (!array_key_exists('is_active', $mapped)) {
            $mapped['is_active'] = true;
        }

        if (!array_key_exists('standard_monthly_fee', $mapped) || $mapped['standard_monthly_fee'] === null) {
            $mapped['standard_monthly_fee'] = 0;
        }

        if (!array_key_exists('discounted_monthly_fee', $mapped) || $mapped['discounted_monthly_fee'] === null) {
            $mapped['discounted_monthly_fee'] = 0;
        }

        if (!array_key_exists('post_discount_monthly_fee', $mapped) || $mapped['post_discount_monthly_fee'] === null) {
            $mapped['post_discount_monthly_fee'] = 0;
        }

        return $mapped;
    }

    protected function transformContract(Contract $contract): array
    {
        return [
            'id' => $contract->id,

            'agreementDate' => $contract->agreement_date?->format('Y-m-d'),
            'effectiveDate' => $contract->effective_date?->format('Y-m-d'),

            'clientName' => $contract->client_name,
            'clientTin' => $contract->client_tin,
            'hotelName' => $contract->hotel_name,
            'websiteName' => $contract->website_name,

            'discountPercent' => $contract->discount_percent,
            'standardMonthlyFee' => $contract->standard_monthly_fee,
            'discountedMonthlyFee' => $contract->discounted_monthly_fee,
            'postDiscountMonthlyFee' => $contract->post_discount_monthly_fee,

            'providerRepresentativeName' => $contract->provider_representative_name,
            'providerSignatureText' => $contract->provider_signature_text,
            'providerSignedDate' => $contract->provider_signed_date?->format('Y-m-d'),

            'clientRepresentativeName' => $contract->client_representative_name,
            'clientSignatureText' => $contract->client_signature_text,
            'clientSignedDate' => $contract->client_signed_date?->format('Y-m-d'),

            'kpiRecipient' => $contract->kpi_recipient,

            'billingCycle' => $contract->billing_cycle,
            'invoiceDay' => $contract->invoice_day,
            'isActive' => $contract->is_active,

            'pdfPath' => $contract->pdf_path,

            'createdAt' => $contract->created_at?->toISOString(),
            'updatedAt' => $contract->updated_at?->toISOString(),
        ];
    }
}