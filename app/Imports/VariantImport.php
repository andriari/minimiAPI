<?php

namespace App\Imports;

use App\ProductVariant;
use Maatwebsite\Excel\Concerns\FromCollection;

class VariantImport implements FromCollection
{
    public function collection()
    {
        return ProductVariant::all();
    }
}