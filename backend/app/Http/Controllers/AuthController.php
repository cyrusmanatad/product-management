<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Get a JWT via given credentials.
     *
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $credentials = [...$request->only('email', 'password'), 'is_active' => true, 'must_reset_password' => false];

        $token = Auth::attempt($credentials);

        if ($token && Auth::user()->must_reset_password) {
            Auth::logout();
            $token = false;
        }

        if (! $token) {
            return response()->json([
                'message' => 'The email or password is incorrect. Check both and try again.',
            ], 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Register a user and return a token.
     *
     * @return JsonResponse
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => strtolower($request->email),
            'password' => Hash::make($request->password),
        ]);

        $token = Auth::attempt($request->only('email', 'password'));

        return $this->respondWithToken($token, [
            'message' => 'User successfully registered',
            'user' => $user,
        ], 201);
    }

    /**
     * Get the authenticated User.
     *
     * @return UserResource
     */
    public function me(Request $request)
    {
        // return response()->json(Auth::user());

        // Eager load roles and permissions to avoid N+1
        $user = $request->user()->load('roles', 'permissions');

        return new UserResource($user);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return JsonResponse
     */
    public function logout()
    {
        Auth::logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(Auth::refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string  $token
     * @return JsonResponse
     */
    protected function respondWithToken($token, array $additionalData = [], int $statusCode = 200)
    {
        return response()->json([
            ...$additionalData,
            'authorization' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth()->factory()->getTTL() * 60,
                'must_reset_password' => (bool) auth()->user()?->must_reset_password,
            ],
        ], $statusCode);
    }
}
