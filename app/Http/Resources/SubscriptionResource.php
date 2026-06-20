<?php

namespace App\Http\Resources;

use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canDelete = array_key_exists('has_collected_payment', $this->resource->getAttributes())
            ? ! (bool) $this->has_collected_payment
            : app(SubscriptionService::class)->canDeleteSubscription($this->resource);

        return [
            'id' => $this->id,
            'subscription_code' => $this->subscription_code,
            'student_id' => $this->student_id,
            'student_code' => $this->whenLoaded('student', fn () => $this->student?->student_code),
            'student_name' => $this->whenLoaded('student', fn () => $this->student?->name),
            'plan' => $this->plan_name,
            'plan_category' => $this->plan_category,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => $this->branch?->name)
                ?? $this->whenLoaded('student', fn () => $this->student?->branch?->name),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status,
            'amount' => $this->amount,
            'auto_renew' => $this->auto_renew,
            'can_delete' => $canDelete,
        ];
    }
}
