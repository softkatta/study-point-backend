<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'emergency_name' => ['nullable', 'string', 'max:100'],
            'emergency_phone' => ['nullable', 'string', 'max:20'],
            'emergency_email' => ['nullable', 'email', 'max:255'],
            'emergency_relation' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'notify_parent_email' => ['nullable', 'boolean'],
            'notify_parent_whatsapp' => ['nullable', 'boolean'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'plan_name' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_mode' => ['nullable', 'string', 'max:50'],
            'payment_status' => ['nullable', 'in:pending,paid'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'payment_date' => ['nullable', 'date'],
            'documents_uploaded' => ['nullable', 'boolean'],
            'notify_email' => ['nullable', 'boolean'],
            'notify_whatsapp' => ['nullable', 'boolean'],
            'source' => ['nullable', 'in:online,admin,branch'],
        ];
    }
}
