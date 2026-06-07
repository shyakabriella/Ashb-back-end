<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    private const STATUSES = [
        'paid',
        'pending',
        'overdue',
    ];

    private const CATEGORIES = [
        'maintenance',
        'repairs',
        'utilities',
        'marketing',
        'transport',
        'office',
        'other',
    ];

    /**
     * List expenses and return the finance summary used by the expense page.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'category' => ['nullable', 'string', 'max:100'],
            'employee_id' => ['nullable', 'integer', 'exists:users,id'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Expense::query()
            ->with([
                'employee:id,first_name,last_name,email',
                'property:id,title,price',
                'creator:id,first_name,last_name,email',
            ])
            ->latest('expense_date')
            ->latest('id');

        $this->applyFilters($query, $validated);

        $expenses = $query
            ->paginate((int) ($validated['per_page'] ?? 20))
            ->withQueryString();

        $expenses->setCollection(
            $expenses->getCollection()
                ->map(fn (Expense $expense) => $this->transformExpense($expense))
        );

        return response()->json([
            'success' => true,
            'message' => 'Expenses fetched successfully.',
            'data' => $expenses,
            'summary' => $this->buildSummary(),
        ]);
    }

    /**
     * Save a new expense and optional receipt files.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->storeRules());

        $expense = DB::transaction(function () use ($request, $validated) {
            $employee = isset($validated['employee_id'])
                ? User::query()->find($validated['employee_id'])
                : null;

            $property = isset($validated['property_id'])
                ? Property::query()->find($validated['property_id'])
                : null;

            $expenseCode = filled($validated['expenseId'] ?? null)
                ? trim((string) $validated['expenseId'])
                : $this->generateExpenseCode();
            $attachments = $this->storeAttachments(
                files: $request->file('attachments', []),
                expenseCode: $expenseCode
            );

            return Expense::query()->create([
                'expense_code' => $expenseCode,
                'expense_date' => $validated['date'],
                'employee_id' => $employee?->id,
                'employee_name' => $employee
                    ? $this->userDisplayName($employee)
                    : trim((string) (
                        $validated['employee_name']
                        ?? $validated['employee']
                        ?? ''
                    )),
                'category' => Str::lower(trim((string) $validated['category'])),
                'amount' => $validated['amount'],
                'status' => Str::lower((string) $validated['status']),
                'property_id' => $property?->id,
                'property_name' => $property?->title
                    ?: trim((string) (
                        $validated['property_name']
                        ?? $validated['property']
                        ?? ''
                    )),
                'description' => filled($validated['description'] ?? null)
                    ? trim((string) $validated['description'])
                    : null,
                'attachments' => $attachments,
                'created_by' => $request->user()?->id,
            ]);
        });

        $expense->load([
            'employee:id,first_name,last_name,email',
            'property:id,title,price',
            'creator:id,first_name,last_name,email',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense created successfully.',
            'data' => $this->transformExpense($expense),
            'summary' => $this->buildSummary(),
        ], 201);
    }

    /**
     * Show one expense.
     */
    public function show(Expense $expense): JsonResponse
    {
        $expense->load([
            'employee:id,first_name,last_name,email',
            'property:id,title,price',
            'creator:id,first_name,last_name,email',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense fetched successfully.',
            'data' => $this->transformExpense($expense),
        ]);
    }

    /**
     * Update only the submitted expense fields.
     * Existing attachments remain unless their paths are explicitly removed.
     */
    public function update(Request $request, Expense $expense): JsonResponse
    {
        $validated = $request->validate($this->updateRules());

        DB::transaction(function () use ($request, $validated, $expense) {
            $payload = [];

            if (array_key_exists('date', $validated)) {
                $payload['expense_date'] = $validated['date'];
            }

            if (array_key_exists('employee_id', $validated)) {
                $employee = filled($validated['employee_id'])
                    ? User::query()->find($validated['employee_id'])
                    : null;

                $payload['employee_id'] = $employee?->id;
                $payload['employee_name'] = $employee
                    ? $this->userDisplayName($employee)
                    : trim((string) (
                        $validated['employee_name']
                        ?? $validated['employee']
                        ?? ''
                    ));
            } elseif (
                array_key_exists('employee_name', $validated)
                || array_key_exists('employee', $validated)
            ) {
                $payload['employee_name'] = trim((string) (
                    $validated['employee_name']
                    ?? $validated['employee']
                    ?? ''
                ));
            }

            if (array_key_exists('category', $validated)) {
                $payload['category'] = Str::lower(trim((string) $validated['category']));
            }

            if (array_key_exists('amount', $validated)) {
                $payload['amount'] = $validated['amount'];
            }

            if (array_key_exists('status', $validated)) {
                $payload['status'] = Str::lower((string) $validated['status']);
            }

            if (array_key_exists('property_id', $validated)) {
                $property = filled($validated['property_id'])
                    ? Property::query()->find($validated['property_id'])
                    : null;

                $payload['property_id'] = $property?->id;
                $payload['property_name'] = $property?->title
                    ?: trim((string) (
                        $validated['property_name']
                        ?? $validated['property']
                        ?? ''
                    ));
            } elseif (
                array_key_exists('property_name', $validated)
                || array_key_exists('property', $validated)
            ) {
                $payload['property_name'] = trim((string) (
                    $validated['property_name']
                    ?? $validated['property']
                    ?? ''
                ));
            }

            if (array_key_exists('description', $validated)) {
                $payload['description'] = filled($validated['description'])
                    ? trim((string) $validated['description'])
                    : null;
            }

            $existingAttachments = collect($expense->attachments ?? []);
            $pathsToRemove = collect($validated['remove_attachment_paths'] ?? [])
                ->filter(fn ($path) => is_string($path) && trim($path) !== '')
                ->map(fn ($path) => trim($path));

            if ($pathsToRemove->isNotEmpty()) {
                foreach ($pathsToRemove as $path) {
                    Storage::disk('public')->delete($path);
                }

                $existingAttachments = $existingAttachments
                    ->reject(fn ($attachment) => in_array(
                        data_get($attachment, 'path'),
                        $pathsToRemove->all(),
                        true
                    ))
                    ->values();
            }

            $newAttachments = collect($this->storeAttachments(
                files: $request->file('attachments', []),
                expenseCode: $expense->expense_code
            ));

            if ($pathsToRemove->isNotEmpty() || $newAttachments->isNotEmpty()) {
                $payload['attachments'] = $existingAttachments
                    ->concat($newAttachments)
                    ->values()
                    ->all();
            }

            if ($payload !== []) {
                $expense->update($payload);
            }
        });

        $expense->refresh()->load([
            'employee:id,first_name,last_name,email',
            'property:id,title,price',
            'creator:id,first_name,last_name,email',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense updated successfully.',
            'data' => $this->transformExpense($expense),
            'summary' => $this->buildSummary(),
        ]);
    }

    /**
     * Delete an expense and its uploaded files.
     */
    public function destroy(Expense $expense): JsonResponse
    {
        DB::transaction(function () use ($expense) {
            foreach ($expense->attachments ?? [] as $attachment) {
                $path = data_get($attachment, 'path');

                if (is_string($path) && $path !== '') {
                    Storage::disk('public')->delete($path);
                }
            }

            $expense->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted successfully.',
            'summary' => $this->buildSummary(),
        ]);
    }

    /**
     * Return only the totals used by dashboards and create-expense forms.
     */
    public function summary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Expense summary fetched successfully.',
            'data' => $this->buildSummary(),
        ]);
    }

    private function storeRules(): array
    {
        return [
            'expenseId' => ['nullable', 'string', 'max:50', 'unique:expenses,expense_code'],
            'date' => ['required', 'date'],
            'employee_id' => ['nullable', 'integer', 'exists:users,id'],
            'employee' => ['nullable', 'string', 'max:255'],
            'employee_name' => ['nullable', 'string', 'max:255', 'required_without_all:employee_id,employee'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'status' => ['required', Rule::in([
                ...self::STATUSES,
                'Paid',
                'Pending',
                'Overdue',
            ])],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'property' => ['nullable', 'string', 'max:255'],
            'property_name' => ['nullable', 'string', 'max:255', 'required_without_all:property_id,property'],
            'description' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                'file',
                'mimes:pdf,jpg,jpeg,png,webp',
                'max:10240',
            ],
        ];
    }

    private function updateRules(): array
    {
        return [
            'date' => ['sometimes', 'required', 'date'],
            'employee_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'employee' => ['sometimes', 'nullable', 'string', 'max:255'],
            'employee_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'required', Rule::in(self::CATEGORIES)],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'status' => ['sometimes', 'required', Rule::in([
                ...self::STATUSES,
                'Paid',
                'Pending',
                'Overdue',
            ])],
            'property_id' => ['sometimes', 'nullable', 'integer', 'exists:properties,id'],
            'property' => ['sometimes', 'nullable', 'string', 'max:255'],
            'property_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                'file',
                'mimes:pdf,jpg,jpeg,png,webp',
                'max:10240',
            ],
            'remove_attachment_paths' => ['nullable', 'array'],
            'remove_attachment_paths.*' => ['string', 'max:1000'],
        ];
    }

    private function applyFilters(Builder $query, array $validated): void
    {
        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        if (filled($validated['category'] ?? null)) {
            $query->where('category', $validated['category']);
        }

        if (filled($validated['employee_id'] ?? null)) {
            $query->where('employee_id', (int) $validated['employee_id']);
        }

        if (filled($validated['property_id'] ?? null)) {
            $query->where('property_id', (int) $validated['property_id']);
        }

        if (filled($validated['from_date'] ?? null)) {
            $query->whereDate('expense_date', '>=', $validated['from_date']);
        }

        if (filled($validated['to_date'] ?? null)) {
            $query->whereDate('expense_date', '<=', $validated['to_date']);
        }

        $search = trim((string) ($validated['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search) {
                $like = '%' . $search . '%';

                $searchQuery
                    ->where('expense_code', 'like', $like)
                    ->orWhere('employee_name', 'like', $like)
                    ->orWhere('property_name', 'like', $like)
                    ->orWhere('category', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('employee', function (Builder $employeeQuery) use ($like) {
                        $employeeQuery
                            ->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    })
                    ->orWhereHas('property', function (Builder $propertyQuery) use ($like) {
                        $propertyQuery->where('title', 'like', $like);
                    });
            });
        }
    }

    private function buildSummary(): array
    {
        $totalPropertyPrice = (float) Property::query()
            ->whereNotNull('price')
            ->sum('price');

        $consumedAmount = (float) Expense::query()->sum('amount');

        return [
            'total_property_price' => $totalPropertyPrice,
            'consumed_amount' => $consumedAmount,
            'balance' => $totalPropertyPrice - $consumedAmount,
            'expenses_count' => Expense::query()->count(),
            'properties_count' => Property::query()->count(),
        ];
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

    /**
     * @param UploadedFile|array<UploadedFile>|null $files
     */
    private function storeAttachments(mixed $files, string $expenseCode): array
    {
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        if (!is_array($files)) {
            return [];
        }

        $stored = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store(
                'expenses/' . Str::slug($expenseCode),
                'public'
            );

            $stored[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
                'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        }

        return $stored;
    }

    private function transformExpense(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'expense_id' => $expense->expense_code,
            'date' => optional($expense->expense_date)->format('Y-m-d'),
            'employee_id' => $expense->employee_id,
            'employee' => $expense->employee
                ? $this->userDisplayName($expense->employee)
                : $expense->employee_name,
            'employee_email' => $expense->employee?->email,
            'category' => $expense->category,
            'amount' => (float) $expense->amount,
            'status' => Str::headline($expense->status),
            'property_id' => $expense->property_id,
            'property' => $expense->property?->title ?: $expense->property_name,
            'description' => $expense->description,
            'attachments' => collect($expense->attachments ?? [])
                ->map(function ($attachment) {
                    $path = data_get($attachment, 'path');

                    return [
                        'name' => data_get($attachment, 'name'),
                        'path' => $path,
                        'url' => is_string($path) && $path !== ''
                            ? Storage::disk('public')->url($path)
                            : data_get($attachment, 'url'),
                        'mime_type' => data_get($attachment, 'mime_type'),
                        'size' => data_get($attachment, 'size'),
                    ];
                })
                ->values()
                ->all(),
            'created_by' => $expense->created_by,
            'created_by_name' => $expense->creator
                ? $this->userDisplayName($expense->creator)
                : null,
            'created_at' => $expense->created_at,
            'updated_at' => $expense->updated_at,
        ];
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