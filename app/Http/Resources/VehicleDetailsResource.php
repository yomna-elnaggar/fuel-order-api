<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VehicleDetailsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'plate_letters' => $this->plate_letters ?? '',
            'plate_numbers' => $this->plate_numbers ?? '',
            'odometer_number' => $this->odometer_number ?? 0,
            'company_id' => $this->company_id ?? null,
            'vehicle_type' => $this->vehicleType->title ?? '',
            'fuel_type' => $this->fuelType->title ?? '',
        ];
    }
}
