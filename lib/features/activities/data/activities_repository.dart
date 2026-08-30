import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../domain/activity_item.dart';
import '../../chat/domain/chat_models.dart';
import '../../../core/application/provider_cache.dart';

class ActivitiesRepository {
  final Dio _dio;
  ActivitiesRepository(this._dio);

  Future<List<ActivityItem>> list({
    String? query,
    String? categoryId,
    double? latitude,
    double? longitude,
    double? radiusKm,
    int page = 1,
  }) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/activities',
      queryParameters: {
        if (query?.trim().isNotEmpty == true) 'query': query!.trim(),
        if (categoryId != null) 'category_id': categoryId,
        if (latitude != null) 'near_lat': latitude,
        if (longitude != null) 'near_lng': longitude,
        if (radiusKm != null) 'radius_km': radiusKm,
        'page': page,
      },
    );
    return (response.data!['data'] as List)
        .map((item) => ActivityItem.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<ActivityPage> nearby({
    required double latitude,
    required double longitude,
    required double radiusKm,
    String? query,
    String? categoryId,
    int page = 1,
  }) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/activities',
      queryParameters: {
        'near_lat': latitude,
        'near_lng': longitude,
        'radius_km': radiusKm,
        'per_page': 20,
        'page': page,
        if (query?.trim().isNotEmpty == true) 'query': query!.trim(),
        if (categoryId != null) 'category_id': categoryId,
      },
    );
    final meta = response.data!['meta'] as Map<String, dynamic>;
    return ActivityPage(
      (response.data!['data'] as List)
          .map((item) => ActivityItem.fromJson(item as Map<String, dynamic>))
          .toList(),
      meta['current_page'] as int,
      meta['last_page'] as int,
    );
  }

  Future<List<ActivityItem>> history(String scope) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/users/me/activity-history',
      queryParameters: {'scope': scope},
    );
    return (response.data!['data'] as List)
        .map((item) => ActivityItem.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<ActivityItem> detail(String id) async {
    final response = await _dio.get<Map<String, dynamic>>('/activities/$id');
    return ActivityItem.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<ActivityItem> create(Map<String, dynamic> data) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/activities',
      data: data,
    );
    return ActivityItem.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<String> join(String id, {String? password}) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/activities/$id/join',
      data: password == null ? null : {'password': password},
    );
    return (response.data!['data'] as Map<String, dynamic>)['status'] as String;
  }

  Future<void> leave(String id) => _dio.post('/activities/$id/leave');

  Future<List<ActivityParticipantItem>> participants(String id) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/activities/$id/participants',
    );
    return (response.data!['data'] as List)
        .map(
          (item) =>
              ActivityParticipantItem.fromJson(item as Map<String, dynamic>),
        )
        .toList();
  }

  Future<void> accept(String activityId, String participantId) =>
      _dio.patch('/activities/$activityId/participants/$participantId/accept');

  Future<void> reject(String activityId, String participantId) =>
      _dio.patch('/activities/$activityId/participants/$participantId/reject');

  Future<ActivityItem> transition(String id, String status) async {
    final response = await _dio.patch<Map<String, dynamic>>(
      '/activities/$id/status',
      data: {'status': status},
    );
    return ActivityItem.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<String> createInvitationCode(String id, {int? maxUses}) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/activities/$id/invitation-secrets',
      data: {'type': 'code', if (maxUses != null) 'max_uses': maxUses},
    );
    return response.data!['secret'] as String;
  }

  Future<String> createInvitationLink(String id, {int? maxUses}) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/activities/$id/invitation-secrets',
      data: {'type': 'link', if (maxUses != null) 'max_uses': maxUses},
    );
    return response.data!['secret'] as String;
  }

  Future<String> redeemCode(String code, {String? password}) async {
    return redeemInvitation('code', code, password: password);
  }

  Future<String> redeemInvitation(
    String type,
    String secret, {
    String? password,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/activity-invitations/redeem',
      data: {
        'type': type,
        'secret': secret,
        if (password?.isNotEmpty == true) 'password': password,
      },
    );
    return (response.data!['data'] as Map<String, dynamic>)['activity_id']
        as String;
  }

  Future<ActivityItem> update(String id, Map<String, dynamic> data) async {
    final response = await _dio.patch<Map<String, dynamic>>(
      '/activities/$id',
      data: data,
    );
    return ActivityItem.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<void> invite(String id, String userId) =>
      _dio.post('/activities/$id/invitations', data: {'user_id': userId});

  Future<void> removeParticipant(
    String id,
    String participantId, {
    bool ban = false,
  }) => _dio.delete(
    '/activities/$id/participants/$participantId',
    data: {'ban': ban},
  );

  Future<void> transferOwnership(String id, String userId) => _dio.post(
    '/activities/$id/transfer-ownership',
    data: {'user_id': userId},
  );

  Future<void> markAttendance(
    String activityId,
    String participantId,
    String status,
  ) => _dio.patch(
    '/activities/$activityId/participants/$participantId/attendance',
    data: {'status': status},
  );

  Future<void> setLiked(String id, bool liked) => liked
      ? _dio.post('/activities/$id/like')
      : _dio.delete('/activities/$id/like');

  Future<List<ActivityCommentItem>> comments(String id) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/activities/$id/comments',
    );
    return (response.data!['data'] as List)
        .map(
          (item) => ActivityCommentItem.fromJson(item as Map<String, dynamic>),
        )
        .toList();
  }

  Future<void> comment(String id, String body) =>
      _dio.post('/activities/$id/comments', data: {'body': body});

  Future<void> deleteComment(String activityId, String commentId) =>
      _dio.delete('/activities/$activityId/comments/$commentId');

  Future<void> hide(String id) => _dio.post('/activities/$id/hide');

  Future<void> report(String id, String reason, String? details) => _dio.post(
    '/reports',
    data: {
      'target_type': 'activity',
      'target_id': id,
      'reason': reason,
      if (details?.trim().isNotEmpty == true) 'details': details!.trim(),
    },
  );

  Future<ConversationItem> eventChat(String id, {bool create = false}) async {
    final response = create
        ? await _dio.post<Map<String, dynamic>>('/activities/$id/chat')
        : await _dio.get<Map<String, dynamic>>('/activities/$id/chat');
    return ConversationItem.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }
}

final activitiesRepositoryProvider = Provider<ActivitiesRepository>(
  (ref) => ActivitiesRepository(ref.watch(dioProvider)),
);

class ActivityPage {
  const ActivityPage(this.items, this.currentPage, this.lastPage);
  final List<ActivityItem> items;
  final int currentPage;
  final int lastPage;
  bool get hasMore => currentPage < lastPage;
}

final activityFeedProvider = FutureProvider.autoDispose<List<ActivityItem>>((
  ref,
) {
  cacheFor(ref, const Duration(minutes: 2));
  return ref.watch(activitiesRepositoryProvider).list();
});

final activityDetailProvider = FutureProvider.autoDispose
    .family<ActivityItem, String>((ref, id) {
      cacheFor(ref, const Duration(minutes: 2));
      return ref.watch(activitiesRepositoryProvider).detail(id);
    });

final activityParticipantsProvider = FutureProvider.autoDispose
    .family<List<ActivityParticipantItem>, String>((ref, id) {
      cacheFor(ref, const Duration(minutes: 1));
      return ref.watch(activitiesRepositoryProvider).participants(id);
    });
