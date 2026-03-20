<?php

namespace App\Models;

use App\Notifications\ActivateAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $feedback_requested_at
 * @property bool $is_admin
 * @property string $locale
 * @property string|null $username
 * @property string|null $bio
 * @property bool $is_profile_public
 * @property string|null $avatar
 * @property string|null $country
 * @property string|null $province
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Game> $games
 * @property-read int|null $games_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFeedbackRequestedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const AVATARS = ['blue', 'green', 'orange', 'pink', 'purple', 'red', 'sky', 'turquoise', 'wine', 'yellow'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'feedback_requested_at',
        'locale',
        'username',
        'bio',
        'is_profile_public',
        'avatar',
        'country',
        'province',
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

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function managerStats(): HasOne
    {
        return $this->hasOne(ManagerStats::class);
    }

    public function trophies(): HasMany
    {
        return $this->hasMany(ManagerTrophy::class);
    }

    public function deviceSessions(): HasMany
    {
        return $this->hasMany(DeviceSession::class);
    }

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
            'feedback_requested_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_profile_public' => 'boolean',
        ];
    }

    public function getInitials(): string
    {
        $initials = collect(explode(' ', $this->name))
            ->map(fn ($w) => mb_substr($w, 0, 1))
            ->join('');

        return mb_strlen($initials) > 2
            ? mb_substr($initials, 0, 1) . mb_substr($initials, -1)
            : $initials;
    }

    public function getAvatarUrl(): string
    {
        return Storage::disk('assets')->url('managers/'.($this->avatar ?? 'blue').'.png');
    }

    public function isActivated(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function sendPasswordResetNotification($token): void
    {
        if (! $this->isActivated()) {
            $this->notify(new ActivateAccount($token));

            return;
        }

        parent::sendPasswordResetNotification($token);
    }
}
