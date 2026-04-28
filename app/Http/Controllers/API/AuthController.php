<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Email atau password salah.'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Akun tidak aktif. Hubungi administrator.'], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('hris-msr-token')->plainTextToken;
        $user->load('employee');

        return response()->json([
            'message' => 'Login berhasil.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'department' => $user->employee?->department,
                'position' => $user->employee?->position,
                'employee_number' => $user->employee?->employee_number,
                'photo' => $user->employee?->photo,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('employee');
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'department' => $user->employee?->department,
                'position' => $user->employee?->position,
                'employee_number' => $user->employee?->employee_number,
                'photo' => $user->employee?->photo,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout berhasil.']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Password lama tidak sesuai.'], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);
        $user->tokens()->delete();

        return response()->json(['message' => 'Password berhasil diubah. Silakan login kembali.']);
    }
}
