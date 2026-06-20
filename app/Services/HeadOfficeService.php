<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Setting;
use App\Support\CompanyDefaults;
use App\Support\GeneralDefaults;
use App\Support\GstDefaults;

class HeadOfficeService
{
    public function find(): ?Branch
    {
        return Branch::query()->where('is_head_office', true)->first();
    }

    public function getOrCreate(): Branch
    {
        $existing = $this->find();
        if ($existing) {
            $this->syncLegacySettingsIfEmpty($existing);

            return $existing->fresh();
        }

        $company = CompanyDefaults::merge(Setting::getSection('company'));
        $gst = GstDefaults::merge(Setting::getSection('gst'));
        $general = GeneralDefaults::merge(Setting::getSection('general'));

        return Branch::create([
            'code' => 'HO',
            'name' => $company['trade_name'] ?: 'StudyPoint Head Office',
            'legal_name' => $company['legal_name'],
            'registration_number' => $company['cin'] ?? '',
            'city' => $company['city'] ?? 'Mumbai',
            'state' => $company['state'] ?? 'Maharashtra',
            'pincode' => $company['pincode'] ?? '',
            'address' => $company['address'] ?? '',
            'manager_phone' => $company['phone'] ?? '+91 98765 43210',
            'email' => $company['email'] ?? 'hello@studypoint.in',
            'website' => $company['website'] ?? '',
            'timezone' => $general['timezone'] ?? 'Asia/Kolkata',
            'currency' => $general['currency'] ?? 'INR',
            'currency_symbol' => $general['currency_symbol'] ?? '₹',
            'opens_at' => '6:00 AM',
            'closes_at' => '11:00 PM',
            'operating_hours' => '6:00 AM – 11:00 PM',
            'capacity' => 120,
            'features' => ['AC Hall', 'WiFi', 'Individual Cabins', 'Library', 'Parking', 'CCTV'],
            'is_accepting_admissions' => true,
            'is_head_office' => true,
            'status' => 'active',
            'gstin' => $gst['gstin'],
            'pan' => $gst['pan'],
            'gst_rate' => $gst['gst_rate'],
            'cgst_rate' => $gst['cgst_rate'],
            'sgst_rate' => $gst['sgst_rate'],
            'igst_rate' => $gst['igst_rate'],
            'gst_registration_type' => $gst['registration_type'],
            'gst_filing_frequency' => $gst['filing_frequency'],
            'gst_reverse_charge' => (bool) $gst['reverse_charge'],
        ]);
    }

    /** @return array<string, mixed> */
    public function generalProfile(): array
    {
        $ho = $this->find() ?? $this->getOrCreate();
        $legacy = GeneralDefaults::merge(Setting::getSection('general'));

        return [
            'support_email' => $ho->email ?: ($legacy['support_email'] ?? ''),
            'support_phone' => $ho->manager_phone ?: ($legacy['support_phone'] ?? ''),
            'timezone' => $ho->timezone ?: ($legacy['timezone'] ?? GeneralDefaults::all()['timezone']),
            'currency' => $ho->currency ?: ($legacy['currency'] ?? GeneralDefaults::all()['currency']),
            'currency_symbol' => $ho->currency_symbol ?: ($legacy['currency_symbol'] ?? GeneralDefaults::all()['currency_symbol']),
        ];
    }

    /** @return array<string, mixed> */
    public function companyProfile(): array
    {
        $ho = $this->find() ?? $this->getOrCreate();

        return [
            'legal_name' => $ho->legal_name ?: CompanyDefaults::all()['legal_name'],
            'trade_name' => $ho->name,
            'email' => $ho->email ?? '',
            'phone' => $ho->manager_phone ?? '',
            'address' => $ho->address ?? '',
            'city' => $ho->city ?? '',
            'state' => $ho->state ?? '',
            'pincode' => $ho->pincode ?? '',
            'website' => $ho->website ?? '',
            'cin' => $ho->registration_number ?? '',
            'social_facebook' => $ho->social_facebook ?? '',
            'social_instagram' => $ho->social_instagram ?? '',
            'social_twitter' => $ho->social_twitter ?? '',
            'social_youtube' => $ho->social_youtube ?? '',
        ];
    }

