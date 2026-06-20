<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'category' => $this->category,
            'duration_days' => $this->duration_days,
            'duration_months' => $this->duration_months,
            'price' => $this->price,
            'description' => $this->description,
            'badge' => $this->badge,
            'is_featured' => (bool) $this->is_featured,
            'highlights' => $this->highlights ?? [],
        ];
    }
}
