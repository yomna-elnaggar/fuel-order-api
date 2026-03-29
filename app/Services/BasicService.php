<?php

namespace App\Services;

class BasicService extends BaseService
{
    public function __construct()
    {
        parent::__construct(env('BASIC_SETTINGS_SERVICE_URL', 'http://127.0.0.1:8004/api'));
    }

    public function getFuels()
    {
        return $this->get("fuels");
    }

    public function getFuel($id)
    {
        return $this->get("fuels/{$id}");
    }
}
