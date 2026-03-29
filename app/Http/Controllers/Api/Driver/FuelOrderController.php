<?php

namespace App\Http\Controllers\Api\Driver;

use App\Helpers\Constants;
use App\Helpers\OCRHelper;
use App\Helpers\VehicleAccounting;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Controller;
use App\Http\Resources\FuelOrderResource;
use App\Interfaces\CRUDRepositoryInterface;
use App\Http\Requests\Api\Driver\CreateFuelOrderRequest;
use App\Models\Ordering\FuelOrder;
use App\Services\CompanyService;
use App\Services\VehicleService;
use App\Services\DriverService;
use App\Services\VehicleAccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FuelOrderController extends Controller
{
    protected $repository;

    public function __construct(CRUDRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        // $driver = auth()->user();
        $userId = $request->get('user_id');

        if (!$userId) {
            return ApiController::respondWithError(__('messages.unauthorized'), null, 403);
        }

        $filters = $request->all();
        $filters['user_id'] = $userId;
        
        $perPage = $request->get('per_page', 15);
        $orders = $this->repository->getAllItems(FuelOrder::class, $filters, $perPage);

        return FuelOrderResource::collection($orders)->response();
    }

    public function show($orderId, Request $request)
    {
        // $user = auth()->user();
        $userId = $request->get('user_id');

        if (!$userId) {
            return ApiController::respondWithError(__('messages.unauthorized'), null, 403);
        }

        $order = $this->repository->getItemById(FuelOrder::class, $orderId, [], [], ['user_id' => $userId]);

        if (!$order) {
            return ApiController::respondWithError(__('messages.order_not_found'), null, 404);
        }

        return ApiController::respondWithSuccess(__('messages.retrieved_successfully'), new FuelOrderResource($order));
    }

    public function createFuelOrder(CreateFuelOrderRequest $request)
    {
        // $user = auth()->user();
        $userId = $request->get('user_id');
        
        // Fetching driver details from Driver Microservice
        $driver = (new DriverService())->getDriver($userId);

        if (!$driver) {
            return ApiController::respondWithError(__('messages.unauthorized'), null, 403);
        }

        $driver = (object) $driver;

        // if ($user->active == 0 || $user->user_type_id !== 4) {
        if (isset($driver->active) && $driver->active == 0) {
            return ApiController::respondWithError(__('messages.account_blocked'), null, 403);
        }

        $validatedData = $request->validated();

        // Fetching vehicle details from External Microservice
        $vehicle = (new VehicleService())->getVehicle($validatedData['vehicle_id']);

        try {
            $this->checkAllowedFuelDays($driver);
        } catch (HttpException $e) {
            return ApiController::respondWithError($e->getMessage(), null, 421);
        }

        // Check wallet balance via External Accounting Service
        if ($vehicle) {
            (new VehicleAccountingService())->checkWalletBalanceFuel($validatedData['vehicle_id'], $validatedData['total_price']);
        }

        DB::beginTransaction();

        try {
            Log::info('Creating a new fuel order', ['user_id' => $userId]);

            $orderData = [
                'user_id'             => $userId,
                'vehicle_id'          => $validatedData['vehicle_id'],
                'fuel_id'             => $validatedData['fuel_id'],
                'company_id'          => $driver->company_id ?? null,
                'reference_number'    => $request->reference_number ?? rand(100000, 999999),
                'status'              => Constants::PENDING_ORDER,
                'total_price'         => $validatedData['total_price'],
                'service_provider_id' => $validatedData['service_provider_id'],
                'branch_id'           => $validatedData['branch_id'] ?? null,
                'quantity'            => $validatedData['quantity'],
                'odometer_number'     => $validatedData['odometer_number'] ?? null,
            ];

            $order = $this->repository->createItem(FuelOrder::class, $orderData);

            if ($request->hasFile('odometer_image')) {
                $odometer_number = OCRHelper::readOdometerHelper(
                    $request->file('odometer_image'),
                    $order->id,
                    1,
                    'odometer_image'
                );
                
                if ($odometer_number) {
                    $this->repository->updateItem(FuelOrder::class, $order->id, ['odometer_number' => $odometer_number]);
                }
            }

            DB::commit();
            $order->refresh();
            return ApiController::respondWithSuccess(__('messages.order_created'), new FuelOrderResource($order));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Fuel order creation failed', ['error' => $e->getMessage()]);
            return ApiController::respondWithError(__('messages.something_wrong'), null, 500);
        }
    }

    public function finishOrder(Request $request)
    {
        // $driver = auth()->user();
        $userId = $request->get('user_id');

        // Fetching driver for verification (since no auth)
        $driver = (new DriverService())->getDriver($userId);

        if (!$driver) {
            return ApiController::respondWithError(__('messages.unauthorized'), null, 403);
        }

        $driver = (object) $driver;

        $request->merge(['reference_number' => trim($request->reference_number)]);

        // Check company settings via External Company Service
        $company = (new CompanyService())->getCompany($driver->company_id);
        $settings = $company['settings'] ?? [];

        $rules = [
            'reference_number' => 'required|string',
            'odometer_number'  => ($settings['is_meter_image_required'] ?? false) ? 'required_without:odometer_image|string' : 'nullable|string',
            'odometer_image'   => ($settings['is_meter_number_required'] ?? false) ? 'required_without:odometer_number|image|mimes:jpeg,png,jpg,gif' : 'nullable|image|mimes:jpeg,png,jpg,gif',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return ApiController::respondWithError(__('messages.validation_errors'), $validator->errors(), 422);
        }

        $order = FuelOrder::where('reference_number', $request->reference_number)
            ->where('user_id', $userId)
            ->first();

        if (!$order) {
            return ApiController::respondWithError(__('messages.order_not_found'), null, 404);
        }

        $updateData = [];
        if ($request->filled('odometer_number')) {
            $updateData['odometer_number'] = $request->odometer_number;
        }

        $this->repository->updateItem(FuelOrder::class, $order->id, $updateData);

        if ($request->hasFile('odometer_image')) {
            $path = 'orders/odometers';
            $request->file('odometer_image')->store($path, 'public');
        }

        $order->refresh();
        return ApiController::respondWithSuccess(__('messages.order_finished'), new FuelOrderResource($order));
    }

    

    private function checkAllowedFuelDays($user)
    {
        $today = strtolower(now()->format('l'));
        $allowedDays = $user->fuel_pull_limit_days ?? [];

        if (is_string($allowedDays)) {
            $allowedDays = json_decode($allowedDays, true);
        }

        $allowedDays = $allowedDays ?? [];

        if (!empty($allowedDays) && !in_array($today, $allowedDays)) {
            throw new HttpException(421, __('messages.restricted_day'));
        }
    }
}
