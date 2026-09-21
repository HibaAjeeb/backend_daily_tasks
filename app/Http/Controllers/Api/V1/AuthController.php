<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController
{
    use ApiResponse;

    public function register(RegisterRequest $request)
    {
        $email = $request->string('email')->toString();

        if (User::where('email', $email)->exists()) {
            throw new ApiException('EMAIL_ALREADY_EXISTS', 'The email address is already in use.', 409);
        }

        $user = User::create([
            'name' => $request->string('name')->toString(),
            'email' => $email,
            'password' => Hash::make($request->string('password')->toString()),
        ]);

        return $this->success($this->tokens($user) + [
            'userId' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->string('email')->toString())->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw new ApiException('INVALID_CREDENTIALS', 'Invalid email or password.', 401);
        }

        return $this->success($this->tokens($user));
    }

    public function refresh(Request $request)
    {
        $request->validate(['refreshToken' => ['required', 'string']]);
        $token = PersonalAccessToken::findToken($request->string('refreshToken')->toString());

        if (! $token || ! $token->can('refresh') || $token->expires_at?->isPast()) {
            throw new ApiException('TOKEN_EXPIRED', 'The refresh token has expired.', 401);
        }

        $token->delete();

        return $this->success($this->tokens($token->tokenable));
    }

    public function logout(Request $request)
    {
        PersonalAccessToken::findToken($request->bearerToken())?->delete();

        return $this->success(['message' => 'Logged out successfully.']);
    }

    private function tokens(User $user): array
    {
        return [
            'accessToken' => $user->createToken('access', ['*'], now()->addHour())->plainTextToken,
            'refreshToken' => $user->createToken('refresh', ['refresh'], now()->addDays(30))->plainTextToken,
            'expiresIn' => 3600,
        ];
    }
}
