<?php

namespace Pipeandpiper\API\Interfaces;

/**
 * Resolves an application-defined API token to a user ID.
 *
 * The consuming application is responsible for determining whether the token
 * is valid, including any expiry, revocation or other application-specific
 * requirements.
 */
interface ApiTokenResolverInterface
{
    /**
     * Resolve an API bearer token to a user ID.
     *
     * @param string $token Bearer token supplied by the API consumer.
     * @return int|null User ID represented by the token, or null if invalid.
     */
    public function resolveToken(string $token): ?int;
}