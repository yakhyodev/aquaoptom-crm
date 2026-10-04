<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels — AquaOptom Wholesale Beverage CRM
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Umumiy do'kon operatsiyalari kanali (faol xodimlar uchun)
Broadcast::channel('store.operations', function (User $user) {
    return $user->isActive();
});

// Maxfiy moliya va tannarx kanali (faqat tannarx ko'rish huquqiga ega xodimlar va do'kon egasi uchun)
Broadcast::channel('store.finance', function (User $user) {
    return $user->isActive() && ($user->isOwner() || $user->hasPermission('view_cost_price'));
});

// Shaxsiy xodim kanali
Broadcast::channel('user.{id}', function (User $user, $id) {
    return (int) $user->id === (int) $id;
});
