<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Setting;
use App\Support\TopBarDefaults;

class PublicTopBarService
{
    public function __construct(private HeadOfficeService $headOffice) {}

    /** @return array<string, mixed> */
    public function build(): array
    {
        $config = TopBarDefaults::merge(Setting::getSection('public_top_bar'));
        $office = $this->headOffice->find();
        $activeBranches = Branch::query()->where('status', 'active')->count();

        $hours = $office?->displayOperatingHours() ?? '';

        return [
            ...$config,
            'phone' => $office?->manager_phone ?? '',
            'email' => $office?->email ?? '',
            'address' => $office?->address ?? '',
            'city' => $office?->city ?? '',
            'head_office_name' => $office?->name ?? '',
            'operating_hours' => $hours,
            'social_facebook' => $office?->social_facebook ?? '',
            'social_instagram' => $office?->social_instagram ?? '',
            'social_twitter' => $office?->social_twitter ?? '',
            'social_youtube' => $office?->social_youtube ?? '',
            'active_branches' => $activeBranches,
        ];
    }
}
