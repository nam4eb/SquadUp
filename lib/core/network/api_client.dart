import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../performance/performance_telemetry.dart';

const apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://127.0.0.1:8000/api/v1',
);

const authTokenKey = 'squadup_auth_token';

final secureStorageProvider = Provider<FlutterSecureStorage>((ref) {
  return const FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );
});

class AuthTokenStore {
  AuthTokenStore(this._storage);

  final FlutterSecureStorage _storage;
  String? _token;
  Future<String?>? _loading;
  bool _loaded = false;

  Future<String?> read() {
    if (_loaded) return Future.value(_token);
    return _loading ??= _storage.read(key: authTokenKey).then((value) {
      _token = value;
      _loaded = true;
      _loading = null;
      return value;
    });
  }

  Future<void> write(String token) async {
    _token = token;
    _loaded = true;
    await _storage.write(key: authTokenKey, value: token);
  }

  Future<void> delete() async {
    _token = null;
    _loaded = true;
    await _storage.delete(key: authTokenKey);
  }
}

final authTokenStoreProvider = Provider<AuthTokenStore>(
  (ref) => AuthTokenStore(ref.watch(secureStorageProvider)),
);

final dioProvider = Provider<Dio>((ref) {
  final tokenStore = ref.watch(authTokenStoreProvider);
  final telemetry = PerformanceTelemetry();
  final dio = Dio(
    BaseOptions(
      baseUrl: apiBaseUrl,
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 15),
      headers: const {'Accept': 'application/json'},
    ),
  );

  dio.interceptors.add(
    InterceptorsWrapper(
      onRequest: (options, handler) async {
        options.extra['request_started_at'] = DateTime.now();
        final token = await tokenStore.read();
        if (token != null && token.isNotEmpty) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
      onResponse: (response, handler) {
        final started =
            response.requestOptions.extra['request_started_at'] as DateTime?;
        if (started != null) {
          telemetry.record(
            method: response.requestOptions.method,
            path: response.requestOptions.path,
            durationMs: DateTime.now().difference(started).inMilliseconds,
            statusCode: response.statusCode,
            cacheHit: response.headers.value('X-Cache')?.toUpperCase() == 'HIT',
          );
        }
        handler.next(response);
      },
      onError: (error, handler) async {
        final started =
            error.requestOptions.extra['request_started_at'] as DateTime?;
        if (started != null) {
          telemetry.record(
            method: error.requestOptions.method,
            path: error.requestOptions.path,
            durationMs: DateTime.now().difference(started).inMilliseconds,
            statusCode: error.response?.statusCode,
            cacheHit: false,
          );
        }
        if (error.response?.statusCode == 401) {
          await tokenStore.delete();
        }
        handler.next(error);
      },
    ),
  );

  return dio;
});
