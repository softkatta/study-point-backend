<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'admission_code' => $this->admission_code,
            'source' => $this->source,
            'status' => $this->status,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'address' => $this->address,
            'city' => $this->city,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'plan_id' => $this->plan_id,
            'plan' => $this->plan_name ?? $this->whenLoaded('plan', fn () => $this->plan?->name),
            'start_date' => $this->start_date?->toDateString(),
            'duration_months' => $this->duration_months,
            'amount' => $this->amount,
            'payment_mode' => $this->payment_mode,
            'payment_status' => $this->payment_status,
            'notify_email' => $this->notify_email,
            'notify_whatsapp' => $this->notify_whatsapp,
            'documents_uploaded' => $this->documents_uploaded,
            'student_id' => $this->student_id,
            'student_code' => $this->whenLoaded('student', fn () => $this->student?->student_code),
            'subscription_id' => $this->subscription_id,
            'subscription_code' => $this->whenLoaded('subscription', fn () => $this->subscription?->subscription_code),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'follow_up_date' => $this->follow_up_date?->toDateString(),
            'follow_up_note' => $this->follow_up_note,
            'created_at' => $this->created_at?->toIso8601String(),
            'payment' => $this->whenLoaded('payments', function () {
                $payment = $this->payments->first();

                return $payment ? new PaymentResource($payment) : null;
            }),
            'documents' => AdmissionDocumentResource::collection($this->whenLoaded('documents')),
        ];
    }
}
