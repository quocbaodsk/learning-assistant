<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'       => 'required|email',
            'password'    => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $device      = $request->device_name ?? $request->userAgent() ?? 'API Token';
        $accessToken = explode('|', $user->createToken($device)->plainTextToken)[1];

        return response()->json([
            'data'    => [
                'user'  => $user,
                'token' => $accessToken,
            ],
            'status'  => 200,
            'message' => 'Login successful',
        ]);
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(Request $request)
    {
        $request->validate([
            'bio'         => 'nullable|string|max:255',
            'age'         => 'required|integer|min:1|max:120',
            'name'        => 'required|string|max:255',
            'email'       => 'required|string|email|max:255|unique:users',
            'gender'      => 'required|string|in:male,female,other',
            'language'    => 'required|string|max:3',
            'password'    => ['required', 'confirmed', Password::defaults()],
            'device_name' => 'nullable|string',
        ]);

        $user = User::create([
            'bio'      => $request->bio,
            'age'      => $request->age,
            'name'     => $request->name,
            'email'    => $request->email,
            'gender'   => $request->gender,
            'language' => $request->language ?? 'en',
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        $device = $request->device_name ?? $request->userAgent() ?? 'API Token';

        $accessToken = explode('|', $user->createToken($device)->plainTextToken)[1];

        return response()->json([
            'data'    => [
                'user'  => $user,
                'token' => $accessToken,
            ],
            'status'  => 201,
            'message' => 'Registration successful',
        ], 201);
    }

    /**
     * Destroy an authenticated session.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Get the authenticated user.
     */
    public function user(Request $request)
    {
        return response()->json($request->user());
    }
}
