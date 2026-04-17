<?php
// app/Models/Product.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'barcode',
        'name',
        'brand',
        'category_id',
        'image_url',
        'source',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'category_id' => 'integer',
    ];

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'product_ingredients')
            ->withPivot('percentage', 'is_additive', 'origin')
            ->withTimestamps();
    }

    public function nutrition(): HasOne
    {
        return $this->hasOne(FoodNutrition::class, 'product_id');
    }

    public function foodScore(): HasOne
    {
        return $this->hasOne(FoodScore::class, 'product_id');
    }

    public function cosmeticScore(): HasOne
    {
        return $this->hasOne(CosmeticScore::class, 'product_id');
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    public function alternatives(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_alternatives',
            'product_id',
            'alternative_product_id'
        )->withPivot('reason', 'score_improvement', 'comparison_data', 'rank')
            ->withTimestamps();
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(ProductContribution::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_favorites')
            ->withTimestamps();
    }

    public function isFood(): bool
    {
        return $this->product_family === 'food';
    }

    public function isCosmetic(): bool
    {
        return $this->product_family === 'cosmetic';
    }

    public function getProductFamilyAttribute(): string
    {
        $storedFamily = data_get($this->raw_data, '_scanwell.product_family');

        if (is_string($storedFamily) && $storedFamily !== '') {
            return $storedFamily;
        }

        return match (true) {
            $this->category_id >= 100 && $this->category_id < 200 => 'cosmetic',
            $this->category_id >= 300 && $this->category_id < 400 => 'pet_food',
            $this->category_id >= 400 && $this->category_id < 500 => 'household',
            $this->category_id >= 900 => 'general',
            default => 'food',
        };
    }

    public function getScoreAttribute(): ?float
    {
        if ($this->isFood() && $this->foodScore) {
            return $this->foodScore->overall_score;
        }

        if ($this->isCosmetic() && $this->cosmeticScore) {
            return $this->cosmeticScore->overall_score;
        }

        return null;
    }

    public function getScoreGradeAttribute(): string
    {
        $score = $this->score;

        if (!$score) return 'N/A';

        if ($score >= 90) return 'A+';
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B+';
        if ($score >= 60) return 'B';
        if ($score >= 50) return 'C';
        if ($score >= 40) return 'D';
        return 'F';
    }
}
//<?php
//
//namespace App\Models;
//
//use Illuminate\Database\Eloquent\Concerns\HasUuids;
//use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
//use Illuminate\Database\Eloquent\SoftDeletes;
//use Illuminate\Database\Eloquent\Relations\HasMany;
//use Illuminate\Database\Eloquent\Relations\BelongsToMany;
//use Illuminate\Database\Eloquent\Relations\HasOne;
//
//class Product
//{
//    use HasFactory, HasUuids, SoftDeletes;
//
//    protected $keyType = 'string';
//    public $incrementing = false;
//
//    protected $fillable = [
//        'barcode',
//        'name',
//        'brand',
//        'category_id',
//        'image_url',
//        'source',
//        'raw_data',
//    ];
//
//    protected $casts = [
//        'raw_data' => 'array',
//        'category_id' => 'integer',
//    ];
//
//    public function ingredients(): BelongsToMany
//    {
//        return $this->belongsToMany(Ingredient::class, 'product_ingredients')
//            ->withTimestamps();
//    }
//
//    public function nutrition(): HasOne
//    {
//        return $this->hasOne(FoodNutrition::class, 'product_id');
//    }
//
//    public function foodScore(): HasOne
//    {
//        return $this->hasOne(FoodScore::class, 'product_id');
//    }
//
//    public function cosmeticScore(): HasOne
//    {
//        return $this->hasOne(CosmeticScore::class, 'product_id');
//    }
//
//    public function scans(): HasMany
//    {
//        return $this->hasMany(Scan::class);
//    }
//
//    public function alternatives(): BelongsToMany
//    {
//        return $this->belongsToMany(
//            Product::class,
//            'product_alternatives',
//            'product_id',
//            'alternative_product_id'
//        )->withPivot('reason');
//    }
//
//    public function contributions(): HasMany
//    {
//        return $this->hasMany(ProductContribution::class);
//    }
//
//    public function isFood(): bool
//    {
//        return $this->category_id >= 1 && $this->category_id <= 100; // Adjust based on your categories
//    }
//
//    public function isCosmetic(): bool
//    {
//        return $this->category_id >= 101; // Adjust based on your categories
//    }
//
//}
