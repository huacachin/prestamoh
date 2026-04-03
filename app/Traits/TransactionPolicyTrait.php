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
        if ($user->hasRole('director')) {
            return true;
        }

        $isToday = $this->isRecordFromToday($record);

        if ($user->hasAnyRole(['gerente', 'administrador'])) {
            return $isToday;
        }

        if ($user->hasAnyRole(['supervisor', 'controlador'])) {
            return $isToday && $record->user_id === $user->id;
        }

        return false;
    }

    public function delete(User $user, Model $record): bool
    {
        if ($user->hasRole('director')) {
            return true;
        }

        if ($user->hasAnyRole(['gerente', 'administrador'])) {
            return $this->isRecordFromToday($record);
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
