<?php

namespace Modules\Tally\Http\Requests\Concerns;

use Modules\Tally\Services\Fields\TallyFieldRegistry;

/**
 * Mixin for Form Requests whose payload accepts any TallyPrime-UI alias in
 * addition to the canonical XML tag. Pass the entity identifier via the
 * `$tallyEntity` property on the host class.
 *
 * Laravel calls prepareForValidation() right before rules() runs, so by the
 * time validation checks `NAME` / `PARENT` / etc., the payload has been
 * rewritten to canonical keys — existing rules keep working unchanged.
 */
trait AcceptsFieldAliases
{
    protected function prepareForValidation(): void
    {
        $entity = $this->tallyEntity ?? null;
        if ($entity === null) {
            return;
        }

        $this->replace(TallyFieldRegistry::canonicalize($entity, $this->all()));
    }
}
