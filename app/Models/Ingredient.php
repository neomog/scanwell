<?php
// app/Models/Ingredient.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ingredient extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'category',
        'risk_level',
        'scientific_reference',
        'description',
        'aliases',
        'health_effects',
        'regulatory_status',
    ];

    protected $casts = [
        'aliases' => 'array',
        'health_effects' => 'array',
        'regulatory_status' => 'array',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_ingredients')
            ->withPivot('percentage', 'is_additive', 'origin')
            ->withTimestamps();
    }

    public function getRiskColorAttribute(): string
    {
        return match($this->risk_level) {
            'high' => 'red',
            'medium' => 'orange',
            'low' => 'green',
            default => 'gray',
        };
    }
}

//namespace App\Models;
//
//use Illuminate\Database\Eloquent\Concerns\HasUuids;
//use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
//use Illuminate\Database\Eloquent\Relations\BelongsToMany;
//
//class Ingredient extends Model
//{
//    use HasFactory, HasUuids;
//
//    protected $keyType = 'string';
//    public $incrementing = false;
//
//    protected $fillable = [
//        'name',
//        'category',
//        'risk_level',
//        'scientific_reference',
//        'description',
//        'aliases',
//        'health_effects',
//        'regulatory_status',
//    ];
//
//    protected $casts = [
//        'aliases' => 'array',
//        'health_effects' => 'array',
//        'regulatory_status' => 'array',
//    ];
//
//    public function products(): BelongsToMany
//    {
//        return $this->belongsToMany(Product::class, 'product_ingredients')
//            ->withPivot('percentage', 'is_additive', 'origin')
//            ->withTimestamps();
//    }
//
//    public function getRiskColorAttribute(): string
//    {
//        return match($this->risk_level) {
//            'high' => 'red',
//            'medium' => 'yellow',
//            'low' => 'green',
//            default => 'gray',
//        };
//    }
//
//    public function getAlternativeNamesAttribute(): string
//    {
//        return implode(', ', $this->aliases ?? []);
//    }
//}
