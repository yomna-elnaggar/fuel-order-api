<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Api\ApiController;
use App\Models\Ordering\FuelOrder;
use App\Services\BasicService;
use App\Services\CompanyService;
use App\Services\DriverService;
use App\Services\ServiceProviderService;
use App\Services\VehicleService;
use App\Helpers\Constants;
use Illuminate\Http\Request;
use App\Exports\FuelOrdersExport;
use Maatwebsite\Excel\Facades\Excel;

class FuelOrderController extends ApiController
{
    /**
     * Display a listing of fuel orders for the logged-in company.
     */
    public function index(Request $request)
    {
        //$company_id = auth()->user()->company_id;
        $company_id = $request->company_id;

        if (!$company_id) {
            return ApiController::respondWithError('Company identification mission', null, 403);
        }

        $query = FuelOrder::where('company_id', $company_id)->latest();

        // Apply Common Filters
        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
        if ($request->filled('vehicle_id')) $query->where('vehicle_id', $request->vehicle_id);
        if ($request->filled('branch_id')) $query->where('branch_id', $request->branch_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->whereDate('created_at', '<=', $request->date_to);

        // Handle Export Action
        if ($request->action == 'export') {
            $timestamp = now()->format('Y-m-d-His');
            $items = $query->get();
            return Excel::download(new FuelOrdersExport($items), "company_fuel_orders_{$timestamp}.xlsx");
        }

        // Summary Calculations (Dashboard Totals)
        $totalsQuery = clone $query;
        $total_liters = $totalsQuery->sum('quantity');
        $total_price_all = $totalsQuery->sum('total_price');
        $total_vehicles = $totalsQuery->distinct('vehicle_id')->count('vehicle_id');

        // Execute Pagination
        $items = $query->paginate($request->limit ?? 50);

        // Fetch Metadata for Filters (Matching Legacy Result)
        $fuelsRaw = (new BasicService())->getFuels();
        $fuels = is_array($fuelsRaw) ? ($fuelsRaw['items'] ?? $fuelsRaw) : [];

        $branchesRaw = (new CompanyService())->getCompanyBranches($company_id);
        $branches = is_array($branchesRaw) ? ($branchesRaw['items'] ?? $branchesRaw) : [];

        $vehiclesRaw = (new VehicleService())->getVehiclesByCompany($company_id);
        $vehicles = is_array($vehiclesRaw) ? ($vehiclesRaw['items'] ?? $vehiclesRaw) : [];

        $driversRaw = (new DriverService())->getDriversByCompany($company_id);
        $drivers = is_array($driversRaw) ? ($driversRaw['items'] ?? $driversRaw) : [];

        $providersRaw = (new ServiceProviderService())->getServiceProviders();
        $service_providers = is_array($providersRaw) ? ($providersRaw['items'] ?? $providersRaw) : [];

        $mappedItems = collect($items->items())->map(function ($item) use ($drivers, $vehicles, $branches, $service_providers) {
            $mapped = is_array($item) ? $item : $item->toArray();
            
            $user = collect($drivers)->firstWhere('id', $mapped['user_id']) ?? null;
            $vehicle = collect($vehicles)->firstWhere('id', $mapped['vehicle_id']) ?? null;
            $branch = collect($branches)->firstWhere('id', $mapped['branch_id']) ?? null;
            $sp = collect($service_providers)->firstWhere('id', $mapped['service_provider_id']) ?? null;
            
            $mapped['user'] = $user;
            $mapped['vehicle'] = $vehicle;
            $mapped['branch'] = $branch;
            $mapped['service_provider'] = $sp;
            
            // map flat properties for the view
            $mapped['name'] = $user['name'] ?? null;
            $mapped['service_provider_name'] = $sp['name'] ?? null;
            $mapped['plate_letters'] = $vehicle['plate_letters'] ?? null;
            $mapped['plate_numbers'] = $vehicle['plate_numbers'] ?? null;
            
            return $mapped;
        });

        return ApiController::respondWithSuccess('Company dashboard data retrieved', [
            'items' => $mappedItems,
            'pagination' => ApiController::formatPagination($items),
            'metadata' => [
                'drivers' => $drivers,
                'vehicles' => $vehicles,
                'branches' => $branches,
                'service_providers' => $service_providers,
                'fuels' => $fuels,
            ],
            'statistics' => [
                'total_price_current_page' => number_format($items->sum('total_price'), 2),
                'total_price_all_pages' => number_format($total_price_all, 2),
                'total_liters' => number_format($total_liters, 2),
                'total_vehicles' => $total_vehicles,
                'counts' => $items->total(),
            ]
        ]);
    }


    /**
     * Show details of a specific order belonging to the company.
     */
    public function show(Request $request, $id)
    {
        //$company_id = auth()->user()->company_id;
        $company_id = $request->company_id;
        $order = FuelOrder::where('company_id', $company_id)->findOrFail($id);

        return ApiController::respondWithSuccess('Order details retrieved', [
            'item' => $order,
            'relations' => [
                'vehicle' => $order->vehicle,
                'driver' => $order->user,
                'branch' => $order->branch,
                'service_provider' => $order->service_provider,
                'media' => $order->media
            ]
        ]);
    }

    /**
     * Get vehicle fuel orders statistics (daily, weekly, monthly) and history.
     */
    public function stats(Request $request)
    {
        $vehicle_id = $request->vehicle_id;
        if (!$vehicle_id) {
            return ApiController::respondWithError('Vehicle ID is required', null, 422);
        }

        $query = FuelOrder::where('vehicle_id', $vehicle_id)
            ->where('status', Constants::CONFIRM_ORDER);

        $daily = (clone $query)->whereDate('created_at', now()->toDateString())->sum('total_price');
        $weekly = (clone $query)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('total_price');
        $monthly = (clone $query)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('total_price');

        $orders = (clone $query)->latest()->limit(10)->get(['created_at', 'quantity', 'total_price']);

        return ApiController::respondWithSuccess('Vehicle statistics retrieved', [
            'daily' => (float)$daily,
            'weekly' => (float)$weekly,
            'monthly' => (float)$monthly,
            'orders' => $orders
        ]);
    }
}
