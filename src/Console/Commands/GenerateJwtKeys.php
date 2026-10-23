<?php

namespace Pipeandpiper\ApiTokens\Console\Commands;

use Illuminate\Console\Command;
use SodiumException;

class GenerateJwtKeys extends Command
{
    protected $signature = 'jwt:generate-keys';

    protected $description = 'Generate an Ed25519 keypair for JWT signing and verification';

    /**
     * @throws SodiumException
     */
    public function handle(): int
    {
        $keypair = sodium_crypto_sign_keypair();

        $privateKey = base64_encode(sodium_crypto_sign_secretkey($keypair));
        $publicKey = base64_encode(sodium_crypto_sign_publickey($keypair));

        $this->line("JWT_PRIVATE_KEY=$privateKey");
        $this->line("JWT_PUBLIC_KEY=$publicKey");

        return self::SUCCESS;
    }
}
