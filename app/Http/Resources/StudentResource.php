<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_code' => $this->student_code,
            'verify_token' => $this->verify_token,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'city' => $this->city,
            'blood_group' => $this->blood_group,
            'emergency_contact' => $this->emergency_contact,
            'photo_url' => $this->photo_path
                ? MediaUrl::absolute(Storage::disk('public')->url($this->photo_path))
                : null,
            'plan' => $this->plan_name,
            'plan_name' => $this->plan_name,
            'status' => $this->status,
            'admission_id' => $this->admission_id,
            'valid_from' => $this->valid_from?->toDateString(),
            'expiry' => $this->expiry?->toDateString(),
            'subscriptions' => SubscriptionResource::collection($this->whenLoaded('subscriptions')),
            'portal_ready' => (bool) $this->user_id,
            'payment_received' => $this->hasReceivedPayment(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
