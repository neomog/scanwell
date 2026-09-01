<?php

// app/Models/FoodScore.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodScore extends Model
{
    use HasFactory;

    protected $table = 'food_scores';

    protected $primaryKey = 'product_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'product_id',
        'overall_score',
        'nutrition_score',
        'ingredient_score',
        'additive_score',
        'processing_score',
        'nova_group',
        'nutriscore_grade',
        'score_breakdown',
        'explanation_text',
        'warnings',
        'benefits',
        'calculated_at',
    ];

    protected $casts = [
        'score_breakdown' => 'array',
        'warnings' => 'array',
        'benefits' => 'array',
        'calculated_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function getGradeAttribute(): string
    {
        return match (true) {
            $this->overall_score >= 90 => 'A+',
            $this->overall_score >= 80 => 'A',
            $this->overall_score >= 70 => 'B+',
            $this->overall_score >= 60 => 'B',
            $this->overall_score >= 50 => 'C',
            $this->overall_score >= 40 => 'D',
            default => 'F',
        };
    }

    public function getScoreColorAttribute(): string
    {
        return match (true) {
            $this->overall_score >= 70 => 'green',
            $this->overall_score >= 50 => 'yellow',
            $this->overall_score >= 30 => 'orange',
            default => 'red',
        };
    }
}
//
//namespace App\Models;
//
//use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
//use Illuminate\Database\Eloquent\Relations\BelongsTo;
//
//class FoodScore extends Model
//{
//    use HasFactory;
//
//    protected $table = 'food_scores';
//
//    protected $primaryKey = 'product_id';
//    public $incrementing = false;
//    protected $keyType = 'string';
//
//    protected $fillable = [
//        'product_id',
//        'overall_score',
//        'nutrition_score',
//        'ingredient_score',
//        'additive_score',
//        'processing_score',
//        'nova_group',
//        'nutriscore_grade',
//        'score_breakdown',
//        'explanation_text',
//        'warnings',
//        'benefits',
//        'calculated_at',
//    ];
//
//    protected $casts = [
//        'score_breakdown' => 'array',
//        'warnings' => 'array',
//        'benefits' => 'array',
//        'overall_score' => 'decimal:2',
//        'nutrition_score' => 'decimal:2',
//        'calculated_at' => 'datetime',
//    ];
//
//    public function product(): BelongsTo
//    {
//        return $this->belongsTo(Product::class, 'product_id');
//    }
//
//    public function getGradeAttribute(): string
//    {
//        if ($this->overall_score >= 90) return 'A+';
//        if ($this->overall_score >= 80) return 'A';
//        if ($this->overall_score >= 70) return 'B+';
//        if ($this->overall_score >= 60) return 'B';
//        if ($this->overall_score >= 50) return 'C';
//        if ($this->overall_score >= 40) return 'D';
//        return 'F';
//    }
//
//    public function getScoreColorAttribute(): string
//    {
//        if ($this->overall_score >= 70) return 'green';
//        if ($this->overall_score >= 50) return 'yellow';
//        if ($this->overall_score >= 30) return 'orange';
//        return 'red';
//    }
//}
