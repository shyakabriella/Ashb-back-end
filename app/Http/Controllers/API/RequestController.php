<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BusinessRequest;
use App\Models\Expense;
use App\Models\Property;
use App\Models\User;
use App\Notifications\BusinessRequestCreatedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class RequestController extends Controller
{
    private const REQUEST_NOTIFICATION_EMAILS = [
        'hotelandsafari@gmail.com',
        'shyakas83@gmail.com',
    ];

    private const REQUEST_TYPES = [
        'salary',
        'maintenance',
        'purchase',
        'project',
        'marketing',
        'utilities',
        'other',
    ];

    private const PRIORITIES = [
        'normal',
        'elevated',
        'urgent',
    ];

    private const STATUSES = [
        'pending',
        'approved',
        'rejected',
    ];

    private const EXPENSE_STATUSES = [
        'paid',
        'pending',
        'overdue',
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'priority' => ['nullable', Rule::in(self::PRIORITIES)],
            'request_type' => ['nullable', Rule::in(self::REQUEST_TYPES)],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'requested_by' => ['nullable', 'integer', 'exists:users,id'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = BusinessRequest::query()
            ->with([
                'property:id,title,price,address,location',
                'requester:id,first_name,last_name,email',
                'reviewer:id,first_name,last_name,email',
                'expense:id,expense_code,amount,status,expense_date',
            ])
            ->latest('id');

        $this->applyFilters($query, $validated);

        $requests = $query
            ->paginate((int) ($validated['per_page'] ?? 20))
            ->withQueryString();

        $requests->setCollection(
            $requests->getCollection()
                ->map(fn (BusinessRequest $businessRequest) => $this->transformRequest($businessRequest))
        );

        return response()->json([
            'success' => true,
            'message' => 'Requests fetched successfully.',
            'data' => $requests,
            'summary' => $this->buildSummary(),
        ]);
    }

    public function references(): JsonResponse
    {
        $properties = Property::query()
            ->select([
                'id',
                'title',
                'price',
                'address',
                'location',
                'status',
            ])
            ->orderBy('title')
            ->get()
            ->map(fn (Property $property) => [
                'id' => $property->id,
                'title' => $property->title,
                'price' => (float) ($property->price ?? 0),
                'address' => $property->address,
                'location' => $property->location,
                'status' => $property->status,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Request references fetched successfully.',
            'data' => [
                'properties' => $properties,
                'request_types' => [
                    [
                        'value' => 'salary',
                        'label' => 'Salary Request',
                    ],
                    [
                        'value' => 'maintenance',
                        'label' => 'Maintenance Request',
                    ],
                    [
                        'value' => 'purchase',
                        'label' => 'Purchase Request',
                    ],
                    [
                        'value' => 'project',
                        'label' => 'Project Request',
                    ],
                    [
                        'value' => 'marketing',
                        'label' => 'Marketing Request',
                    ],
                    [
                        'value' => 'utilities',
                        'label' => 'Utilities Request',
                    ],
                    [
                        'value' => 'other',
                        'label' => 'Other Request',
                    ],
                ],
                'priorities' => [
                    [
                        'value' => 'normal',
                        'label' => 'Normal Priority',
                    ],
                    [
                        'value' => 'elevated',
                        'label' => 'Elevated Priority',
                    ],
                    [
                        'value' => 'urgent',
                        'label' => 'Urgent Execution',
                    ],
                ],
                'statuses' => [
                    [
                        'value' => 'pending',
                        'label' => 'Pending',
                    ],
                    [
                        'value' => 'approved',
                        'label' => 'Approved',
                    ],
                    [
                        'value' => 'rejected',
                        'label' => 'Rejected',
                    ],
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->storeRules());

        $businessRequest = DB::transaction(function () use ($request, $validated) {
            $property = Property::query()->find($validated['property_id']);

            return BusinessRequest::query()->create([
                'request_code' => $this->generateRequestCode(),
                'property_id' => $property?->id,
                'property_name' => $property?->title,
                'requested_by' => $request->user()?->id,
                'request_type' => Str::lower((string) $validated['request_type']),
                'title' => trim((string) $validated['title']),
                'description' => filled($validated['description'] ?? null)
                    ? trim((string) $validated['description'])
                    : null,
                'amount' => $validated['amount'] ?? 0,
                'priority' => Str::lower((string) ($validated['priority'] ?? 'normal')),
                'status' => 'pending',
                'expected_date' => $validated['expected_date'] ?? null,
            ]);
        });

        $businessRequest->load([
            'property:id,title,price,address,location',
            'requester:id,first_name,last_name,email',
            'reviewer:id,first_name,last_name,email',
            'expense:id,expense_code,amount,status,expense_date',
        ]);

        $this->notifyRequestRecipients($businessRequest);

        return response()->json([
            'success' => true,
            'message' => 'Request created successfully.',
            'data' => $this->transformRequest($businessRequest),
            'summary' => $this->buildSummary(),
        ], 201);
    }

    public function show(BusinessRequest $businessRequest): JsonResponse
    {
        $businessRequest->load([
            'property:id,title,price,address,location',
            'requester:id,first_name,last_name,email',
            'reviewer:id,first_name,last_name,email',
            'expense:id,expense_code,amount,status,expense_date',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Request fetched successfully.',
            'data' => $this->transformRequest($businessRequest),
        ]);
    }

    public function update(Request $request, BusinessRequest $businessRequest): JsonResponse
    {
        if ($businessRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be updated.',
            ], 422);
        }

        $validated = $request->validate($this->updateRules());

        DB::transaction(function () use ($validated, $businessRequest) {
            $payload = [];

            if (array_key_exists('property_id', $validated)) {
                $property = Property::query()->find($validated['property_id']);

                $payload['property_id'] = $property?->id;
                $payload['property_name'] = $property?->title;
            }

            if (array_key_exists('request_type', $validated)) {
                $payload['request_type'] = Str::lower((string) $validated['request_type']);
            }

            if (array_key_exists('title', $validated)) {
                $payload['title'] = trim((string) $validated['title']);
            }

            if (array_key_exists('description', $validated)) {
                $payload['description'] = filled($validated['description'])
                    ? trim((string) $validated['description'])
                    : null;
            }

            if (array_key_exists('amount', $validated)) {
                $payload['amount'] = $validated['amount'] ?? 0;
            }

            if (array_key_exists('priority', $validated)) {
                $payload['priority'] = Str::lower((string) $validated['priority']);
            }

            if (array_key_exists('expected_date', $validated)) {
                $payload['expected_date'] = $validated['expected_date'];
            }

            if ($payload !== []) {
                $businessRequest->update($payload);
            }
        });

        $businessRequest->refresh()->load([
            'property:id,title,price,address,location',
            'requester:id,first_name,last_name,email',
            'reviewer:id,first_name,last_name,email',
            'expense:id,expense_code,amount,status,expense_date',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Request updated successfully.',
            'data' => $this->transformRequest($businessRequest),
            'summary' => $this->buildSummary(),
        ]);
    }

    public function approve(Request $request, BusinessRequest $businessRequest): JsonResponse
    {
        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:3000'],
            'expense_status' => ['nullable', Rule::in(self::EXPENSE_STATUSES)],
        ]);

        if ($businessRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be approved.',
            ], 422);
        }

        $expense = DB::transaction(function () use ($request, $validated, $businessRequest) {
            $businessRequest->load([
                'property:id,title,price,address,location',
                'requester:id,first_name,last_name,email',
            ]);

            $propertyName = $businessRequest->property?->title
                ?: $businessRequest->property_name
                ?: 'Monthly Balance';

            $requesterName = $businessRequest->requester
                ? $this->userDisplayName($businessRequest->requester)
                : 'Requester';

            $expense = Expense::query()->create([
                'expense_code' => $this->generateExpenseCode(),
                'expense_date' => now()->toDateString(),
                'employee_id' => $businessRequest->requested_by,
                'employee_name' => $requesterName,
                'category' => $this->expenseCategoryFromRequestType($businessRequest->request_type),
                'amount' => $businessRequest->amount,
                'status' => Str::lower((string) ($validated['expense_status'] ?? 'paid')),
                'property_id' => $businessRequest->property_id,
                'property_name' => $propertyName,
                'description' => $this->buildExpenseDescription($businessRequest),
                'attachments' => [],
                'created_by' => $request->user()?->id,
            ]);

            $businessRequest->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()?->id,
                'reviewed_at' => now(),
                'review_note' => filled($validated['review_note'] ?? null)
                    ? trim((string) $validated['review_note'])
                    : null,
                'expense_id' => $expense->id,
            ]);

            return $expense;
        });

        $businessRequest->refresh()->load([
            'property:id,title,price,address,location',
            'requester:id,first_name,last_name,email',
            'reviewer:id,first_name,last_name,email',
            'expense:id,expense_code,amount,status,expense_date',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Request approved successfully and added to expenses.',
            'data' => $this->transformRequest($businessRequest),
            'expense' => [
                'id' => $expense->id,
                'expense_id' => $expense->expense_code,
                'amount' => (float) $expense->amount,
                'status' => Str::headline($expense->status),
                'date' => optional($expense->expense_date)->format('Y-m-d'),
            ],
            'summary' => $this->buildSummary(),
        ]);
    }

    public function reject(Request $request, BusinessRequest $businessRequest): JsonResponse
    {
        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:3000'],
        ]);

        if ($businessRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be rejected.',
            ], 422);
        }

        $businessRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'review_note' => filled($validated['review_note'] ?? null)
                ? trim((string) $validated['review_note'])
                : null,
        ]);

        $businessRequest->refresh()->load([
            'property:id,title,price,address,location',
            'requester:id,first_name,last_name,email',
            'reviewer:id,first_name,last_name,email',
            'expense:id,expense_code,amount,status,expense_date',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Request rejected successfully.',
            'data' => $this->transformRequest($businessRequest),
            'summary' => $this->buildSummary(),
        ]);
    }

    public function destroy(BusinessRequest $businessRequest): JsonResponse
    {
        if ($businessRequest->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Approved requests cannot be deleted because they are already linked to expenses.',
            ], 422);
        }

        $businessRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Request deleted successfully.',
            'summary' => $this->buildSummary(),
        ]);
    }

    private function storeRules(): array
    {
        return [
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'request_type' => ['required', Rule::in(self::REQUEST_TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'priority' => ['nullable', Rule::in(self::PRIORITIES)],
            'expected_date' => ['nullable', 'date'],
        ];
    }

    private function updateRules(): array
    {
        return [
            'property_id' => ['sometimes', 'required', 'integer', 'exists:properties,id'],
            'request_type' => ['sometimes', 'required', Rule::in(self::REQUEST_TYPES)],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'max:5000'],
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'priority' => ['sometimes', 'nullable', Rule::in(self::PRIORITIES)],
            'expected_date' => ['sometimes', 'nullable', 'date'],
        ];
    }

    private function applyFilters(Builder $query, array $validated): void
    {
        if (filled($validated['status'] ?? null)) {
            $query->where('status', Str::lower((string) $validated['status']));
        }

        if (filled($validated['priority'] ?? null)) {
            $query->where('priority', Str::lower((string) $validated['priority']));
        }

        if (filled($validated['request_type'] ?? null)) {
            $query->where('request_type', Str::lower((string) $validated['request_type']));
        }

        if (filled($validated['property_id'] ?? null)) {
            $query->where('property_id', (int) $validated['property_id']);
        }

        if (filled($validated['requested_by'] ?? null)) {
            $query->where('requested_by', (int) $validated['requested_by']);
        }

        if (filled($validated['from_date'] ?? null)) {
            $query->whereDate('created_at', '>=', $validated['from_date']);
        }

        if (filled($validated['to_date'] ?? null)) {
            $query->whereDate('created_at', '<=', $validated['to_date']);
        }

        $search = trim((string) ($validated['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search) {
                $like = '%' . $search . '%';

                $searchQuery
                    ->where('request_code', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('property_name', 'like', $like)
                    ->orWhere('request_type', 'like', $like)
                    ->orWhereHas('property', function (Builder $propertyQuery) use ($like) {
                        $propertyQuery->where('title', 'like', $like);
                    })
                    ->orWhereHas('requester', function (Builder $requesterQuery) use ($like) {
                        $requesterQuery
                            ->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    });
            });
        }
    }

    private function transformRequest(BusinessRequest $businessRequest): array
    {
        return [
            'id' => $businessRequest->id,
            'request_id' => $businessRequest->request_code,
            'request_type' => $businessRequest->request_type,
            'request_type_label' => $this->requestTypeLabel($businessRequest->request_type),
            'title' => $businessRequest->title,
            'description' => $businessRequest->description,
            'amount' => (float) $businessRequest->amount,
            'priority' => $businessRequest->priority,
            'priority_label' => $this->priorityLabel($businessRequest->priority),
            'status' => $businessRequest->status,
            'status_label' => Str::headline($businessRequest->status),
            'expected_date' => optional($businessRequest->expected_date)->format('Y-m-d'),

            'property_id' => $businessRequest->property_id,
            'property' => $businessRequest->property?->title ?: $businessRequest->property_name,

            'requested_by' => $businessRequest->requested_by,
            'requested_by_name' => $businessRequest->requester
                ? $this->userDisplayName($businessRequest->requester)
                : null,
            'requested_by_email' => $businessRequest->requester?->email,

            'reviewed_by' => $businessRequest->reviewed_by,
            'reviewed_by_name' => $businessRequest->reviewer
                ? $this->userDisplayName($businessRequest->reviewer)
                : null,
            'reviewed_by_email' => $businessRequest->reviewer?->email,
            'review_note' => $businessRequest->review_note,
            'reviewed_at' => $businessRequest->reviewed_at,

            'expense_id' => $businessRequest->expense_id,
            'expense' => $businessRequest->expense
                ? [
                    'id' => $businessRequest->expense->id,
                    'expense_id' => $businessRequest->expense->expense_code,
                    'amount' => (float) $businessRequest->expense->amount,
                    'status' => Str::headline($businessRequest->expense->status),
                    'date' => optional($businessRequest->expense->expense_date)->format('Y-m-d'),
                ]
                : null,

            'created_at' => $businessRequest->created_at,
            'updated_at' => $businessRequest->updated_at,
        ];
    }

    private function buildSummary(): array
    {
        return [
            'total_requests' => BusinessRequest::query()->count(),
            'pending_requests' => BusinessRequest::query()->where('status', 'pending')->count(),
            'approved_requests' => BusinessRequest::query()->where('status', 'approved')->count(),
            'rejected_requests' => BusinessRequest::query()->where('status', 'rejected')->count(),
            'pending_amount' => (float) BusinessRequest::query()->where('status', 'pending')->sum('amount'),
            'approved_amount' => (float) BusinessRequest::query()->where('status', 'approved')->sum('amount'),
        ];
    }

    private function notifyRequestRecipients(BusinessRequest $businessRequest): void
    {
        try {
            foreach (self::REQUEST_NOTIFICATION_EMAILS as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                Notification::route('mail', $email)
                    ->notify(new BusinessRequestCreatedNotification($businessRequest));
            }
        } catch (Throwable $exception) {
            Log::warning('Business request email notification failed.', [
                'request_id' => $businessRequest->id,
                'request_code' => $businessRequest->request_code,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function generateRequestCode(): string
    {
        $year = now()->format('Y');
        $nextNumber = ((int) BusinessRequest::query()->max('id')) + 1;

        do {
            $code = sprintf('R-%s-%05d', $year, $nextNumber);
            $nextNumber++;
        } while (BusinessRequest::query()->where('request_code', $code)->exists());

        return $code;
    }

    private function generateExpenseCode(): string
    {
        $year = now()->format('Y');
        $nextNumber = ((int) Expense::query()->max('id')) + 1;

        do {
            $code = sprintf('E-%s-%05d', $year, $nextNumber);
            $nextNumber++;
        } while (Expense::query()->where('expense_code', $code)->exists());

        return $code;
    }

    private function expenseCategoryFromRequestType(string $requestType): string
    {
        return match (Str::lower($requestType)) {
            'salary' => 'salary',
            'maintenance' => 'maintenance',
            'marketing' => 'marketing',
            'utilities' => 'utilities',
            default => 'other',
        };
    }

    private function buildExpenseDescription(BusinessRequest $businessRequest): string
    {
        $lines = [
            'Approved request: ' . $businessRequest->title,
            'Request ID: ' . $businessRequest->request_code,
            'Request type: ' . $this->requestTypeLabel($businessRequest->request_type),
        ];

        if ($businessRequest->description) {
            $lines[] = 'Description: ' . $businessRequest->description;
        }

        return implode("\n", $lines);
    }

    private function requestTypeLabel(string $requestType): string
    {
        return match (Str::lower($requestType)) {
            'salary' => 'Salary Request',
            'maintenance' => 'Maintenance Request',
            'purchase' => 'Purchase Request',
            'project' => 'Project Request',
            'marketing' => 'Marketing Request',
            'utilities' => 'Utilities Request',
            default => 'Other Request',
        };
    }

    private function priorityLabel(string $priority): string
    {
        return match (Str::lower($priority)) {
            'urgent' => 'Urgent Execution',
            'elevated' => 'Elevated Priority',
            default => 'Normal Priority',
        };
    }

    private function userDisplayName(User $user): string
    {
        $name = trim(implode(' ', array_filter([
            $user->first_name,
            $user->last_name,
        ])));

        return $name !== '' ? $name : ($user->email ?: 'User');
    }
}