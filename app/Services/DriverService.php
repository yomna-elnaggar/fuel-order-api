<?php

namespace App\Services;

class DriverService extends BaseService
{
    public function __construct()
    {
        parent::__construct(env('DRIVER_SERVICE_URL', 'http://localhost/driver-api/api'));
    }

    public function getDriver($id)
    {
        return $this->get("drivers/service/{$id}");
    }

    public function getDrivers()
    {
        return $this->get("drivers/service");
    }

    public function getDriversByCompany($companyId)
    {
        return $this->get("drivers/service", ['company_id' => $companyId, 'limit' => 'all']);
    }
}
