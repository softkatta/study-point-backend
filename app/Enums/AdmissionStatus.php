<?php

namespace App\Enums;

enum AdmissionStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Active = 'active';
    case Rejected = 'rejected';
}
