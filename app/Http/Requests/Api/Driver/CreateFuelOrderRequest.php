<?php

namespace App\Http\Requests\Api\Driver;

use Illuminate\Foundation\Http\FormRequest;

class CreateFuelOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|string',
            'service_provider_id' => 'required|string',
            'branch_id' => 'nullable|string',
            'fuel_id' => 'required|string',
            'vehicle_id' => 'required|string',
            'quantity' => 'required|numeric|min:0.01',
            'total_price' => 'required|numeric|min:0.01',
            'odometer_number' => 'nullable|string',
            'odometer_image' => 'nullable|image|mimes:jpeg,png,jpg,gif',
        ];
    }

 
}
