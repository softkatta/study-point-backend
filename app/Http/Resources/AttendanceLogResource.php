<?php



namespace App\Http\Resources;



use Illuminate\Http\Request;

use Illuminate\Http\Resources\Json\JsonResource;



class AttendanceLogResource extends JsonResource

{

    public function toArray(Request $request): array

    {

        return [

            'id' => $this->id,

            'student_id' => $this->student_id,

            'student_code' => $this->whenLoaded('student', fn () => $this->student?->student_code),

            'student_name' => $this->whenLoaded('student', fn () => $this->student?->name),

            'student_photo' => $this->whenLoaded('student', fn () => $this->student?->photo_path),

            'course' => $this->whenLoaded('student', fn () => $this->student?->plan_name),

            'branch' => $this->whenLoaded('branch', fn () => $this->branch?->name),

            'attendance_date' => $this->attendance_date?->toDateString(),

            'check_in' => $this->check_in?->toIso8601String(),

            'check_out' => $this->check_out?->toIso8601String(),

            'hours' => $this->hours,

            'status' => $this->status,

            'source' => $this->source,

            'marked_by' => $this->whenLoaded('markedBy', fn () => $this->markedBy?->name),

            'marked_by_role' => $this->marked_by_role,

            'date' => $this->attendance_date?->toDateString() ?? $this->check_in?->toDateString(),

        ];

    }

}


