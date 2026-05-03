<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

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
        'stripe_customer_id',
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

    public function favoriteProducts(): BelongsToMany
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

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(ProductContribution::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ProductAuditLog::class, 'actor_id');
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscriptionEvents(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class);
    }

    public function billingInvoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class);
    }

    public function billingTransactions(): HasMany
    {
        return $this->hasMany(BillingTransaction::class);
    }

    public function billingRefunds(): HasMany
    {
        return $this->hasMany(BillingRefund::class);
    }

    public function notificationCampaigns(): HasMany
    {
        return $this->hasMany(NotificationCampaign::class, 'created_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function pushTokens(): HasMany
    {
        return $this->hasMany(UserPushToken::class);
    }

    public function supportCases(): HasMany
    {
        return $this->hasMany(SupportCase::class);
    }

    public function assignedSupportCases(): HasMany
    {
        return $this->hasMany(SupportCase::class, 'assigned_to');
    }

    public function supportMessages(): HasMany
    {
        return $this->hasMany(SupportMessage::class);
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', Subscription::CURRENT_STATUSES)
            ->latestOfMany('created_at');
    }

    public function roleDefinition(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role', 'slug');
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        $role = $this->relationLoaded('roleDefinition')
            ? $this->roleDefinition
            : $this->roleDefinition()->with('permissions')->first();

        if ($role) {
            return $role->permissions->contains('slug', $permission);
        }

        $configuredPermissions = config("rbac.roles.{$this->role}.permissions", []);

        return in_array('*', $configuredPermissions, true) || in_array($permission, $configuredPermissions, true);
    }

    public function canAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
