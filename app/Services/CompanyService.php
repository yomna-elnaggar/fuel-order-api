<?php

namespace App\Services;

class CompanyService extends BaseService
{
    public function __construct()
    {
        parent::__construct(env('COMPANY_SERVICE_URL', 'http://127.0.0.1:8002/api'));
    }

    public function getCompany($id)
    {
        return $this->get("companies/{$id}");
    }

    public function getBranch($id)
    {
        return $this->get("branches/{$id}");
    }

    public function getCompanies()
    {
        return $this->get("companies");
    }

    public function getCompanySettings($id)
    {
        return $this->get("companies/{$id}/settings");
    }

    public function getCompanyBranches($companyId)
    {
        return $this->get("branches", ['company_id' => $companyId, 'limit' => 'all']);
    }
}
