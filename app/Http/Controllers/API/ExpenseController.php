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
use Illuminate\Support\Facades\Http;
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

    private const AI_FILE_EXTENSIONS = [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'webp',
        'txt',
        'csv',
        'json',
        'xml',
        'md',
        'log',
        'rtf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
    ];

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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->storeRules());

        $expense = DB::transaction(function () use ($request, $validated) {
            $employee = filled($validated['employee_id'] ?? null)
                ? User::query()->find($validated['employee_id'])
                : null;

            $property = filled($validated['property_id'] ?? null)
                ? Property::query()->find($validated['property_id'])
                : null;

            $expenseCode = filled($validated['expenseId'] ?? null)
                ? trim((string) $validated['expenseId'])
                : $this->generateExpenseCode();

            $attachments = $this->storeAttachments(
                files: $request->file('attachments', []),
                expenseCode: $expenseCode
            );

            $employeeName = $employee
                ? $this->userDisplayName($employee)
                : trim((string) (
                    $validated['employee_name']
                    ?? $validated['employee']
                    ?? ''
                ));

            $propertyName = $property?->title
                ?: trim((string) (
                    $validated['property_name']
                    ?? $validated['property']
                    ?? ''
                ));

            if ($propertyName === '') {
                $propertyName = 'Monthly Balance';
            }

            return Expense::query()->create([
                'expense_code' => $expenseCode,
                'expense_date' => $validated['date'],
                'employee_id' => $employee?->id,
                'employee_name' => $employeeName,
                'category' => Str::lower(trim((string) $validated['category'])),
                'amount' => $validated['amount'],
                'status' => Str::lower((string) $validated['status']),
                'property_id' => $property?->id,
                'property_name' => $propertyName,
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

    public function generateDescription(Request $request): JsonResponse
    {
        $allowedExtensions = implode(',', self::AI_FILE_EXTENSIONS);

        $validated = $request->validate([
            'files' => ['required_without:images', 'array', 'min:1', 'max:5'],
            'files.*' => [
                'required',
                'file',
                'mimes:' . $allowedExtensions,
                'max:20480',
            ],
            'images' => ['required_without:files', 'array', 'min:1', 'max:5'],
            'images.*' => [
                'required',
                'file',
                'mimes:' . $allowedExtensions,
                'max:20480',
            ],
            'purpose' => ['nullable', 'string', 'max:100'],
            'instruction' => ['nullable', 'string', 'max:2000'],
        ], [
            'files.required_without' => 'Please upload at least one supported file.',
            'images.required_without' => 'Please upload at least one supported file.',
            'files.*.mimes' => 'Only PDF, image, text, Word, Excel, PowerPoint, RTF, CSV, JSON, XML, Markdown, and LOG files are supported.',
            'images.*.mimes' => 'Only PDF, image, text, Word, Excel, PowerPoint, RTF, CSV, JSON, XML, Markdown, and LOG files are supported.',
        ]);

        $apiKey = trim((string) (
            config('services.ashbhub_ai.api_key')
            ?: env('ASHBHUB_AI_API_KEY')
            ?: ''
        ));

        $model = trim((string) (
            config('services.ashbhub_ai.model')
            ?: env('ASHBHUB_AI_MODEL')
            ?: ''
        ));

        if ($apiKey === '' || $model === '') {
            return response()->json([
                'success' => false,
                'message' => 'ASHBHUB AI is not configured on the server.',
            ], 500);
        }

        $uploadedFiles = $request->file('files', []);

        if (empty($uploadedFiles)) {
            $uploadedFiles = $request->file('images', []);
        }

        $uploadedFiles = $this->normalizeUploadedFiles($uploadedFiles);

        if (count($uploadedFiles) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Please upload at least one supported file.',
            ], 422);
        }

        $totalFileBytes = collect($uploadedFiles)->sum(function (UploadedFile $file) {
            return (int) $file->getSize();
        });

        if ($totalFileBytes > 14 * 1024 * 1024) {
            return response()->json([
                'success' => false,
                'message' => 'The selected files are too large for ASHBHUB AI description generation. Please upload fewer or smaller files.',
            ], 422);
        }

        $instruction = trim((string) ($validated['instruction'] ?? ''));

        if ($instruction === '') {
            $instruction = implode(' ', [
                'Read the uploaded expense document, receipt, payment proof, invoice, image, PDF, spreadsheet, or text file.',
                'Generate one short professional expense description.',
                'Include vendor, item or service, amount, date, and reason only if visible.',
                'Do not invent missing information.',
                'Keep the description short and clear.',
            ]);
        }

        $parts = [
            [
                'text' => $instruction,
            ],
        ];

        foreach ($uploadedFiles as $file) {
            $realPath = $file->getRealPath();

            if (!$realPath || !is_file($realPath)) {
                continue;
            }

            $mimeType = $file->getMimeType()
                ?: $file->getClientMimeType()
                ?: $this->guessMimeTypeFromFileName($file->getClientOriginalName());

            if ($mimeType === 'application/octet-stream') {
                $mimeType = $this->guessMimeTypeFromFileName($file->getClientOriginalName());
            }

            $parts[] = [
                'inline_data' => [
                    'mime_type' => $mimeType,
                    'data' => base64_encode((string) file_get_contents($realPath)),
                ],
            ];
        }

        if (count($parts) <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'No readable file was found.',
            ], 422);
        }

        $response = $this->sendAshbhubAiRequest(
            model: $model,
            apiKey: $apiKey,
            parts: $parts,
            timeout: 60,
            maxOutputTokens: 400
        );

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'ASHBHUB AI could not generate the description. The uploaded file type may not be readable.',
                'error' => data_get($response->json(), 'error.message', $response->body()),
            ], $response->status() >= 500 ? 502 : 422);
        }

        $description = $this->extractAiText($response->json());

        if ($description === '') {
            return response()->json([
                'success' => false,
                'message' => 'ASHBHUB AI did not return a usable description.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Description generated successfully.',
            'data' => [
                'description' => $description,
            ],
        ]);
    }

    public function generatePreview(Request $request, Expense $expense): JsonResponse
    {
        $expense->load([
            'employee:id,first_name,last_name,email',
            'property:id,title,price',
            'creator:id,first_name,last_name,email',
        ]);

        $apiKey = trim((string) (
            config('services.ashbhub_ai.api_key')
            ?: env('ASHBHUB_AI_API_KEY')
            ?: ''
        ));

        $model = trim((string) (
            config('services.ashbhub_ai.model')
            ?: env('ASHBHUB_AI_MODEL')
            ?: ''
        ));

        if ($apiKey === '' || $model === '') {
            return response()->json([
                'success' => false,
                'message' => 'ASHBHUB AI is not configured on the server.',
            ], 500);
        }

        $employeeName = $expense->employee
            ? $this->userDisplayName($expense->employee)
            : $expense->employee_name;

        $propertyName = $expense->property?->title
            ?: $expense->property_name
            ?: 'Monthly Balance';

        $prompt = implode("\n", [
            'Generate one short professional note for an expense preview PDF.',
            'Do not invent missing details.',
            'Keep it clear, business-like, and maximum 2 sentences.',
            '',
            'Expense details:',
            'Expense ID: ' . $expense->expense_code,
            'Date: ' . optional($expense->expense_date)->format('Y-m-d'),
            'Employee in use: ' . ($employeeName ?: 'No employee'),
            'Source: ' . $propertyName,
            'Category: ' . $expense->category,
            'Amount RWF: ' . $expense->amount,
            'Status: ' . $expense->status,
            'Description: ' . ($expense->description ?: 'No description provided.'),
        ]);

        $response = $this->sendAshbhubAiRequest(
            model: $model,
            apiKey: $apiKey,
            parts: [
                [
                    'text' => $prompt,
                ],
            ],
            timeout: 45,
            maxOutputTokens: 180
        );

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'ASHBHUB AI could not generate the expense preview note.',
                'error' => data_get($response->json(), 'error.message', $response->body()),
            ], $response->status() >= 500 ? 502 : 422);
        }

        $previewText = $this->extractAiText($response->json());

        if ($previewText === '') {
            return response()->json([
                'success' => false,
                'message' => 'ASHBHUB AI did not return a usable preview note.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Expense preview note generated successfully.',
            'data' => [
                'preview_text' => $previewText,
            ],
        ]);
    }

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

                $propertyName = $property?->title
                    ?: trim((string) (
                        $validated['property_name']
                        ?? $validated['property']
                        ?? ''
                    ));

                $payload['property_id'] = $property?->id;
                $payload['property_name'] = $propertyName !== ''
                    ? $propertyName
                    : 'Monthly Balance';
            } elseif (
                array_key_exists('property_name', $validated)
                || array_key_exists('property', $validated)
            ) {
                $propertyName = trim((string) (
                    $validated['property_name']
                    ?? $validated['property']
                    ?? ''
                ));

                $payload['property_name'] = $propertyName !== ''
                    ? $propertyName
                    : 'Monthly Balance';
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
        $allowedExtensions = implode(',', self::AI_FILE_EXTENSIONS);

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
            'property_name' => ['nullable', 'string', 'max:255'],

            'affects_balance' => ['nullable', 'boolean'],
            'balance_source' => ['nullable', 'string', 'max:100'],

            'description' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                'file',
                'mimes:' . $allowedExtensions,
                'max:20480',
            ],
        ];
    }

    private function updateRules(): array
    {
        $allowedExtensions = implode(',', self::AI_FILE_EXTENSIONS);

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

            'affects_balance' => ['sometimes', 'nullable', 'boolean'],
            'balance_source' => ['sometimes', 'nullable', 'string', 'max:100'],

            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                'file',
                'mimes:' . $allowedExtensions,
                'max:20480',
            ],
            'remove_attachment_paths' => ['nullable', 'array'],
            'remove_attachment_paths.*' => ['string', 'max:1000'],
        ];
    }

    private function applyFilters(Builder $query, array $validated): void
    {
        if (filled($validated['status'] ?? null)) {
            $query->where('status', Str::lower((string) $validated['status']));
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
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $totalPropertyPrice = (float) Property::query()
            ->whereNotNull('price')
            ->sum('price');

        $monthlyExpensesQuery = Expense::query()
            ->whereDate('expense_date', '>=', $monthStart)
            ->whereDate('expense_date', '<=', $monthEnd);

        $consumedAmount = (float) (clone $monthlyExpensesQuery)->sum('amount');

        return [
            'total_property_price' => $totalPropertyPrice,
            'consumed_amount' => $consumedAmount,
            'balance' => $totalPropertyPrice - $consumedAmount,
            'expenses_count' => (clone $monthlyExpensesQuery)->count(),
            'properties_count' => Property::query()->count(),
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
            'month_label' => now()->format('F Y'),
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
        $files = $this->normalizeUploadedFiles($files);

        if ($files === []) {
            return [];
        }

        $stored = [];

        foreach ($files as $file) {
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

    private function sendAshbhubAiRequest(
        string $model,
        string $apiKey,
        array $parts,
        int $timeout = 60,
        int $maxOutputTokens = 400
    ) {
        $modelPath = Str::startsWith($model, 'models/')
            ? $model
            : 'models/' . $model;

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/{$modelPath}:generateContent";

        return Http::timeout($timeout)
            ->withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post($endpoint, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => $parts,
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => $maxOutputTokens,
                ],
            ]);
    }

    private function extractAiText(array $payload): string
    {
        $parts = data_get($payload, 'candidates.0.content.parts', []);

        if (!is_array($parts)) {
            return '';
        }

        $text = collect($parts)
            ->map(fn ($part) => is_array($part) ? ($part['text'] ?? '') : '')
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->implode("\n");

        $text = trim(preg_replace('/\s+/', ' ', $text) ?: '');

        return Str::limit($text, 1200, '');
    }

    private function guessMimeTypeFromFileName(string $fileName): string
    {
        $extension = Str::lower(pathinfo($fileName, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'txt', 'log' => 'text/plain',
            'csv' => 'text/csv',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'md' => 'text/markdown',
            'rtf' => 'application/rtf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            default => 'application/octet-stream',
        };
    }

    /**
     * @param mixed $files
     * @return array<int, UploadedFile>
     */
    private function normalizeUploadedFiles(mixed $files): array
    {
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (!is_array($files)) {
            return [];
        }

        return collect($files)
            ->flatten()
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->values()
            ->all();
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