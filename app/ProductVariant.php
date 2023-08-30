<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'minimi_product_variant';

    protected $primaryKey = 'variant_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'product_id', 'variant_name', 'variant_sku', 'stock_count', 'stock_weight', 'stock_price', 'stock_price_gb', 'stock_agent_price', 'stock_restriction_count', 'status', 'publish', 'created_at', 'updated_at'
    ];
}