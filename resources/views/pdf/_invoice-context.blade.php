@php
    $branch = $student?->branch;
    $branchAddressLine = collect([$branch?->address, $branch?->city])->filter()->implode(', ');
    $admission = $student?->admission;
    $studentAddress = collect([
        $admission?->address,
        $admission?->city ?? $student?->city,
        $admission?->state,
        $admission?->pincode,
    ])->filter()->implode(', ');
@endphp
