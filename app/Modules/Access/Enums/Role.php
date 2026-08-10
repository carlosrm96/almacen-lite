<?php

namespace App\Modules\Access\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Vendedor = 'vendedor';
}
