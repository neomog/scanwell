<?php

namespace App\Contracts;

interface ProductCatalogImportProvider
{
    public function searchProducts(
        string $query,
        int $page = 1,
        int $pageSize = 20,
        array $settings = [],
        array $credentials = []
    ): array;
}
