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
        $reference = '';
        if (!$conversation || $conversation == null) {
            $reference = \Str::uuid();

            $request->merge([
                'reference' => $reference,
                'role' => 'user',
            ]);

            Conversation::create([
                ...$request->all(),
            ]);

            $reply = [
                'message' => "Halo, ada yang bisa kami bantu?",
            ];
        } else {
            $reference = $conversation->reference;

            $request->merge([
                'reference' => $reference,
                'role' => 'user',
            ]);

            Conversation::create([
                ...$request->all(),
            ]);

            $reply = [
                'message' => "Mohon ditunggu",
            ];
        }

        $conversations = Conversation::query()
            ->where('reference', $reference)
            ->orderBy('created_at')
            ->limit(20)
            ->get()
            ->map(fn(Conversation $item) => "{$item->role}:{$item->message}")
            ->implode("\n");

        $this->sendFonnte($request->all(), $reply, $conversations);
    }

    private function sendFonnte(array $data, array $reply, string $conversations): string
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
                    'conversations' => $conversations,
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

