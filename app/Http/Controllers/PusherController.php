<?php

namespace App\Http\Controllers;

use App\Models\ChatRoom;
use App\Models\User;
use App\Services\ChatRoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Pusher\Pusher;

class PusherController extends Controller
{
    public function auth(Request $request)
    {
        $user = User::find(Auth::user()->id);

        $channelId = explode('.', $request->channel_name)[1];
        $chatRoom = ChatRoom::find($channelId);

        if(!$chatRoom) {
            return res('Chat room not found', 403);
        }

        try {
            $pusher = new Pusher(env('PUSHER_APP_KEY'), env('PUSHER_APP_SECRET'), env('PUSHER_APP_ID'));
            $auth = $pusher->presenceAuth($request->channel_name, $request->socket_id, $user->id);

            $chatRoomService = new ChatRoomService();
            if(!$chatRoomService->checkIfAuthorized($user, $chatRoom)) {
                return res('Unauthorized', 403);
            }

            $authData = json_decode($auth, true);
            return response(json_encode([
                'auth' => $pusher->socketAuth($request->channel_name, $request->socket_id),
                'what_data' => $authData,
                'user_info' => $user->toArray(),
            ]), 200);

        } catch (\Exception $e) {
            Log::info($e->getMessage());
            return res('Unauthorized', 403);
        }
    }
}
