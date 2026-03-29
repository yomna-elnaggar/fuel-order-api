<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class CompanyAccountingService extends BaseService
{
    public function __construct()
    {
        parent::__construct(env('COMPANY_ACCOUNTING_SERVICE_URL', 'http://127.0.0.1/Company%20Accounting-api/public/api'));
    }

    /**
     * Add balance to company wallet (Refunds etc)
     */
    public function addBalance($companyId, $amount, $orderId = null)
    {
        Log::info("Requesting balance addition for company {$companyId} from external company accounting service.");

        return $this->post("accounting/add-balance", [
            'company_id' => $companyId,
            'amount'     => $amount,
            'order_id'   => $orderId,
        ]);
    }

    /**
     * Reduce company wallet balance
     */
    public function reduceBalance($companyId, $amount, $orderId = null)
    {
        Log::info("Requesting balance reduction for company {$companyId} from external company accounting service.");

        return $this->post("accounting/reduce-balance", [
            'company_id' => $companyId,
            'amount'     => $amount,
            'order_id'   => $orderId,
        ]);
    }
}
