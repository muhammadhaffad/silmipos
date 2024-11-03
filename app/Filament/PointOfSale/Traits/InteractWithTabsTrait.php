<?php

namespace App\Filament\PointOfSale\Traits;

use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

trait InteractWithTabsTrait
{
    use InteractsWithTable {makeTable as makeBaseTable; }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->query(fn (): Builder => $this->getTableQuery())
            ->modifyQueryUsing($this->modifyQueryWithActiveTab(...));
    }
}