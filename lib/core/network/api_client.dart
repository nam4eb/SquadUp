import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

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
        final token = await tokenStore.read();
        if (token != null && token.isNotEmpty) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
      onError: (error, handler) async {
        if (error.response?.statusCode == 401) {
          await tokenStore.delete();
        }
        handler.next(error);
      },
    ),
  );

  return dio;
});
