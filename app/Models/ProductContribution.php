<?php

// app/Models/ProductContribution.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductContribution extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'product_id',
        'change_type',
        'old_data',
        'new_data',
        'reason',
        'evidence',
        'status',
        'reviewed_by_admin',
        'review_notes',
        'reviewed_at',
        'barcode',
        'product_name',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'evidence' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_admin');
    }

    public function approve(User $admin, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by_admin' => $admin->id,
            'review_notes' => $notes,
            'reviewed_at' => now(),
        ]);

        // Apply the changes to the product if it exists
        if ($this->product && $this->change_type === 'update') {
            $this->product->update($this->new_data);
        }
    }

    public function reject(User $admin, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by_admin' => $admin->id,
            'review_notes' => $reason,
            'reviewed_at' => now(),
        ]);
    }
}
//
//namespace App\Models;
//
//use Illuminate\Database\Eloquent\Concerns\HasUuids;
//use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
//use Illuminate\Database\Eloquent\Relations\BelongsTo;
//
//class ProductContribution extends Model
//{
//    use HasFactory, HasUuids;
//
//    protected $keyType = 'string';
//    public $incrementing = false;
//
//    protected $fillable = [
//        'user_id',
//        'product_id',
//        'change_type',
//        'old_data',
//        'new_data',
//        'reason',
//        'status',
//        'reviewed_by_admin',
//        'review_notes',
//        'reviewed_at',
//    ];
//
//    protected $casts = [
//        'old_data' => 'array',
//        'new_data' => 'array',
//        'reviewed_at' => 'datetime',
//    ];
//
//    public function user(): BelongsTo
//    {
//        return $this->belongsTo(User::class);
//    }
//
//    public function product(): BelongsTo
//    {
//        return $this->belongsTo(Product::class);
//    }
//
//    public function reviewer(): BelongsTo
//    {
//        return $this->belongsTo(User::class, 'reviewed_by_admin');
//    }
//
//    public function approve(User $admin, ?string $notes = null): void
//    {
//        $this->update([
//            'status' => 'approved',
//            'reviewed_by_admin' => $admin->id,
//            'review_notes' => $notes,
//            'reviewed_at' => now(),
//        ]);
//
//        // Apply the changes to the product
//        if ($this->product && $this->change_type === 'update') {
//            $this->product->update($this->new_data);
//        }
//    }
//
//    public function reject(User $admin, string $reason): void
//    {
//        $this->update([
//            'status' => 'rejected',
//            'reviewed_by_admin' => $admin->id,
//            'review_notes' => $reason,
//            'reviewed_at' => now(),
//        ]);
//    }
//}
