<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductContributionResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductContribution;
use App\Models\UserPreference;
use App\Services\ProductAnalysisService;
use App\Services\ScoreCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductContributionController extends Controller
{
    protected ProductAnalysisService $productAnalysisService;
    protected ScoreCalculationService $scoreCalculationService;

    public function __construct(
        ProductAnalysisService $productAnalysisService,
        ScoreCalculationService $scoreCalculationService
    ) {
        $this->productAnalysisService = $productAnalysisService;
        $this->scoreCalculationService = $scoreCalculationService;
    }

    /**
     * Store a new product contribution
     */
    public function store(Request $request, string $barcode): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'change_type' => 'required|string|in:add,update,correct,report_issue',
            'field_name' => 'required_if:change_type,update,correct|string',
            'old_value' => 'sometimes',
            'new_value' => 'required',
            'reason' => 'required|string|min:10',
            'evidence' => 'sometimes|array',
            'evidence.*' => 'url',
            'product_name' => 'required_if:change_type,add|string|max:255',
            'brand' => 'nullable|string|max:255',
            'ingredients' => 'nullable|array',
            'nutrition' => 'nullable|array',
            'image_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Find existing product
            $existingProduct = Product::where('barcode', $barcode)->first();

            // For 'add' contributions, create the product immediately with pending status
            $product = null;
            $contribution = null;

            if ($request->change_type === 'add') {
                // Create the product first
                $productData = [
                    'barcode' => $barcode,
                    'name' => $request->product_name,
                    'brand' => $request->brand ?? null,
                    'image_url' => $request->image_url ?? null,
                    'source' => 'user_contribution',
                    'raw_data' => [
                        'product_name' => $request->product_name,
                        'brand' => $request->brand,
                        'ingredients' => $request->ingredients,
                        'nutrition' => $request->nutrition,
                    ],
                ];

                if (!$existingProduct) {
                    $product = Product::create($productData);
                } else  {
                    $product = $existingProduct;
                }


                // Process ingredients if provided
                if ($request->has('ingredients') && is_array($request->ingredients)) {
                    $this->processIngredients($product, $request->ingredients);
                }

                // Add nutrition data if provided
                if ($request->has('nutrition') && is_array($request->nutrition)) {
                    $this->addNutritionData($product, $request->nutrition);
                }

                // Calculate scores for the product
                $this->calculateProductScores($product);

                // Refresh product to load all relationships
                $product = Product::with([
                    'ingredients',
                    'nutrition',
                    'foodScore',
                    'cosmeticScore'
                ])->find($product->id);

                // Create contribution record linking to the product
                $contribution = ProductContribution::create([
                    'user_id' => Auth::id(),
                    'product_id' => $product->id,
                    'change_type' => $request->change_type,
                    'old_data' => null,
                    'new_data' => $request->new_value,
                    'reason' => $request->reason,
                    'evidence' => $request->evidence,
                    'status' => 'approved',
                    'barcode' => $barcode,
                    'product_name' => $request->product_name,
                ]);

            } else {
                // For updates/corrections, create pending contribution
                $contribution = ProductContribution::create([
                    'user_id' => Auth::id(),
                    'product_id' => $existingProduct?->id,
                    'change_type' => $request->change_type,
                    'old_data' => $request->old_value ? [$request->field_name => $request->old_value] : null,
                    'new_data' => [$request->field_name => $request->new_value],
                    'reason' => $request->reason,
                    'evidence' => $request->evidence,
                    'status' => 'pending',
                    'barcode' => $barcode,
                    'product_name' => $request->product_name ?? $existingProduct?->name,
                ]);

                $product = $existingProduct ? Product::with([
                    'ingredients',
                    'nutrition',
                    'foodScore',
                    'cosmeticScore'
                ])->find($existingProduct->id) : null;
            }

            DB::commit();

            // Get personalized warnings if user is authenticated
            $personalizedWarnings = [];
            $scoreInterpretation = null;

            if ($product && Auth::check()) {
                $preferences = UserPreference::where('user_id', Auth::id())->first();
                if ($preferences && $product) {
                    $personalizedWarnings = $preferences->getPersonalizedWarnings($product);
                }

                // Get score interpretation
                $score = $product->score;
                if ($score !== null) {
                    $scoreInterpretation = $this->interpretScore($score);
                }
            }

            // Find alternatives if product score is low
            $alternatives = [];
            if ($product && $product->score && $product->score < 50) {
                $alternatives = $this->findAlternatives($product);
            }

            return response()->json([
                'success' => true,
                'message' => $request->change_type === 'add'
                    ? 'Product added successfully with complete analysis!'
                    : 'Contribution submitted successfully. Thank you for helping improve our database!',
                'data' => [
                    'contribution' => new ProductContributionResource($contribution),
                    'product' => $product ? new ProductResource($product) : null,
                    'personalized_warnings' => $personalizedWarnings,
                    'alternatives' => $alternatives ? ProductResource::collection($alternatives) : [],
                    'score_interpretation' => $scoreInterpretation,
                ],
            ], 201);

        } catch (QueryException $e) {
            DB::rollBack();

            // MySQL duplicate entry error code
            if ($e->errorInfo[1] === 1062) {
                $existingProduct = Product::with([
                    'ingredients',
                    'nutrition',
                    'foodScore',
                    'cosmeticScore'
                ])->where('barcode', $barcode)->first();

                return response()->json([
                    'success' => false,
                    'message' => 'This product already exists. You can submit an update or correction instead.',
                    'data' => [
                        'product' => $existingProduct ? new ProductResource($existingProduct) : null
                    ]
                ], 409);
            }

            return response()->json([
                'success' => false,
                'message' => 'Database error occurred',
            ], 500);

//        catch (\Exception $e) {
//                DB::rollBack();
//                return response()->json([
//                    'success' => false,
//                    'message' => 'Failed to submit contribution',
//                    'error' => $e->getMessage(),
//                ], 500);
//            }
        }
    }

    /**
     * Get user's contributions
     */
    public function userContributions(): JsonResponse
    {
        $contributions = ProductContribution::where('user_id', Auth::id())
            ->with(['product', 'reviewer', 'product.foodScore', 'product.cosmeticScore'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => ProductContributionResource::collection($contributions),
            'meta' => [
                'total' => $contributions->total(),
                'per_page' => $contributions->perPage(),
                'current_page' => $contributions->currentPage(),
                'last_page' => $contributions->lastPage(),
            ],
        ]);
    }

    /**
     * Get pending contributions (admin only)
     */
    public function pending(): JsonResponse
    {
        $this->authorize('viewPending', ProductContribution::class);

        $contributions = ProductContribution::with(['user', 'product', 'reviewer'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => ProductContributionResource::collection($contributions),
            'meta' => [
                'total' => $contributions->total(),
                'pending_count' => ProductContribution::where('status', 'pending')->count(),
            ],
        ]);
    }

    /**
     * Approve a contribution (admin only)
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $this->authorize('approve', ProductContribution::class);

        DB::beginTransaction();

        try {
            $contribution = ProductContribution::findOrFail($id);
            $validator = Validator::make($request->all(), [
                'notes' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Process the contribution based on type
            $product = null;

            if ($contribution->change_type === 'add') {
                // Create new product from contribution
                $product = $this->createProductFromContribution($contribution);
            } elseif ($contribution->change_type === 'update' && $contribution->product) {
                // Update existing product
                $product = $this->updateProductFromContribution($contribution);
            }

            // Mark contribution as approved
            $contribution->approve(Auth::user(), $request->notes);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contribution approved and product updated successfully',
                'data' => [
                    'contribution' => new ProductContributionResource($contribution->load(['user', 'product', 'reviewer'])),
                    'product' => $product ? new \App\Http\Resources\ProductResource($product) : null,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve contribution',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a contribution (admin only)
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $this->authorize('reject', ProductContribution::class);

        $contribution = ProductContribution::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $contribution->reject(Auth::user(), $request->reason);

        return response()->json([
            'success' => true,
            'message' => 'Contribution rejected',
            'data' => new ProductContributionResource($contribution->load(['user', 'product', 'reviewer'])),
        ]);
    }

    /**
     * Create product from contribution and calculate scores
     */
    protected function createProductFromContribution(ProductContribution $contribution): Product
    {
        $newData = $contribution->new_data;

        // Create the product
        $product = Product::create([
            'barcode' => $contribution->barcode,
            'name' => $newData['name'] ?? $contribution->product_name,
            'brand' => $newData['brand'] ?? null,
            'image_url' => $newData['image_url'] ?? null,
            'source' => 'user_contribution',
            'raw_data' => $newData,
        ]);

        // Process ingredients if provided
        if (isset($newData['ingredients']) && is_array($newData['ingredients'])) {
            $this->processIngredients($product, $newData['ingredients']);
        }

        // Add nutrition data if provided
        if (isset($newData['nutrition']) && is_array($newData['nutrition'])) {
            $this->addNutritionData($product, $newData['nutrition']);
        }

        // Calculate scores for the product
        $this->calculateProductScores($product);

        // Update contribution with product ID
        $contribution->update(['product_id' => $product->id]);

        return $product;
    }

    /**
     * Update product from contribution and recalculate scores
     */
    protected function updateProductFromContribution(ProductContribution $contribution): Product
    {
        $product = $contribution->product;
        $newData = $contribution->new_data;
        $fieldName = array_key_first($newData);
        $fieldValue = $newData[$fieldName];

        // Update the specific field
        switch ($fieldName) {
            case 'name':
                $product->update(['name' => $fieldValue]);
                break;
            case 'brand':
                $product->update(['brand' => $fieldValue]);
                break;
            case 'image_url':
                $product->update(['image_url' => $fieldValue]);
                break;
            case 'ingredients':
                // Re-process ingredients
                $product->ingredients()->detach();
                $this->processIngredients($product, $fieldValue);
                break;
            case 'nutrition':
                // Update nutrition
                if ($product->nutrition) {
                    $product->nutrition->update($fieldValue);
                } else {
                    $this->addNutritionData($product, $fieldValue);
                }
                break;
        }

        // Recalculate scores after update
        $this->calculateProductScores($product);

        return $product->fresh();
    }

    /**
     * Process and link ingredients to product
     */
    protected function processIngredients(Product $product, array $ingredients): void
    {
        foreach ($ingredients as $ingredientData) {
            $ingredientName = is_array($ingredientData) ? $ingredientData['name'] : $ingredientData;

            // Check if ingredient exists
            $ingredient = \App\Models\Ingredient::where('name', $ingredientName)->first();

            if (!$ingredient) {
                // Create ingredient with default values
                $ingredient = \App\Models\Ingredient::create([
                    'name' => $ingredientName,
                    'category' => $this->determineIngredientCategory($ingredientName),
                    'risk_level' => $this->determineRiskLevel($ingredientName),
                ]);
            }

            $percentage = is_array($ingredientData) ? ($ingredientData['percentage'] ?? null) : null;

            $product->ingredients()->syncWithoutDetaching([
                $ingredient->id => [
                    'percentage' => $percentage,
                    'is_additive' => $this->isAdditive($ingredientName),
                ]
            ]);
        }
    }

    /**
     * Add nutrition data to product
     */
    protected function addNutritionData(Product $product, array $nutritionData): void
    {
        \App\Models\FoodNutrition::updateOrCreate(
            ['product_id' => $product->id],
            [
                'calories' => $nutritionData['calories'] ?? null,
                'fat' => $nutritionData['fat'] ?? null,
                'saturated_fat' => $nutritionData['saturated_fat'] ?? null,
                'carbohydrates' => $nutritionData['carbohydrates'] ?? null,
                'fiber' => $nutritionData['fiber'] ?? null,
                'sugars' => $nutritionData['sugars'] ?? null,
                'protein' => $nutritionData['protein'] ?? null,
            ]
        );
    }

    /**
     * Calculate and save scores for product
     */
    protected function calculateProductScores(Product $product): void
    {
        // Determine if it's food or cosmetic based on category or ingredients
        $isFood = $product->category_id < 100 || empty($product->category_id);

        if ($isFood) {
            $scores = $this->scoreCalculationService->calculateFoodScores($product);

            \App\Models\FoodScore::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'overall_score' => $scores['overall'],
                    'nutrition_score' => $scores['nutrition'],
                    'ingredient_score' => $scores['ingredient'] ?? 50,
                    'additive_score' => $scores['additive'],
                    'processing_score' => $scores['processing'],
                    'explanation_text' => $scores['explanation'],
                    'calculated_at' => now(),
                ]
            );
        } else {
            $scores = $this->scoreCalculationService->calculateCosmeticScores($product);

            \App\Models\CosmeticScore::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'overall_score' => $scores['overall'],
                    'irritant_score' => $scores['irritant'],
                    'endocrine_score' => $scores['endocrine'],
                    'explanation_text' => $scores['explanation'],
                    'calculated_at' => now(),
                ]
            );
        }
    }

    /**
     * Find alternative products
     */
    protected function findAlternatives(Product $product): ?object
    {
        return Product::with(['foodScore', 'cosmeticScore'])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where(function ($query) {
                $query->whereHas('foodScore', function ($q) {
                    $q->where('overall_score', '>=', 70);
                })->orWhereHas('cosmeticScore', function ($q) {
                    $q->where('overall_score', '>=', 70);
                });
            })
            ->limit(3)
            ->get();
    }

    /**
     * Interpret score in human-readable format
     */
    protected function interpretScore(float $score): array
    {
        if ($score >= 80) {
            return [
                'grade' => 'Excellent',
                'description' => 'This is a very healthy product with minimal concerns.',
                'color' => 'green',
            ];
        } elseif ($score >= 60) {
            return [
                'grade' => 'Good',
                'description' => 'This product has good quality with some minor concerns.',
                'color' => 'lightgreen',
            ];
        } elseif ($score >= 40) {
            return [
                'grade' => 'Moderate',
                'description' => 'This product is average. Consider checking the ingredients list.',
                'color' => 'yellow',
            ];
        } elseif ($score >= 20) {
            return [
                'grade' => 'Poor',
                'description' => 'This product has several concerns. Look for healthier alternatives.',
                'color' => 'orange',
            ];
        } else {
            return [
                'grade' => 'Avoid',
                'description' => 'This product is not recommended. Please consider healthier alternatives.',
                'color' => 'red',
            ];
        }
    }

    /**
     * Determine ingredient category
     */
    protected function determineIngredientCategory(string $ingredientName): string
    {
        $ingredientName = strtolower($ingredientName);

        $categories = [
            'sweetener' => ['sugar', 'syrup', 'honey', 'aspartame', 'stevia'],
            'preservative' => ['preservative', 'sulfite', 'nitrite', 'benzoate'],
            'fat' => ['oil', 'fat', 'butter', 'lard'],
            'additive' => ['e1', 'e2', 'e3', 'e4', 'e5', 'flavor', 'color'],
            'protein' => ['protein', 'whey', 'casein', 'collagen'],
            'fiber' => ['fiber', 'cellulose', 'inulin', 'psyllium'],
        ];

        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($ingredientName, $keyword)) {
                    return $category;
                }
            }
        }

        return 'other';
    }

    /**
     * Determine risk level of ingredient
     */
    protected function determineRiskLevel(string $ingredientName): string
    {
        $ingredientName = strtolower($ingredientName);

        $highRisk = [
            'aspartame', 'saccharin', 'msg', 'monosodium glutamate',
            'sodium nitrite', 'potassium bromate', 'bha', 'bht',
            'artificial color', 'red 40', 'yellow 5', 'blue 1'
        ];

        $mediumRisk = [
            'high fructose corn syrup', 'partially hydrogenated', 'carrageenan',
            'sodium benzoate', 'potassium sorbate'
        ];

        if (in_array($ingredientName, $highRisk)) {
            return 'high';
        } elseif (in_array($ingredientName, $mediumRisk)) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Check if ingredient is an additive
     */
    protected function isAdditive(string $ingredientName): bool
    {
        $additives = ['lecithin', 'vanillin', 'emulsifier', 'preservative', 'color', 'flavor'];
        $ingredientName = strtolower($ingredientName);

        foreach ($additives as $additive) {
            if (str_contains($ingredientName, $additive)) {
                return true;
            }
        }

        return false;
    }

}
