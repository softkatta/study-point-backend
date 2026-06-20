<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_code' => $this->invoice_code,
            'student_id' => $this->student_id,
            'student_code' => $this->whenLoaded('student', fn () => $this->student?->student_code),
            'student_name' => $this->whenLoaded('student', fn () => $this->student?->name),
            'student_email' => $this->whenLoaded('student', fn () => $this->student?->email),
            'student_phone' => $this->whenLoaded('student', fn () => $this->student?->phone),
            'student_address' => $this->whenLoaded('student', fn () => $this->formatStudentAddress($this->student)),
            'student_branch' => $this->whenLoaded('student', fn () => $this->student?->branch?->name),
            'branch_name' => $this->whenLoaded('student', fn () => $this->student?->branch?->name),
            'branch_address' => $this->whenLoaded('student', fn () => $this->student?->branch?->address),
            'branch_city' => $this->whenLoaded('student', fn () => $this->student?->branch?->city),
            'plan_name' => $this->whenLoaded('student', fn () => $this->student?->plan_name),
            'payment_id' => $this->payment_id,
            'document_type' => $this->document_type ?? 'payment',
            'document_type' => $this->document_type ?? 'payment',
            'amount' => $this->amount,
            'gst_amount' => $this->gst_amount,
            'gst' => $this->gst_amount,
            'total' => $this->total,
            'status' => $this->status,
            'type' => 'GST',
            'issued_at' => $this->issued_at?->toDateString(),
            'date' => $this->issued_at?->toDateString() ?? $this->created_at?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function formatStudentAddress(?\App\Models\Student $student): ?string
    {
        if (! $student) {
            return null;
        }

        $admission = $student->relationLoaded('admission') ? $student->admission : null;

        $parts = array_filter([
            $admission?->address,
            $admission?->city ?? $student->city,
            $admission?->state,
            $admission?->pincode,
        ], fn ($value) => filled($value));

        return $parts !== [] ? implode(', ', $parts) : null;
    }
}
