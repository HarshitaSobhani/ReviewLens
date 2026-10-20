<?php

namespace App\Policies;

use App\Models\AnalysisRun;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AnalysisRunPolicy
{
    private function own(User $user, AnalysisRun $run): Response
    {
        return $user->id === $run->user_id ? Response::allow() : Response::denyAsNotFound();
    }

    public function view(User $user, AnalysisRun $run): Response
    {
        return $this->own($user, $run);
    }

    public function delete(User $user, AnalysisRun $run): Response
    {
        return $this->own($user, $run);
    }

    public function export(User $user, AnalysisRun $run): Response
    {
        return $this->own($user, $run);
    }
}
