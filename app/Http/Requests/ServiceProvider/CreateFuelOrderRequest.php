<?php

namespace App\Http\Requests\ServiceProvider;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

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
     */
    public function rules(): array
    {
        return [
            'driver_id'      => 'required|uuid',
            'fuel_id'        => 'required|numeric',
            'vehicle_id'     => 'required|uuid',
            'quantity'       => 'required|numeric|min:0.01',
            'total_price'    => 'required|numeric|min:0.01',
            'odometer_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
        ];
    }

    /**
     * Custom validation messages (ar & en)
     */
    public function messages(): array
    {
        return [
            'driver_id.required'      => __('messages.driver_id_required'),
            'driver_id.uuid'          => __('messages.driver_id_uuid'),
            'fuel_id.required'        => __('messages.fuel_id_required'),
            'vehicle_id.required'     => __('messages.vehicle_id_required'),
            'quantity.required'       => __('messages.quantity_required'),
            'quantity.numeric'        => __('messages.quantity_numeric'),
            'total_price.required'    => __('messages.total_price_required'),
            'odometer_image.image'    => __('messages.odometer_image_type'),
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success'     => false,
            'message'     => __('messages.validation_errors'),
            'errors'      => $validator->errors(),
            'status_Code' => 422
        ], 422));
    }
}
