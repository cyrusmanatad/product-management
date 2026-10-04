<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(Request $request): UserResource
    {
        $user = $request->user()->load('roles', 'permissions');

        return new UserResource($user);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data' => new UserResource($user->fresh()->load('roles', 'permissions')),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
                'errors' => [
                    'current_password' => ['Current password is incorrect.'],
                ],
            ], 422);
        }

        $user->forceFill(['must_reset_password' => false, 'token_version' => $user->token_version + 1, 'password' => Hash::make($request->password)])->save();
        $token = auth('api')->login($user);

        return response()->json([
            'message' => 'Password updated successfully.',
            'authorization' => ['access_token' => $token],
        ]);
    }
}
