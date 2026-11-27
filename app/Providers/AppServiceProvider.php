<?php
namespace App\Providers;

use App\Models\Notice;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as BladeView;

class AppServiceProvider extends ServiceProvider
{
    public function register():void {}

    public function boot():void
    {
        // One database-backed unread count for the authenticated application shell.
        // The bell appears on every screen and clears after opening notifications.
        View::composer('layouts.app',function(BladeView $view):void{
            $user=auth()->user();
            $unread=$user
                ? Notice::where('recipient_user_id',(string)$user->id)->where('is_read',false)->count()
                : 0;
            $view->with('unreadNotificationCount',$unread);
        });
    }
}
