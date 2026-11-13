<?php

namespace App\Contracts;

interface SystemDataIntegration
{
    public function cacheVersion(): ?string;

    public function acceptsCachedData(array $data): bool;

    public function usesLocalEndpoint(string $endpoint): bool;

    /**
     * @return array<string, mixed>
     */
    public function fetchLocalData(): array;
}
