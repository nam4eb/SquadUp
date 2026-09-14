import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:file_picker/file_picker.dart';

import '../../../core/network/api_client.dart';
import '../domain/auth_user.dart';

class AuthFailure implements Exception {
  final String message;

  const AuthFailure(this.message);

  @override
  String toString() => message;
}

class AuthRepository {
  final Dio _dio;
  final Ref _ref;

  AuthRepository(this._dio, this._ref);

  Future<AuthUser> login({required String email, required String password}) {
    return _authenticate('/auth/login', {
      'email': email,
      'password': password,
      'device_name': 'squadup-flutter',
    });
  }

  Future<AuthUser> register({
    required String username,
    required String displayName,
    required String email,
    required String password,
  }) {
    return _authenticate('/auth/register', {
      'username': username,
      'display_name': displayName,
      'email': email,
      'password': password,
      'password_confirmation': password,
      'device_name': 'squadup-flutter',
    });
  }

  Future<AuthUser> me() async {
    final response = await _dio.get<Map<String, dynamic>>('/auth/me');
    return AuthUser.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<AuthUser> updateProfile({
    required String displayName,
    String? bio,
    String? location,
    PlatformFile? avatar,
    PlatformFile? cover,
  }) async {
    final data = <String, dynamic>{
      '_method': 'PATCH',
      'display_name': displayName,
      'bio': bio,
      'location': location,
    };
    for (final entry in {'avatar': avatar, 'cover': cover}.entries) {
      final file = entry.value;
      if (file == null) continue;
      data[entry.key] = file.bytes != null
          ? MultipartFile.fromBytes(file.bytes!, filename: file.name)
          : await MultipartFile.fromFile(file.path!, filename: file.name);
    }
    final response = await _dio.post<Map<String, dynamic>>(
      '/users/me',
      data: FormData.fromMap(data),
    );
    return AuthUser.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<List<DeviceSession>> sessions() async {
    final response = await _dio.get<Map<String, dynamic>>('/auth/sessions');
    return (response.data!['data'] as List)
        .map((item) => DeviceSession.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<void> revokeSession(String id) => _dio.delete('/auth/sessions/$id');

  Future<void> logoutAll() async {
    await _dio.post('/auth/logout-all');
    await _ref.read(authTokenStoreProvider).delete();
  }

  Future<void> deactivate(String password) async {
    await _dio.delete('/users/me', data: {'password': password});
    await _ref.read(authTokenStoreProvider).delete();
  }

  Future<AuthUser> _authenticate(
    String path,
    Map<String, dynamic> payload,
  ) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        path,
        data: payload,
      );
      final data = response.data!['data'] as Map<String, dynamic>;
      await _ref.read(authTokenStoreProvider).write(data['token'] as String);
      return AuthUser.fromJson(data['user'] as Map<String, dynamic>);
    } on DioException catch (error) {
      throw AuthFailure(_messageFrom(error));
    }
  }

  String _messageFrom(DioException error) {
    final body = error.response?.data;
    if (body is Map<String, dynamic>) {
      final errors = body['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final first = errors.values.first;
        if (first is List && first.isNotEmpty) return first.first.toString();
      }
      if (body['message'] is String) return body['message'] as String;
    }
    if (error.type == DioExceptionType.connectionError ||
        error.type == DioExceptionType.connectionTimeout) {
      return 'Cannot connect to SquadUP. Check that the API is running.';
    }
    return 'Authentication failed. Please try again.';
  }
}

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(ref.watch(dioProvider), ref);
});

final currentUserProvider = FutureProvider<AuthUser>((ref) {
  return ref.watch(authRepositoryProvider).me();
});
