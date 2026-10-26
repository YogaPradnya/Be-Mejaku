<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

abstract class TestCase extends BaseTestCase
{
    /**
     * Buat JWT untuk user (pengganti Sanctum::actingAs).
     */
    protected function jwtFor(User $user): string
    {
        return JWTAuth::fromUser($user);
    }

    /**
     * Autentikasi request berikutnya sebagai user via JWT.
     */
    protected function actingAsJwt(User $user): static
    {
        return $this->withToken($this->jwtFor($user));
    }
}
