<?php
// app/Models/FoodNutrition.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodNutrition extends Model
{
    use HasFactory;

    protected $table = 'food_nutrition';

    protected $primaryKey = 'product_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'product_id',
        'calories',
        'fat',
        'saturated_fat',
        'trans_fat',
        'cholesterol',
        'sodium',
        'carbohydrates',
        'fiber',
        'sugars',
        'added_sugars',
        'protein',
        'vitamin_d',
        'calcium',
        'iron',
        'potassium',
        'vitamins',
        'minerals',
        'serving_size',
        'servings_per_container',
    ];

    protected $casts = [
        'vitamins' => 'array',
        'minerals' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function calculateHealthScore(): float
    {
        $score = 100;

        // Deduct for unhealthy components
        if ($this->sugars > 10) $score -= ($this->sugars - 10) * 2;
        if ($this->saturated_fat > 5) $score -= ($this->saturated_fat - 5) * 3;
        if ($this->sodium > 400) $score -= ($this->sodium - 400) / 100;
        if ($this->trans_fat > 0) $score -= $this->trans_fat * 10;

        // Add for healthy components
        if ($this->fiber > 3) $score += $this->fiber * 2;
        if ($this->protein > 10) $score += ($this->protein - 10) * 1.5;
        if ($this->vitamin_d > 2) $score += 5;
        if ($this->calcium > 200) $score += 5;

        return max(0, min(100, round($score)));
    }
}
//<?php
//
//namespace App\Models;
//
//use Illuminate\Database\Eloquent\Concerns\HasUuids;
//use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
//use Illuminate\Database\Eloquent\Relations\BelongsTo;
//
//class FoodNutrition extends Model
//{
//    use HasFactory;
//
//    protected $table = 'food_nutrition';
//
//    protected $primaryKey = 'product_id';
//    public $incrementing = false;
//    protected $keyType = 'string';
//
//    protected $fillable = [
//        'product_id',
//        'calories',
//        'fat',
//        'saturated_fat',
//        'trans_fat',
//        'cholesterol',
//        'sodium',
//        'carbohydrates',
//        'fiber',
//        'sugars',
//        'added_sugars',
//        'protein',
//        'vitamin_d',
//        'calcium',
//        'iron',
//        'potassium',
//        'vitamins',
//        'minerals',
//        'serving_size',
//        'servings_per_container',
//    ];
//
//    protected $casts = [
//        'vitamins' => 'array',
//        'minerals' => 'array',
//        'calories' => 'decimal:2',
//        'fat' => 'decimal:2',
//        'sugars' => 'decimal:2',
//    ];
//
//    public function product(): BelongsTo
//    {
//        return $this->belongsTo(Product::class, 'product_id');
//    }
//
//    /**
//     * Calculate health score based on nutritional content
//     */

//}
