<?php
// app/Models/CosmeticScore.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CosmeticScore extends Model
{
    use HasFactory;

    protected $table = 'cosmetic_scores';

    protected $primaryKey = 'product_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'product_id',
        'overall_score',
        'irritant_score',
        'endocrine_score',
        'allergen_score',
        'environmental_score',
        'score_breakdown',
        'explanation_text',
        'warnings',
        'benefits',
        'skin_types_suitable',
        'calculated_at',
    ];

    protected $casts = [
        'score_breakdown' => 'array',
        'warnings' => 'array',
        'benefits' => 'array',
        'skin_types_suitable' => 'array',
        'calculated_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
//
//namespace App\Models;
//
//use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
//use Illuminate\Database\Eloquent\Relations\BelongsTo;
//
//class CosmeticScore extends Model
//{
//    use HasFactory;
//
//    protected $table = 'cosmetic_scores';
//
//    protected $primaryKey = 'product_id';
//    public $incrementing = false;
//    protected $keyType = 'string';
//
//    protected $fillable = [
//        'product_id',
//        'overall_score',
//        'irritant_score',
//        'endocrine_score',
//        'allergen_score',
//        'environmental_score',
//        'score_breakdown',
//        'explanation_text',
//        'warnings',
//        'benefits',
//        'skin_types_suitable',
//        'calculated_at',
//    ];
//
//    protected $casts = [
//        'score_breakdown' => 'array',
//        'warnings' => 'array',
//        'benefits' => 'array',
//        'skin_types_suitable' => 'array',
//        'overall_score' => 'decimal:2',
//        'calculated_at' => 'datetime',
//    ];
//
//    public function product(): BelongsTo
//    {
//        return $this->belongsTo(Product::class, 'product_id');
//    }
//
//    public function getSafetyLevelAttribute(): string
//    {
//        if ($this->overall_score >= 80) return 'Excellent';
//        if ($this->overall_score >= 60) return 'Good';
//        if ($this->overall_score >= 40) return 'Moderate';
//        if ($this->overall_score >= 20) return 'Poor';
//        return 'Avoid';
//    }
//
//    public function isSuitableFor(string $skinType): bool
//    {
//        return in_array($skinType, $this->skin_types_suitable ?? []);
//    }
//}
