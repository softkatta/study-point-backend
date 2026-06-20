<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $enrollmentOpen = $this->enrollmentOpen();
        $managerName = $this->assignedManagerName();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'city' => $this->city,
            'manager_name' => $managerName,
            'manager' => $managerName,
            'manager_phone' => $this->manager_phone,
            'phone' => $this->manager_phone,
            'address' => $this->address,
            'operating_hours' => $this->operating_hours,
            'hours' => $this->operating_hours,
            'features' => $this->features ?? [],
            'is_accepting_admissions' => (bool) $this->is_accepting_admissions,
            'capacity' => (int) $this->capacity,
            'status' => $this->status,
            'revenue' => $this->revenue,
            'students' => $this->whenCounted('students', $this->students_count),
            'student_count' => $this->whenCounted('students', $this->students_count),
            'enrollment_open' => $enrollmentOpen,
            'enrollmentOpen' => $enrollmentOpen,
            'enrollment_status' => $this->enrollmentDisplayStatus(),
            'display_status' => $this->enrollmentDisplayStatus(),
            'is_head_office' => (bool) $this->is_head_office,
        ];
    }
}
