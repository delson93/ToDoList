<?php

namespace App\Policies;

use App\ListAccess;
use App\Models\Participant;

class ListPolicy
{
    public function allows(Participant $participant, string $required): bool
    {
        return ListAccess::LEVELS[$participant->role] >= ListAccess::LEVELS[$required];
    }
}
