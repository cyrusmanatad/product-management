<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function request(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'If that account exists, a password reset link has been sent.']);
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'token' => 'required|string', 'password' => 'required|string|min:12|not_in:Password@1234|confirmed']);
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'must_reset_password' => false, 'token_version' => $user->token_version + 1, 'remember_token' => Str::random(60)])->save();
        });
        abort_unless($status === Password::PASSWORD_RESET, 422, 'Password reset link is invalid or expired.');

        return response()->json(['message' => 'Password reset. Sign in with your new password.']);
    }
}
