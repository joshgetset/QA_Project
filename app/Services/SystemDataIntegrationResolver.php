<?php

namespace App\Services;

use App\Contracts\SystemDataIntegration;
use InvalidArgumentException;
use LogicException;

class SystemDataIntegrationResolver
{
    public function resolve(array $system): ?SystemDataIntegration
    {
        $integrationName = $system['integration'] ?? null;
        if ($integrationName === null) {
            return null;
        }
        if (! is_string($integrationName) || trim($integrationName) === '') {
            throw new InvalidArgumentException('A system integration must have a non-empty name.');
        }

        $integrationClass = config("qams.integrations.{$integrationName}");
        if (! is_string($integrationClass) || trim($integrationClass) === '') {
            throw new InvalidArgumentException("The QAMS integration [{$integrationName}] is not configured.");
        }

        $integration = app($integrationClass);
        if (! $integration instanceof SystemDataIntegration) {
            throw new LogicException("The QAMS integration [{$integrationName}] must implement ".SystemDataIntegration::class.'.');
        }

        return $integration;
    }
}
