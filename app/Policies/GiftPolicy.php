<?php

namespace App\Policies;

use App\Models\Gift;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class GiftPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Gift  $gift
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Gift $gift)
    {
        return ($user->id === $gift->user_id) || ($user->email === $gift->receiver_email);
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        //
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Gift  $gift
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Gift $gift)
    {
        return $this->view($user, $gift);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Gift  $gift
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Gift $gift)
    {
        return $this->view($user, $gift);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Gift  $gift
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Gift $gift)
    {
        return $this->view($user, $gift);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Gift  $gift
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Gift $gift)
    {
        return $this->view($user, $gift);
    }
}
