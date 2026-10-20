<?php

namespace App\Policies;

use App\Models\ReviewImport;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReviewImportPolicy
{
    public function manage(User $user, ReviewImport $import): Response
    {
        return $user->id === $import->user_id ? Response::allow() : Response::denyAsNotFound();
    }

    public function view(User $user, ReviewImport $import): Response
    {
        return $this->manage($user, $import);
    }

    public function delete(User $user, ReviewImport $import): Response
    {
        return $this->manage($user, $import);
    }
}
