<?php

namespace App\Support;

use App\Models\User;

class AuthPayload
{
    public static function forUser(User $user): array
    {
        return [
            'user' => $user,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ];
    }
}
