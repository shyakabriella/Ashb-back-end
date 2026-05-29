<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProfileController extends BaseController
{
    private function managerRoleSlugs(): array
    {
        return [
            'admin',
            'ceo',
            'md',
            'chief_market',
        ];
    }

    private function canManageUsers(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $user->loadMissing('role');

        $roleSlug = strtolower((string) ($user->role?->slug ?? ''));

        return in_array($roleSlug, $this->managerRoleSlugs(), true);
    }

    /**
     * GET /api/me
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->sendError('Unauthorised.', [
                'error' => 'User not authenticated.',
            ], 401);
        }

        $user->load(['role', 'profile']);

        return $this->sendResponse([
            'auth_user_id' => $user->id,
            'target_user_id' => $user->id,
            'viewing_mode' => 'self',
            'user' => $this->formatUser($user),
        ], 'Profile fetched successfully.');
    }

    /**
     * POST /api/me
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->sendError('Unauthorised.', [
                'error' => 'User not authenticated.',
            ], 401);
        }

        return $this->saveProfile(
            request: $request,
            targetUser: $user,
            message: 'Your profile updated successfully.',
            viewingMode: 'self',
            authUser: $user,
            allowAdminFields: false
        );
    }

    /**
     * GET /api/users/{user}/profile
     */
    public function showUser(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();

        if (!$authUser) {
            return $this->sendError('Unauthorised.', [
                'error' => 'User not authenticated.',
            ], 401);
        }

        if (!$this->canManageUsers($authUser) && (int) $authUser->id !== (int) $user->id) {
            return $this->sendError('Forbidden.', [
                'error' => 'You are not allowed to view this user profile.',
            ], 403);
        }

        $user->load(['role', 'profile']);

        return $this->sendResponse([
            'auth_user_id' => $authUser->id,
            'target_user_id' => $user->id,
            'viewing_mode' => (int) $authUser->id === (int) $user->id ? 'self' : 'admin_view',
            'user' => $this->formatUser($user),
        ], 'User profile fetched successfully.');
    }

    /**
     * POST/PATCH /api/users/{user}/profile
     */
    public function updateUser(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();

        if (!$authUser) {
            return $this->sendError('Unauthorised.', [
                'error' => 'User not authenticated.',
            ], 401);
        }

        if (!$this->canManageUsers($authUser) && (int) $authUser->id !== (int) $user->id) {
            return $this->sendError('Forbidden.', [
                'error' => 'You are not allowed to update this user profile.',
            ], 403);
        }

        return $this->saveProfile(
            request: $request,
            targetUser: $user,
            message: 'User profile updated successfully.',
            viewingMode: (int) $authUser->id === (int) $user->id ? 'self' : 'admin_update',
            authUser: $authUser,
            allowAdminFields: $this->canManageUsers($authUser)
        );
    }

    /**
     * DELETE /api/users/{user}
     */
    public function destroyUser(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();

        if (!$authUser) {
            return $this->sendError('Unauthorised.', [
                'error' => 'User not authenticated.',
            ], 401);
        }

        if (!$this->canManageUsers($authUser)) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only admin/manager can permanently delete users.',
            ], 403);
        }

        if ((int) $authUser->id === (int) $user->id) {
            return $this->sendError('Delete blocked.', [
                'error' => 'You cannot permanently delete your own logged-in account.',
            ], 422);
        }

        DB::transaction(function () use ($user) {
            $user->loadMissing('profile');

            if ($user->profile?->avatar && Storage::disk('public')->exists($user->profile->avatar)) {
                Storage::disk('public')->delete($user->profile->avatar);
            }

            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }

            if (method_exists($user, 'assignedTasks')) {
                $user->assignedTasks()->detach();
            }

            if ($user->profile) {
                $user->profile()->delete();
            }

            /*
             * If your tasks.created_by column is nullable, this prevents FK blocking.
             * If your DB does not have this column, it is ignored.
             */
            if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'created_by')) {
                DB::table('tasks')
                    ->where('created_by', $user->id)
                    ->update(['created_by' => null]);
            }

            if (method_exists($user, 'forceDelete')) {
                $user->forceDelete();
            } else {
                $user->delete();
            }
        });

        return $this->sendResponse([], 'User permanently deleted successfully.');
    }

    private function saveProfile(
        Request $request,
        User $targetUser,
        string $message,
        string $viewingMode,
        ?User $authUser,
        bool $allowAdminFields = false
    ): JsonResponse {
        $request->request->remove('id');
        $request->request->remove('user_id');
        $request->request->remove('password');

        if (!$allowAdminFields) {
            $request->request->remove('role_id');
            $request->request->remove('is_active');
        }

        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($targetUser->id),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'dob' => ['nullable', 'date', 'before:tomorrow'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];

        if ($allowAdminFields) {
            $rules['role_id'] = ['nullable', 'integer', 'exists:roles,id'];
            $rules['is_active'] = ['nullable', 'boolean'];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $validated = $validator->validated();

        DB::transaction(function () use ($targetUser, $request, $validated, $allowAdminFields) {
            $payload = [
                'first_name' => trim((string) $validated['first_name']),
                'last_name' => trim((string) $validated['last_name']),
                'email' => strtolower(trim((string) $validated['email'])),
                'phone' => filled($validated['phone'] ?? null)
                    ? trim((string) $validated['phone'])
                    : null,
            ];

            if ($allowAdminFields && array_key_exists('role_id', $validated) && filled($validated['role_id'])) {
                $payload['role_id'] = (int) $validated['role_id'];
            }

            if ($allowAdminFields && array_key_exists('is_active', $validated)) {
                $payload['is_active'] = (bool) $validated['is_active'];
            }

            $targetUser->forceFill($payload)->save();

            $profile = $targetUser->profile()->updateOrCreate(
                [
                    'user_id' => $targetUser->id,
                ],
                [
                    'date_of_birth' => filled($validated['dob'] ?? null)
                        ? $validated['dob']
                        : null,
                ]
            );

            if ((int) $profile->user_id !== (int) $targetUser->id) {
                abort(403, 'Invalid profile ownership.');
            }

            if ($request->hasFile('avatar')) {
                if ($profile->avatar && Storage::disk('public')->exists($profile->avatar)) {
                    Storage::disk('public')->delete($profile->avatar);
                }

                $avatarPath = $request->file('avatar')->store(
                    'profile-photos/' . $targetUser->id,
                    'public'
                );

                $profile->avatar = $avatarPath;
                $profile->save();
            }
        });

        $targetUser->refresh();
        $targetUser->load(['role', 'profile']);

        return $this->sendResponse([
            'auth_user_id' => $authUser?->id,
            'target_user_id' => $targetUser->id,
            'viewing_mode' => $viewingMode,
            'user' => $this->formatUser($targetUser),
        ], $message);
    }

    private function formatUser(User $user): array
    {
        $user->loadMissing(['role', 'profile']);

        $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return [
            'id' => $user->id,
            'name' => $fullName,
            'full_name' => $fullName,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'phone' => $user->phone,
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
            'last_login_at' => $user->last_login_at,
            'role_id' => $user->role_id,
            'role_name' => $user->role?->name,
            'role_slug' => $user->role?->slug,
            'role' => [
                'id' => $user->role?->id,
                'name' => $user->role?->name,
                'slug' => $user->role?->slug,
            ],
            'dob' => $user->profile?->date_of_birth?->format('Y-m-d'),
            'avatar' => $user->profile?->avatar,
            'avatar_url' => $user->profile?->avatar
                ? asset('storage/' . $user->profile->avatar)
                : null,
            'profile' => [
                'id' => $user->profile?->id,
                'user_id' => $user->profile?->user_id,
                'dob' => $user->profile?->date_of_birth?->format('Y-m-d'),
                'avatar' => $user->profile?->avatar,
                'avatar_url' => $user->profile?->avatar
                    ? asset('storage/' . $user->profile->avatar)
                    : null,
            ],
            'created_at' => $user->created_at,
        ];
    }
}