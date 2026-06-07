<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PropertyController extends BaseController
{
    /**
     * Short cache duration for property listing requests.
     *
     * The version number is increased after create, update, or delete, so users
     * never remain stuck on an old property list.
     */
    private const LIST_CACHE_SECONDS = 45;
    private const LIST_CACHE_VERSION_KEY = 'properties:list:version';

    /**
     * Display a fast, lightweight property listing.
     *
     * Performance improvements:
     * - Selects only fields required by the property cards.
     * - Uses simplePaginate() to avoid an expensive COUNT(*) query.
     * - Caches identical list/filter requests briefly.
     * - Orders by the indexed primary key instead of loading every record.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => [
                'nullable',
                'string',
                'in:all,available,fully_booked,inactive',
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation Error.',
                $validator->errors(),
                422
            );
        }

        $validated = $validator->validated();

        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? 'all'));
        $location = trim((string) ($validated['location'] ?? 'all'));
        $perPage = max(1, min((int) ($validated['per_page'] ?? 12), 50));
        $page = max(1, (int) ($validated['page'] ?? 1));

        $version = (int) Cache::get(self::LIST_CACHE_VERSION_KEY, 1);

        $cacheKey = 'properties:list:v' . $version . ':' . sha1(json_encode([
            'search' => $search,
            'status' => $status,
            'location' => $location,
            'per_page' => $perPage,
            'page' => $page,
        ]) ?: '');

        $payload = Cache::remember(
            $cacheKey,
            now()->addSeconds(self::LIST_CACHE_SECONDS),
            function () use (
                $search,
                $status,
                $location,
                $perPage,
                $page
            ): array {
                $query = Property::query()
                    ->select([
                        'id',
                        'title',
                        'slug',
                        'href',
                        'image',
                        'price',
                        'address',
                        'location',
                        'units',
                        'occupancy',
                        'status',
                        'description',
                        'is_favorite',
                        'created_at',
                        'updated_at',
                    ])
                    ->orderByDesc('id');

                if ($search !== '') {
                    $query->where(function ($searchQuery) use ($search) {
                        if (ctype_digit($search)) {
                            $searchQuery->where('id', (int) $search);
                        } else {
                            $like = '%' . $search . '%';

                            $searchQuery
                                ->where('title', 'like', $like)
                                ->orWhere('address', 'like', $like)
                                ->orWhere('location', 'like', $like)
                                ->orWhere('slug', 'like', $like);
                        }
                    });
                }

                if ($status !== '' && strtolower($status) !== 'all') {
                    $query->where('status', $status);
                }

                if ($location !== '' && strtolower($location) !== 'all') {
                    $query->where(
                        'location',
                        'like',
                        '%' . $location . '%'
                    );
                }

                /*
                 * simplePaginate() avoids the total COUNT(*) query that can make
                 * a large property table slow. It still supports Previous/Next.
                 */
                $properties = $query->simplePaginate(
                    perPage: $perPage,
                    columns: ['*'],
                    pageName: 'page',
                    page: $page
                );

                $properties->getCollection()->transform(
                    fn (Property $property): array =>
                        $this->transformProperty($property)
                );

                $data = $properties->toArray();

                $data['has_more'] = $properties->hasMorePages();
                $data['last_page'] = $properties->hasMorePages()
                    ? $properties->currentPage() + 1
                    : $properties->currentPage();

                /*
                 * null is intentional: avoiding a total count is one of the
                 * largest performance improvements on a large table.
                 */
                $data['total'] = null;

                return $data;
            }
        );

        return $this->sendResponse(
            $payload,
            'Properties retrieved successfully.'
        );
    }

    /**
     * Store a newly created property.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'address' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'units' => ['required', 'integer', 'min:0'],
            'occupancy' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status' => [
                'nullable',
                'string',
                'in:available,fully_booked,inactive',
            ],
            'description' => ['nullable', 'string'],
            'is_favorite' => ['nullable', 'boolean'],
            'href' => [
                'nullable',
                'string',
                'max:255',
                'unique:properties,href',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation Error.',
                $validator->errors(),
                422
            );
        }

        $data = $validator->validated();

        $occupancy =
            array_key_exists('occupancy', $data) &&
            $data['occupancy'] !== null
                ? (int) $data['occupancy']
                : 0;

        $description =
            array_key_exists('description', $data) &&
            trim((string) $data['description']) !== ''
                ? trim((string) $data['description'])
                : null;

        $data['slug'] = $this->generateUniqueSlug($data['title']);

        if (!filled($data['status'] ?? null)) {
            $data['status'] = $occupancy >= 100
                ? 'fully_booked'
                : 'available';
        }

        $property = Property::create([
            'title' => trim((string) $data['title']),
            'slug' => $data['slug'],
            'href' => $data['href'] ?? null,
            'image' => $data['image'] ?? null,
            'price' => $data['price'] ?? null,
            'address' => trim((string) $data['address']),
            'location' =>
                isset($data['location']) &&
                trim((string) $data['location']) !== ''
                    ? trim((string) $data['location'])
                    : null,
            'units' => (int) $data['units'],
            'occupancy' => $occupancy,
            'status' => $data['status'],
            'description' => $description,
            'is_favorite' => (bool) ($data['is_favorite'] ?? false),
        ]);

        if (blank($property->href)) {
            $property->href = '/dashboard/properties/' . $property->id;
            $property->save();
        }

        $this->invalidatePropertyListCache();

        return $this->sendResponse(
            $this->transformProperty($property->fresh()),
            'Property created successfully.'
        );
    }

    /**
     * Display one property.
     */
    public function show(int|string $id): JsonResponse
    {
        $property = Property::query()
            ->select([
                'id',
                'title',
                'slug',
                'href',
                'image',
                'price',
                'address',
                'location',
                'units',
                'occupancy',
                'status',
                'description',
                'is_favorite',
                'created_at',
                'updated_at',
            ])
            ->find($id);

        if (!$property) {
            return $this->sendError('Property not found.');
        }

        return $this->sendResponse(
            $this->transformProperty($property),
            'Property retrieved successfully.'
        );
    }

    /**
     * Update one property.
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $property = Property::find($id);

        if (!$property) {
            return $this->sendError('Property not found.');
        }

        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'image' => ['nullable', 'string'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'address' => ['sometimes', 'required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'units' => ['sometimes', 'required', 'integer', 'min:0'],
            'occupancy' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status' => [
                'nullable',
                'string',
                'in:available,fully_booked,inactive',
            ],
            'description' => ['nullable', 'string'],
            'is_favorite' => ['nullable', 'boolean'],
            'href' => [
                'nullable',
                'string',
                'max:255',
                'unique:properties,href,' . $property->id,
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation Error.',
                $validator->errors(),
                422
            );
        }

        $data = $validator->validated();

        if (array_key_exists('title', $data) && filled($data['title'])) {
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
            $value = trim((string) ($data['location'] ?? ''));
            $data['location'] = $value !== '' ? $value : null;
        }

        if (array_key_exists('description', $data)) {
            $value = trim((string) ($data['description'] ?? ''));
            $data['description'] = $value !== '' ? $value : null;
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

        $property->update($data);

        if (blank($property->href)) {
            $property->href = '/dashboard/properties/' . $property->id;
            $property->save();
        }

        $this->invalidatePropertyListCache();

        return $this->sendResponse(
            $this->transformProperty($property->fresh()),
            'Property updated successfully.'
        );
    }

    /**
     * Delete one property.
     */
    public function destroy(int|string $id): JsonResponse
    {
        $property = Property::find($id);

        if (!$property) {
            return $this->sendError('Property not found.');
        }

        $property->delete();
        $this->invalidatePropertyListCache();

        return $this->sendResponse([], 'Property deleted successfully.');
    }

    /**
     * Convert the model to the lightweight frontend card structure.
     */
    private function transformProperty(Property $property): array
    {
        return [
            'id' => (int) $property->id,
            'title' => (string) $property->title,
            'slug' => $property->slug,
            'href' => $property->href
                ?: '/dashboard/properties/' . $property->id,
            'image' => $property->image,
            'price' => $property->price !== null
                ? (float) $property->price
                : null,
            'address' => (string) $property->address,
            'location' => $property->location,
            'units' => (int) ($property->units ?? 0),
            'occupancy' => (int) ($property->occupancy ?? 0),
            'status' => $property->status ?: 'available',
            'description' => $property->description,
            'is_favorite' => (bool) $property->is_favorite,
            'created_at' => $property->created_at,
            'updated_at' => $property->updated_at,
        ];
    }

    /**
     * Increase the list cache version instead of scanning and deleting keys.
     */
    private function invalidatePropertyListCache(): void
    {
        $currentVersion = (int) Cache::get(
            self::LIST_CACHE_VERSION_KEY,
            1
        );

        Cache::forever(
            self::LIST_CACHE_VERSION_KEY,
            $currentVersion + 1
        );
    }

    /**
     * Generate a unique property slug.
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
            $query = Property::query()->where('slug', $slug);

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