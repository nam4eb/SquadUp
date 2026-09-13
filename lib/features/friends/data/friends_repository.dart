import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../domain/social_user.dart';

class FriendsRepository {
  final Dio _dio;

  FriendsRepository(this._dio);

  Future<List<SocialUser>> search(
    String query, {
    String? sportId,
    int? skillMin,
    int? skillMax,
    String? city,
  }) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/users',
      queryParameters: {
        'query': query,
        if (sportId != null) 'sport_id': sportId,
        if (skillMin != null) 'skill_min': skillMin,
        if (skillMax != null) 'skill_max': skillMax,
        if (city?.trim().isNotEmpty == true) 'city': city!.trim(),
      },
    );
    return _users(response.data!['data'] as List);
  }

  Future<List<SocialUser>> friends() async {
    final response = await _dio.get<Map<String, dynamic>>('/friends');
    return (response.data!['data'] as List)
        .map(
          (item) => SocialUser.fromJson(
            (item as Map<String, dynamic>)['friend'] as Map<String, dynamic>,
          ),
        )
        .toList();
  }

  Future<List<FriendRequestItem>> incoming() async {
    final response = await _dio.get<Map<String, dynamic>>('/friend-requests');
    return (response.data!['data'] as List)
        .map((item) => FriendRequestItem.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<List<SocialUser>> blocked() async {
    final response = await _dio.get<Map<String, dynamic>>('/blocks');
    return (response.data!['data'] as List)
        .map(
          (item) => SocialUser.fromJson(
            (item as Map<String, dynamic>)['user'] as Map<String, dynamic>,
          ),
        )
        .toList();
  }

  Future<void> send(String userId) =>
      _dio.post('/friend-requests', data: {'receiver_id': userId});
  Future<void> accept(String requestId) =>
      _dio.patch('/friend-requests/$requestId/accept');
  Future<void> reject(String requestId) =>
      _dio.patch('/friend-requests/$requestId/reject');
  Future<void> remove(String userId) => _dio.delete('/friends/$userId');
  Future<void> block(String userId) => _dio.post('/blocks/$userId');
  Future<void> unblock(String userId) => _dio.delete('/blocks/$userId');

  List<SocialUser> _users(List<dynamic> data) => data
      .map((item) => SocialUser.fromJson(item as Map<String, dynamic>))
      .toList();
}

final friendsRepositoryProvider = Provider<FriendsRepository>(
  (ref) => FriendsRepository(ref.watch(dioProvider)),
);
