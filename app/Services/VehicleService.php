<?php

namespace App\Services;

class VehicleService extends BaseService
{
    public function __construct()
    {
        parent::__construct(env('VEHICLE_SERVICE_URL', 'http://127.0.0.1:8001/api'));
    }

    public function getVehicle($id)
    {
        return $this->get("vehicles/service/{$id}");
    }

    public function getVehiclesByCompany($companyId)
    {
        return $this->get("vehicles/service", ['company_id' => $companyId, 'limit' => 'all']);
    }
}
