<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FonnteController extends Controller
{
    public function webhook(Request $request)
    {
        if ($request->isMethod('GET')) {
            return response()->json(['ok' => true]);
        }

        $message = Str::lower(trim((string) ($request->message ?? '')));

        $request->merge([
            'message' => $message,
        ]);

        $isNewSession = $this->isSessionReset($message);

        if ($isNewSession) {
            $reference = (string) Str::uuid();
        } else {
            $reference = Conversation::where('sender', $request->sender)
                ->latest('id')
                ->value('reference') ?? (string) Str::uuid();
        }

        $request->merge([
            'reference' => $reference,
            'role' => 'user',
        ]);

        Conversation::create($request->only([
            'reference',
            'device',
            'sender',
            'message',
            'member',
            'name',
            'location',
            'url',
            'filename',
            'extension',
            'role',
        ]));

        $this->sendToN8n($reference, $request->only(['name', 'sender']));
    }

    public function answer(Request $request)
    {
        $token = config('services.fonnte.token');

        try {
            Conversation::create($request->only([
                'reference',
                'device',
                'sender',
                'message',
                'member',
                'name',
                'location',
                'url',
                'filename',
                'extension',
            ]) + ['role' => 'assistant']);

            Http::withHeaders([
                'Authorization' => $token,
            ])->asForm()->post('https://api.fonnte.com/send', [
                'target' => $request->sender ?? '',
                'message' => $request->message ?? '',
            ]);

            return '';
        } catch (\Exception $e) {
            \Log::error('Webhook Error: '.$e->getMessage());

            throw $e;
        }
    }

    public function summary(Request $request)
    {
        $reference = $request->input('reference');
        $summary = $request->input('summary');

        if (! $reference || ! $summary) {
            return response()->json(['error' => 'Missing reference or summary'], 422);
        }

        Conversation::query()
            ->where('reference', $reference)
            ->latest('id')
            ->limit(1)
            ->update(['summary' => $summary]);

        return response()->json(['ok' => true]);
    }

    private function isSessionReset(string $message): bool
    {
        $patterns = [
            '/^(halo|hai|hi|hey|hello|selamat\s+(pagi|siang|sore|malam))$/i',
            '/^(makasih|thanks|terima\s+kasih|thx|ok\s+(makasih|thanks|terima\s+kasih)|sama[\s-]*sama)/i',
            '/^(sampai\s+jumpa|dadah|bye|dah|met\s+malam|good\s+(night|bye))$/i',
            '/^(ok|sip|baik|sudah\s+jelas|udah\s+jelas|paham|oke|okeh)$/i',
        ];

        return (bool) preg_match(implode('|', $patterns), trim($message));
    }

    private function sendToN8n(string $reference, array $data): string
    {
        $recentLimit = config('services.n8n.recent_message_limit', 10);

        $summaryRow = Conversation::query()
            ->where('reference', $reference)
            ->whereNotNull('summary')
            ->latest('id')
            ->first();

        $messages = Conversation::query()
            ->where('reference', $reference)
            ->orderBy('id', 'asc')
            ->limit($recentLimit)
            ->get();

        $conversations = '';

        if ($summaryRow?->summary) {
            $conversations .= "[Ringkasan percakapan sebelumnya]\n{$summaryRow->summary}\n\n";
        }

        $conversations .= '[Percakapan terakhir]'."\n".$messages
            ->map(fn (Conversation $c) => "{$c->role}:{$c->message}")
            ->implode("\n");

        $token = config('services.fonnte.token');

        Http::withHeaders([
            'Authorization' => $token,
        ])->asForm()->post('https://api.fonnte.com/send', [
            'target' => $data['sender'] ?? '',
            'message' => '',
        ]);

        $client = new Client;
        $client->post(config('services.n8n.webhook_url'), [
            'json' => [
                'name' => $data['name'] ?? '',
                'sender' => $data['sender'] ?? '',
                'reference' => $reference,
                'conversations' => $conversations,
            ],
            'timeout' => 10,
            'verify' => false,
        ]);

        return '';
    }
}
