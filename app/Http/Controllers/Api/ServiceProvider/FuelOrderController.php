<?php

namespace App\Http\Controllers\Api\ServiceProvider;

use App\Helpers\Constants;
use App\Helpers\OCRHelper;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Controller;
use App\Http\Resources\FuelOrderResource;
use App\Http\Resources\VehicleDetailsResource;
use App\Models\Ordering\FuelOrder;
use App\Models\Ordering\OrderLog;
use App\Models\Ordering\OrderVerifyCode;
use App\Services\VehicleService;
use App\Services\DriverService;
use App\Services\ServiceProviderService;
use App\Services\VehicleAccountingService;
use App\Services\ServiceProviderWalletService;
use App\Services\CompanyAccountingService;
use App\Services\CompanyService;
use App\Http\Requests\ServiceProvider\CreateFuelOrderRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class FuelOrderController extends Controller
{
    /**
     * Helper to get authenticated service provider details from request.
     */
    public function scanQRForVehicles(Request $request)
    {
        Log::info('scanQRForVehicles called');

        $user = auth()->user();
        if (!$user) {
            return ApiController::respondWithError('Unauthorized', null, 401);
        }

        $request->validate(['qr_code' => 'required|string']);
        Log::info('QR code validated:', ['qr_code' => $request->qr_code]);

        Log::info('Checking if QR code matches a vehicle...');
        $vehicle = (new VehicleService())->getVehicle($request->qr_code);
        if ($vehicle) {
            Log::info('Vehicle found:', ['vehicle_id' => $vehicle['id'] ?? null]);
            return response()->json(['data' => new VehicleDetailsResource((object) $vehicle)]);
        }

        Log::warning('QR code did not match any vehicle.');
        return ApiController::respondWithError(__('QR code did not match any vehicle.'), null, 400);
    }

    public function scanQRData(Request $request)
    {
        Log::info('scan qr data');

        $user = auth()->user();
        if (!$user) {
            return ApiController::respondWithError('Unauthorized', null, 401);
        }

        Log::info('scanQRData called by user:', ['user_id' => $user->id]);

        $request->validate(['referance_number' => 'required|string']);
        Log::info('QR code validated:', ['referance_number' => $request->referance_number]);

        $vehicle = (new VehicleService())->getVehicle($request->referance_number);
        $order = FuelOrder::where('reference_number', $request->referance_number)->first();
        if ($vehicle) {
            Log::info('Vehicle found:', ['vehicle_id' => $vehicle['id'] ?? null]);
            return response()->json([
                'type' => 'vehicle',
                'id' => '1',
                'data' => [
                    'vehicle' => new VehicleDetailsResource((object) $vehicle),
                    'order' => $order ? new FuelOrderResource($order) : null
                ]
            ]);
        }

        if ($order) {
            Log::info('Fuel order found:', ['order_id' => $order->id]);
            if ($order->created_at->toDateString() !== Carbon::today()->toDateString()) {
                return ApiController::respondWithError(__('messages.The order is not valid'), null, 421);
            }
            return response()->json(['type' => 'fuel_order', 'id' => '0', 'data' => new FuelOrderResource($order)]);
        }

        Log::info('Checking if QR code matches a vehicle (fallback)...');
        $vehicle = (new VehicleService())->getVehicle($request->referance_number);
        if ($vehicle) {
            Log::info('Vehicle found:', ['vehicle_id' => $vehicle['id'] ?? null]);
            return response()->json(['type' => 'vehicle', 'id' => '1', 'data' => new VehicleDetailsResource((object) $vehicle)]);
        }

        Log::warning('QR code did not match any order or vehicle.');
        return ApiController::respondWithError(__('messages.cannot_accept_request'), null, 400);
    }

    public function createFuelOrder(CreateFuelOrderRequest $request)
    {
        Log::info('create fuel order');

        $user = auth()->user();
        if (!$user) {
            return ApiController::respondWithError('Unauthorized', null, 401);
        }

        // Extract IDs from the authenticated user
        $service_provider_id = $user->service_provider_id;
        $employee_id = $user->id;
        $branch_id = $user->service_branch_id;

        $validatedData = $request->validated();

        $vehicle = (new VehicleService())->getVehicle($request->vehicle_id);
        $driver = (new DriverService())->getDriver($request->driver_id);

        if (!$vehicle || !$driver) {
            Log::warning('Vehicle or Driver not found', ['vehicle_id' => $request->vehicle_id, 'driver_id' => $request->driver_id]);
            return ApiController::respondWithError('Vehicle or Driver not found', null, 404);
        }

        // Check if the user can place a fuel order today (Added as requested)
        $this->checkAllowedFuelDays($vehicle);

        $vehicle = (object) $vehicle;
        $driver = (object) $driver;

        // Check wallet balance via External Accounting Service
        $wallet = (new VehicleAccountingService())->checkWalletBalanceFuel($vehicle->id, $validatedData['total_price']);
        if (!$wallet || ($wallet['balance'] ?? 0) < $validatedData['total_price']) {
            return ApiController::respondWithError(__('messages.driver_WalletLimitation'), null, 421);
        }

        DB::beginTransaction();
        try {
            Log::info('Creating a new fuel order', ['vehicle_id' => $vehicle->id]);

            $orderData = [
                'user_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'fuel_id' => $validatedData['fuel_id'],
                'company_id' => $vehicle->company_id ?? null,
                'reference_number' => $request->reference_number ?? rand(100000, 999999),
                'status' => Constants::PENDING_ORDER,
                'total_price' => $validatedData['total_price'],
                'employee_id' => $employee_id,
                'service_provider_id' => $service_provider_id,
                'branch_id' => $branch_id,
                'quantity' => $validatedData['quantity'],
            ];

            $order = FuelOrder::create($orderData);

            if ($request->hasFile('odometer_image')) {
                $odometer_number = OCRHelper::readOdometerHelper(
                    $request->file('odometer_image'),
                    $order->id,
                    1,
                    'odometer_image'
                );
                Log::info('reading odometer', ['odometer_number' => $odometer_number]);
                $order->odometer_number = $odometer_number;
                $order->save();
            }

            Log::info('Fuel order created successfully', ['order_id' => $order->id]);
            DB::commit();

            $companySettings = (new CompanyService())->getCompanySettings($driver->company_id);
            $otp = $this->createOtp($order, $service_provider_id, (array) $driver, $companySettings);
            return ApiController::respondWithSuccess('Order created successfully', ['order' => new FuelOrderResource($order), 'otp' => $otp]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Fuel order creation failed', ['error' => $e->getMessage()]);
            return ApiController::respondWithError(__('messages.SomethingWrong'), null, 500);
        }
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return ApiController::respondWithError('Unauthorized', null, 401);
        }

        $perPage = $request->get('per_page', 15);
        $filters = $request->all();

        $filters['employee_id'] = $user->id;
        $filters['service_provider_id'] = $user->service_provider_id;

        $orders = FuelOrder::filter($filters)->latest()->paginate($perPage);

        return FuelOrderResource::collection($orders)->response();
    }

    public function show(Request $request, $orderId)
    {
        $user = auth()->user();
        if (!$user) {
            return ApiController::respondWithError('Unauthorized', null, 401);
        }

        $order = FuelOrder::where('service_provider_id', $user->service_provider_id)->find($orderId);

        if (!$order) {
            return ApiController::respondWithError('Order not found or unauthorized', null, 404);
        }

        return ApiController::respondWithSuccess('Order details retrieved successfully', new FuelOrderResource($order));
    }

    public function receiveOrder(Request $request)
    {
        Log::info('--- Start receiveOrder Process ---', ['request' => $request->all()]);

        $user = auth()->user();
        if (!$user) {
            return ApiController::respondWithError('Unauthorized', null, 401);
        }
        Log::info('Service Provider authenticated', ['id' => $user->id, 'user_type' => $user->userType->id ?? 'N/A']);

        $validator = Validator::make($request->all(), [
            'reference_number' => 'required|string|exists:fuel_orders,reference_number',
        ]);

        if ($validator->fails()) {
            Log::info('Validation failed', ['errors' => $validator->errors()]);
            return ApiController::respondWithError('Validation errors', $validator->errors(), 422);
        }

        $order = FuelOrder::where('reference_number', $request->reference_number)->first();
        if (!$order) {
            Log::warning('Order not found in DB', ['reference_number' => $request->reference_number]);
            return ApiController::respondWithError('order not found', null, 404);
        }
        Log::info('Order found', ['order_id' => $order->id, 'current_status' => $order->status]);

        // Fetch Driver and Vehicle details from external services
        $driver = (new DriverService())->getDriver($order->user_id);
        $vehicle = (new VehicleService())->getVehicle($order->vehicle_id);

        Log::info('External services: Identity Lookup', [
            'driver_found' => (bool) $driver,
            'vehicle_found' => (bool) $vehicle,
            'driver_company_id' => $driver['company_id'] ?? 'N/A',
            'vehicle_pull_limit' => $vehicle['fuel_pull_limit'] ?? 0
        ]);

        if (!$driver || !$vehicle) {
            return ApiController::respondWithError('Driver or Vehicle details not found', null, 404);
        }

        // Check wallet balance via External Accounting Service
        $wallet = (new VehicleAccountingService())->checkWalletBalanceFuel($order->vehicle_id, $order->total_price);

        // Temporary Debug Override: Bypass timeout for a specific test vehicle during local deadlock
        // if ($order->vehicle_id === '019d27b2-182b-7277-bfa4-c325b1f87dc4') {
        //     Log::info('DEBUG: Bypassing wallet microservice for test vehicle', ['vehicle_id' => $order->vehicle_id]);
        //     $wallet = ['balance' => 1000];
        // }

        Log::info('External services: Wallet Check', ['wallet_response' => $wallet]);

        if (!$wallet || ($wallet['balance'] ?? 0) <= 0) {
            Log::warning('Zero or negative wallet balance', ['vehicle_id' => $order->vehicle_id]);
            return ApiController::respondWithError(__('messages.driver_WalletLimitation'), null, 421);
        }

        // Fetch Company settings to get vehicle_limit_type
        $companySettings = (new CompanyService())->getCompanySettings($driver['company_id']);
        $vehicleLimitType = $companySettings['vehicle_limit_type'] ?? null;
        Log::info('External services: Company Settings', ['vehicle_limit_type' => $vehicleLimitType]);

        $currentOrdersTotal = 0;
        if ($vehicleLimitType) {
            $query = FuelOrder::where('user_id', $order->user_id)
                ->where('status', Constants::PENDING_ORDER);

            if ($vehicleLimitType === 'daily') {
                $query->whereDate('created_at', now());
            } elseif ($vehicleLimitType === 'monthly') {
                $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
            }
            $currentOrdersTotal = $query->sum('total_price');
            Log::info('Usage Limit Calculation', [
                'type' => $vehicleLimitType,
                'current_sum' => $currentOrdersTotal,
                'planned_order' => $order->total_price
            ]);
        }

        // Fuel Pull Limit Check from Vehicle Service
        $fuelPullLimit = $vehicle['fuel_pull_limit'] ?? 0;

        if ($fuelPullLimit > 0 && ($order->total_price + $currentOrdersTotal > $fuelPullLimit)) {
            Log::info('BLOCK: Vehicle fuel pull limit exceeded', [
                'vehicle_id' => $order->vehicle_id,
                'total_usage' => $order->total_price + $currentOrdersTotal,
                'limit' => $fuelPullLimit
            ]);
            return ApiController::respondWithError(__('messages.driver_fuel_pull_limit_message'), null, 421);
        }

        if ($order->total_price > ($wallet['balance'] ?? 0)) {
            Log::info('BLOCK: Insufficient balance for order', ['order' => $order->total_price, 'balance' => $wallet['balance'] ?? 0]);
            return ApiController::respondWithError(__('messages.driver_WalletLimitation'), null, 421);
        }

        if ((int) $order->status === 2) {
            Log::info('Order already received, skipping status update');
            return ApiController::respondWithSuccess('Order already received', new FuelOrderResource($order));
        }

        $order->status = 2;
        if ($user->userType->id == 2) {
            $order->branch_id = $user->service_branch_id;
            $order->service_provider_id = $user->service_provider_id;
            $order->employee_id = $user->id;
        }

        $order->save();
        Log::info('--- receiveOrder Completed Successfully ---', ['order_id' => $order->id]);

        $otp = $this->createOtp($order, $user->service_provider_id, $driver, $companySettings);

        return ApiController::respondWithSuccess('Order received successfully', [
            'order' => new FuelOrderResource($order),
            'otp' => $otp
        ]);
    }

    public function completeOrder(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return ApiController::respondWithError('Unauthorized', null, 401);
        }

        $validator = Validator::make($request->all(), [
            'otp' => 'required|numeric',
            'reference_number' => 'required|string|exists:fuel_orders,reference_number',
            'vehicle_image' => 'nullable|image|mimes:jpeg,png,jpg,gif',
        ]);

        if ($validator->fails()) {
            return ApiController::respondWithError('Validation errors', $validator->errors(), 422);
        }

        $order = FuelOrder::where('reference_number', $request->reference_number)->first();
        if (!$order) {
            return ApiController::respondWithError('Order not found', null, 404);
        }

        if ($order->created_at->toDateString() !== now()->toDateString()) {
            return ApiController::respondWithError(__('messages.The order is not valid'), null, 421);
        }

        // Fetch driver and company setting
        $driver = (new DriverService())->getDriver($order->user_id);
        Log::info('Driver API Response Data: ', (array) $driver);

        $companySettings = (new CompanyService())->getCompanySettings($driver['company_id'] ?? null);
        $isCodeChangeable = $companySettings['is_code_changeable'] ?? true;

        if ($isCodeChangeable == 0 && !empty($driver['otp'])) {
            Log::info('Driver OTP: ' . $driver['otp']);
            Log::info('Request OTP: ' . $request->otp);
            if ($driver['otp'] != $request->otp) {
                return ApiController::respondWithError('Invalid or expired OTP', null, 403);
            }

            $codeVerify = OrderVerifyCode::where('code', $request->otp)
                ->where('fuel_order_id', $order->id)
                ->whereNull('status')
                ->where('user_id', $order->user_id)
                ->first();

            if (!$codeVerify) {
                OrderVerifyCode::where('fuel_order_id', $order->id)->update([
                    'status' => 1,
                ]);

                $codeVerify = OrderVerifyCode::create([
                    'code' => $request->otp,
                    'expired_at' => now()->addMinutes(10),
                    'fuel_order_id' => $order->id,
                    'service_provider_id' => $user->service_provider_id,
                    'user_id' => $order->user_id,
                ]);
            }
        } else {

            $codeVerify = OrderVerifyCode::where('code', $request->otp)
                ->where('fuel_order_id', $order->id)
                ->whereNull('status')
                ->where('user_id', $order->user_id)
                ->first();

            if (!$codeVerify) {
                // FALLBACK: Try Static OTP from Driver
                if ($request->otp != ($driver['otp'] ?? null)) {
                    return ApiController::respondWithError('Invalid or expired OTP', null, 403);
                }
            }
        }

        DB::transaction(function () use ($order, $user, $request, $companySettings) {
            $oldStatus = $order->status;

            // Update order details and status
            $updateData = [
                'verified_at' => now(),
                'verified_by' => $order->user_id,
                'status' => Constants::CONFIRM_ORDER,
            ];

            // Assign branch and provider info if it's a service provider employee (type 2)
            if ($user->userType->id == 2) {
                $updateData['branch_id'] = $user->service_branch_id;
                $updateData['service_provider_id'] = $user->service_provider_id;
                $updateData['employee_id'] = $user->id;
            }

            // Handle odometer/vehicle image if uploaded at this stage
            if ($request->hasFile('vehicle_image')) {
                $path = 'orders/vehicles';
                $updateData['vehicle_image'] = $request->file('vehicle_image')->store($path, 'public');
            }

            $order->update($updateData);

            OrderLog::create([
                'fuel_order_id' => $order->id,
                'user_id' => $order->user_id,
                'employee_id' => $user->id,
                'status' => Constants::CONFIRM_ORDER,
                'old_status' => $oldStatus,
            ]);

            // 1. Check company settings for fuel balance usage (from already fetched settings)
            $canUseCompanyBalance = $companySettings['vehicles_can_use_fuel_balance'] ?? false;

            if ($canUseCompanyBalance && !empty($order->company_id)) {
                // First: add to vehicle wallet (from company)
                (new VehicleAccountingService())->addBalance($order->vehicle_id, $order->total_price, $order->id);
                // Second: reduce from company wallet
                (new CompanyAccountingService())->reduceBalance($order->company_id, $order->total_price);
            }

            // 2. Final Reduction from vehicle (The actual payment)
            (new VehicleAccountingService())->reduceBalance($order->vehicle_id, $order->total_price, $order->id);


            // // Mark the OTP as used
            // OrderVerifyCode::where('id', $request->otp_id ?? null) // Or through the record we found
            //     ->orWhere('code', $request->otp)
            //     ->where('fuel_order_id', $order->id)
            //     ->update(['status' => 1]);

            // Service Provider Accounting Transaction (External API)
            (new ServiceProviderWalletService())->processOrder($order);
        });

        return ApiController::respondWithSuccess(
            __('messages.OrderConfirmed'),
            new FuelOrderResource($order->fresh())
        );
    }

    public function cancelOrder(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return ApiController::respondWithError('Unauthorized', null, 401);
        }

        $validator = Validator::make($request->all(), [
            'reference_number' => 'required',
        ]);

        if ($validator->fails()) {
            return ApiController::respondWithError('Validation errors', $validator->errors(), 422);
        }

        $order = FuelOrder::where('reference_number', $request->reference_number)
            ->where(function ($q) use ($user) {
                $q->where('service_provider_id', $user->service_provider_id)
                    ->orWhere('employee_id', $user->id);
            })
            ->first();

        if (!$order) {
            return ApiController::respondWithError('Order not found or unauthorized', null, 404);
        }

        $order->status = 3; // Cancelled
        $order->save();

        return ApiController::respondWithSuccess('Order canceled successfully', new FuelOrderResource($order));
    }

    public function sendOtpToConfirmOrder(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return ApiController::respondWithError('Unauthorized', null, 401);
        }

        $validator = Validator::make($request->all(), [
            'reference_number' => 'required',
        ]);

        if ($validator->fails()) {
            return ApiController::respondWithError('Validation errors', $validator->errors(), 422);
        }

        $order = FuelOrder::where('reference_number', $request->reference_number)->first();
        if (!$order) {
            return ApiController::respondWithError('Order not found or unauthorized', null, 404);
        }

        // Fetch driver and company settings for OTP policy lookup
        $driver = (new DriverService())->getDriver($order->user_id);
        if (!$driver || empty($driver['mobile'])) {
            return ApiController::respondWithError('Driver or driver mobile number not found', null, 404);
        }

        $companySettings = (new CompanyService())->getCompanySettings($driver['company_id']);

        $otp = $this->createOtp($order, $user->service_provider_id, (array) $driver, $companySettings);

        return ApiController::respondWithSuccess('OTP generated successfully', ['otp' => $otp]);
    }

    private function createOtp($order, $service_provider_id, $driver = null, $companySettings = null)
    {
        $otpCode = mt_rand(1000, 9999);

        // Logic from settings: if OTP is not changeable, use driver's preset OTP
        if ($companySettings && isset($companySettings['is_code_changeable']) && $companySettings['is_code_changeable'] == 0) {
            if ($driver && !empty($driver['otp'])) {
                $otpCode = $driver['otp'];
                Log::info('Using Driver preset OTP from settings', ['otp' => $otpCode]);
            }
        }

        OrderVerifyCode::where('fuel_order_id', $order->id)->update(['status' => 1]);
        OrderVerifyCode::create([
            'code' => $otpCode,
            'expired_at' => now()->addMinutes(10),
            'fuel_order_id' => $order->id,
            'service_provider_id' => $service_provider_id,
            'user_id' => $order->user_id,
        ]);

        return $otpCode;
    }



    /**
     * Check if the vehicle is allowed to fuel on the current day
     */
    private function checkAllowedFuelDays($vehicle)
    {
        $today = strtolower(now()->format('l'));

        // If it's already an array/object, use it. Otherwise decode it.
        $allowedDays = $vehicle->fuel_pull_limit_days ?? [];

        if (!is_array($allowedDays)) {
            $allowedDays = json_decode(json_encode($allowedDays), true) ?? [];
        }

        if (!empty($allowedDays) && !in_array($today, $allowedDays)) {
            Log::info("Fuel order attempt on a restricted day", [
                'day' => $today,
                'vehicle_id' => $vehicle->id ?? 'unknown'
            ]);

            abort(421, __('Fuel order attempt on a restricted day'));
        }
    }
}
