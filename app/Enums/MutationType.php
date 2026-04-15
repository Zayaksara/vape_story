<?php

namespace App\Enums;

enum MutationType: string
{
    case RESTOCK = 'restock';
    case SALE    = 'sale';
    case RETURN  = 'return';     // kalau ada
    case ADJUST  = 'adjust';
}