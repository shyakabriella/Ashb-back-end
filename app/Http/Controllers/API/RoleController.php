<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * Display all roles.
     *
     * GET /api/roles
     */
    public function index(Request $request): JsonResponse
    {
        $query = Role::query()
            ->withCount('users');

        /*
        |--------------------------------------------------------------------------
        | Optional search
        |--------------------------------------------------------------------------
        */
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($roleQuery) use ($search) {
                $roleQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Optional active-status filter
        |--------------------------------------------------------------------------
        |
        | Examples:
        | /api/roles?is_active=1
        | /api/roles?is_active=0
        |
        */
        if ($request->has('is_active')) {
            $query->where(
                'is_active',
                $request->boolean('is_active')
            );
        }

        $roles = $query
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Roles retrieved successfully.',
            'data' => $roles,
        ]);
    }

    /**
     * Store a new role.
     *
     * POST /api/roles
     */
    public function store(Request $request): JsonResponse
    {
        if ($response = $this->denyUnauthorizedUser($request)) {
            return $response;
        }

        /*
        |--------------------------------------------------------------------------
        | Generate slug when it is not provided
        |--------------------------------------------------------------------------
        */
        $requestedSlug = $request->filled('slug')
            ? $request->input('slug')
            : $request->input('name');

        $request->merge([
            'slug' => Str::slug((string) $requestedSlug, '_'),
        ]);

        $validator = Validator::make(
            $request->all(),
            [
                'name' => [
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('roles', 'name'),
                ],

                'slug' => [
                    'required',
                    'string',
                    'max:100',
                    'regex:/^[a-z0-9_]+$/',
                    Rule::unique('roles', 'slug'),
                ],

                'description' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'is_active' => [
                    'sometimes',
                    'boolean',
                ],
            ],
            [
                'slug.regex' => 'The role slug may only contain lowercase letters, numbers, and underscores.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Role validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $role = Role::create([
            'name' => trim($validated['name']),
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $role->loadCount('users');

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully.',
            'data' => $role,
        ], 201);
    }

    /**
     * Display one role.
     *
     * GET /api/roles/{role}
     */
    public function show(Role $role): JsonResponse
    {
        $role->loadCount('users');

        return response()->json([
            'success' => true,
            'message' => 'Role retrieved successfully.',
            'data' => $role,
        ]);
    }

    /**
     * Update an existing role.
     *
     * PUT/PATCH /api/roles/{role}
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        if ($response = $this->denyUnauthorizedUser($request)) {
            return $response;
        }

        /*
        |--------------------------------------------------------------------------
        | Protect system role slugs
        |--------------------------------------------------------------------------
        |
        | Your application may use constants such as Role::CEO or Role::MD
        | for permissions. Changing those slugs could break access control.
        |
        */
        if (
            $request->filled('slug') &&
            $this->isDefaultSystemRole($role)
        ) {
            $newSlug = Str::slug(
                (string) $request->input('slug'),
                '_'
            );

            if ($newSlug !== $role->slug) {
                return response()->json([
                    'success' => false,
                    'message' => 'The slug of a default system role cannot be changed.',
                    'errors' => [
                        'slug' => [
                            "The system role slug '{$role->slug}' is protected.",
                        ],
                    ],
                ], 422);
            }
        }

        if ($request->filled('slug')) {
            $request->merge([
                'slug' => Str::slug(
                    (string) $request->input('slug'),
                    '_'
                ),
            ]);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'name' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('roles', 'name')->ignore($role->id),
                ],

                'slug' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:100',
                    'regex:/^[a-z0-9_]+$/',
                    Rule::unique('roles', 'slug')->ignore($role->id),
                ],

                'description' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'is_active' => [
                    'sometimes',
                    'boolean',
                ],
            ],
            [
                'slug.regex' => 'The role slug may only contain lowercase letters, numbers, and underscores.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Role validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        if (isset($validated['name'])) {
            $validated['name'] = trim($validated['name']);
        }

        $role->update($validated);

        $role->refresh();
        $role->loadCount('users');

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully.',
            'data' => $role,
        ]);
    }

    /**
     * Activate or deactivate a role.
     *
     * PATCH /api/roles/{role}/status
     */
    public function updateStatus(
        Request $request,
        Role $role
    ): JsonResponse {
        if ($response = $this->denyUnauthorizedUser($request)) {
            return $response;
        }

        $validator = Validator::make(
            $request->all(),
            [
                'is_active' => [
                    'required',
                    'boolean',
                ],
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Role status validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $role->update([
            'is_active' => $validator->validated()['is_active'],
        ]);

        $role->refresh();
        $role->loadCount('users');

        return response()->json([
            'success' => true,
            'message' => $role->is_active
                ? 'Role activated successfully.'
                : 'Role deactivated successfully.',
            'data' => $role,
        ]);
    }

    /**
     * Delete a role.
     *
     * DELETE /api/roles/{role}
     */
    public function destroy(
        Request $request,
        Role $role
    ): JsonResponse {
        if ($response = $this->denyUnauthorizedUser($request)) {
            return $response;
        }

        /*
        |--------------------------------------------------------------------------
        | Default roles cannot be deleted
        |--------------------------------------------------------------------------
        */
        if ($this->isDefaultSystemRole($role)) {
            return response()->json([
                'success' => false,
                'message' => 'Default system roles cannot be deleted.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Roles assigned to users cannot be deleted
        |--------------------------------------------------------------------------
        */
        $assignedUsersCount = $role->users()->count();

        if ($assignedUsersCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "This role cannot be deleted because it is assigned to {$assignedUsersCount} user(s).",
                'data' => [
                    'users_count' => $assignedUsersCount,
                ],
            ], 422);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully.',
        ]);
    }

    /**
     * Only CEO and Managing Director can manage roles.
     */
    private function denyUnauthorizedUser(
        Request $request
    ): ?JsonResponse {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $user->loadMissing('role');

        $roleSlug = $user->role?->slug;

        $allowedRoles = [
            Role::CEO,
            Role::MD,
        ];

        if (!in_array($roleSlug, $allowedRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to manage roles.',
            ], 403);
        }

        return null;
    }

    /**
     * Check whether the role is one of the default system roles.
     */
    private function isDefaultSystemRole(Role $role): bool
    {
        return in_array(
            $role->slug,
            [
                Role::CEO,
                Role::MD,
                Role::CHIEF_MARKET,
                Role::EMPLOYEE,
                Role::INTERN,
            ],
            true
        );
    }
}