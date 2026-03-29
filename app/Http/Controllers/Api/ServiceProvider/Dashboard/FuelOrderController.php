<?php

namespace App\Http\Controllers\Api\ServiceProvider\Dashboard;

use App\Http\Controllers\Api\ApiController;
use App\Models\Ordering\FuelOrder;
use App\Services\ServiceProviderService;
use App\Exports\FuelOrdersExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;

class FuelOrderController extends ApiController
{
    /**
     * Display a listing of confirmed orders for the logged-in Service Provider.
     */
    public function index(Request $request)
    {
        //$provider_id = auth()->user()->service_provider_id;
        $provider_id = $request->service_provider_id;

        if (!$provider_id) {
            return ApiController::respondWithError('Service Provider identification missing', null, 403);
        }

        // Use the model's 'confirmed' scope and 'filter' method as per legacy logic
        $query = FuelOrder::confirmed()->where('service_provider_id', $provider_id)->filter($request->all())->latest();

        // Handle Export
        if ($request->action == 'export') {
            $timestamp = now()->format('Y-m-d-His');
            return Excel::download(new FuelOrdersExport($query->get()), "provider_orders_{$timestamp}.xlsx");
        }

        // Summary Calculations (Dashboard Totals)
        $totalsQuery = clone $query;
        $total_price_all = $totalsQuery->sum('total_price');
        $counts = $totalsQuery->count();

        // Execute Pagination
        $items = $query->paginate($request->limit ?? 10);

        // Fetch Metadata (Matching Legacy Result)
        $employeesRaw = (new ServiceProviderService())->getEmployeesByProvider($provider_id);
        $employees = $employeesRaw['items'] ?? $employeesRaw ?? [];

        $providersRaw = (new ServiceProviderService())->getServiceProviders('all');
        $service_providers = $providersRaw['items'] ?? $providersRaw ?? [];

        return ApiController::respondWithSuccess('Provider dashboard data retrieved successfully', [
            'items' => $items->items(),
            'pagination' => ApiController::formatPagination($items),
            'metadata' => [
                'employees' => $employees,
                'service_providers' => $service_providers,
            ],
            'statistics' => [
                'total_price_current_page' => number_format($items->sum('total_price'), 2),
                'total_price_all_pages' => number_format($total_price_all, 2),
                'counts' => $counts,
            ]
        ]);
    }

    /**
     * Show single order details with ownership verification.
     */
    public function show(Request $request, $id)
    {
        //$provider_id = auth()->user()->service_provider_id;
        $provider_id = $request->service_provider_id;
        
        $order = FuelOrder::where('service_provider_id', $provider_id)->findOrFail($id);

        return ApiController::respondWithSuccess('Order details retrieved for provider dashboard', [
            'item' => $order,
            'relations' => [
                'vehicle' => $order->vehicle,
                'driver' => $order->user,
                'media' => $order->media
            ]
        ]);
    }
}
