<?php

use App\Models\Staff;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('facility.{id}', function ($user, $id) {
    return Staff::where('user_id', $user->id)
        ->whereHas('facilityStaffRoles', function ($q) use ($id) {
            $q->where('facility_id', (int) $id)
              ->whereIn('assignment_status', ['active', 'on_leave']);
        })->exists();
});
