<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ChatbotService
{
    private string $apiKey;
    private string $model;
    private string $apiUrl;
    private int $maxTokens;
    private float $temperature;

    public function __construct()
    {
        $this->apiKey = config('groq.api_key', '');
        $this->model = config('groq.model', 'qwen/qwen3.8-27b');
        $this->apiUrl = config('groq.api_url', 'https://api.groq.com/openai/v1/chat/completions');
        $this->maxTokens = config('groq.max_tokens', 1024);
        $this->temperature = config('groq.temperature', 0.7);
    }

    /**
     * Cek apakah chatbot sudah dikonfigurasi.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * System prompt untuk chatbot MejaKu.
     */
    public function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Kamu adalah asisten virtual MejaKu, aplikasi pemesanan makanan di pujasera.
Kamu membantu pelanggan dengan ramah dan informatif menggunakan bahasa Indonesia.

Informasi yang kamu ketahui:
- MejaKu adalah website pemesanan makanan di pujasera melalui kode QR meja.
- Pelanggan bisa memesan langsung dari meja tanpa antre.
- Saat ini tersedia menu dari Warung Bali Dwi: Nasi Campur Bali Agung (Rp 58.000).
- Pilihan porsi: Biasa (tanpa tambahan) dan Jumbo (+Rp 15.000).
- Pelengkap: Ekstra Sambal Matah (+Rp 5.000), Sate Lilit Tuna (+Rp 8.000), Kulit Ayam Bali Renyah (+Rp 12.000).
- Tingkat pedas: Tidak Pedas, Sedang, Pedas, Sangat Pedas.
- Pembayaran melalui QRIS.
- Lokasi: Jakarta Culinary Hall, Pujasera Utama.

Aturan:
- Jawab dengan singkat, jelas, dan ramah.
- Jika ditanya hal di luar konteks MejaKu atau makanan, arahkan kembali ke topik yang relevan.
- Jangan memberikan informasi yang tidak akurat.
- Gunakan format yang mudah dibaca.
PROMPT;
    }

    /**
     * Bangun array messages untuk API Groq.
     */
    public function buildMessages(string $userMessage, array $history = []): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->getSystemPrompt()],
        ];

        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        return $messages;
    }

    /**
     * Kirim pesan ke Groq API (non-streaming).
     *
     * @return array{success: bool, reply?: string, usage?: array, error?: string}
     */
    public function chat(string $userMessage, array $history = []): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Chatbot belum dikonfigurasi. API key tidak tersedia.',
            ];
        }

        $messages = $this->buildMessages($userMessage, $history);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->apiUrl, [
                'model' => $this->model,
                'messages' => $messages,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
                'stream' => false,
            ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => 'Gagal mendapatkan respons dari AI.',
                    'debug' => config('app.debug') ? $response->json() : null,
                ];
            }

            $data = $response->json();
            $reply = $data['choices'][0]['message']['content'] ?? 'Maaf, saya tidak bisa menjawab saat ini.';

            return [
                'success' => true,
                'reply' => $reply,
                'model' => $this->model,
                'usage' => $data['usage'] ?? null,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Terjadi kesalahan saat menghubungi AI.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * Dapatkan konfigurasi untuk streaming request via cURL.
     */
    public function getStreamConfig(string $userMessage, array $history = []): array
    {
        $messages = $this->buildMessages($userMessage, $history);

        return [
            'url' => $this->apiUrl,
            'headers' => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
            'body' => json_encode([
                'model' => $this->model,
                'messages' => $messages,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
                'stream' => true,
            ]),
        ];
    }
}
