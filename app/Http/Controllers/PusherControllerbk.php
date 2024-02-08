<?php

namespace App\Http\Controllers;

use Pusher\Pusher;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Message;


class PusherController extends Controller
{
    public function sendMessage(Request $request, $eventName)
{
    $user = auth()->user();

    $pusher = new Pusher(
        env('PUSHER_APP_KEY'),
        env('PUSHER_APP_SECRET'),
        env('PUSHER_APP_ID'),
        [
            'cluster' => env('PUSHER_APP_CLUSTER'),
            'useTLS' => true,
        ]
    );

    $data = [
        'user_id' => $user->id,
        'from_username' => $user->name,
        'message' => $request->input('message'),
        'attachment' => null,
    ];

    $filename = '';
    $path = '';
    $file = '';
    $filename_url='';

    if ($request->hasFile('attachment')) {
        $file = $request->file('attachment');
        $filename = time() . '_' .$user->id.'.'.$file->getClientOriginalExtension();
        $path = $file->move('/home/doddiplexus/doddi.plexustechdev.com/templete/api/public/attachments', $filename);
        $data['attachments'] = $path;
        $filename_url = url('attachments/' . $filename);
    }


    Message::create($data);

    $pusher->trigger('my-channel', $eventName, $data);

    return response()->json([
        'status' => 'SUCCESS',
        'event' => $eventName,
        'user_id' => $user->id,
        'username' => $user->name,
        'message' => $request->get('message'),
        'filename' => $filename,
        'filename_url' => $filename_url,
    ]);
}

}
