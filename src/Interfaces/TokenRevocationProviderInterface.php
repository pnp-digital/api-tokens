<?php

namespace Pipeandpiper\ApiTokens\Interfaces;

interface TokenRevocationProviderInterface
{
    public function revokedHashes(): array;
}