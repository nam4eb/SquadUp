<?php

namespace App\Services;

use App\Models\PushDevice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FcmService
{
    public function send(PushDevice $device, string $title, string $body, array $data): void
    {
        if (! config('services.fcm.enabled')) {
            return;
        }
        $project = config('services.fcm.project_id');
        $response = Http::withToken($this->accessToken())->timeout(10)
            ->post("https://fcm.googleapis.com/v1/projects/{$project}/messages:send", [
                'message' => [
                    'token' => $device->token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => collect($data)->map(fn ($value) => is_scalar($value) ? (string) $value : json_encode($value))->all(),
                ],
            ]);
        if ($response->status() === 404 || str_contains($response->body(), 'UNREGISTERED')) {
            $device->delete();

            return;
        }
        $response->throw();
    }

    private function accessToken(): string
    {
        return Cache::remember('fcm:access-token', now()->addMinutes(50), function (): string {
            $email = config('services.fcm.client_email');
            $privateKey = str_replace('\\n', "\n", (string) config('services.fcm.private_key'));
            $now = time();
            $header = $this->encode(['alg' => 'RS256', 'typ' => 'JWT']);
            $claims = $this->encode([
                'iss' => $email,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]);
            if (! openssl_sign("{$header}.{$claims}", $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Unable to sign the FCM service-account assertion.');
            }
            $assertion = "{$header}.{$claims}.".$this->encodeRaw($signature);

            return Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ])->throw()->json('access_token');
        });
    }

    private function encode(array $value): string
    {
        return $this->encodeRaw(json_encode($value, JSON_THROW_ON_ERROR));
    }

    private function encodeRaw(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
