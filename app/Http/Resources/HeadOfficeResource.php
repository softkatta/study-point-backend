<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Public head office profile — org identity, contact, platform defaults, GST (for invoices/display). */
class HeadOfficeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'registration_number' => $this->registration_number,
            'city' => $this->city,
            'state' => $this->state,
            'pincode' => $this->pincode,
            'address' => $this->address,
            'phone' => $this->manager_phone,
            'email' => $this->email,
            'website' => $this->website,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'currency_symbol' => $this->currency_symbol,
            'opens_at' => $this->opens_at,
            'closes_at' => $this->closes_at,
            'operating_hours' => $this->displayOperatingHours(),
            'social_facebook' => $this->social_facebook,
            'social_instagram' => $this->social_instagram,
            'social_twitter' => $this->social_twitter,
            'social_youtube' => $this->social_youtube,
            'gstin' => $this->gstin,
            'pan' => $this->pan,
            'gst_rate' => (float) ($this->gst_rate ?? 0),
            'cgst_rate' => (float) ($this->cgst_rate ?? 0),
            'sgst_rate' => (float) ($this->sgst_rate ?? 0),
            'igst_rate' => (float) ($this->igst_rate ?? 0),
            'gst_registration_type' => $this->gst_registration_type,
            'gst_filing_frequency' => $this->gst_filing_frequency,
            'gst_reverse_charge' => (bool) $this->gst_reverse_charge,
        ];
    }
}
