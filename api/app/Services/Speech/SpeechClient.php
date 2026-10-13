<?php

namespace App\Services\Speech;

use App\Exceptions\ExternalServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Client for the self-hosted speech service (faster-whisper transcription + Kokoro text-to-speech).
 */
class SpeechClient
{
    /**
     * Transcribe an audio file with word-level timestamps and confidence.
     *
     * @return array{text: string, duration: float, words: list<array{word: string, start: float, end: float, probability: float}>}
     */
    public function transcribe(string $absolutePath): array
    {
        try {
            $response = $this->request()
                ->timeout(900)
                ->attach('audio', fopen($absolutePath, 'r'), basename($absolutePath))
                ->post('/transcribe')
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new ExternalServiceException('Transcription failed: '.$exception->getMessage(), previous: $exception);
        }

        return [
            'text' => trim((string) $response->json('text', '')),
            'duration' => (float) $response->json('duration', 0),
            'words' => array_values($response->json('words', [])),
        ];
    }

    /**
     * Synthesize speech and return MP3 bytes.
     *
     * @param  list<array{voice: string, text: string}>  $lines  One entry per utterance; a pause is inserted between lines.
     */
    public function synthesize(array $lines, float $speed = 1.0): string
    {
        try {
            $response = $this->request()
                ->timeout(1800)
                ->post('/tts', ['lines' => $lines, 'speed' => $speed, 'format' => 'mp3'])
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new ExternalServiceException('Speech synthesis failed: '.$exception->getMessage(), previous: $exception);
        }

        return $response->body();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('services.speech.url'), '/'))
            ->withHeaders(['X-Service-Token' => (string) config('services.speech.token')]);
    }
}
