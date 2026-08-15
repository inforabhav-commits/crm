<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'reports_to_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'reports_to_id' => 'integer',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    public function managedTeams()
    {
        return $this->hasMany(Team::class, 'manager_id');
    }

    public function justCallMapping()
    {
        return $this->hasOne(JustCallUserMapping::class)->where('is_active', true);
    }

    public function justCallMappings()
    {
        return $this->hasMany(JustCallUserMapping::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'reports_to_id');
    }

    public function directReports()
    {
        return $this->hasMany(User::class, 'reports_to_id');
    }

    public function hasRole(string $role): bool
    {
        return $this->roles->contains('slug', $role);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole('super-admin')) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permission) {
                $query->where('slug', $permission);
            })
            ->exists();
    }

    public function wouldCreateReportingCycle(?int $managerId): bool
    {
        if ($managerId === null) {
            return false;
        }

        if (! $this->exists || $managerId === $this->id) {
            return $managerId === $this->id;
        }

        $seen = [$this->id];
        $current = User::find($managerId);

        while ($current) {
            if (in_array($current->id, $seen, true)) {
                return true;
            }

            $seen[] = $current->id;
            $current = $current->reports_to_id ? User::find($current->reports_to_id) : null;
        }

        return false;
    }

    public function reportingTreeUserIds(): array
    {
        if (! $this->exists) {
            return [];
        }

        $ids = [];
        $frontier = [$this->id];

        while ($frontier) {
            $directReportIds = User::whereIn('reports_to_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $directReportIds = array_values(array_diff($directReportIds, $ids));
            $ids = array_values(array_unique(array_merge($ids, $directReportIds)));
            $frontier = $directReportIds;
        }

        return $ids;
    }
}
