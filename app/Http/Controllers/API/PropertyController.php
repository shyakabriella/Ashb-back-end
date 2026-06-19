<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PropertyController extends BaseController
{
    /**
     * Display a lightweight paginated property list.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:all,available,fully_booked,inactive',
            'location' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation Error.',
                $validator->errors(),
                422
            );
        }

        $query = Property::query()
            ->select([
                'id',
                'title',
                'slug',
                'href',
                'price',
                'address',
                'location',
                'manager_name',
                'manager_email',
                'property_email',
                'units',
                'occupancy',
                'status',
                'is_favorite',
                'created_at',
                'updated_at',
            ])
            ->selectRaw(
                "CASE WHEN image IS NULL OR image = '' THEN 0 ELSE 1 END AS has_image"
            )
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($subQuery) use ($search) {
                if (is_numeric($search)) {
                    $subQuery->orWhere('id', (int) $search);
                }

                $subQuery
                    ->orWhere('title', 'like', '%' . $search . '%')
                    ->orWhere('address', 'like', '%' . $search . '%')
                    ->orWhere('location', 'like', '%' . $search . '%')
                    ->orWhere('manager_name', 'like', '%' . $search . '%')
                    ->orWhere('manager_email', 'like', '%' . $search . '%')
                    ->orWhere('property_email', 'like', '%' . $search . '%')
                    ->orWhere('slug', 'like', '%' . $search . '%');
            });
        }

        if (
            $request->filled('status') &&
            $request->input('status') !== 'all'
        ) {
            $query->where('status', $request->input('status'));
        }

        if (
            $request->filled('location') &&
            strtolower((string) $request->input('location')) !== 'all'
        ) {
            $location = trim((string) $request->input('location'));

            $query->where('location', 'like', '%' . $location . '%');
        }

        $perPage = min(
            max((int) $request->input('per_page', 12), 1),
            50
        );

        $properties = $query
            ->paginate($perPage)
            ->withQueryString();

        $properties->setCollection(
            $properties->getCollection()->map(
                fn (Property $property) => $this->transformProperty(
                    $property,
                    true
                )
            )
        );

        return $this->sendResponse(
            $properties,
            'Properties retrieved successfully.'
        );
    }

    /**
     * Serve the real database image separately from the property JSON list.
     */
    public function image(Property $property)
    {
        $value = trim((string) $property->image);

        if ($value === '') {
            return response()->json([
                'success' => false,
                'message' => 'Property image not found.',
            ], 404);
        }

        if (Str::startsWith($value, 'data:image/')) {
            $storedPath = $this->convertLegacyBase64Image($property, $value);

            if ($storedPath === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'The stored property image is invalid.',
                ], 422);
            }

            return $this->publicStorageImageResponse($storedPath);
        }

        $storagePath = $this->extractPublicStoragePath($value);

        if (
            $storagePath !== null &&
            Storage::disk('public')->exists($storagePath)
        ) {
            return $this->publicStorageImageResponse($storagePath);
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return redirect()
                ->away($value)
                ->withHeaders([
                    'Cache-Control' => 'public, max-age=3600',
                ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Property image file not found.',
        ], 404);
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
            'manager_name' => 'nullable|string|max:255',
            'manager_email' => 'nullable|email|max:255',
            'property_email' => 'nullable|email|max:255',
            'units' => 'required|integer|min:0',
            'occupancy' => 'nullable|integer|min:0|max:100',
            'status' => 'nullable|string|in:available,fully_booked,inactive',
            'description' => 'nullable|string',
            'is_favorite' => 'nullable|boolean',
            'href' => 'nullable|string|max:255|unique:properties,href',
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation Error.',
                $validator->errors(),
                422
            );
        }

        $data = $validator->validated();

        $occupancy = array_key_exists('occupancy', $data) &&
            $data['occupancy'] !== null
                ? (int) $data['occupancy']
                : 0;

        $description = array_key_exists('description', $data) &&
            trim((string) $data['description']) !== ''
                ? trim((string) $data['description'])
                : null;

        $storedImage = $this->storePropertyImage(
            $data['image'] ?? null
        );

        $status = $data['status'] ??
            ($occupancy >= 100 ? 'fully_booked' : 'available');

        $property = Property::create([
            'title' => trim((string) $data['title']),
            'slug' => $this->generateUniqueSlug($data['title']),
            'href' => $data['href'] ?? null,
            'image' => $storedImage,
            'price' => $data['price'] ?? null,
            'address' => trim((string) $data['address']),
            'location' => $this->nullableString($data['location'] ?? null),
            'manager_name' => $this->nullableString($data['manager_name'] ?? null),
            'manager_email' => $this->nullableString($data['manager_email'] ?? null),
            'property_email' => $this->nullableString($data['property_email'] ?? null),
            'units' => (int) $data['units'],
            'occupancy' => $occupancy,
            'status' => $status,
            'description' => $description,
            'is_favorite' => (bool) ($data['is_favorite'] ?? false),
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
            return $this->sendError('Property not found.', [], 404);
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
            return $this->sendError('Property not found.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'image' => 'sometimes|nullable|string',
            'price' => 'sometimes|nullable|numeric|min:0',
            'address' => 'sometimes|required|string|max:255',
            'location' => 'sometimes|nullable|string|max:255',
            'manager_name' => 'sometimes|nullable|string|max:255',
            'manager_email' => 'sometimes|nullable|email|max:255',
            'property_email' => 'sometimes|nullable|email|max:255',
            'units' => 'sometimes|required|integer|min:0',
            'occupancy' => 'sometimes|nullable|integer|min:0|max:100',
            'status' => 'sometimes|nullable|string|in:available,fully_booked,inactive',
            'description' => 'sometimes|nullable|string',
            'is_favorite' => 'sometimes|nullable|boolean',
            'href' => 'sometimes|nullable|string|max:255|unique:properties,href,' . $property->id,
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation Error.',
                $validator->errors(),
                422
            );
        }

        $data = $validator->validated();

        if (array_key_exists('title', $data)) {
            $data['title'] = trim((string) $data['title']);
            $data['slug'] = $this->generateUniqueSlug(
                $data['title'],
                (int) $property->id
            );
        }

        if (array_key_exists('address', $data)) {
            $data['address'] = trim((string) $data['address']);
        }

        foreach ([
            'location',
            'manager_name',
            'manager_email',
            'property_email',
            'description',
        ] as $nullableTextField) {
            if (array_key_exists($nullableTextField, $data)) {
                $data[$nullableTextField] = $this->nullableString(
                    $data[$nullableTextField] ?? null
                );
            }
        }

        if (array_key_exists('occupancy', $data)) {
            $data['occupancy'] = $data['occupancy'] === null
                ? 0
                : (int) $data['occupancy'];

            if (
                !array_key_exists('status', $data) &&
                $property->status !== 'inactive'
            ) {
                $data['status'] = $data['occupancy'] >= 100
                    ? 'fully_booked'
                    : 'available';
            }
        }

        if (array_key_exists('units', $data)) {
            $data['units'] = (int) $data['units'];
        }

        if (array_key_exists('is_favorite', $data)) {
            $data['is_favorite'] = (bool) $data['is_favorite'];
        }

        if (array_key_exists('image', $data)) {
            $oldImage = $property->image;
            $newImage = $this->storePropertyImage($data['image']);

            $data['image'] = $newImage;

            if ($oldImage && $oldImage !== $newImage) {
                $this->deleteStoredPropertyImage($oldImage);
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
     * Force-send or resend an invoice to the property email.
     */
    public function pushInvoice(Request $request, Property $property)
    {
        $validator = Validator::make($request->all(), [
            'invoice_id' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric|min:0',
            'invoice_date' => 'nullable|string|max:255',
            'due_date' => 'nullable|string|max:255',
            'force' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation Error.',
                $validator->errors(),
                422
            );
        }

        $data = $validator->validated();

        $recipient = $this->nullableString($property->property_email)
            ?: $this->nullableString($property->manager_email);

        if (!$recipient || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return $this->sendError(
                'Property email not found.',
                [
                    'property_email' => [
                        'Please add a valid property email or manager email before pushing invoice.',
                    ],
                ],
                422
            );
        }

        $invoice = [
            'invoice_id' => $data['invoice_id'] ?? 'INV-PROP-' . $property->id,
            'invoice_date' => $data['invoice_date'] ?? now()->toDateString(),
            'due_date' => $data['due_date'] ?? now()->addDays(7)->toDateString(),
            'amount' => array_key_exists('amount', $data)
                ? (float) $data['amount']
                : (float) ($property->price ?? 0),
            'recipient' => $recipient,
        ];

        try {
            Mail::html(
                $this->buildInvoiceEmailHtml($property, $invoice),
                function ($message) use ($recipient, $invoice) {
                    $message
                        ->to($recipient)
                        ->subject('ASHBHUB Property Invoice - ' . $invoice['invoice_id']);
                }
            );

            return $this->sendResponse(
                [
                    'invoice_id' => $invoice['invoice_id'],
                    'property_id' => $property->id,
                    'recipient' => $recipient,
                    'sent_at' => now()->toDateTimeString(),
                ],
                'Invoice pushed successfully to ' . $recipient . '.'
            );
        } catch (Throwable $exception) {
            Log::error('Property invoice push failed.', [
                'property_id' => $property->id,
                'property_email' => $property->property_email,
                'manager_email' => $property->manager_email,
                'error' => $exception->getMessage(),
            ]);

            return $this->sendError(
                'Invoice email could not be sent.',
                [
                    'mail' => [$exception->getMessage()],
                ],
                500
            );
        }
    }

    /**
     * Remove the specified property.
     */
    public function destroy($id)
    {
        $property = Property::find($id);

        if (!$property) {
            return $this->sendError('Property not found.', [], 404);
        }

        $this->deleteStoredPropertyImage($property->image);

        $property->delete();

        return $this->sendResponse([], 'Property deleted successfully.');
    }

    /**
     * Convert the model to a frontend-friendly structure.
     */
    private function transformProperty(
        Property $property,
        bool $forList = false
    ): array {
        $hasImage = $forList
            ? (bool) $property->getAttribute('has_image')
            : trim((string) $property->image) !== '';

        $imageUrl = $hasImage
            ? $this->propertyImageEndpoint($property)
            : null;

        $propertyEmail = $this->nullableString($property->property_email);
        $managerEmail = $this->nullableString($property->manager_email);

        $response = [
            'id' => $property->id,
            'title' => $property->title,
            'slug' => $property->slug,
            'href' => $property->href
                ?: '/dashboard/properties/' . $property->id,
            'image' => $forList
                ? null
                : $this->resolvePropertyImageUrl($property->image),
            'image_url' => $forList
                ? $imageUrl
                : $this->resolvePropertyImageUrl($property->image),
            'price' => $property->price !== null
                ? (float) $property->price
                : null,
            'address' => $property->address,
            'location' => $property->location,
            'manager_name' => $property->manager_name,
            'manager_email' => $managerEmail,
            'property_email' => $propertyEmail,
            'client_name' => $property->manager_name ?: 'Property Account',
            'client_email' => $propertyEmail ?: $managerEmail,
            'email' => $propertyEmail ?: $managerEmail,
            'units' => (int) ($property->units ?? 0),
            'occupancy' => (int) ($property->occupancy ?? 0),
            'status' => $property->status ?: 'available',
            'is_favorite' => (bool) $property->is_favorite,
            'created_at' => $property->created_at,
            'updated_at' => $property->updated_at,
        ];

        if (!$forList) {
            $response['description'] = $property->description;
        }

        return $response;
    }

    private function propertyImageEndpoint(Property $property): string
    {
        $version = optional($property->updated_at)->timestamp
            ?: time();

        return '/api/property-images/' . $property->id . '?v=' . $version;
    }

    private function convertLegacyBase64Image(
        Property $property,
        string $dataUrl
    ): ?string {
        if (!preg_match(
            '/^data:image\/(jpeg|jpg|png|webp|gif);base64,(.+)$/s',
            $dataUrl,
            $matches
        )) {
            return null;
        }

        $extension = strtolower($matches[1]);
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;

        $decodedImage = base64_decode(
            preg_replace('/\s+/', '', $matches[2]),
            true
        );

        if ($decodedImage === false || $decodedImage === '') {
            return null;
        }

        $relativePath = 'properties/property-' .
            $property->id . '-' .
            substr(sha1($decodedImage), 0, 20) .
            '.' . $extension;

        if (!Storage::disk('public')->exists($relativePath)) {
            $saved = Storage::disk('public')->put(
                $relativePath,
                $decodedImage
            );

            if (!$saved) {
                return null;
            }
        }

        $property->forceFill([
            'image' => $relativePath,
        ])->saveQuietly();

        return $relativePath;
    }

    private function publicStorageImageResponse(string $path)
    {
        return Storage::disk('public')->response(
            $path,
            basename($path),
            [
                'Cache-Control' => 'public, max-age=86400, immutable',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline'
        );
    }

    private function extractPublicStoragePath(?string $image): ?string
    {
        $value = trim((string) $image);

        if ($value === '' || Str::startsWith($value, 'data:image/')) {
            return null;
        }

        $urlPath = parse_url($value, PHP_URL_PATH);
        $normalized = is_string($urlPath) && $urlPath !== ''
            ? $urlPath
            : $value;

        $normalized = str_replace('\\', '/', $normalized);
        $normalized = ltrim($normalized, '/');

        foreach ([
            'storage/app/public/',
            'public/storage/',
            'storage/',
        ] as $prefix) {
            if (Str::startsWith($normalized, $prefix)) {
                $normalized = Str::after($normalized, $prefix);
                break;
            }
        }

        return $normalized !== '' ? $normalized : null;
    }

    private function storePropertyImage(?string $image): ?string
    {
        if ($image === null || trim($image) === '') {
            return null;
        }

        $image = trim($image);

        if (!Str::startsWith($image, 'data:image/')) {
            return $image;
        }

        if (!preg_match(
            '/^data:image\/(jpeg|jpg|png|webp|gif);base64,(.+)$/s',
            $image,
            $matches
        )) {
            throw ValidationException::withMessages([
                'image' => ['The image data is invalid.'],
            ]);
        }

        $extension = strtolower($matches[1]);
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;

        $decodedImage = base64_decode(
            preg_replace('/\s+/', '', $matches[2]),
            true
        );

        if ($decodedImage === false) {
            throw ValidationException::withMessages([
                'image' => ['The image could not be decoded.'],
            ]);
        }

        if (strlen($decodedImage) > 8 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'image' => ['The image must not be larger than 8 MB.'],
            ]);
        }

        $relativePath = 'properties/' .
            Str::uuid()->toString() .
            '.' .
            $extension;

        $saved = Storage::disk('public')->put(
            $relativePath,
            $decodedImage
        );

        if (!$saved) {
            throw ValidationException::withMessages([
                'image' => ['The image could not be saved.'],
            ]);
        }

        return $relativePath;
    }

    private function resolvePropertyImageUrl(?string $image): ?string
    {
        $value = trim((string) $image);

        if ($value === '') {
            return null;
        }

        if (Str::startsWith($value, 'data:image/')) {
            return $value;
        }

        if (Str::startsWith($value, ['http://', 'https://', 'blob:'])) {
            return $value;
        }

        $normalized = str_replace('\\', '/', $value);
        $normalized = ltrim($normalized, '/');

        if (Str::startsWith($normalized, 'storage/app/public/')) {
            $normalized = Str::after($normalized, 'storage/app/public/');
        }

        if (Str::startsWith($normalized, 'public/storage/')) {
            $normalized = Str::after($normalized, 'public/storage/');
        }

        if (Str::startsWith($normalized, 'storage/')) {
            return url('/' . $normalized);
        }

        $storageUrl = Storage::disk('public')->url($normalized);

        return Str::startsWith($storageUrl, ['http://', 'https://'])
            ? $storageUrl
            : url($storageUrl);
    }

    private function deleteStoredPropertyImage(?string $image): void
    {
        $value = trim((string) $image);

        if ($value === '' || Str::startsWith($value, 'data:image/')) {
            return;
        }

        $path = parse_url($value, PHP_URL_PATH);
        $normalized = is_string($path) && $path !== '' ? $path : $value;
        $normalized = str_replace('\\', '/', $normalized);
        $normalized = ltrim($normalized, '/');

        if (Str::startsWith($normalized, 'storage/app/public/')) {
            $normalized = Str::after($normalized, 'storage/app/public/');
        } elseif (Str::startsWith($normalized, 'public/storage/')) {
            $normalized = Str::after($normalized, 'public/storage/');
        } elseif (Str::startsWith($normalized, 'storage/')) {
            $normalized = Str::after($normalized, 'storage/');
        }

        if ($normalized !== '' && Str::startsWith($normalized, 'properties/')) {
            Storage::disk('public')->delete($normalized);
        }
    }

    private function generateUniqueSlug(
        string $title,
        ?int $ignoreId = null
    ): string {
        $baseSlug = Str::slug($title);

        if ($baseSlug === '') {
            $baseSlug = 'property';
        }

        $slug = $baseSlug;
        $counter = 1;

        while (true) {
            $query = Property::where('slug', $slug);

            if ($ignoreId !== null) {
                $query->where('id', '!=', $ignoreId);
            }

            if (!$query->exists()) {
                return $slug;
            }

            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private function buildInvoiceEmailHtml(Property $property, array $invoice): string
    {
        $amount = number_format((float) ($invoice['amount'] ?? 0), 0);
        $invoiceId = e((string) ($invoice['invoice_id'] ?? 'INV-PROP-' . $property->id));
        $invoiceDate = e($this->formatInvoiceDate((string) ($invoice['invoice_date'] ?? '')));
        $dueDate = e($this->formatInvoiceDate((string) ($invoice['due_date'] ?? '')));
        $propertyTitle = e((string) $property->title);
        $managerName = e((string) ($property->manager_name ?: 'Property Manager'));
        $address = e((string) $property->address);
        $location = e((string) ($property->location ?: ''));

        return <<<HTML
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{$invoiceId}</title>
</head>
<body style="margin:0;background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a;">
    <div style="max-width:760px;margin:0 auto;padding:28px;">
        <div style="background:#ffffff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;">
            <div style="background:#0f172a;color:#ffffff;padding:24px 28px;">
                <div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;font-weight:700;color:#fb923c;">
                    ASHBHUB Management System
                </div>
                <h1 style="margin:8px 0 0;font-size:30px;">Property Invoice</h1>
                <p style="margin:6px 0 0;color:#cbd5e1;">Invoice ID: {$invoiceId}</p>
            </div>

            <div style="padding:28px;">
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                    <tr>
                        <td style="vertical-align:top;">
                            <div style="font-size:12px;text-transform:uppercase;font-weight:700;color:#64748b;">Bill To</div>
                            <div style="font-size:18px;font-weight:800;margin-top:8px;">{$managerName}</div>
                            <div style="font-size:14px;color:#475569;margin-top:4px;">{$propertyTitle}</div>
                            <div style="font-size:14px;color:#475569;margin-top:4px;">{$address}</div>
                            <div style="font-size:14px;color:#475569;">{$location}</div>
                        </td>
                        <td style="vertical-align:top;text-align:right;">
                            <div style="font-size:12px;text-transform:uppercase;font-weight:700;color:#64748b;">Invoice Date</div>
                            <div style="font-size:15px;font-weight:700;margin-top:8px;">{$invoiceDate}</div>
                            <div style="font-size:12px;text-transform:uppercase;font-weight:700;color:#64748b;margin-top:14px;">Due Date</div>
                            <div style="font-size:15px;font-weight:700;margin-top:8px;">{$dueDate}</div>
                        </td>
                    </tr>
                </table>

                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                    <thead>
                        <tr style="background:#f1f5f9;">
                            <th align="left" style="padding:14px;font-size:12px;text-transform:uppercase;color:#475569;">Description</th>
                            <th align="right" style="padding:14px;font-size:12px;text-transform:uppercase;color:#475569;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding:16px;border-top:1px solid #e2e8f0;">
                                <strong>{$propertyTitle}</strong><br>
                                <span style="color:#64748b;font-size:13px;">Monthly property billing invoice</span>
                            </td>
                            <td align="right" style="padding:16px;border-top:1px solid #e2e8f0;font-weight:800;">
                                RWF {$amount}
                            </td>
                        </tr>
                        <tr style="background:#fff7ed;">
                            <td align="right" style="padding:16px;border-top:1px solid #fed7aa;font-weight:800;">
                                Total Amount
                            </td>
                            <td align="right" style="padding:16px;border-top:1px solid #fed7aa;font-weight:900;color:#ea580c;font-size:18px;">
                                RWF {$amount}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p style="margin-top:24px;font-size:13px;color:#64748b;line-height:1.6;">
                    Please review this invoice and complete payment according to your agreement with ASHBHUB.
                </p>
            </div>
        </div>

        <p style="text-align:center;font-size:12px;color:#94a3b8;margin-top:18px;">
            Generated by ASHBHUB Management System
        </p>
    </div>
</body>
</html>
HTML;
    }

    private function formatInvoiceDate(?string $value): string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return now()->format('M d, Y');
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return $value;
        }

        return date('M d, Y', $timestamp);
    }
}