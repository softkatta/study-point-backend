<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $enrollmentOpen = $this->enrollmentOpen();
        $phone = $this->manager_phone;
        $managerName = $this->assignedManagerName();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'city' => $this->city,
            'address' => $this->address,
            'phone' => $phone,
            'hours' => $this->operating_hours,
            'operating_hours' => $this->operating_hours,
            'capacity' => (int) $this->capacity,
            'enrollment_open' => $enrollmentOpen,
            'enrollmentOpen' => $enrollmentOpen,
            'manager' => $managerName,
            'manager_name' => $managerName,
            'features' => $this->features ?? [],
            'status' => $this->enrollmentDisplayStatus(),
            'display_status' => $this->enrollmentDisplayStatus(),
            'is_head_office' => (bool) $this->is_head_office,
        ];
    }
}
