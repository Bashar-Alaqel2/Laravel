<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->user_id === (int) $id;
});

Broadcast::channel('admin.ledger', function ($user) {
    return $user->can('manage_all');
});

Broadcast::channel('owner.earnings.{id}', function ($user, $id) {
    return (int) $user->user_id === (int) $id;
});
