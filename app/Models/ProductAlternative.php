<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAlternative extends Model
{
    use HasFactory;

    protected $table = 'product_alternatives';

    public $incrementing = false;
    protected $primaryKey = ['product_id', 'alternative_product_id'];

    protected $fillable = [
        'product_id',
        'alternative_product_id',
        'reason',
        'score_improvement',
        'comparison_data',
        'rank',
    ];

    protected $casts = [
        'comparison_data' => 'array',
        'score_improvement' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function alternative(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'alternative_product_id');
    }
}
