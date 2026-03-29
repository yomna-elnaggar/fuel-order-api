<?php

namespace App\Services;

class ServiceProviderService extends BaseService
{
    public function __construct()
    {
        parent::__construct(env('SERVICE_PROVIDER_SERVICE_URL', 'http://127.0.0.1:8003/api'));
    }

    public function getServiceProvider($id)
    {
        return $this->get("service-providers/{$id}");
    }

    public function getBranch($id)
    {
        return $this->get("branches/{$id}");
    }

    public function getEmployee($id)
    {
        return $this->get("employees/{$id}");
    }

    public function getServiceProviders($limit = null)
    {
        return $this->get("service-providers", ['limit' => $limit]);
    }

    public function getEmployees()
    {
        return $this->get("employees");
    }

    public function getUser($id)
    {
        return $this->get("users/{$id}");
    }

    public function getEmployeesByProvider($providerId)
    {
        return $this->get("employees", ['service_provider_id' => $providerId, 'limit' => 'all']);
    }
}
