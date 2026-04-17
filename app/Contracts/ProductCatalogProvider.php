<?php

namespace App\Contracts;

interface ProductCatalogProvider
{
    public function providerKey(): string;

    public function findByBarcode(string $barcode): ?array;
}
