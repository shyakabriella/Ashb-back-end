<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Property;
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
            $search = trim((string) $request->search);

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

        if ($request->filled('location') && strtolower((string) $request->location) !== 'all') {
            $query->where('location', 'like', '%' . trim((string) $request->location) . '%');
        }

        $perPage = (int) $request->get('per_page', 12);

        $properties = $query->paginate($perPage);

        $properties->getCollection()->transform(function ($property) {
            return $this->transformProperty($property);
        });

        return $this->sendResponse($properties, 'Properties retrieved successfully.');
    }

    /**
     * Store a newly created property.
     */
    public function store(Request $request)
    {
        /**
         * Important:
         * Occupancy and description are now optional because they were removed
         * from the Add Property popup.
         */
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
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $data = $validator->validated();

        $occupancy = array_key_exists('occupancy', $data) && $data['occupancy'] !== null
            ? (int) $data['occupancy']
            : 0;

        $description = array_key_exists('description', $data) && trim((string) $data['description']) !== ''
            ? trim((string) $data['description'])
            : null;

        $data['slug'] = $this->generateUniqueSlug($data['title']);

        if (!isset($data['status']) || empty($data['status'])) {
            $data['status'] = $occupancy >= 100 ? 'fully_booked' : 'available';
        }

        $property = Property::create([
            'title' => trim((string) $data['title']),
            'slug' => $data['slug'],
            'href' => $data['href'] ?? null,
            'image' => $data['image'] ?? null,
            'price' => $data['price'] ?? null,
            'address' => trim((string) $data['address']),
            'location' => isset($data['location']) && trim((string) $data['location']) !== ''
                ? trim((string) $data['location'])
                : null,
            'units' => (int) $data['units'],
            'occupancy' => $occupancy,
            'status' => $data['status'],
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

        /**
         * Important:
         * Occupancy and description are optional.
         * If they are not sent, we do not force validation error.
         */
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
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $data = $validator->validated();

        if (array_key_exists('title', $data) && !empty($data['title'])) {
            $data['title'] = trim((string) $data['title']);
            $data['slug'] = $this->generateUniqueSlug($data['title'], $property->id);
        }

        if (array_key_exists('address', $data)) {
            $data['address'] = trim((string) $data['address']);
        }

        if (array_key_exists('location', $data)) {
            $data['location'] = trim((string) ($data['location'] ?? '')) !== ''
                ? trim((string) $data['location'])
                : null;
        }

        if (array_key_exists('description', $data)) {
            $data['description'] = trim((string) ($data['description'] ?? '')) !== ''
                ? trim((string) $data['description'])
                : null;
        }

        if (array_key_exists('occupancy', $data)) {
            $data['occupancy'] = $data['occupancy'] === null
                ? 0
                : (int) $data['occupancy'];

            if (!array_key_exists('status', $data) && $property->status !== 'inactive') {
                $data['status'] = $data['occupancy'] >= 100 ? 'fully_booked' : 'available';
            }
        }

        if (array_key_exists('units', $data)) {
            $data['units'] = (int) $data['units'];
        }

        if (array_key_exists('is_favorite', $data)) {
            $data['is_favorite'] = (bool) $data['is_favorite'];
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