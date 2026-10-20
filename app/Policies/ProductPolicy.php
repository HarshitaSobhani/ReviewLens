<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductPolicy
{
    public function manage(User $user, Product $product): Response
    {
        return $user->id === $product->user_id ? Response::allow() : Response::denyAsNotFound();
    }

    public function view(User $user, Product $product): Response
    {
        return $this->manage($user, $product);
    }

    public function update(User $user, Product $product): Response
    {
        return $this->manage($user, $product);
    }

    public function delete(User $user, Product $product): Response
    {
        return $this->manage($user, $product);
    }
}
