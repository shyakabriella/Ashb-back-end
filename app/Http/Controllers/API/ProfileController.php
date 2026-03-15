<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends BaseController
{
    /**
     * Get logged-in user profile.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (!$user) {
            return $this->sendError('Unauthorised.', [
                'error' => 'User not authenticated.',
            ], 401);
        }

        $user->load(['role', 'profile']);

        return $this->sendResponse([
            'user' => $this->formatUser($user),
        ], 'Profile fetched successfully.');
    }

    /**
     * Update logged-in user profile.
     */
    public function update(Request $request): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (!$user) {
            return $this->sendError('Unauthorised.', [
                'error' => 'User not authenticated.',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'dob' => 'nullable|date|before:tomorrow',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $user->update([
            'first_name' => trim((string) $request->first_name),
            'last_name' => trim((string) $request->last_name),
            'email' => strtolower(trim((string) $request->email)),
            'phone' => $request->filled('phone') ? trim((string) $request->phone) : null,
        ]);

        $profile = $user->profile()->firstOrCreate([]);

        if ($request->hasFile('avatar')) {
            if ($profile->avatar && Storage::disk('public')->exists($profile->avatar)) {
                Storage::disk('public')->delete($profile->avatar);
            }

            $avatarPath = $request->file('avatar')->store('profile-photos', 'public');
            $profile->avatar = $avatarPath;
        }

        $profile->date_of_birth = $request->filled('dob') ? $request->dob : null;
        $profile->save();

        $user->load(['role', 'profile']);

        return $this->sendResponse([
            'user' => $this->formatUser($user),
        ], 'Profile updated successfully.');
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