<?php

namespace App\Services\Ai;

use App\Exceptions\ExternalServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Minimal client for DeepSeek's OpenAI-compatible chat completions API, used in JSON mode.
 */
class DeepSeekClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.deepseek.key'));
    }

    /**
     * Send a prompt and decode the model's JSON reply.
     *
     * @return array<string, mixed>
     */
    public function json(string $system, string $user, float $temperature = 0.3, int $maxTokens = 4000): array
    {
        if (! $this->isConfigured()) {
            throw new ExternalServiceException('DeepSeek API key is not configured.');
        }

        try {
            $response = Http::withToken(config('services.deepseek.key'))
                ->baseUrl(rtrim(config('services.deepseek.base_url'), '/'))
                ->timeout(180)
                ->retry(2, 2000, fn ($exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()))
                ->post('/chat/completions', [
                    'model' => config('services.deepseek.model'),
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                ])
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new ExternalServiceException('DeepSeek request failed: '.$exception->getMessage(), previous: $exception);
        }

        $content = (string) $response->json('choices.0.message.content', '');
        $decoded = json_decode($this->stripCodeFence($content), true);

        if (! is_array($decoded)) {
            throw new ExternalServiceException('DeepSeek returned invalid JSON.');
        }

        return $decoded;
    }

    private function stripCodeFence(string $content): string
    {
        $content = trim($content);

        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $content) ?? $content;
        }

        return $content;
    }
}
