<?php

use App\Models\ProductContribution;

function pendingContributions()
{
    return ProductContribution::where('status', 'pending')->count();

}
