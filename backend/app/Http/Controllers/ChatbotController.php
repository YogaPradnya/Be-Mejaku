<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChatRequest;
use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatbotController extends Controller
{
    protected ChatbotService $chatbotService;

    public function __construct(ChatbotService $chatbotService)
    {
        $this->chatbotService = $chatbotService;
    }

    /**
     * POST /api/chat
     * Kirim pesan ke chatbot, terima respons AI (non-streaming).
     */
    public function chat(ChatRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (!$this->chatbotService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Chatbot belum dikonfigurasi. API key tidak tersedia.',
            ], 503);
        }

        $result = $this->chatbotService->chat(
            $validated['message'],
            $validated['history'] ?? []
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'error' => $result['debug'] ?? null,
            ], 502);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'reply' => $result['reply'],
                'model' => $result['model'],
                'usage' => $result['usage'],
            ],
        ]);
    }

    /**
     * POST /api/chat/stream
     * Kirim pesan dengan streaming response (SSE) untuk UX real-time di FE.
     */
    public function stream(ChatRequest $request): StreamedResponse
    {
        $validated = $request->validated();

        return response()->stream(function () use ($validated) {
            if (!$this->chatbotService->isConfigured()) {
                echo "data: " . json_encode(['error' => 'API key tidak tersedia.']) . "\n\n";
                echo "data: [DONE]\n\n";
                ob_flush();
                flush();
                return;
            }

            $config = $this->chatbotService->getStreamConfig(
                $validated['message'],
                $validated['history'] ?? []
            );

            try {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $config['url'],
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => $config['headers'],
                    CURLOPT_POSTFIELDS => $config['body'],
                    CURLOPT_WRITEFUNCTION => function ($ch, $data) {
                        echo $data;
                        ob_flush();
                        flush();
                        return strlen($data);
                    },
                    CURLOPT_TIMEOUT => 60,
                ]);

                curl_exec($ch);

                if (curl_errno($ch)) {
                    echo "data: " . json_encode(['error' => 'Koneksi ke AI gagal.']) . "\n\n";
                    echo "data: [DONE]\n\n";
                    ob_flush();
                    flush();
                }

                curl_close($ch);

            } catch (\Exception $e) {
                echo "data: " . json_encode(['error' => 'Terjadi kesalahan.']) . "\n\n";
                echo "data: [DONE]\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
