<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('workspaces.{uuid}', function ($user, $uuid) {
    return $user->hasAccessToWorkspace($uuid);
});