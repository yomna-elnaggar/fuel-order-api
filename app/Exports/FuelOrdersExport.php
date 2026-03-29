<?php

namespace App\Exports;

use App\Models\Ordering\FuelOrder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FuelOrdersExport implements FromCollection, WithHeadings, WithMapping
{
    protected $collection;

    public function __construct($collection)
    {
        $this->collection = $collection;
    }

    public function collection()
    {
        return $this->collection;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Reference Number',
            'Driver ID',
            'Vehicle ID',
            'Fuel ID',
            'Quantity',
            'Total Price',
            'Status',
            'Date',
        ];
    }

    public function map($order): array
    {
        return [
            $order->id,
            $order->reference_number,
            $order->user_id,
            $order->vehicle_id,
            $order->fuel_id,
            $order->quantity,
            $order->total_price,
            $order->status,
            $order->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
