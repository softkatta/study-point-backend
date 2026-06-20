<?php

namespace App\Enums;

enum AdmissionSource: string
{
    case Online = 'online';
    case Admin = 'admin';
    case Branch = 'branch';
}