    /** @return array<string, mixed> */
    public function gstProfile(): array
    {
        $ho = $this->find() ?? $this->getOrCreate();

        return [
            'gstin' => $ho->gstin ?? GstDefaults::all()['gstin'],
            'pan' => $ho->pan ?? GstDefaults::all()['pan'],
            'state_code' => GstDefaults::merge(Setting::getSection('gst'))['state_code'] ?? '27',
            'state_name' => $ho->state ?: GstDefaults::all()['state_name'],
            'gst_rate' => (float) ($ho->gst_rate ?? 18),
            'cgst_rate' => (float) ($ho->cgst_rate ?? 9),
            'sgst_rate' => (float) ($ho->sgst_rate ?? 9),
            'igst_rate' => (float) ($ho->igst_rate ?? 18),
            'registration_type' => $ho->gst_registration_type ?? 'regular',
            'filing_frequency' => $ho->gst_filing_frequency ?? 'monthly',
            'reverse_charge' => (bool) $ho->gst_reverse_charge,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public function update(array $data): Branch
    {
        $headOffice = $this->getOrCreate();

        if (! empty($data['opens_at']) && ! empty($data['closes_at'])) {
            $data['operating_hours'] = trim($data['opens_at']).' – '.trim($data['closes_at']);
        }

        if (array_key_exists('phone', $data)) {
            $data['manager_phone'] = $data['phone'];
            unset($data['phone']);
        }

        $headOffice->update($data);

        return $headOffice->fresh();
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'legal_name' => ['nullable', 'string', 'max:150'],
            'registration_number' => ['nullable', 'string', 'max:30'],
            'city' => ['required', 'string', 'max:50'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:12'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'manager_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'string', 'max:200'],
            'timezone' => ['nullable', 'string', 'max:60'],
            'currency' => ['nullable', 'string', 'max:10'],
            'currency_symbol' => ['nullable', 'string', 'max:5'],
            'opens_at' => ['nullable', 'string', 'max:30'],
            'closes_at' => ['nullable', 'string', 'max:30'],
            'social_facebook' => ['nullable', 'string', 'max:300'],
            'social_instagram' => ['nullable', 'string', 'max:300'],
            'social_twitter' => ['nullable', 'string', 'max:300'],
            'social_youtube' => ['nullable', 'string', 'max:300'],
            'gstin' => ['nullable', 'string', 'max:15'],
            'pan' => ['nullable', 'string', 'max:10'],
            'gst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cgst_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'sgst_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'igst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'gst_registration_type' => ['nullable', 'in:regular,composition,unregistered'],
            'gst_filing_frequency' => ['nullable', 'in:monthly,quarterly'],
            'gst_reverse_charge' => ['nullable', 'boolean'],
        ];
    }

    private function syncLegacySettingsIfEmpty(Branch $headOffice): void
    {
        $updates = [];
        $company = CompanyDefaults::merge(Setting::getSection('company'));
        $gst = GstDefaults::merge(Setting::getSection('gst'));
        $general = GeneralDefaults::merge(Setting::getSection('general'));

        if (! $headOffice->legal_name && ($company['legal_name'] ?? null)) {
            $updates['legal_name'] = $company['legal_name'];
        }
        if (! $headOffice->registration_number && ($company['cin'] ?? null)) {
            $updates['registration_number'] = $company['cin'];
        }
        if (! $headOffice->website && ($company['website'] ?? null)) {
            $updates['website'] = $company['website'];
        }
        if (! $headOffice->state && ($company['state'] ?? null)) {
            $updates['state'] = $company['state'];
        }
        if (! $headOffice->pincode && ($company['pincode'] ?? null)) {
            $updates['pincode'] = $company['pincode'];
        }
        if (! $headOffice->gstin && ($gst['gstin'] ?? null)) {
            $updates['gstin'] = $gst['gstin'];
        }
        if (! $headOffice->pan && ($gst['pan'] ?? null)) {
            $updates['pan'] = $gst['pan'];
        }
        if (! $headOffice->timezone && ($general['timezone'] ?? null)) {
            $updates['timezone'] = $general['timezone'];
        }
        if (! $headOffice->currency && ($general['currency'] ?? null)) {
            $updates['currency'] = $general['currency'];
        }
        if (! $headOffice->currency_symbol && ($general['currency_symbol'] ?? null)) {
            $updates['currency_symbol'] = $general['currency_symbol'];
        }
        if (! $headOffice->email && ($general['support_email'] ?? null)) {
            $updates['email'] = $general['support_email'];
        }
        if (! $headOffice->manager_phone && ($general['support_phone'] ?? null)) {
            $updates['manager_phone'] = $general['support_phone'];
        }

        if ($updates !== []) {
            $headOffice->update($updates);
        }
    }
}
