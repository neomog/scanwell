<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use Illuminate\Database\Seeder;

class IngredientSeeder extends Seeder
{
    public function run(): void
    {
        $ingredients = [
            // Sweeteners
            [
                'name' => 'High Fructose Corn Syrup',
                'category' => 'sweetener',
                'risk_level' => 'high',
                'aliases' => ['HFCS', 'Corn Syrup', 'Glucose-Fructose Syrup'],
                'health_effects' => ['linked to obesity', 'insulin resistance', 'liver damage'],
                'description' => 'An artificial sweetener made from corn starch that has been linked to various health issues.',
                'scientific_reference' => 'https://pubmed.ncbi.nlm.nih.gov/20086073/',
            ],
            [
                'name' => 'Aspartame',
                'category' => 'sweetener',
                'risk_level' => 'high',
                'aliases' => ['NutraSweet', 'Equal', 'E951'],
                'health_effects' => ['headaches', 'dizziness', 'possible carcinogen'],
                'description' => 'An artificial non-saccharide sweetener used as a sugar substitute.',
                'scientific_reference' => 'https://www.ncbi.nlm.nih.gov/pmc/articles/PMC3856475/',
            ],
            [
                'name' => 'Stevia',
                'category' => 'sweetener',
                'risk_level' => 'low',
                'aliases' => ['Steviol glycosides', 'E960', 'Rebaudioside A'],
                'health_effects' => ['natural', 'zero calories', 'may help blood sugar'],
                'description' => 'A natural sweetener extracted from the leaves of the Stevia plant.',
                'scientific_reference' => 'https://pubmed.ncbi.nlm.nih.gov/28659154/',
            ],

            // Preservatives
            [
                'name' => 'Sodium Nitrite',
                'category' => 'preservative',
                'risk_level' => 'high',
                'aliases' => ['E250', 'Nitrite'],
                'health_effects' => ['may form carcinogens', 'linked to cancer'],
                'description' => 'A preservative commonly used in processed meats that can form carcinogenic compounds.',
                'scientific_reference' => 'https://www.ncbi.nlm.nih.gov/pmc/articles/PMC4586559/',
            ],
            [
                'name' => 'Potassium Sorbate',
                'category' => 'preservative',
                'risk_level' => 'medium',
                'aliases' => ['E202'],
                'health_effects' => ['may cause allergies', 'skin irritation'],
                'description' => 'A common preservative used to inhibit mold and yeast growth.',
                'scientific_reference' => 'https://pubmed.ncbi.nlm.nih.gov/26751467/',
            ],

            // Additives
            [
                'name' => 'Monosodium Glutamate',
                'category' => 'additive',
                'risk_level' => 'medium',
                'aliases' => ['MSG', 'E621', 'Yeast Extract'],
                'health_effects' => ['headaches', 'flushing', 'sweating'],
                'description' => 'A flavor enhancer commonly used in Asian cuisine and processed foods.',
                'scientific_reference' => 'https://pubmed.ncbi.nlm.nih.gov/27075406/',
            ],

            // Artificial Colors
            [
                'name' => 'Red 40',
                'category' => 'color',
                'risk_level' => 'high',
                'aliases' => ['Allura Red AC', 'E129', 'FD&C Red No. 40'],
                'health_effects' => ['hyperactivity in children', 'allergic reactions'],
                'description' => 'A synthetic azo dye used in many processed foods and beverages.',
                'scientific_reference' => 'https://www.ncbi.nlm.nih.gov/pmc/articles/PMC3441937/',
            ],
            [
                'name' => 'Yellow 5',
                'category' => 'color',
                'risk_level' => 'high',
                'aliases' => ['Tartrazine', 'E102', 'FD&C Yellow No. 5'],
                'health_effects' => ['allergic reactions', 'hyperactivity'],
                'description' => 'A synthetic lemon yellow azo dye used in many foods.',
                'scientific_reference' => 'https://pubmed.ncbi.nlm.nih.gov/24620742/',
            ],

            // Healthy Ingredients
            [
                'name' => 'Olive Oil',
                'category' => 'fat',
                'risk_level' => 'low',
                'aliases' => ['Extra Virgin Olive Oil', 'EVOO'],
                'health_effects' => ['healthy fats', 'antioxidants', 'anti-inflammatory'],
                'description' => 'A healthy oil rich in monounsaturated fats and antioxidants.',
                'scientific_reference' => 'https://pubmed.ncbi.nlm.nih.gov/31540342/',
            ],
            [
                'name' => 'Whole Grain Oats',
                'category' => 'grain',
                'risk_level' => 'low',
                'aliases' => ['Oatmeal', 'Rolled Oats'],
                'health_effects' => ['fiber rich', 'heart healthy', 'lowers cholesterol'],
                'description' => 'A whole grain rich in fiber, vitamins, and minerals.',
                'scientific_reference' => 'https://pubmed.ncbi.nlm.nih.gov/26923413/',
            ],
            [
                'name' => 'Organic Cane Sugar',
                'category' => 'sweetener',
                'risk_level' => 'medium',
                'aliases' => ['Cane Sugar', 'Evaporated Cane Juice'],
                'health_effects' => ['empty calories', 'tooth decay', 'blood sugar spike'],
                'description' => 'While natural, it should still be consumed in moderation.',
                'scientific_reference' => 'https://pubmed.ncbi.nlm.nih.gov/26773067/',
            ],
        ];

        foreach ($ingredients as $ingredient) {
            Ingredient::updateOrCreate(
                ['name' => $ingredient['name']],
                $ingredient
            );
        }

        $this->command->info('Seeded ' . count($ingredients) . ' ingredients successfully!');
    }
}
