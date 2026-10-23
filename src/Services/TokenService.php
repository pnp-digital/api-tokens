<?php

namespace Pipeandpiper\ApiTokens\Services;

use App\Models\ApiToken;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Eddsa;
use Lcobucci\JWT\Signer\Key\InMemory;
use Random\RandomException;

class TokenService
{
    /**
     * @throws RandomException
     */
    public function create(User $user, string $name, CarbonInterface $expiresAt): string
    {
        if ($expiresAt->getTimestamp() <= time()) {
            throw new InvalidArgumentException('Token expiry must be in the future.');
        }

        $config = Configuration::forAsymmetricSigner(
            new Eddsa(),
            InMemory::base64Encoded(config('api_tokens.private_key')),
            InMemory::base64Encoded(config('api_tokens.public_key'))
        );

        $now = now()->toDateTimeImmutable();

        $token = $config->builder()
            ->relatedTo((string) $user->getKey())
            ->identifiedBy(bin2hex(random_bytes(16)))
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($expiresAt->toDateTimeImmutable())
            ->getToken($config->signer(), $config->signingKey());

        $jwt = $token->toString();

        ApiToken::create([
            'user_id' => $user->getKey(),
            'name' => $name,
            'sha256' => hash('sha256', $jwt),
            'token_prefix' => substr($jwt, 0, 10),
            'token_suffix' => substr($jwt, -10),
            'expires_at' => $expiresAt,
        ]);

        return $jwt;
    }

    public function list(User $user): Collection
    {
        return ApiToken::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('created_at')
            ->get();
    }

    public function revoke(User $user, int $tokenId): void
    {
        $token = ApiToken::query()
            ->where('user_id', $user->getKey())
            ->findOrFail($tokenId);

        if ($token->revoked_at === null) {
            $token->revoked_at = now();
            $token->save();
        }
    }

    public function revoked(): Collection
    {
        return ApiToken::query()
            ->whereNotNull('revoked_at')
            ->where('expires_at', '>', now())
            ->get(['sha256', 'expires_at']);
    }
}
