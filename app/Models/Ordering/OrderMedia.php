<?php

namespace App\Models\Ordering;

//use App\Models\Type;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OrderMedia extends Model
{
    use HasFactory;

    public $incrementing = true;
    protected $keyType = 'int';

    protected $table = 'order_media';  

    protected $fillable = [
        'order_id',  
        'type_id',
        'vehicle_image_before',
        'vehicle_plate',
        'odometer_image',
        'vehicle_image_after',
    ];

    // Relationships
 

    // public function type()
    // {
    //     return $this->belongsTo(Type::class);
    // }
}