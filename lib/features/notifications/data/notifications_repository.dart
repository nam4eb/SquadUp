import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../domain/app_notification.dart';
import '../domain/notification_preference.dart';

class NotificationsRepository {
  final Dio _dio;
  NotificationsRepository(this._dio);

  Future<List<AppNotification>> list({bool unreadOnly = false}) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/notifications',
      queryParameters: unreadOnly ? {'unread': 1} : null,
    );
    return (response.data!['data'] as List)
        .map((item) => AppNotification.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<void> markRead(String id) => _dio.patch('/notifications/$id/read');

  Future<void> markAllRead() => _dio.post('/notifications/read-all');

  Future<List<NotificationPreference>> preferences() async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/notification-preferences',
    );
    return (response.data!['data'] as List)
        .map(
          (item) =>
              NotificationPreference.fromJson(item as Map<String, dynamic>),
        )
        .toList();
  }

  Future<List<NotificationPreference>> updatePreferences(
    List<NotificationPreference> preferences,
  ) async {
    final response = await _dio.put<Map<String, dynamic>>(
      '/notification-preferences',
      data: {'preferences': preferences.map((item) => item.toJson()).toList()},
    );
    return (response.data!['data'] as List)
        .map(
          (item) =>
              NotificationPreference.fromJson(item as Map<String, dynamic>),
        )
        .toList();
  }
}

final notificationsRepositoryProvider = Provider<NotificationsRepository>(
  (ref) => NotificationsRepository(ref.watch(dioProvider)),
);

final notificationPreferencesProvider =
    FutureProvider.autoDispose<List<NotificationPreference>>(
      (ref) => ref.watch(notificationsRepositoryProvider).preferences(),
    );
