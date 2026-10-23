<?php

namespace Pipeandpiper\ApiTokens\Services;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Exception as JwtException;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Signer\Eddsa;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Lcobucci\JWT\Validation\Validator;
use Symfony\Component\Clock\NativeClock;

class TokenVerificationService
{
    public function verify(string $jwt, array $revokedHashes): int|null
    {
        if (in_array(hash('sha256', $jwt), $revokedHashes, true)) {
            return null;
        }
        try {
            $token = new Parser(new JoseEncoder())->parse($jwt);
        } catch (JwtException) {
            return null;
        }

        if (!new Validator()->validate($token, new SignedWith(new Eddsa(), InMemory::base64Encoded(config('api_tokens.public_key'))), new StrictValidAt(new NativeClock()))) {
            return null;
        }

        return (int) $token->claims()->get('sub');
    }
}
