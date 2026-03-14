<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountSetupNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RegisterController extends BaseController
{
    /**
     * Get active roles for dropdown/select.
     */
    public function roles(): JsonResponse
    {
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
     * Get saved users for frontend.
     */
    public function users(): JsonResponse
    {
        $users = User::with('role')
            ->latest()
            ->get()
            ->map(function (User $user) {
                return $this->formatUser($user);
            })
            ->values();

        return $this->sendResponse($users, 'Users fetched successfully.');
    }

    /**
     * Admin creates a user and sends account setup email.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'phone'      => 'required|string|max:20',
            'email'      => 'required|string|email|max:255|unique:users,email',
            'role_id'    => 'required|exists:roles,id',
            'is_active'  => 'nullable|boolean',
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
            'first_name'        => $firstName,
            'last_name'         => $lastName,
            'phone'             => $phone,
            'email'             => $email,
            'password'          => $generatedPassword,
            'role_id'           => (int) $request->role_id,
            'is_active'         => $isActive,
            'email_verified_at' => null,
        ]);

        $user->load('role');

        $token = Password::broker()->createToken($user);

        $resetBaseUrl = rtrim(
            env('FRONTEND_RESET_PASSWORD_URL', 'https://www.d.ashbhub.com/reset-password'),
            '/'
        );

        $loginUrl = rtrim(
            env('FRONTEND_LOGIN_URL', 'https://www.d.ashbhub.com/login'),
            '/'
        );

        $resetUrl = $resetBaseUrl
            . '?token=' . urlencode($token)
            . '&email=' . urlencode($user->email);

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
                'email'   => $user->email,
                'error'   => $e->getMessage(),
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
     * Login api.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $email = strtolower(trim((string) $request->email));

        if (!Auth::attempt([
            'email'    => $email,
            'password' => $request->password,
        ])) {
            return $this->sendError('Unauthorised.', [
                'error' => 'Invalid email or password.',
            ], 401);
        }

        /** @var \App\Models\User|null $user */
        $user = User::with('role')->find(Auth::id());

        if (!$user || !$user->is_active) {
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
            'user'  => [
                'id'            => $user->id,
                'first_name'    => $user->first_name,
                'last_name'     => $user->last_name,
                'name'          => $user->name,
                'phone'         => $user->phone,
                'email'         => $user->email,
                'is_active'     => (bool) $user->is_active,
                'last_login_at' => $user->last_login_at,
                'role'          => [
                    'id'   => $user->role?->id,
                    'name' => $user->role?->name,
                    'slug' => $user->role?->slug,
                ],
            ],
        ];

        return $this->sendResponse($success, 'User login successfully.');
    }

    /**
     * Format user response.
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
            'role' => [
                'id' => $user->role?->id,
                'name' => $user->role?->name,
                'slug' => $user->role?->slug,
            ],
            'created_at' => $user->created_at,
        ];
    }
}