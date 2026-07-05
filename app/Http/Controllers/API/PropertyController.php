<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Expense;
use App\Models\MonthlyFinancialSnapshot;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PropertyController extends BaseController
{
    /**
     * Display a listing of properties.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:all,available,fully_booked,inactive',
            'location' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $query = Property::query()->latest();

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }

                $q->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('location') && strtolower($request->location) !== 'all') {
            $query->where('location', 'like', '%' . trim($request->location) . '%');
        }

        $perPage = (int) $request->get('per_page', 12);

        $properties = $query->paginate($perPage);

        $properties->getCollection()->transform(function ($property) {
            return $this->transformProperty($property);
        });

        return $this->sendResponse($properties, 'Properties retrieved successfully.');
    }

    /**
     * Monthly finance summary.
     *
     * Example:
     * GET /api/properties/monthly-finance?month=2026-01
     *
     * This allows frontend to check current month and previous months.
     */
    public function monthlyFinance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month' => ['nullable', 'date_format:Y-m'],
            'refresh' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $month = $request->get('month', now()->format('Y-m'));
        $refresh = (bool) $request->boolean('refresh', false);

        $currentMonth = now()->format('Y-m');
        $isPastMonth = $month < $currentMonth;

        /**
         * If previous month already has snapshot, return saved snapshot.
         * This protects old balance from changing later when property price changes.
         */
        if ($isPastMonth && !$refresh) {
            $snapshot = MonthlyFinancialSnapshot::where('month', $month)->first();

            if ($snapshot) {
                return $this->sendResponse([
                    'month' => $snapshot->month,
                    'period_label' => Carbon::createFromFormat('Y-m', $snapshot->month)->format('F Y'),
                    'total_available_balance' => (float) $snapshot->total_available_balance,
                    'consumed_amount' => (float) $snapshot->consumed_amount,
                    'balance' => (float) $snapshot->balance,
                    'properties_count' => (int) $snapshot->properties_count,
                    'expenses_count' => (int) $snapshot->expenses_count,
                    'paid_expenses_count' => (int) $snapshot->paid_expenses_count,
                    'pending_expenses_count' => (int) $snapshot->pending_expenses_count,
                    'overdue_expenses_count' => (int) $snapshot->overdue_expenses_count,
                    'is_closed' => true,
                    'source' => 'snapshot',
                    'closed_at' => $snapshot->closed_at,
                ], 'Monthly finance snapshot retrieved successfully.');
            }
        }

        $summary = $this->calculateMonthlyFinance($month);

        /**
         * For previous months, save the result as a closed snapshot.
         * Current month remains live because expenses can still be added.
         */
        if ($isPastMonth) {
            MonthlyFinancialSnapshot::updateOrCreate(
                ['month' => $month],
                [
                    'total_available_balance' => $summary['total_available_balance'],
                    'consumed_amount' => $summary['consumed_amount'],
                    'balance' => $summary['balance'],
                    'properties_count' => $summary['properties_count'],
                    'expenses_count' => $summary['expenses_count'],
                    'paid_expenses_count' => $summary['paid_expenses_count'],
                    'pending_expenses_count' => $summary['pending_expenses_count'],
                    'overdue_expenses_count' => $summary['overdue_expenses_count'],
                    'is_closed' => true,
                    'source' => 'snapshot',
                    'closed_at' => now(),
                ]
            );

            $summary['is_closed'] = true;
            $summary['source'] = 'snapshot';
        }

        return $this->sendResponse($summary, 'Monthly finance retrieved successfully.');
    }

    /**
     * Store a newly created property.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'image' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'address' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'units' => 'required|integer|min:0',
            'occupancy' => 'required|integer|min:0|max:100',
            'status' => 'nullable|string|in:available,fully_booked,inactive',
            'description' => 'nullable|string',
            'is_favorite' => 'nullable|boolean',
            'href' => 'nullable|string|max:255|unique:properties,href',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $data = $validator->validated();

        $data['slug'] = $this->generateUniqueSlug($data['title']);

        if (!isset($data['status']) || empty($data['status'])) {
            $data['status'] = ((int) $data['occupancy'] >= 100) ? 'fully_booked' : 'available';
        }

        $property = Property::create([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'href' => $data['href'] ?? null,
            'image' => $data['image'] ?? null,
            'price' => $data['price'] ?? null,
            'address' => $data['address'],
            'location' => $data['location'] ?? null,
            'units' => $data['units'],
            'occupancy' => $data['occupancy'],
            'status' => $data['status'],
            'description' => $data['description'] ?? null,
            'is_favorite' => $data['is_favorite'] ?? false,
        ]);

        if (empty($property->href)) {
            $property->href = '/dashboard/properties/' . $property->id;
            $property->save();
        }

        return $this->sendResponse(
            $this->transformProperty($property->fresh()),
            'Property created successfully.'
        );
    }

    /**
     * Display the specified property.
     */
    public function show($id)
    {
        $property = Property::find($id);

        if (!$property) {
            return $this->sendError('Property not found.');
        }

        return $this->sendResponse(
            $this->transformProperty($property),
            'Property retrieved successfully.'
        );
    }

    /**
     * Update the specified property.
     */
    public function update(Request $request, $id)
    {
        $property = Property::find($id);

        if (!$property) {
            return $this->sendError('Property not found.');
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'image' => 'nullable|string',
            'price' => 'sometimes|nullable|numeric|min:0',
            'address' => 'sometimes|required|string|max:255',
            'location' => 'nullable|string|max:255',
            'units' => 'sometimes|required|integer|min:0',
            'occupancy' => 'sometimes|required|integer|min:0|max:100',
            'status' => 'nullable|string|in:available,fully_booked,inactive',
            'description' => 'nullable|string',
            'is_favorite' => 'nullable|boolean',
            'href' => 'nullable|string|max:255|unique:properties,href,' . $property->id,
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $data = $validator->validated();

        if (array_key_exists('title', $data) && !empty($data['title'])) {
            $data['slug'] = $this->generateUniqueSlug($data['title'], $property->id);
        }

        if (array_key_exists('occupancy', $data) && !array_key_exists('status', $data)) {
            if ($property->status !== 'inactive') {
                $data['status'] = ((int) $data['occupancy'] >= 100) ? 'fully_booked' : 'available';
            }
        }

        $property->update($data);

        if (empty($property->href)) {
            $property->href = '/dashboard/properties/' . $property->id;
            $property->save();
        }

        return $this->sendResponse(
            $this->transformProperty($property->fresh()),
            'Property updated successfully.'
        );
    }

    /**
     * Remove the specified property.
     */
    public function destroy($id)
    {
        $property = Property::find($id);

        if (!$property) {
            return $this->sendError('Property not found.');
        }

        $property->delete();

        return $this->sendResponse([], 'Property deleted successfully.');
    }

    /**
     * Calculate monthly finance from properties and expenses.
     */
    private function calculateMonthlyFinance(string $month): array
    {
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        /**
         * We count all active properties as monthly available balance.
         * If you want to count only booked properties, change this to:
         * ->where('status', 'fully_booked')
         */
        $propertiesQuery = Property::query()
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'inactive');
            });

        $propertiesCount = (clone $propertiesQuery)->count();

        $totalAvailableBalance = (float) (clone $propertiesQuery)->sum('price');

        $expensesQuery = Expense::query()
            ->whereBetween('expense_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ]);

        $expensesCount = (clone $expensesQuery)->count();

        $paidExpensesCount = (clone $expensesQuery)
            ->where('status', 'Paid')
            ->count();

        $pendingExpensesCount = (clone $expensesQuery)
            ->where('status', 'Pending')
            ->count();

        $overdueExpensesCount = (clone $expensesQuery)
            ->where('status', 'Overdue')
            ->count();

        /**
         * Consumed amount uses all expense statuses.
         * If you only want Paid expenses to affect balance, add:
         * ->where('status', 'Paid')
         */
        $consumedAmount = (float) (clone $expensesQuery)->sum('amount');

        $balance = $totalAvailableBalance - $consumedAmount;

        $currentMonth = now()->format('Y-m');

        return [
            'month' => $month,
            'period_label' => $startDate->format('F Y'),
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_available_balance' => round($totalAvailableBalance, 2),
            'consumed_amount' => round($consumedAmount, 2),
            'balance' => round($balance, 2),
            'properties_count' => $propertiesCount,
            'expenses_count' => $expensesCount,
            'paid_expenses_count' => $paidExpensesCount,
            'pending_expenses_count' => $pendingExpensesCount,
            'overdue_expenses_count' => $overdueExpensesCount,
            'is_closed' => $month < $currentMonth,
            'source' => 'live',
        ];
    }

    /**
     * Convert model to frontend-friendly structure.
     */
    private function transformProperty(Property $property): array
    {
        return [
            'id' => $property->id,
            'title' => $property->title,
            'slug' => $property->slug,
            'href' => $property->href ?: '/dashboard/properties/' . $property->id,
            'image' => $property->image,
            'price' => $property->price !== null ? (float) $property->price : null,
            'address' => $property->address,
            'location' => $property->location,
            'units' => (int) $property->units,
            'occupancy' => (int) $property->occupancy,
            'status' => $property->status,
            'description' => $property->description,
            'is_favorite' => (bool) $property->is_favorite,
            'created_at' => $property->created_at,
            'updated_at' => $property->updated_at,
        ];
    }

    /**
     * Generate unique slug.
     */
    private function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($title);

        if ($baseSlug === '') {
            $baseSlug = 'property';
        }

        $slug = $baseSlug;
        $counter = 1;

        while (true) {
            $query = Property::where('slug', $slug);

            if ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            }

            if (!$query->exists()) {
                break;
            }

            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}