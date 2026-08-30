import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:pusher_reverb_flutter/pusher_reverb_flutter.dart';

import '../network/api_client.dart';

const _reverbHost = String.fromEnvironment(
  'REVERB_HOST',
  defaultValue: '127.0.0.1',
);
const _reverbPort = int.fromEnvironment('REVERB_PORT', defaultValue: 8080);
const _reverbAppKey = String.fromEnvironment(
  'REVERB_APP_KEY',
  defaultValue: 'squadup-local-key',
);
const _reverbUseTls = bool.fromEnvironment(
  'REVERB_USE_TLS',
  defaultValue: false,
);
const _broadcastAuthUrl = String.fromEnvironment(
  'BROADCAST_AUTH_URL',
  defaultValue: 'http://127.0.0.1:8000/api/broadcasting/auth',
);

typedef RealtimeEventHandler = void Function(String event, dynamic data);

class RealtimeService {
  RealtimeService(this._tokens, this._dio);

  final AuthTokenStore _tokens;
  final Dio _dio;
  ReverbClient? _client;
  Future<void>? _connecting;
  Timer? _presenceTimer;
  final Map<String, PrivateChannel> _channels = {};

  Future<void> connect() {
    if (_client != null) return Future.value();
    return _connecting ??= _connect().whenComplete(() => _connecting = null);
  }

  Future<void> _connect() async {
    final token = await _tokens.read();
    if (token == null || token.isEmpty) return;

    final client = ReverbClient.instance(
      host: _reverbHost,
      port: _reverbPort,
      appKey: _reverbAppKey,
      useTLS: _reverbUseTls,
      authEndpoint: _broadcastAuthUrl,
      authorizer: (_, _) async => {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
      },
    );
    try {
      await client.connect();
      _client = client;
    } catch (_) {
      _client = null;
      rethrow;
    }
  }

  Future<void> subscribeConversation(
    String conversationId,
    RealtimeEventHandler onEvent,
  ) async {
    try {
      await connect();
      final client = _client;
      if (client == null) return;
      final channelName = 'private-conversation.$conversationId';
      client.unsubscribeFromChannel(channelName);
      final channel = client.subscribeToPrivateChannel(channelName);
      for (final event in const [
        'message.created',
        'message.updated',
        'message.deleted',
        'message.reaction',
        'message.read',
        'conversation.read',
        'conversation.typing',
      ]) {
        channel.bind(event, onEvent);
      }
      _channels[conversationId] = channel;
    } catch (_) {
      // REST remains the fallback if the realtime server is unavailable.
    }
  }

  void unsubscribeConversation(String conversationId) {
    final channel = _channels.remove(conversationId);
    if (channel != null) {
      _client?.unsubscribeFromChannel(channel.name);
    }
  }

  Future<void> startPresence() async {
    await heartbeat('online');
    _presenceTimer ??= Timer.periodic(
      const Duration(seconds: 60),
      (_) => heartbeat('online'),
    );
  }

  Future<void> heartbeat(String status) async {
    try {
      await _dio.post('/presence/heartbeat', data: {'status': status});
    } catch (_) {
      // Presence is best-effort and must not block the rest of the app.
    }
  }

  Future<void> stopPresence() async {
    _presenceTimer?.cancel();
    _presenceTimer = null;
    try {
      await _dio.delete('/presence');
    } catch (_) {}
  }
}

final realtimeServiceProvider = Provider<RealtimeService>((ref) {
  return RealtimeService(
    ref.watch(authTokenStoreProvider),
    ref.watch(dioProvider),
  );
});
