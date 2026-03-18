<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientReservation;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:all,active,inactive'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 12);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = $validated['status'] ?? 'all';
        $propertyId = $validated['property_id'] ?? null;

        $query = Client::query()
            ->with([
                'property',
                'reservations' => function ($reservationQuery) {
                    $reservationQuery
                        ->latest()
                        ->with('customers');
                },
            ])
            ->latest();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('client_type', 'like', "%{$search}%")
                    ->orWhere('internal_reference', 'like', "%{$search}%")
                    ->orWhereHas('property', function ($propertyQuery) use ($search) {
                        $propertyQuery
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('location', 'like', "%{$search}%")
                            ->orWhere('address', 'like', "%{$search}%");
                    });
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($propertyId) {
            $query->where('property_id', $propertyId);
        }

        $clients = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Clients retrieved successfully.',
            'data' => $clients,
        ]);
    }

    public function store(Request $request)
    {
        $normalizedPayload = $this->normalizeIncomingPayload($request);
        $request->merge($normalizedPayload);

        $validated = $request->validate([
            'client' => ['required', 'array'],

            'client.name' => ['required', 'string', 'max:255'],
            'client.email' => ['nullable', 'email', 'max:255'],
            'client.phone' => ['nullable', 'string', 'max:50'],
            'client.clientType' => ['nullable', 'string', 'max:100'],
            'client.website' => ['nullable', 'string', 'max:255'],
            'client.internalReference' => ['nullable', 'string', 'max:100'],
            'client.propertyId' => ['required', 'integer', 'exists:properties,id'],
            'client.isActive' => ['nullable', 'boolean'],
            'client.notes' => ['nullable', 'string'],

            'reservationSummary' => ['nullable', 'array'],
            'reservationSummary.propertyName' => ['nullable', 'string', 'max:255'],
            'reservationSummary.bookingNumber' => ['nullable', 'string', 'max:100'],
            'reservationSummary.guestName' => ['nullable', 'string', 'max:255'],
            'reservationSummary.email' => ['nullable', 'string', 'max:255'],
            'reservationSummary.location' => ['nullable', 'string', 'max:255'],
            'reservationSummary.preferredLanguage' => ['nullable', 'string', 'max:100'],
            'reservationSummary.checkIn' => ['nullable', 'date'],
            'reservationSummary.checkOut' => ['nullable', 'date'],
            'reservationSummary.nights' => ['nullable', 'integer', 'min:0'],
            'reservationSummary.totalGuests' => ['nullable', 'integer', 'min:0'],
            'reservationSummary.totalUnits' => ['nullable', 'integer', 'min:0'],
            'reservationSummary.totalPrice' => ['nullable', 'numeric', 'min:0'],
            'reservationSummary.commissionableAmount' => ['nullable', 'numeric', 'min:0'],
            'reservationSummary.commission' => ['nullable', 'numeric', 'min:0'],
            'reservationSummary.arrivalTime' => ['nullable', 'string', 'max:255'],

            'bookedCustomers' => ['nullable', 'array'],
            'bookedCustomers.*.guestName' => ['nullable', 'string', 'max:255'],
            'bookedCustomers.*.roomLabel' => ['nullable', 'string', 'max:100'],
            'bookedCustomers.*.roomType' => ['nullable', 'string', 'max:255'],
            'bookedCustomers.*.occupancy' => ['nullable', 'string', 'max:100'],
            'bookedCustomers.*.mealPlan' => ['nullable', 'string', 'max:100'],
            'bookedCustomers.*.checkIn' => ['nullable', 'date'],
            'bookedCustomers.*.checkOut' => ['nullable', 'date'],
            'bookedCustomers.*.pricePerNight' => ['nullable', 'numeric', 'min:0'],
            'bookedCustomers.*.rateName' => ['nullable', 'string'],
            'bookedCustomers.*.source' => ['nullable', 'in:pdf,manual'],

            'reservation_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $result = DB::transaction(function () use ($request, $validated) {
            $clientData = $validated['client'];
            $reservationData = $validated['reservationSummary'] ?? [];
            $bookedCustomers = $validated['bookedCustomers'] ?? [];

            $property = Property::find($clientData['propertyId']);

            $client = Client::create([
                'property_id' => (int) $clientData['propertyId'],
                'name' => $clientData['name'],
                'email' => $clientData['email'] ?? null,
                'phone' => $clientData['phone'] ?? null,
                'client_type' => $clientData['clientType'] ?? null,
                'website' => $clientData['website'] ?? null,
                'internal_reference' => $clientData['internalReference'] ?? null,
                'notes' => $clientData['notes'] ?? null,
                'is_active' => array_key_exists('isActive', $clientData)
                    ? (bool) $clientData['isActive']
                    : true,
            ]);

            $reservation = null;

            $pdfOriginalName = null;
            $storedPdfPath = null;

            if ($request->hasFile('reservation_pdf')) {
                $file = $request->file('reservation_pdf');
                $pdfOriginalName = $file->getClientOriginalName();
                $storedPdfPath = $file->store('client-reservations', 'public');
            }

            if (!empty($reservationData) || !empty($bookedCustomers) || $storedPdfPath) {
                $derivedCheckIn = $this->deriveBoundaryDate($bookedCustomers, 'checkIn', 'min');
                $derivedCheckOut = $this->deriveBoundaryDate($bookedCustomers, 'checkOut', 'max');

                $resolvedCheckIn = $this->normalizeDate($reservationData['checkIn'] ?? $derivedCheckIn);
                $resolvedCheckOut = $this->normalizeDate($reservationData['checkOut'] ?? $derivedCheckOut);

                $derivedUnits = count($bookedCustomers);
                $derivedGuests = $this->countGuestsFromCustomers($bookedCustomers);
                $derivedTotalPrice = $this->sumBookedCustomerPrices($bookedCustomers);

                $reservation = ClientReservation::create([
                    'client_id' => $client->id,
                    'property_id' => $client->property_id,
                    'property_name' => $reservationData['propertyName']
                        ?? $property?->title
                        ?? null,
                    'booking_number' => $reservationData['bookingNumber']
                        ?? $clientData['internalReference']
                        ?? null,
                    'guest_name' => $reservationData['guestName']
                        ?? $this->firstFilledCustomerValue($bookedCustomers, 'guestName')
                        ?? null,
                    'guest_email' => $reservationData['email']
                        ?? $clientData['email']
                        ?? null,
                    'location' => $reservationData['location']
                        ?? $property?->location
                        ?? null,
                    'preferred_language' => $reservationData['preferredLanguage'] ?? null,
                    'check_in' => $resolvedCheckIn,
                    'check_out' => $resolvedCheckOut,
                    'nights' => $this->resolveNights(
                        $reservationData['nights'] ?? null,
                        $resolvedCheckIn,
                        $resolvedCheckOut
                    ),
                    'total_guests' => isset($reservationData['totalGuests'])
                        ? (int) $reservationData['totalGuests']
                        : $derivedGuests,
                    'total_units' => isset($reservationData['totalUnits'])
                        ? (int) $reservationData['totalUnits']
                        : $derivedUnits,
                    'currency' => 'USD',
                    'total_price' => isset($reservationData['totalPrice'])
                        ? (float) $reservationData['totalPrice']
                        : $derivedTotalPrice,
                    'commissionable_amount' => isset($reservationData['commissionableAmount'])
                        ? (float) $reservationData['commissionableAmount']
                        : (isset($reservationData['totalPrice'])
                            ? (float) $reservationData['totalPrice']
                            : $derivedTotalPrice),
                    'commission' => isset($reservationData['commission'])
                        ? (float) $reservationData['commission']
                        : 0,
                    'arrival_time' => $reservationData['arrivalTime'] ?? null,
                    'pdf_original_name' => $pdfOriginalName,
                    'stored_pdf_path' => $storedPdfPath,
                    'raw_payload' => [
                        'client' => $clientData,
                        'reservationSummary' => $reservationData,
                        'bookedCustomers' => $bookedCustomers,
                        'property' => $request->input('property'),
                    ],
                ]);

                foreach ($bookedCustomers as $customer) {
                    $reservation->customers()->create([
                        'guest_name' => $customer['guestName'] ?? null,
                        'room_label' => $customer['roomLabel'] ?? null,
                        'room_type' => $customer['roomType'] ?? null,
                        'occupancy' => $customer['occupancy'] ?? null,
                        'meal_plan' => $customer['mealPlan'] ?? null,
                        'check_in' => $this->normalizeDate($customer['checkIn'] ?? null),
                        'check_out' => $this->normalizeDate($customer['checkOut'] ?? null),
                        'currency' => 'USD',
                        'price_per_night' => isset($customer['pricePerNight'])
                            ? (float) $customer['pricePerNight']
                            : null,
                        'rate_name' => $customer['rateName'] ?? null,
                        'source' => $customer['source'] ?? 'manual',
                    ]);
                }
            }

            return [
                'client' => $client->load([
                    'property',
                    'reservations.customers',
                ]),
                'reservation' => $reservation,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Client and reservation saved successfully.',
            'data' => $result['client'],
        ], 201);
    }

    private function normalizeIncomingPayload(Request $request): array
    {
        $client = is_array($request->input('client')) ? $request->input('client') : [];

        $reservationSummary = is_array($request->input('reservationSummary'))
            ? $request->input('reservationSummary')
            : (is_array($request->input('reservation_summary')) ? $request->input('reservation_summary') : []);

        $bookedCustomers = is_array($request->input('bookedCustomers'))
            ? $request->input('bookedCustomers')
            : (is_array($request->input('booked_customers')) ? $request->input('booked_customers') : []);

        $property = is_array($request->input('property')) ? $request->input('property') : [];

        return [
            'client' => [
                'name' => $client['name'] ?? $request->input('name'),
                'email' => $client['email'] ?? $request->input('email'),
                'phone' => $client['phone'] ?? $request->input('phone'),
                'clientType' => $client['clientType']
                    ?? $client['client_type']
                    ?? $request->input('clientType')
                    ?? $request->input('client_type'),
                'website' => $client['website'] ?? $request->input('website'),
                'internalReference' => $client['internalReference']
                    ?? $client['internal_reference']
                    ?? $request->input('internalReference')
                    ?? $request->input('internal_reference'),
                'propertyId' => $client['propertyId']
                    ?? $client['property_id']
                    ?? $request->input('propertyId')
                    ?? $request->input('property_id'),
                'isActive' => $client['isActive']
                    ?? $client['is_active']
                    ?? $request->input('isActive')
                    ?? $request->input('is_active')
                    ?? true,
                'notes' => $client['notes'] ?? $request->input('notes'),
            ],

            'reservationSummary' => [
                'propertyName' => $reservationSummary['propertyName']
                    ?? $reservationSummary['property_name']
                    ?? $property['name']
                    ?? null,
                'bookingNumber' => $reservationSummary['bookingNumber']
                    ?? $reservationSummary['booking_number']
                    ?? null,
                'guestName' => $reservationSummary['guestName']
                    ?? $reservationSummary['guest_name']
                    ?? null,
                'email' => $reservationSummary['email']
                    ?? null,
                'location' => $reservationSummary['location']
                    ?? $property['location']
                    ?? null,
                'preferredLanguage' => $reservationSummary['preferredLanguage']
                    ?? $reservationSummary['preferred_language']
                    ?? null,
                'checkIn' => $reservationSummary['checkIn']
                    ?? $reservationSummary['check_in']
                    ?? null,
                'checkOut' => $reservationSummary['checkOut']
                    ?? $reservationSummary['check_out']
                    ?? null,
                'nights' => $reservationSummary['nights'] ?? null,
                'totalGuests' => $reservationSummary['totalGuests']
                    ?? $reservationSummary['total_guests']
                    ?? null,
                'totalUnits' => $reservationSummary['totalUnits']
                    ?? $reservationSummary['total_units']
                    ?? null,
                'totalPrice' => $reservationSummary['totalPrice']
                    ?? $reservationSummary['total_price']
                    ?? null,
                'commissionableAmount' => $reservationSummary['commissionableAmount']
                    ?? $reservationSummary['commissionable_amount']
                    ?? null,
                'commission' => $reservationSummary['commission'] ?? null,
                'arrivalTime' => $reservationSummary['arrivalTime']
                    ?? $reservationSummary['arrival_time']
                    ?? null,
            ],

            'bookedCustomers' => array_map(function ($customer) {
                return [
                    'guestName' => $customer['guestName'] ?? $customer['guest_name'] ?? null,
                    'roomLabel' => $customer['roomLabel'] ?? $customer['room_label'] ?? null,
                    'roomType' => $customer['roomType'] ?? $customer['room_type'] ?? null,
                    'occupancy' => $customer['occupancy'] ?? null,
                    'mealPlan' => $customer['mealPlan'] ?? $customer['meal_plan'] ?? null,
                    'checkIn' => $customer['checkIn'] ?? $customer['check_in'] ?? null,
                    'checkOut' => $customer['checkOut'] ?? $customer['check_out'] ?? null,
                    'pricePerNight' => $customer['pricePerNight'] ?? $customer['price_per_night'] ?? null,
                    'rateName' => $customer['rateName'] ?? $customer['rate_name'] ?? null,
                    'source' => $customer['source'] ?? 'manual',
                ];
            }, $bookedCustomers),
        ];
    }

    private function normalizeDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function deriveBoundaryDate(array $customers, string $field, string $mode = 'min'): ?string
    {
        $dates = [];

        foreach ($customers as $customer) {
            $normalized = $this->normalizeDate($customer[$field] ?? null);
            if ($normalized) {
                $dates[] = $normalized;
            }
        }

        if (empty($dates)) {
            return null;
        }

        sort($dates);

        return $mode === 'max'
            ? end($dates)
            : $dates[0];
    }

    private function resolveNights($providedNights, ?string $checkIn, ?string $checkOut): int
    {
        if ($providedNights !== null && $providedNights !== '') {
            return (int) $providedNights;
        }

        if (!$checkIn || !$checkOut) {
            return 0;
        }

        try {
            return Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function sumBookedCustomerPrices(array $customers): float
    {
        $sum = 0;

        foreach ($customers as $customer) {
            $sum += (float) ($customer['pricePerNight'] ?? 0);
        }

        return round($sum, 2);
    }

    private function countGuestsFromCustomers(array $customers): int
    {
        $total = 0;

        foreach ($customers as $customer) {
            $occupancy = strtolower((string) ($customer['occupancy'] ?? ''));

            if (preg_match_all('/\d+/', $occupancy, $matches)) {
                $numbers = array_map('intval', $matches[0]);
                $total += array_sum($numbers);
            } elseif (!empty($occupancy)) {
                $total += 1;
            }
        }

        if ($total === 0 && !empty($customers)) {
            return count($customers);
        }

        return $total;
    }

    private function firstFilledCustomerValue(array $customers, string $field): ?string
    {
        foreach ($customers as $customer) {
            $value = $customer[$field] ?? null;

            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }
}