<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\Constants;
use App\Helpers\Image;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Controller;
use App\Http\Resources\FuelOrderResource;
use App\Models\Ordering\FuelOrder;
use App\Models\Ordering\OrderLog; 
use App\Models\Ordering\OrderMedia;
use App\Services\VehicleService;
use App\Services\DriverService;
use App\Services\ServiceProviderService;
use App\Services\VehicleAccountingService;
use App\Services\CompanyAccountingService;
use App\Services\BranchAccountingService;
use App\Services\CompanyService;
use App\Services\ServiceProviderWalletService;
use App\Services\BasicService;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\FuelOrdersExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class FuelOrderController extends ApiController
{
    /**
     * Get data required to create a new order (fuels, companies, providers).
     */
    public function create()
    {
        $fuelsRaw = (new BasicService())->getFuels();
        $fuels = $fuelsRaw['items'] ?? $fuelsRaw ?? [];

        $companiesRaw = (new CompanyService())->getCompanies();
        $companies = $companiesRaw['items'] ?? $companiesRaw ?? [];

        $providersRaw = (new ServiceProviderService())->getServiceProviders();
        $service_providers = $providersRaw['items'] ?? $providersRaw ?? [];

        return ApiController::respondWithSuccess('Creation data retrieved successfully', [
            'fuels' => $fuels,
            'companies' => $companies,
            'service_providers' => $service_providers,
        ]);
    }

    /**
     * Display a listing of fuel orders (Admin view).
     */
    public function index(Request $request)
    {
        $data = $request->all();

        // Apply filters
        $query = FuelOrder::query()->latest();

        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->whereBetween('created_at', [$request->date_from, $request->date_to]);
        }

        // Handle Excel Export
        if ($request->action == 'export') {
            return Excel::download(new FuelOrdersExport($query->get()), 'fuel_orders.xlsx');
        }

        $items = $query->paginate($request->per_page ?? 50);

        // Fetch filter lists from Microservices to restore dropdowns functionality
        $companies = (new CompanyService())->getCompanies() ?: [];
        $drivers = (new DriverService())->getDrivers() ?: [];
        $employees = (new ServiceProviderService())->getEmployees() ?: [];
        $service_providers = (new ServiceProviderService())->getServiceProviders() ?: [];

        return ApiController::respondWithSuccess('Fuel orders retrieved successfully', [
            'orders' => FuelOrderResource::collection($items),
            'filters_data' => [
                'companies' => $companies,
                'drivers' => $drivers,
                'employees' => $employees,
                'service_providers' => $service_providers,
            ],
            'pagination' => [
                'total' => $items->total(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
            ],
            'summary' => [
                'total_price_all_pages' => $query->sum('total_price'),
                'total_price_current_page' => $items->sum('total_price'),
            ]
        ]);
    }

    /**
     * Display details of a fuel order.
     */
    public function show($id)
    {
        $order = FuelOrder::findOrFail($id);

        // Fetch external details for admin view if needed
        $driver = (new DriverService())->getDriver($order->user_id);
        $vehicle = (new VehicleService())->getVehicle($order->vehicle_id);
        $company = (new CompanyService())->getCompany($order->company_id);

        return ApiController::respondWithSuccess('Order details retrieved successfully', [
            'order' => new FuelOrderResource($order),
            'external_data' => [
                'driver' => $driver,
                'vehicle' => $vehicle,
                'company' => $company,
            ]
        ]);
    }

    /**
     * Create a new fuel order from Admin dashboard.
     */
    public function store(Request $request)
    {
        $request->merge([
            'service_provider_id' => $request->service_provider_id ?? $request->puncher_id ?? $request->provider_id
        ]);

        $validator = Validator::make($request->all(), [
            'user_id' => 'required', 
            'vehicle_id' => 'required', 
            'fuel_id' => 'required',
            'quantity' => 'required|numeric',
            'total_price' => 'required|numeric',
            'status' => 'required|integer',
            'plate_image' => 'nullable|image',
            'odometer_image' => 'nullable|image',
            'odometer_number' => 'nullable|string',
            'service_provider_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiController::respondWithError('Validation errors', $validator->errors(), 422);
        }

        DB::beginTransaction();
        try {
            $data = $request->except(['plate_image', 'odometer_image', 'puncher_id', 'provider_id']);
            $data['reference_number'] = rand(100000000, 999999999);

            // Fetch vehicle and company details from Services
            $vehicle = (new VehicleService())->getVehicle($request->vehicle_id);
            if (!$vehicle) {
                return ApiController::respondWithError('Vehicle not found', null, 404);
            }
            

            $company_id = $vehicle['company_id'] ?? null;
            $data['company_id'] = $company_id;
            $branch_id = $vehicle['branch_id'] ?? null;
            $data['branch_id'] = $branch_id;

            // CHECK BALANCE VIA MICROSERVICE
            $accounting = new VehicleAccountingService();
            $balanceCheck = $accounting->checkWalletBalanceFuel($request->vehicle_id, $request->total_price);

            if (!$balanceCheck) {
                return ApiController::respondWithError(__('messages.wallet_not_found'), null, 404);
            }

            /* 
            // Allow Admin to skip balance check
            if (($balanceCheck['balance'] ?? 0) < $request->total_price) {
                return ApiController::respondWithError(__('messages.driver_WalletLimitation'), null, 421);
            }
            */

            // CHECK IF DRIVER CAN USE COMPANY BALANCE (FROM COMPANY SERVICE)
            $companySettings = (new CompanyService())->getCompanySettings($company_id);
            $canUseCompanyBalance = $companySettings['vehicles_can_use_fuel_balance'] ?? false;

            // CREATE THE ORDER
            $order = FuelOrder::create($data);

            // Handle wallet transactions based on company settings (After Order Creation)
            if ($canUseCompanyBalance) {
                // Adjust wallets via accounting services and link to $order->id
                (new VehicleAccountingService())->addBalance($order->vehicle_id, $order->total_price, $order->id);
                
                // If branch wallet is enabled and exists, reduce from branch
                if (($companySettings['can_branch_has_wallet'] ?? false) && $order->branch_id) {
                    (new BranchAccountingService())->reduceBalance($order->branch_id, $order->total_price, $order->id);
                } else {
                    (new CompanyAccountingService())->reduceBalance($order->company_id, $order->total_price, $order->id);
                }
            }

            // HANDLE MEDIA
            if ($request->hasFile('plate_image') || $request->hasFile('odometer_image')) {
                 $mediaData = ['order_id' => $order->id, 'type_id' => 1];
                 if ($request->hasFile('plate_image')) {
                     $mediaData['vehicle_plate'] = Image::uploadToPublic($request->file('plate_image'), 'orders/plates');
                 }
                 if ($request->hasFile('odometer_image')) {
                     $mediaData['odometer_image'] = Image::uploadToPublic($request->file('odometer_image'), 'orders/odometers');
                 }
                 OrderMedia::create($mediaData);
            }

            // IF CONFIRMED, REDUCE FROM VEHICLE WALLET (THE ACTUAL PAYMENT) AND ADD TO SERVICE PROVIDER WALLET
            if ($order->status == Constants::CONFIRM_ORDER) {
                 (new VehicleAccountingService())->reduceBalance($request->vehicle_id, $request->total_price, $order->id);
                 (new ServiceProviderWalletService())->processOrder($order);
            }

            DB::commit();
            return ApiController::respondWithSuccess('Fuel order created successfully', new FuelOrderResource($order));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin Fuel Order Create Error: ' . $e->getMessage());
            return ApiController::respondWithError('Creation failed', $e->getMessage(), 500);
        }
    }

    

    /**
     * Update order and handle balance refunds/deductions.
     */
    public function update(Request $request, $id)
    {
        $order = FuelOrder::findOrFail($id);
        $oldPrice = (float) $order->total_price;
        $newPrice = (float) ($request->total_price ?? $oldPrice);

        DB::beginTransaction();
        try {
            $priceDiff = $newPrice - $oldPrice;

            if ($priceDiff != 0 && $order->status == Constants::CONFIRM_ORDER) {
                // If price increased, check and reduce balance
                if ($priceDiff > 0) {
                    $balanceCheck = (new VehicleAccountingService())->checkWalletBalanceFuel($order->vehicle_id, $priceDiff);
                    if (!($balanceCheck['success'] ?? false)) {
                        return ApiController::respondWithError('Insufficient balance for price increase', null, 422);
                    }

                    // Handle company/branch balance if needed
                    $companySettings = (new CompanyService())->getCompanySettings($order->company_id);
                    if ($companySettings['vehicles_can_use_fuel_balance'] ?? false) {
                        (new VehicleAccountingService())->addBalance($order->vehicle_id, $priceDiff);
                        
                        // Use Branch if enabled and exists - DEDUCT for price increase
                        if (($companySettings['can_branch_has_wallet'] ?? false) && $order->branch_id) {
                            (new BranchAccountingService())->reduceBalance($order->branch_id, $priceDiff, $order->id);
                        } else {
                            (new CompanyAccountingService())->reduceBalance($order->company_id, $priceDiff, $order->id);
                        }

                        // Update Service Provider Balance (Increase case)
                        (new ServiceProviderWalletService())->addBalance($order->service_provider_id, $priceDiff, $order->id);
                    }

                    (new VehicleAccountingService())->reduceBalance($order->vehicle_id, $priceDiff, $order->id);
                } 
                // If price decreased, refund balance
                else {
                    $refundAmount = abs($priceDiff);
                    (new VehicleAccountingService())->addBalance($order->vehicle_id, $refundAmount, $order->id);
                    
                    // Refund to company/branch if the original payment came from it - ADD for price decrease
                    $companySettings = (new CompanyService())->getCompanySettings($order->company_id);
                    if ($companySettings['vehicles_can_use_fuel_balance'] ?? false) {
                        (new VehicleAccountingService())->reduceBalance($order->vehicle_id, $refundAmount);
                        
                        // Use Branch if enabled and exists
                        if (($companySettings['can_branch_has_wallet'] ?? false) && $order->branch_id) {
                            (new BranchAccountingService())->addBalance($order->branch_id, $refundAmount, $order->id);
                        } else {
                            (new CompanyAccountingService())->addBalance($order->company_id, $refundAmount, $order->id);
                        }

                        // Update Service Provider Balance (Refund/Decrease case)
                        (new ServiceProviderWalletService())->reduceBalance($order->service_provider_id, $refundAmount, $order->id);
                    }
                }
            }

            $order->update($request->all());

            // If confirmed, update service provider wallet
            if ($order->status == Constants::CONFIRM_ORDER) {
                 (new ServiceProviderWalletService())->processOrder($order);
            }

            DB::commit();
            return ApiController::respondWithSuccess('Order updated successfully', new FuelOrderResource($order));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin Order Update Error: ' . $e->getMessage());
            return ApiController::respondWithError('Update failed', $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        $order = FuelOrder::findOrFail($id);
        $order->delete();
        return ApiController::respondWithSuccess('Order deleted successfully');
    }
}
