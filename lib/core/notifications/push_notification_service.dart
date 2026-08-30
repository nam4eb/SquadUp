import 'dart:async';

import 'package:dio/dio.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../network/api_client.dart';

class PushNotificationService {
  PushNotificationService(this._dio);

  final Dio _dio;
  StreamSubscription<String>? _refreshSubscription;
  bool _started = false;

  Future<void> start() async {
    if (_started) return;
    try {
      if (Firebase.apps.isEmpty) {
        await Firebase.initializeApp();
      }
      final messaging = FirebaseMessaging.instance;
      final permission = await messaging.requestPermission();
      if (permission.authorizationStatus == AuthorizationStatus.denied) return;
      final token = await messaging.getToken();
      if (token != null) await _register(token);
      _refreshSubscription = messaging.onTokenRefresh.listen(_register);
      _started = true;
    } catch (_) {
      // Firebase platform credentials are deployment configuration. The rest
      // of the app remains usable when they have not been installed yet.
    }
  }

  Future<void> _register(String token) async {
    await _dio.post(
      '/push-devices',
      data: {
        'token': token,
        'platform': kIsWeb
            ? 'web'
            : switch (defaultTargetPlatform) {
                TargetPlatform.iOS || TargetPlatform.macOS => 'ios',
                _ => 'android',
              },
        'device_name': 'squadup-flutter',
      },
    );
  }

  void dispose() {
    _refreshSubscription?.cancel();
  }
}

final pushNotificationServiceProvider = Provider<PushNotificationService>((
  ref,
) {
  final service = PushNotificationService(ref.watch(dioProvider));
  ref.onDispose(service.dispose);
  return service;
});
