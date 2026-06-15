<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** is_admin bilinçli olarak fillable dışında — forceFill ile yazılır. */
    protected function handleRecordCreation(array $data): Model
    {
        $user = new User;
        $user->forceFill($data)->save(); // password cast'i parolayı hash'ler

        return $user;
    }
}
