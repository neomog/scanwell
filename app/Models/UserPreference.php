<?php

// app/Models/UserPreference.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    use HasFactory;

    protected $table = 'user_preferences';

    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'diet_type',
        'allergies',
        'skin_type',
        'health_goals',
        'avoid_ingredients',
        'min_score_threshold',
    ];

    protected $casts = [
        'allergies' => 'array',
        'health_goals' => 'array',
        'avoid_ingredients' => 'array',
        'min_score_threshold' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAllergicTo(string $ingredient): bool
    {
        return in_array(
            strtolower($ingredient),
            array_map('strtolower', $this->allergies ?? []),
            true
        );
    }

    public function avoidsIngredient(string $ingredient): bool
    {
        return in_array(
            strtolower($ingredient),
            array_map('strtolower', $this->avoid_ingredients ?? []),
            true
        );
    }

    public function getPersonalizedWarnings(Product $product): array
    {
        $warnings = [];

        foreach ($product->ingredients ?? [] as $ingredient) {
            if (!isset($ingredient->name) || !is_string($ingredient->name)) {
                continue;
            }

            if ($this->isAllergicTo($ingredient->name)) {
                $warnings[] = "Contains {$ingredient->name} which you're allergic to";
            }

            if ($this->avoidsIngredient($ingredient->name)) {
                $warnings[] = "Contains {$ingredient->name} which you prefer to avoid";
            }
        }

        return $warnings;
    }
}
//
//namespace App\Models;
//
//use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
//use Illuminate\Database\Eloquent\Relations\BelongsTo;
//
//class UserPreference extends Model
//{
//    use HasFactory;
//
//    protected $table = 'user_preferences';
//
//    protected $primaryKey = 'user_id';
//    public $incrementing = false;
//    protected $keyType = 'string';
//
//    protected $fillable = [
//        'user_id',
//        'diet_type',
//        'allergies',
//        'skin_type',
//        'health_goals',
//        'avoid_ingredients',
//        'min_score_threshold',
//    ];
//
//    protected $casts = [
//        'allergies' => 'array',
//        'health_goals' => 'array',
//        'avoid_ingredients' => 'array',
//        'min_score_threshold' => 'integer',
//    ];
//
//    public function user(): BelongsTo
//    {
//        return $this->belongsTo(User::class);
//    }
//
//    /**
//     * Check if user is allergic to an ingredient
//     */
//    public function isAllergicTo(string $ingredient): bool
//    {
//        return in_array(strtolower($ingredient), array_map('strtolower', $this->allergies ?? []));
//    }
//
//    /**
//     * Check if user avoids an ingredient
//     */
//    public function avoidsIngredient(string $ingredient): bool
//    {
//        return in_array(strtolower($ingredient), array_map('strtolower', $this->avoid_ingredients ?? []));
//    }
//
//    /**
//     * Get personalized warning for a product based on preferences
//     */
//    public function getPersonalizedWarnings(Product $product): array
//    {
//        $warnings = [];
//
//        foreach ($product->ingredients as $ingredient) {
//            if ($this->isAllergicTo($ingredient->name)) {
//                $warnings[] = "Contains {$ingredient->name} which you're allergic to";
//            }
//
//            if ($this->avoidsIngredient($ingredient->name)) {
//                $warnings[] = "Contains {$ingredient->name} which you prefer to avoid";
//            }
//        }
//
//        return $warnings;
//    }
//}
