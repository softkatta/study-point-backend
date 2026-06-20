<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_code' => $this->payment_code,
            'student_id' => $this->student_id,
            'student_code' => $this->student?->student_code
                ?? ($this->relationLoaded('admission') ? $this->admission?->admission_code : null),
            'student_name' => $this->student?->name
                ?? ($this->relationLoaded('admission') ? $this->admission?->name : null),
            'plan' => $this->student?->plan_name
                ?? ($this->relationLoaded('admission') ? $this->admission?->plan_name : null),
            'admission_id' => $this->admission_id,
            'subscription_id' => $this->subscription_id,
            'subscription_action' => $this->subscription_action,
            'target_plan_id' => $this->target_plan_id,
            'amount' => $this->amount,
            'refund_amount' => $this->refund_amount,
            'refund_status' => $this->refund_status,
            'method' => $this->method,
            'status' => $this->status,
            'transaction_id' => $this->transaction_id,
            'paid_at' => $this->paid_at?->toDateString(),
            'refunded_at' => $this->refunded_at?->toDateString(),
            'date' => $this->paid_at?->toDateString() ?? $this->created_at?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
