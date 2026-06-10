<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountSetupNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegisterController extends BaseController
{
    /**
     * Roles allowed to manage users.
     */
    private function managerRoleSlugs(): array
    {
        return [
            'admin',
            'ceo',
            'md',
            'chief_market',
        ];
    }

    /**
     * Check if user can manage users.
     */
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
     * Get active roles for dropdown/select.
     */
    public function roles(Request $request): JsonResponse
    {
        if (!$this->canManageUsers($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'You are not allowed to view roles.',
            ], 403);
        }

        $roles = Role::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'slug',
                'description',
                'is_active',
            ]);

        return $this->sendResponse($roles, 'Roles fetched successfully.');
    }

    /**
     * Get current authenticated user.
     *
     * Optional old method. Your /api/me route can stay in ProfileController.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (!$user) {
            return $this->sendError('Unauthorised.', [
                'error' => 'User not authenticated.',
            ], 401);
        }

        $user->load(['role', 'profile']);

        if (!(bool) $user->is_active) {
            if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }

            return $this->sendError('Account disabled.', [
                'error' => 'Your account is inactive. Please contact administrator.',
            ], 403);
        }

        return $this->sendResponse([
            'auth_user_id' => $user->id,
            'target_user_id' => $user->id,
            'viewing_mode' => 'self',
            'user' => $this->formatUser($user),
        ], 'Authenticated user fetched successfully.');
    }

    /**
     * Get saved users for frontend.
     */
    public function users(Request $request): JsonResponse
    {
        if (!$this->canManageUsers($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'You are not allowed to view users.',
            ], 403);
        }

        $users = User::with(['role', 'profile'])
            ->latest()
            ->get()
            ->map(function (User $user) {
                return $this->formatUser($user);
            })
            ->values();

        return $this->sendResponse($users, 'Users fetched successfully.');
    }

    /**
     * Show one user.
     *
     * GET /api/users/{user}
     * GET /api/users/{user}/profile
     */
    public function showUser(Request $request, User $user): JsonResponse
    {
        /** @var \App\Models\User|null $authUser */
        $authUser = $request->user();

        if (!$authUser) {
            return $this->sendError('Unauthorised.', [
                'error' => 'User not authenticated.',
            ], 401);
        }

        if (!$this->canManageUsers($authUser) && (int) $authUser->id !== (int) $user->id) {
            return $this->sendError('Forbidden.', [
                'error' => 'You are not allowed to view this user.',
            ], 403);
        }

        $user->load(['role', 'profile']);

        return $this->sendResponse([
            'auth_user_id' => $authUser->id,
            'target_user_id' => $user->id,
            'viewing_mode' => (int) $authUser->id === (int) $user->id ? 'self' : 'admin_view',
            'user' => $this->formatUser($user),
        ], 'User fetched successfully.');
    }

    /**
     * Update one user.
     *
     * PUT/PATCH /api/users/{user}
     * POST/PATCH /api/users/{user}/profile
     */
    public function updateUser(Request $request, User $user): JsonResponse
    {
        /** @var \App\Models\User|null $authUser */
        $authUser = $request->user();

        if (!$authUser) {
            return $this->sendError('Unauthorised.', [
                'error' => 'User not authenticated.',
            ], 401);
        }

        if (!$this->canManageUsers($authUser)) {
            return $this->sendError('Forbidden.', [
                'error' => 'Only admin/manager can update users.',
            ], 403);
        }

        $request->request->remove('id');
        $request->request->remove('user_id');
        $request->request->remove('password');

        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],

            'dob' => ['nullable', 'date', 'before:tomorrow'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $validated = $validator->validated();

        DB::transaction(function () use ($request, $user, $validated) {
            $payload = [
                'first_name' => trim((string) $validated['first_name']),
                'last_name' => trim((string) $validated['last_name']),
                'email' => strtolower(trim((string) $validated['email'])),
                'phone' => filled($validated['phone'] ?? null)
                    ? trim((string) $validated['phone'])
                    : null,
            ];

            if (array_key_exists('role_id', $validated) && filled($validated['role_id'])) {
                $payload['role_id'] = (int) $validated['role_id'];
            }

            if (array_key_exists('is_active', $validated)) {
                $payload['is_active'] = (bool) $validated['is_active'];
            }

            $user->forceFill($payload)->save();

            $profile = $user->profile()->updateOrCreate(
                [
                    'user_id' => $user->id,
                ],
                [
                    'date_of_birth' => filled($validated['dob'] ?? null)
                        ? $validated['dob']
                        : null,
                ]
            );

            if ($request->hasFile('avatar')) {
                if ($profile->avatar && Storage::disk('public')->exists($profile->avatar)) {
                    Storage::disk('public')->delete($profile->avatar);
                }

                $avatarPath = $request->file('avatar')->store(
                    'profile-photos/' . $user->id,
                    'public'
                );

                $profile->avatar = $avatarPath;
                $profile->save();
            }
        });

        $user->refresh();
        $user->load(['role', 'profile']);

        return $this->sendResponse([
            'auth_user_id' => $authUser->id,
            'target_user_id' => $user->id,
            'viewing_mode' => (int) $authUser->id === (int) $user->id ? 'self' : 'admin_update',
            'user' => $this->formatUser($user),
        ], 'User profile updated successfully.');
    }

    /**
     * Permanently delete one user.
     *
     * DELETE /api/users/{user}
     */
    public function destroyUser(Request $request, User $user): JsonResponse
    {
        /** @var \App\Models\User|null $authUser */
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

            if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'created_by')) {
                DB::table('tasks')
                    ->where('created_by', $user->id)
                    ->update([
                        'created_by' => null,
                    ]);
            }

            $user->delete();
        });

        return $this->sendResponse([], 'User permanently deleted successfully.');
    }

    /**
     * Admin creates a user and sends account setup email.
     */
    public function register(Request $request): JsonResponse
    {
        if (!$this->canManageUsers($request->user())) {
            return $this->sendError('Forbidden.', [
                'error' => 'You are not allowed to create users.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'required|string|email|max:255|unique:users,email',
            'role_id' => 'required|exists:roles,id',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $firstName = trim((string) $request->first_name);
        $lastName = trim((string) $request->last_name);
        $email = strtolower(trim((string) $request->email));
        $phone = trim((string) $request->phone);
        $isActive = $request->boolean('is_active', true);

        $generatedPassword = Str::random(12);

        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'email' => $email,
            'password' => Hash::make($generatedPassword),
            'role_id' => (int) $request->role_id,
            'is_active' => $isActive,
            'email_verified_at' => null,
        ]);

        $user->profile()->firstOrCreate([
            'user_id' => $user->id,
        ]);

        $user->load(['role', 'profile']);

        $token = Password::broker()->createToken($user);

        $resetBaseUrl = rtrim(
            env('FRONTEND_RESET_PASSWORD_URL', 'https://www.d.ashbhub.com/reset-password'),
            '/'
        );

        $loginUrl = rtrim(
            env('FRONTEND_LOGIN_URL', 'https://www.d.ashbhub.com/login'),
            '/'
        );

        $resetUrl = $resetBaseUrl . '?token=' . urlencode($token) . '&email=' . urlencode($user->email);

        $emailSent = false;

        try {
            $user->notify(new AccountSetupNotification(
                resetUrl: $resetUrl,
                user: $user,
                loginUrl: $loginUrl,
                appName: config('app.name', 'ASHBHUB')
            ));

            $emailSent = true;
        } catch (\Throwable $e) {
            Log::error('Failed to send account setup email for admin-created user.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        $success = [
            'user' => $this->formatUser($user),
            'email_sent' => $emailSent,
            'reset_url' => $resetUrl,
        ];

        return $this->sendResponse(
            $success,
            $emailSent
                ? 'User created successfully and account setup email sent.'
                : 'User created successfully, but account setup email could not be sent.'
        );
    }

    /**
     * Send forgot password link.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $email = strtolower(trim((string) $request->email));

        $user = User::where('email', $email)->first();

        if (!$user) {
            return $this->sendError('User not found.', [
                'email' => ['We could not find a user with that email address.'],
            ], 404);
        }

        if (!(bool) $user->is_active) {
            return $this->sendError('Account disabled.', [
                'error' => 'This account is inactive. Please contact administrator.',
            ], 403);
        }

        $status = Password::broker()->sendResetLink([
            'email' => $email,
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            return $this->sendError('Failed to send reset link.', [
                'error' => __($status),
            ], 422);
        }

        return $this->sendResponse([], 'Password reset link sent successfully.');
    }

    /**
     * Reset password using token, email, password and password_confirmation.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
        ], [
            'password.confirmed' => 'Password confirmation does not match.',
            'password.regex' => 'Password must include at least one uppercase letter, one number, and one special character.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $email = strtolower(trim((string) $request->email));

        $status = Password::broker()->reset(
            [
                'email' => $email,
                'password' => (string) $request->password,
                'password_confirmation' => (string) $request->password_confirmation,
                'token' => (string) $request->token,
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                if (method_exists($user, 'tokens')) {
                    $user->tokens()->delete();
                }
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->sendError('Password reset failed.', [
                'error' => __($status),
            ], 422);
        }

        return $this->sendResponse([], 'Password reset successfully.');
    }

    /**
     * Login api.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $email = strtolower(trim((string) $request->email));

        if (!Auth::attempt([
            'email' => $email,
            'password' => $request->password,
        ])) {
            return $this->sendError('Unauthorised.', [
                'error' => 'Invalid email or password.',
            ], 401);
        }

        /** @var \App\Models\User|null $user */
        $user = User::with(['role', 'profile'])->find(Auth::id());

        if (!$user || !(bool) $user->is_active) {
            Auth::logout();

            return $this->sendError('Account disabled.', [
                'error' => 'Your account is inactive. Please contact administrator.',
            ], 403);
        }

        $user->update([
            'last_login_at' => now(),
        ]);

        $success = [
            'token' => $user->createToken('ASHB')->plainTextToken,
            'user' => $this->formatUser($user),
        ];

        return $this->sendResponse($success, 'User login successfully.');
    }

    /**
     * Format user response.
     */
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