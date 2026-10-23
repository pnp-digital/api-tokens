<?php

namespace Pipeandpiper\ApiTokens\Services;

use Pipeandpiper\API\Interfaces\ApiTokenResolverInterface;
use Pipeandpiper\ApiTokens\Interfaces\TokenRevocationProviderInterface;

readonly class ApiTokenResolver implements ApiTokenResolverInterface
{
    public function __construct(private TokenVerificationService $verifier)
    {
    }

    public function resolveToken(string $token): ?int
    {
        $revokedHashes = app()->bound(TokenRevocationProviderInterface::class) ? app(TokenRevocationProviderInterface::class)->revokedHashes() : [];
        return $this->verifier->verify($token, $revokedHashes);
    }
}