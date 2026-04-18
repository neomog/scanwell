<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasUuids, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'email_verified_at',
        'provider',
        'provider_id',
        'avatar',
        'role',
        'reputation_points',
        'approved_contributions_count',
        'rejected_contributions_count',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'reputation_points' => 'integer',
            'approved_contributions_count' => 'integer',
            'rejected_contributions_count' => 'integer',
        ];
    }

    public function favoriteProducts()
    {
        return $this->belongsToMany(Product::class, 'user_favorites')
            ->withTimestamps();
    }

    public function hasFavorited(Product $product): bool
    {
        return $this->favoriteProducts()->where('product_id', $product->id)->exists();
    }

    public function isBanned(): bool
    {
        return cache()->has("user_banned_{$this->id}");
    }

    public function getBanInfo(): ?array
    {
        return cache()->get("user_banned_{$this->id}");
    }

    public function scans()
    {
        return $this->hasMany(Scan::class);
    }

    public function contributions()
    {
        return $this->hasMany(ProductContribution::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(ProductAuditLog::class, 'actor_id');
    }

}
