<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class BranchAccountingService extends BaseService
{
    public function __construct()
    {
        // Assuming Branch Accounting is handled by the same company-accounting-api service but different endpoints or params
        // Or if it's a different URL, update accordingly
        parent::__construct(env('BRANCH_ACCOUNTING_SERVICE_URL', env('COMPANY_ACCOUNTING_SERVICE_URL', 'http://127.0.0.1/Company%20Accounting-api/public/api')));
    }

    /**
     * Add balance to branch wallet (Refunds etc)
     */
    public function addBalance($branchId, $amount, $orderId = null)
    {
        Log::info("Requesting balance addition for branch {$branchId} from external accounting service.");

        return $this->post("accounting/branch/add-balance", [
            'branch_id' => $branchId,
            'amount'     => $amount,
            'order_id'   => $orderId,
        ]);
    }

    /**
     * Reduce branch wallet balance
     */
    public function reduceBalance($branchId, $amount, $orderId = null)
    {
        Log::info("Requesting balance reduction for branch {$branchId} from external accounting service.");

        return $this->post("accounting/branch/reduce-balance", [
            'branch_id' => $branchId,
            'amount'     => $amount,
            'order_id'   => $orderId,
        ]);
    }
}
