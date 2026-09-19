import 'package:flutter/foundation.dart';

String resolveHostForPlatform(String host, {TargetPlatform? platform}) {
  final normalizedHost = host.trim();
  final targetPlatform = platform ?? defaultTargetPlatform;

  final isLocalHost =
      normalizedHost == 'localhost' ||
      normalizedHost == '127.0.0.1' ||
      normalizedHost == '::1';

  if (isLocalHost && targetPlatform == TargetPlatform.android) {
    return '10.0.2.2';
  }

  return normalizedHost;
}

String buildApiBaseUrl(String rawBaseUrl, {TargetPlatform? platform}) {
  final uri = Uri.parse(rawBaseUrl);
  final resolvedHost = resolveHostForPlatform(uri.host, platform: platform);
  return uri.replace(host: resolvedHost).toString();
}

String get apiBaseUrl => buildApiBaseUrl(
  String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://127.0.0.1:8000/api/v1',
  ),
);

String get broadcastAuthUrl => buildApiBaseUrl(
  String.fromEnvironment(
    'BROADCAST_AUTH_URL',
    defaultValue: 'http://127.0.0.1:8000/api/broadcasting/auth',
  ),
);

String get reverbHost => resolveHostForPlatform(
  String.fromEnvironment('REVERB_HOST', defaultValue: '127.0.0.1'),
);

int get reverbPort => int.fromEnvironment('REVERB_PORT', defaultValue: 8080);

bool get reverbUseTls =>
    bool.fromEnvironment('REVERB_USE_TLS', defaultValue: false);

String get reverbAppKey =>
    String.fromEnvironment('REVERB_APP_KEY', defaultValue: 'squadup-local-key');
