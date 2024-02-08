<?php

namespace App\Http\Controllers;

use Pusher\Pusher;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\RoomChat;
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

    $cekchat = Message::where('event',$eventName)->get();
    $data = [
        'user_id' => $user->id,
        'from_username' => $user->name,
        'message' => $request->input('message'),
        'event' => $eventName,
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
    if ($request->input('penerima')){
        $penerima =[
            'user_id' => $request->input('penerima'),
            'event' =>$eventName
        ];
        Message::create($penerima);
    }
    
    $pusher->trigger('my-channel', $eventName, $data);

    return response()->json([
        'status' => 'SUCCESS',
        'user_id' => $user->id,
        'event' => $eventName,
        'username' => $user->name,
        'filename' => $filename,
        'filename_url' => $filename_url,
    ]);
}

public function chatList()
{
    $user = auth()->user();

    // Ambil semua event yang pernah diikuti oleh user
    $events = Message::select('event')
        ->where('user_id', $user->id)
        ->groupBy('event')
        ->get();

    // Looping setiap event dan ambil daftar user yang terlibat pada event tersebut
    $data = [];
    $i=1;
    foreach ($events as $event) {
        $users = Message::select('users.name as name','users.id as id')
        ->join('users', 'messages.user_id', '=', 'users.id')
        ->where('event', $event->event)
        ->where('user_id', '<>', $user->id)
        ->groupBy('user_id', 'name')
        ->get();
        // dd($users);
    

    $room_name ='';
    $usr = [];
    foreach ($users as $user) {
        $room_name = $user->name;
        $usr[] = [
            'user_id' => $user->id,
            'username' => $user->name
        ];
    }
    
           

            $data[] = [
                'id' => $i,
                'event' => $event->event,
                'room_name' => $room_name,
                'user_list' => $user,
            ];
            $i++;
    }

    return response()->json([
        'status' => 'SUCCESS',
        'user_id' => $user->id,
        'data' => $data
    ]);
}

public function chatDetail($penerima_id){
    $user = auth()->user();
    $events = Message::select('event')
    ->whereIn('user_id', [$user->id, $penerima_id])
    ->whereNotNull('event')
    ->groupBy('event')
    ->get();


        // dd($events);

        $users = User::where('id',$penerima_id)->first();
        $room_name =$users->name;
       
            $usr[] = [
                'user_id' => $users->id,
                'username' => $users->name
            ];

        $data = [];
        foreach ($events as $event) {
        
            $i=1;
                $data[] = [
                    'id' => $i,
                    'event' => $event->event,
                    'room_name' => $room_name,
                    'user_list' => $usr,
                ];
            $i++;
               
        }

    
        return response()->json([
            'status' => 'SUCCESS',
            'user_id' => $user->id,
            'data' => $data
        ]);
    
    
}


}
