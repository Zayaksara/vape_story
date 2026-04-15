<?php 

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case E_WALLET = 'e_wallet';
    case BANK_TRANSFER = 'bank_transfer';
    case QRIS = 'qris';
}