<?php

namespace App\Models;

use Spatie\Permission\Traits\HasRoles;
use Lab404\Impersonate\Models\Impersonate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    // HasFactory para datos demo, Notifiable para alertas, HasRoles para permisos y Impersonate para suplantación
    use HasFactory, Notifiable, HasRoles, Impersonate;

    /**
     * Casts de atributos.
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
     * Relación uno a uno con Client (si el usuario es un cliente).
     */
    public function client(): HasOne
    {
        return $this->hasOne(Client::class, 'user_id');
    }

    /**
     * Relación uno a muchos con Expedients (expedientes asignados si el usuario es un trabajador).
     */
    public function expedients(): HasMany
    {
        return $this->hasMany(Expedient::class, 'worker_id');
    }

    /**
     * Relación uno a muchos con Appointments (citas asignadas si el usuario es un trabajador).
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'worker_id');
    }
}
