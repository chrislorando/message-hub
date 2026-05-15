<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use GuzzleHttp\Client;

class FonnteController extends Controller
{
    /**
     * Fonnte incoming webhook (JSON body).
     */
    public function webhook(Request $request)
    {
        if ($request->isMethod('GET')) {
            return response()->json(['ok' => true]);
        }

        $request->only([
            'device',
            'sender',
            'message',
            'member',
            'name',
            'location',
            'url',
            'filename',
            'extension',
        ]);

        $message = Str::lower(trim((string) ($request->message ?? '')));

        $request->merge([
            'message' => $message
        ]);

        $conversation = Conversation::where('sender', $request->sender)->first();

        $reply = [];
        if (!$conversation || $conversation == null) {
            $ref = \Str::uuid();

            Conversation::create([
                ...$request->all(),
                'reference' => $ref,
                'role' => 'user',
            ]);

            $request->merge([
                'reference' => $ref,
            ]);

            $reply = [
                'message' => "Halo, ada yang bisa kami bantu?",
            ];
        } else {
            Conversation::create([
                ...$request->all(),
                'reference' => $conversation->reference,
                'role' => 'user',
            ]);

            $request->merge([
                'reference' => $conversation->reference,
            ]);

            $reply = [
                'message' => "Mohon ditunggu",
            ];
        }

        $this->sendFonnte($request->all(), $reply);
    }

    private function sendFonnte(array $data, array $reply): string
    {
        try {
            $token = config('services.fonnte.token');

            // $response = Http::withHeaders([
            //     'Authorization' => $token,
            // ])->asForm()->post('https://api.fonnte.com/send', [
            //             'target' => $data['sender'] ?? '',
            //             'message' => $reply['message'] ?? '',
            //             'url' => $reply['url'] ?? '',
            //             'filename' => $reply['filename'] ?? '',
            //         ]);

            $client = new Client();
            $client->post(config('services.n8n.webhook_url'), [
                'json' => [
                    'sender' => $data['sender'] ?? '',
                    'reference' => $data['reference'] ?? '',
                ],
                'timeout' => 10,
                'verify' => false,
            ]);

            // return $response->body();
            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            \Log::error('Webhook Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function answer(Request $request)
    {
        $token = config('services.fonnte.token');

        try {
            Conversation::create([
                ...$request->all(),
                'role' => 'assistant',
            ]);

            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->asForm()->post('https://api.fonnte.com/send', [
                        'target' => $request->sender ?? '',
                        'message' => $request->message ?? '',
                    ]);

            return $response->body();
        } catch (\Exception $e) {
            \Log::error('Webhook Error: ' . $e->getMessage());
            throw $e;
        }
    }
}

