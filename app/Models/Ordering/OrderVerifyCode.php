<?php

namespace App\Models\Ordering;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OrderVerifyCode extends Model
{
    use HasFactory;

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'code',
        'status',
        'expired_at',
        'fuel_order_id',
        'service_provider_id',
    ];

    public function fuelOrder()
    {
        return $this->belongsTo(\App\Models\Ordering\FuelOrder::class, 'fuel_order_id');
    }
}