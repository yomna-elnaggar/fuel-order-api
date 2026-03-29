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
        return $this->get("drivers/{$id}");
    }

    public function getDrivers()
    {
        return $this->get("drivers");
    }

    public function getDriversByCompany($companyId)
    {
        return $this->get("drivers", ['company_id' => $companyId, 'limit' => 'all']);
    }
}
