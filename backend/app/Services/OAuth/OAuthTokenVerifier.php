<?php

namespace App\Services\OAuth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class OAuthTokenVerifier
{
    public function verify(string $provider, string $token): ProviderIdentity
    {
        return match ($provider) {
            'google' => $this->google($token),
            'facebook' => $this->facebook($token),
            default => throw ValidationException::withMessages(['provider' => ['Unsupported OAuth provider.']]),
        };
    }

    private function google(string $token): ProviderIdentity
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            $this->invalid();
        }
        $header = $this->decode($parts[0]);
        $claims = $this->decode($parts[1]);
        $kid = $header['kid'] ?? null;
        $certificates = Cache::remember('oauth:google:certificates', now()->addMinutes(50), function (): array {
            return Http::timeout(5)->acceptJson()
                ->get('https://www.googleapis.com/oauth2/v1/certs')->throw()->json();
        });
        $certificate = $kid ? ($certificates[$kid] ?? null) : null;
        $signature = $this->base64UrlDecode($parts[2]);
        if (! $certificate || ! is_string($signature)
            || openssl_verify("{$parts[0]}.{$parts[1]}", $signature, $certificate, OPENSSL_ALGO_SHA256) !== 1) {
            $this->invalid();
        }
        $audiences = array_filter(array_map('trim', explode(',', (string) config('services.google.client_ids'))));
        if (! in_array($claims['aud'] ?? null, $audiences, true)
            || ! in_array($claims['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
            || (int) ($claims['exp'] ?? 0) <= time() || empty($claims['sub'])) {
            $this->invalid();
        }

        return new ProviderIdentity(
            'google', (string) $claims['sub'], (string) ($claims['name'] ?? $claims['email'] ?? 'Google user'),
            isset($claims['email']) ? mb_strtolower($claims['email']) : null,
            filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOL),
            $claims['picture'] ?? null,
        );
    }

    private function facebook(string $token): ProviderIdentity
    {
        $version = config('services.facebook.graph_version');
        $appId = config('services.facebook.client_id');
        $secret = config('services.facebook.client_secret');
        $debug = Http::timeout(5)->get("https://graph.facebook.com/{$version}/debug_token", [
            'input_token' => $token,
            'access_token' => "{$appId}|{$secret}",
        ])->throw()->json('data');
        if (! ($debug['is_valid'] ?? false) || ($debug['app_id'] ?? null) !== $appId || empty($debug['user_id'])) {
            $this->invalid();
        }
        $profile = Http::timeout(5)->get("https://graph.facebook.com/{$version}/me", [
            'fields' => 'id,name,email,picture', 'access_token' => $token,
        ])->throw()->json();
        if (($profile['id'] ?? null) !== (string) $debug['user_id']) {
            $this->invalid();
        }

        return new ProviderIdentity(
            'facebook', (string) $profile['id'], (string) ($profile['name'] ?? 'Facebook user'),
            isset($profile['email']) ? mb_strtolower($profile['email']) : null,
            isset($profile['email']), $profile['picture']['data']['url'] ?? null,
        );
    }

    private function decode(string $value): array
    {
        $decoded = $this->base64UrlDecode($value);
        if (! is_string($decoded)) {
            $this->invalid();
        }

        return json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);
    }

    private function base64UrlDecode(string $value): string|false
    {
        return base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['token' => ['The OAuth token is invalid or expired.']]);
    }
}
