# API Tokens

Ed25519 JWT authentication for `pnp-digital/generic-api`.

## Installation

```bash
composer require pnp-digital/api-tokens
```

Requires PHP 8.3+, Sodium and Laravel 12+.

The package automatically registers its service provider, authentication resolver and database migration.

## Configuration

Generate an Ed25519 keypair:

```bash
php artisan jwt:generate-keys
```

Configure the issuing application:

```dotenv
JWT_PRIVATE_KEY=<generated-private-key>
JWT_PUBLIC_KEY=<generated-public-key>
```

Applications that only verify tokens but share the same user ids require the same public key:

```dotenv
JWT_PUBLIC_KEY=<generated-public-key>
```

The package loads `config/api_tokens.php` automatically.

## Issuing tokens

```php
use Pipeandpiper\ApiTokens\Services\TokenService;

$service = app(TokenService::class);

$jwt = $service->create($user, 'Integration', now()->addYear());
```

The full JWT is returned only when created. Token metadata, including its SHA-256 hash and first/last 10 characters, is stored in `api_tokens`.

## Managing tokens

```php
$tokens = $service->list($user);
$service->revoke($user, $tokenId);
$revoked = $service->revoked();
```

Revoked and expired token records are retained.

## Authentication

The package automatically registers `ApiTokenResolver` with Generic API.

```http
Authorization: Bearer <jwt>
```

Tokens are verified using the public key. The resolver returns the user ID, which Generic API uses to authenticate the request.

## Revocation

Applications can optionally implement `TokenRevocationProviderInterface` to supply revoked token SHA-256 hashes.

```php
use Pipeandpiper\ApiTokens\Interfaces\TokenRevocationProviderInterface;

class TokenRevocationProvider implements TokenRevocationProviderInterface
{
    public function revokedHashes(): array
    {
        return ['<sha256-hash>', '<sha256-hash>'];
    }
}
```

Register the implementation in `AppServiceProvider::register()`:

```php
$this->app->bind(TokenRevocationProviderInterface::class, TokenRevocationProvider::class);
```

The resolver automatically uses the registered provider. If none is registered, tokens are verified without revocation checks.

The provider can retrieve hashes from the local database, a cache or another application.