<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PropertyController extends BaseController
{
    /**
     * Display a lightweight paginated property list.
     *
     * Important performance rule:
     * The database image value is NOT selected for the list response. Legacy
     * records may contain multi-megabyte base64 strings, and returning several
     * of them inside one JSON response causes production timeouts.
     *
     * Each card receives a small public image endpoint instead. The browser
     * loads images separately and lazily after the property data is displayed.
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
                'units',
                'occupancy',
                'status',
                'is_favorite',
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
     *
     * This route is public because a normal <img> request cannot attach the
     * dashboard Bearer token. Property cover images are public assets.
     *
     * Legacy base64 records are converted to Laravel public storage the first
     * time the image is requested. Later requests are streamed from storage.
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
            'location' => isset($data['location']) &&
                trim((string) $data['location']) !== ''
                    ? trim((string) $data['location'])
                    : null,
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
            'image' => 'nullable|string',
            'price' => 'sometimes|nullable|numeric|min:0',
            'address' => 'sometimes|required|string|max:255',
            'location' => 'nullable|string|max:255',
            'units' => 'sometimes|required|integer|min:0',
            'occupancy' => 'nullable|integer|min:0|max:100',
            'status' => 'nullable|string|in:available,fully_booked,inactive',
            'description' => 'nullable|string',
            'is_favorite' => 'nullable|boolean',
            'href' => 'nullable|string|max:255|unique:properties,href,' . $property->id,
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

        if (array_key_exists('location', $data)) {
            $data['location'] =
                trim((string) ($data['location'] ?? '')) !== ''
                    ? trim((string) $data['location'])
                    : null;
        }

        if (array_key_exists('description', $data)) {
            $data['description'] =
                trim((string) ($data['description'] ?? '')) !== ''
                    ? trim((string) $data['description'])
                    : null;
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
            'units' => (int) ($property->units ?? 0),
            'occupancy' => (int) ($property->occupancy ?? 0),
            'status' => $property->status ?: 'available',
            'is_favorite' => (bool) $property->is_favorite,
        ];

        if (!$forList) {
            $response['description'] = $property->description;
            $response['created_at'] = $property->created_at;
            $response['updated_at'] = $property->updated_at;
        }

        return $response;
    }

    /**
     * Return the small public endpoint used by property cards.
     */
    private function propertyImageEndpoint(Property $property): string
    {
        $version = optional($property->updated_at)->timestamp
            ?: time();

        return '/api/property-images/' . $property->id . '?v=' . $version;
    }

    /**
     * Convert a legacy database base64 image to a public-storage file once.
     */
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

    /**
     * Stream one public-storage image with browser caching enabled.
     */
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

    /**
     * Extract a Laravel public-disk path from a stored path or local URL.
     */
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

    /**
     * Convert an uploaded base64 data URL to a real file.
     *
     * Normal URLs and storage paths are kept unchanged.
     */
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

    /**
     * Return a browser-ready image value while preserving the real database
     * image in the separate `image` field.
     *
     * Existing base64 images are returned unchanged. Relative Laravel public
     * storage paths are converted to complete URLs.
     */
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

    /**
     * Delete only files that belong to Laravel public storage.
     */
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

    /**
     * Generate a unique slug.
     */
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
}