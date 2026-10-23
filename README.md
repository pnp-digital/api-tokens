# API Tokens

JWT authentication for `pnp-digital/generic-api`.

## Installation

```bash
composer require pnp-digital/api-tokens
```

Requires PHP 8.3+, Sodium and Laravel 12+.

## How it works

- **TokenService** — Issues signed JWTs, stores token metadata, lists tokens and handles revocation.
- **TokenVerificationService** — Validates JWT signatures, timestamps and revocation hashes. Returns the user ID or `null`.
- **ApiTokenResolver** — Implements Generic API's `ApiTokenResolverInterface`, passing the verified user ID to Generic API.
- **GenerateJwtKeys** — Artisan command for generating Ed25519 signing keys.

Tokens are signed using Ed25519 and verified using the corresponding public key.

## Configuration

Generate a keypair:

```bash
php artisan jwt:generate-keys
```

Configure the application issuing tokens:

```dotenv
JWT_PRIVATE_KEY=<generated-private-key>
JWT_PUBLIC_KEY=<generated-public-key>
```

Other applications sharing the same user IDs only require the public key:

```dotenv
JWT_PUBLIC_KEY=<generated-public-key>
```

The package loads `config/api_tokens.php` automatically.

## Issuing tokens

```php
use Pipeandpiper\ApiTokens\Services\TokenService;

$token = app(TokenService::class)->create($user, 'Integration', now()->addYear());
```

The returned JWT must be shown or stored securely when created. The full token cannot be retrieved later.

Token metadata is stored in the application's `api_tokens` table, including a SHA-256 hash and the first and last 10 characters for identification.

## Managing tokens

```php
$service = app(TokenService::class);

$tokens = $service->list($user);
$service->revoke($user, $tokenId);
$revoked = $service->revoked();
```

Revoked and expired token records are retained.

## Authentication

The package automatically registers `ApiTokenResolver` with Generic API.

Clients authenticate using:

```http
Authorization: Bearer <jwt>
```

The resolver verifies the JWT and returns its subject ID. Generic API then retrieves the corresponding user.

No additional authentication guard configuration is required.

## Revocation

`TokenVerificationService::verify()` accepts an array of revoked token SHA-256 hashes:

```php
$userId = $verifier->verify($jwt, $revokedHashes);
```