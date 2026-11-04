<?php

namespace App\Services;

use App\Models\User;

class OwnerBusinessScope
{
    public function ids(User $user): array
    {
        $ids = $user->businesses()->pluck('businesses.id')->map(fn ($id) => (string) $id)->all();
        if ($user->business_id) $ids[] = (string) $user->business_id;
        $ids = array_values(array_unique(array_filter($ids)));
        abort_unless($ids, 403, 'No business is assigned to this account.');
        return $ids;
    }

    public function primary(User $user): string
    {
        return $this->ids($user)[0];
    }
}
