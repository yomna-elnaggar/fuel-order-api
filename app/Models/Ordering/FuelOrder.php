<?php

namespace App\Models\Ordering;

use App\Helpers\Constants;
use App\Models\FuelPump;
use App\Models\Ordering\OrderMedia;
use App\Models\Ordering\OrderVerifyCode;
use App\Services\BasicService;
use App\Services\CompanyService;
use App\Services\ServiceProviderService;
use App\Services\VehicleService;
use App\Services\DriverService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class FuelOrder extends Model
{
    use HasFactory;

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'employee_id',
        'vehicle_id',
        'fuel_id',
        'branch_id',
        'service_provider_id',
        'company_id',
        'reference_number',
        'quantity',
        'total_price', 
        'status',
        'verified_at',
        'verified_by',
        'odometer_number',
        'pump_match_price',
    ];

    protected $casts = [
        'user_id' => 'string',
        'employee_id' => 'string',
        'vehicle_id' => 'string',
        'fuel_id' => 'string',
        'branch_id' => 'string',
        'service_provider_id' => 'string',
        'company_id' => 'string',
        'status' => 'integer',
        'pump_match_price' => 'boolean',
    ];

    protected function serializeDate(\DateTimeInterface $date)
    {
        return Carbon::instance($date)
            ->timezone('Asia/Riyadh')
            ->format('Y-m-d H:i:s');
    }


    /**
     * VIRTUAL RELATIONS (Lazy Loaded from Microservices)
     */

    public function getEmployeeAttribute()
    {
        return (new ServiceProviderService())->getEmployee($this->employee_id);
    }

    public function getVehicleAttribute()
    {
        return (new VehicleService())->getVehicle($this->vehicle_id);
    }

    public function getBranchAttribute()
    {
        return (new CompanyService())->getBranch($this->branch_id);
    }

    public function getFuelAttribute()
    {
        return (new BasicService())->getFuel($this->fuel_id);
    }

    public function getUserAttribute()
    {
        // Data from Driver API
        return (new DriverService())->getDriver($this->user_id);
    }

    public function getServiceProviderAttribute()
    {
        return (new ServiceProviderService())->getServiceProvider($this->service_provider_id);
    }

    public function getCompanyAttribute()
    {
        return (new CompanyService())->getCompany($this->company_id);
    }

    /**
     * LOCAL RELATIONS
     */

    public function media()
    {
        return $this->hasOne(OrderMedia::class, 'order_id');
    }

    public function fuelPump()
    {
        return $this->hasOne(FuelPump::class);
    }

    public function orderVerifyCode()
    {
        return $this->hasOne(OrderVerifyCode::class);
    }

     public function scopeCanVerify($query)
    {
        return $query->whereNull('verified_at');
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    
    public function scopeOfMonth($query, $year, $month, $column = 'created_at')
    {

        return $query->whereYear($column, $year)
            ->whereMonth($column, $month);
    }

    /**
     * ATTRIBUTES & SCOPES
     */

    public function getStatusSpanAttribute($value)
    {
        if ($this->status == Constants::PENDING_ORDER) {
            $value = "<span class='badge bg-warning text-dark'>" . __('titles.pending') . '</span>';
        } elseif ($this->status == Constants::CONFIRM_ORDER) {
            $value = "<span class='badge bg-success'>" . __('titles.confirmed') . '</span>';
        } elseif ($this->status == Constants::RECEIVED_ORDER) {
            $value = "<span class='badge bg-warning text-dark'>" . __('titles.received') . '</span>';
        } elseif ($this->status == Constants::CANCEL_ORDER) {
            $value = "<span class='badge bg-danger'>" . __('titles.cancelled') . '</span>';
        }

        return $value;
    }

    public function scopeReceived($query)
    {
        return $query->where('status', Constants::RECEIVED_ORDER);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', Constants::CONFIRM_ORDER);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', date('Y-m-d'));
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('id', 'desc');
    }

    public function scopeFilter($query, $filters)
    {
        return $query
            ->when(!empty($filters['user_id']), function ($q) use ($filters) {
                $q->where('user_id', $filters['user_id']);
            })
            ->when(!empty($filters['employee_id']), function ($q) use ($filters) {
                $q->where('employee_id', $filters['employee_id']);
            })
            ->when(isset($filters['pump_match_price']) && $filters['pump_match_price'] !== 'All Status', function ($q) use ($filters) {
                $q->where('pump_match_price', $filters['pump_match_price']);
            })
            ->when(!empty($filters['vehicle_id']), function ($q) use ($filters) {
                $q->where('vehicle_id', $filters['vehicle_id']);
            })
            ->when(!empty($filters['fuel_id']), function ($q) use ($filters) {
                $q->where('fuel_id', $filters['fuel_id']);
            })
            ->when(!empty($filters['branch_id']), function ($q) use ($filters) {
                $q->where('branch_id', $filters['branch_id']);
            })
            ->when(!empty($filters['service_provider_id']), function ($q) use ($filters) {
                $q->where('service_provider_id', $filters['service_provider_id']);
            })
            ->when(!empty($filters['company_id']), function ($q) use ($filters) {
                $q->where('company_id', $filters['company_id']);
            })
            ->when(!empty($filters['reference_number']), function ($q) use ($filters) {
                $q->where('reference_number', 'like', '%' . $filters['reference_number'] . '%');
            })
            ->when(!empty($filters['odometer_number']), function ($q) use ($filters) {
                $q->where('odometer_number', 'like', '%' . $filters['odometer_number'] . '%');
            })
            ->when(isset($filters['status']) && $filters['status'] !== 'all', function ($q) use ($filters) {
                $q->where('status', $filters['status']);
            })
            ->when(!empty($filters['date_from']), function ($q) use ($filters) {
                $q->whereDate('created_at', '>=', $filters['date_from']);
            })
            ->when(!empty($filters['date_to']), function ($q) use ($filters) {
                $q->whereDate('created_at', '<=', $filters['date_to']);
            });
    }
}
