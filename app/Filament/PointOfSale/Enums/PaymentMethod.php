<?php
namespace App\Filament\PointOfSale\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod : string implements HasColor, HasIcon, HasLabel {
    case Tunai = 'tunai';
    // case Transfer = 'transfer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Tunai => 'Tunai',
            // self::Transfer => 'Bank Transfer'
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Tunai => 'info',
            // self::Transfer => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Tunai => 'heroicon-m-banknotes',
            // self::Transfer => 'heroicon-m-building-library'
        };
    }
}