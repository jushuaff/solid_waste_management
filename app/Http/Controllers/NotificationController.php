<?php
namespace App\Http\Controllers;
use App\Models\Notice;
use Illuminate\Http\Request;
class NotificationController extends Controller
{
    public function index(Request $request){$notices=Notice::where('recipient_user_id',(string)$request->user()->id)->orderBy('created_at','desc')->paginate(20);return view('notifications.index',compact('notices'));}
    public function read(Request $request,string $id){$n=Notice::findOrFail($id);abort_unless((string)$n->recipient_user_id===(string)$request->user()->id,404);$n->is_read=true;$n->save();return back()->with('status','Marked as read.');}
}
