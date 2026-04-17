<?php

namespace Tests\Unit;

use App\Services\ProductFamilyResolver;
use Tests\TestCase;

class ProductFamilyResolverTest extends TestCase
{
    public function test_it_resolves_water_and_household_categories_correctly(): void
    {
        $resolver = app(ProductFamilyResolver::class);

        $waterProduct = [
            'name' => 'Wisconsin Spring Water',
            'brand' => 'Wisconsin Water',
            'raw_data' => [
                'categories' => 'Waters, Spring waters',
                'categories_tags' => ['en:waters', 'en:spring-waters'],
            ],
            'packaging' => [
                'materials' => ['plastic'],
                'is_plastic' => true,
            ],
            'nutrition' => ['sugars' => 0],
        ];

        $cleanerProduct = [
            'name' => 'Multi Surface Cleaner',
            'brand' => 'Home Bright',
            'raw_data' => [
                'categories' => 'Household cleaners',
            ],
        ];

        $this->assertSame(ProductFamilyResolver::FOOD, $resolver->resolveFromNormalized($waterProduct));
        $this->assertTrue($resolver->isWater($waterProduct));
        $this->assertTrue($resolver->hasPlasticPackaging($waterProduct));
        $this->assertSame(10, $resolver->categoryIdForFamily(ProductFamilyResolver::FOOD, $waterProduct));

        $this->assertSame(ProductFamilyResolver::HOUSEHOLD, $resolver->resolveFromNormalized($cleanerProduct));
        $this->assertSame(410, $resolver->categoryIdForFamily(ProductFamilyResolver::HOUSEHOLD, $cleanerProduct));
    }
}
