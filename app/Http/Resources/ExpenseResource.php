<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'amount' => $this->amount,
            'category' => $this->category,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'status' => $this->status,
            'bill_path' => $this->bill_path,
            'has_bill' => (bool) $this->bill_path,
            'expense_date' => $this->expense_date?->toDateString(),
            'date' => $this->expense_date?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
