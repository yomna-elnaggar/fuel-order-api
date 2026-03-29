<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class VehicleAccountingService extends BaseService
{
    public function __construct()
    {
        parent::__construct(env('VEHICLE_ACCOUNTING_SERVICE_URL', 'http://localhost/vehicle-accounting-api/api'));
    }

    /**
     * Request balance check from Vehicle Accounting Microservice
     * Matches GET /vehicles-accounting/wallet in vehicle-accounting-api
     */
    public function checkWalletBalanceFuel($vehicleId, $price)
    {
        Log::info("Requesting balance check for vehicle {$vehicleId} from external accounting service.");
        
        $response = $this->get("vehicles-accounting/wallet", ['vehicle_id' => $vehicleId]);
        
        return $response;
    }

    /**
     * Add balance to vehicle
     * Matches POST /vehicles-accounting/add-balance in vehicle-accounting-api
     */
    public function addBalance($vehicleId, $amount, $orderId = null)
    {
        Log::info("Requesting balance addition for vehicle $vehicleId from external service.");
        
        return $this->post("vehicles-accounting/add-balance", [
            'vehicle_id' => $vehicleId,
            'amount'     => $amount,
            'order_id'   => $orderId
        ]);
    }

    /**
     * Reduce balance from vehicle
     * Matches POST /vehicles-accounting/reduce-balance in vehicle-accounting-api
     */
    public function reduceBalance($vehicleId, $amount, $orderId = null)
    {
        Log::info("Requesting balance reduction for vehicle $vehicleId from external service.");
        
        return $this->post("vehicles-accounting/reduce-balance", [
            'vehicle_id' => $vehicleId,
            'amount'     => $amount,
            'order_id'   => $orderId
        ]);
    }
}
