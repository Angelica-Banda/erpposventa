<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasFactory, Notifiable;

    protected $table = 'usuarios';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Roles asignados a este usuario.
     */
    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'roles_usuarios', 'usuario_id', 'rol_id')->withTimestamps();
    }

    /**
     * Comprueba si el usuario tiene uno o varios roles.
     */
    public function tieneRol(string|array $roles): bool
    {
        if (is_string($roles)) {
            return $this->roles->contains('nombre', $roles);
        }

        return $this->roles->whereIn('nombre', $roles)->isNotEmpty();
    }

    /**
     * Asigna un rol al usuario.
     */
    public function asignarRol(string|Rol $rol): void
    {
        if (is_string($rol)) {
            $rol = Rol::firstOrCreate(['nombre' => $rol], ['nombre_visible' => ucfirst($rol)]);
        }

        $this->roles()->syncWithoutDetaching([$rol->id]);
    }
}
