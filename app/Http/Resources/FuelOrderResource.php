<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FuelOrderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'vehicle_id' => $this->vehicle_id,
            'fuel_id' => $this->fuel_id,
            'company_id' => $this->company_id,
            'service_provider_id' => $this->service_provider_id,
            'branch_id' => $this->branch_id,
            'reference_number' => $this->reference_number,
            'quantity' => $this->quantity,
            'total_price' => $this->total_price,
            'status' => $this->status,
            'verified_at' => $this->verified_at,
            'odometer_number' => $this->odometer_number,
            'pump_match_price' => $this->pump_match_price,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
