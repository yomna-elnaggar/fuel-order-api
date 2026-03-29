<?php

namespace App\Models\Ordering;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OrderLog extends Model
{
    use HasFactory;

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'old_status',
 
        'status',
        'fuel_order_id',
        'employee_id',

    ];
}