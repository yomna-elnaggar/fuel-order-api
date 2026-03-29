<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ServiceProviderWalletService extends BaseService
{
    public function __construct()
    {
        parent::__construct(env('SERVICE_PROVIDER_WALLET_SERVICE_URL', 'http://127.0.0.1/service-provider-wallet-api/public/api'));
    }

    /**
     * Process Order Transaction in External Wallet Service
     */
    public function processOrder($order)
    {
        Log::info("Requesting order transaction record for order {$order->id} from external wallet service.");

        return $this->post("accounting/order", [
            'id'                  => $order->id,
            'service_provider_id' => $order->service_provider_id,
            'total_price'         => $order->total_price,
            'reference_number'    => $order->reference_number,
            'user_id'             => $order->user_id,
            'branch_id'           => $order->branch_id,
            'employee_id'         => $order->employee_id,
        ]);
    }

    /**
     * Add balance to service provider wallet
     */
    public function addBalance($providerId, $amount, $orderId = null)
    {
        Log::info("Adding balance to provider {$providerId} for order {$orderId}");
        return $this->post("accounting/add-balance", [
            'service_provider_id' => $providerId,
            'amount'              => $amount,
            'order_id'            => $orderId
        ]);
    }

    /**
     * Reduce balance from service provider wallet (Refund case)
     */
    public function reduceBalance($providerId, $amount, $orderId = null)
    {
        Log::info("Reducing balance from provider {$providerId} for order {$orderId}");
        return $this->post("accounting/reduce-balance", [
            'service_provider_id' => $providerId,
            'amount'              => $amount,
            'order_id'            => $orderId
        ]);
    }
}
