<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Pipeandpiper\API\Interfaces\ApiTokenResolverInterface;

readonly class ApiTokenResolver implements ApiTokenResolverInterface
{
    public function __construct(private TokenVerificationService $verifier)
    {
    }

    public function resolveToken(string $token): ?int
    {
        return $this->verifier->verify($token, []);
    }
}
