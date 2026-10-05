<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthApiController extends Controller
{
    /**
     * Authenticate via API and obtain Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
        ]);

        $loginInput = $validated['email'];
        if (config('app.preview_mode') && app()->environment(['local', 'staging', 'testing'])
            && $loginInput === 'admin') {
            $loginInput = 'owner@preview.aquaoptom.test';
        }
        $user = filter_var($loginInput, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $loginInput)->first()
            : User::where('phone', $loginInput)->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kiritilgan login yoki parol noto\'g\'ri.',
            ], 401);
        }

        if (! $user->isActive()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Hisobingiz bloklangan. Iltimos, do\'kon egasiga murojaat qiling.',
            ], 403);
        }

        $deviceName = $validated['device_name'] ?? 'aquaoptom-mobile-device';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'permissions' => $user->getAllPermissions(),
            ],
        ]);
    }

    /**
     * Logout and revoke active Sanctum token.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sessiya muvaffaqiyatli yakunlandi va token bekor qilindi.',
        ]);
    }

    /**
     * Get authenticated user profile and permissions.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'permissions' => $user->getAllPermissions(),
            ],
        ]);
    }
}
