<?php

namespace App\Models;

use App\Models\Ordering\FuelOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids; 
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FuelPump extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false; 
    protected $keyType = 'string'; 

    protected $fillable = [
        'fuel_order_id',
        'price',
        'quantity',
        'image',
    ];

    public function fuelOrder()
    {
        return $this->belongsTo(FuelOrder::class);
    }
}
