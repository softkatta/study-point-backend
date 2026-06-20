<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'short_description' => $this->short_description,
            'shortDescription' => $this->short_description,
            'description' => $this->description,
            'bullet_points' => $this->bullet_points ?? [],
            'bulletPoints' => $this->bullet_points ?? [],
            'icon' => $this->icon,
            'sort_order' => $this->sort_order,
            'sortOrder' => $this->sort_order,
            'show_in_nav' => (bool) $this->show_in_nav,
            'showInNav' => (bool) $this->show_in_nav,
            'show_on_home' => (bool) $this->show_on_home,
            'showOnHome' => (bool) $this->show_on_home,
            'show_on_page' => (bool) $this->show_on_page,
            'showOnPage' => (bool) $this->show_on_page,
            'status' => $this->status,
        ];
    }
}
