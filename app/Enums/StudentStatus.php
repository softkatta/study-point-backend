<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Expired = 'expired';
    case Blacklisted = 'blacklisted';
}
