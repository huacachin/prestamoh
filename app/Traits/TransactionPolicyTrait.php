<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait TransactionPolicyTrait
{
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Model $record): bool
    {
        // Quien puede editar histórico edita siempre.
        if ($user->can('caja.editar-historico')) {
            return true;
        }

        // Resto: solo registros propios y del día.
        return $this->isRecordFromToday($record) && $record->user_id === $user->id;
    }

    public function delete(User $user, Model $record): bool
    {
        if ($user->can('caja.eliminar')) {
            return true;
        }

        return false;
    }

    protected function isRecordFromToday(Model $record): bool
    {
        $tz = config('app.timezone', 'America/Lima');
        $today = now($tz)->toDateString();

        $date = $record->date instanceof \Carbon\Carbon
            ? $record->date->toDateString()
            : ($record->created_at?->timezone($tz)->toDateString());

        return $date === $today;
    }
}
