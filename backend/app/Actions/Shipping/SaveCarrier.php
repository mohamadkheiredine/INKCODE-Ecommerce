<?php

namespace App\Actions\Shipping;

use App\Models\Carrier;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Carrier, or saves an existing one's new name and tracking link template.
 */
final class SaveCarrier
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    /**
     * @param  array{name: string, tracking_url_template: string|null}  $values
     */
    public function handle(?Carrier $carrier, array $values): Carrier
    {
        return DatabaseErrors::refuseDuplicate(fn (): Carrier => DB::transaction(function () use ($carrier, $values): Carrier {
            if ($carrier === null) {
                $carrier = Carrier::query()->create($values);
                $this->auditTrail->created($carrier);

                return $carrier;
            }

            $before = $carrier->auditValues();
            $carrier->update($values);
            $this->auditTrail->updated($carrier, $before);

            return $carrier;
        }), [
            'carriers_name_unique' => ['name', __('validation.unique', ['attribute' => 'name'])],
        ]);
    }
}
