<?php

namespace App\Services\OAuth;

final readonly class ProviderIdentity
{
    public function __construct(
        public string $provider,
        public string $subject,
        public string $name,
        public ?string $email,
        public bool $emailVerified,
        public ?string $avatarUrl = null,
    ) {}
}
