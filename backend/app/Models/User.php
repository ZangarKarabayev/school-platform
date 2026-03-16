<?php

namespace App\Models;

use App\Modules\Organizations\Models\City;
use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\Region;
use App\Modules\Organizations\Models\School;
use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\UserScope;
use App\Modules\Identity\Models\ApiToken;
use App\Modules\Identity\Models\AuthIdentity;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'phone',
        'status',
        'school_id',
        'city_id',
        'district_id',
        'region_id',
        'preferred_locale',
        'password',
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
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    public function authIdentities(): HasMany
    {
        return $this->hasMany(AuthIdentity::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role_assignments')
            ->withTimestamps();
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(UserScope::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->last_name,
            $this->first_name,
            $this->middle_name,
        ])));
    }

    public function hasRole(string $roleCode): bool
    {
        return $this->roles->contains(fn (Role $role): bool => $role->code === $roleCode);
    }

    public function hasPermission(string $permissionCode): bool
    {
        return $this->roles->contains(function (Role $role) use ($permissionCode): bool {
            return $role->permissions->contains('code', $permissionCode);
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin') {
            return false;
        }

        $this->loadMissing('roles.permissions');

        return $this->hasPermission('filament.access')
            || $this->hasRole('super_admin')
            || $this->hasRole('support_admin');
    }

    public function getFilamentName(): string
    {
        return $this->full_name ?: $this->phone ?: (string) $this->id;
    }
}
