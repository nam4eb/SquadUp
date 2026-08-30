import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:file_picker/file_picker.dart';

import '../../../core/network/api_client.dart';
import '../../../core/application/provider_cache.dart';
import '../domain/clan_item.dart';
import '../../activities/domain/activity_item.dart';

class ClansRepository {
  final Dio _dio;
  ClansRepository(this._dio);

  Future<List<ClanItem>> list() async {
    final response = await _dio.get<Map<String, dynamic>>('/clans');
    return (response.data!['data'] as List)
        .map((item) => ClanItem.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<void> create({required String name, required String slug}) =>
      _dio.post(
        '/clans',
        data: {
          'name': name,
          'slug': slug,
          'visibility': 'public',
          'join_policy': 'approval',
        },
      );

  Future<void> join(String id) => _dio.post('/clans/$id/join');
  Future<void> leave(String id) => _dio.post('/clans/$id/leave');

  Future<ClanItem> detail(String id) async {
    final response = await _dio.get<Map<String, dynamic>>('/clans/$id');
    return ClanItem.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<List<ClanMemberItem>> members(String id) async {
    final response = await _dio.get<Map<String, dynamic>>('/clans/$id/members');
    return (response.data!['data'] as List)
        .map((item) => ClanMemberItem.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<List<ClanRoleItem>> roles(String id) async {
    final response = await _dio.get<Map<String, dynamic>>('/clans/$id/roles');
    return (response.data!['data'] as List)
        .map((item) => ClanRoleItem.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<void> review(String clanId, String memberId, bool accept) =>
      _dio.patch(
        '/clans/$clanId/members/$memberId/review',
        data: {'accept': accept},
      );
  Future<void> remove(String clanId, String memberId, bool ban) =>
      _dio.delete('/clans/$clanId/members/$memberId', data: {'ban': ban});
  Future<void> assignRole(String clanId, String memberId, String roleId) =>
      _dio.patch(
        '/clans/$clanId/members/$memberId/role',
        data: {'role_id': roleId},
      );
  Future<void> transfer(String clanId, String userId) =>
      _dio.post('/clans/$clanId/transfer-ownership', data: {'user_id': userId});
  Future<void> createRole(
    String clanId,
    String name,
    String slug,
    List<String> permissions,
  ) => _dio.post(
    '/clans/$clanId/roles',
    data: {'name': name, 'slug': slug, 'permissions': permissions},
  );

  Future<List<ActivityItem>> activities(String clanId) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/clans/$clanId/activities',
    );
    return (response.data!['data'] as List)
        .map((item) => ActivityItem.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<ClanStatistics> statistics(String clanId) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/clans/$clanId/statistics',
    );
    return ClanStatistics.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<List<ClanLeaderboardEntry>> leaderboard(String clanId) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/clans/$clanId/leaderboard',
    );
    return (response.data!['data'] as List)
        .map(
          (item) => ClanLeaderboardEntry.fromJson(item as Map<String, dynamic>),
        )
        .toList();
  }

  Future<List<ClanMessageItem>> messages(String clanId) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/clans/$clanId/messages',
    );
    return (response.data!['data'] as List)
        .map((item) => ClanMessageItem.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<void> sendMessage(String clanId, String body) =>
      _dio.post('/clans/$clanId/messages', data: {'body': body});

  Future<void> sendMediaMessage(
    String clanId,
    PlatformFile file, {
    String? body,
  }) async {
    final upload = file.bytes != null
        ? MultipartFile.fromBytes(file.bytes!, filename: file.name)
        : await MultipartFile.fromFile(file.path!, filename: file.name);
    await _dio.post(
      '/clans/$clanId/messages',
      data: FormData.fromMap({
        'file': upload,
        if (body?.trim().isNotEmpty == true) 'body': body!.trim(),
      }),
    );
  }

  Future<void> deleteMessage(String clanId, String messageId) =>
      _dio.delete('/clans/$clanId/messages/$messageId');

  Future<List<ClanAnnouncementItem>> announcements(String clanId) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/clans/$clanId/announcements',
    );
    return (response.data!['data'] as List)
        .map(
          (item) => ClanAnnouncementItem.fromJson(item as Map<String, dynamic>),
        )
        .toList();
  }

  Future<void> createAnnouncement(String clanId, String title, String body) =>
      _dio.post(
        '/clans/$clanId/announcements',
        data: {'title': title, 'body': body, 'is_pinned': false},
      );

  Future<void> deleteAnnouncement(String clanId, String id) =>
      _dio.delete('/clans/$clanId/announcements/$id');
}

final clansRepositoryProvider = Provider<ClansRepository>(
  (ref) => ClansRepository(ref.watch(dioProvider)),
);

final clanDetailProvider = FutureProvider.autoDispose.family<ClanItem, String>((
  ref,
  id,
) {
  cacheFor(ref, const Duration(minutes: 2));
  return ref.watch(clansRepositoryProvider).detail(id);
});
final clanMembersProvider = FutureProvider.autoDispose
    .family<List<ClanMemberItem>, String>((ref, id) {
      cacheFor(ref, const Duration(minutes: 2));
      return ref.watch(clansRepositoryProvider).members(id);
    });
final clanRolesProvider = FutureProvider.autoDispose
    .family<List<ClanRoleItem>, String>((ref, id) {
      cacheFor(ref, const Duration(minutes: 5));
      return ref.watch(clansRepositoryProvider).roles(id);
    });

final clanActivitiesProvider = FutureProvider.autoDispose
    .family<List<ActivityItem>, String>((ref, id) {
      cacheFor(ref, const Duration(minutes: 2));
      return ref.watch(clansRepositoryProvider).activities(id);
    });

final clanStatisticsProvider = FutureProvider.autoDispose
    .family<ClanStatistics, String>((ref, id) {
      cacheFor(ref, const Duration(minutes: 2));
      return ref.watch(clansRepositoryProvider).statistics(id);
    });

final clanLeaderboardProvider = FutureProvider.autoDispose
    .family<List<ClanLeaderboardEntry>, String>((ref, id) {
      cacheFor(ref, const Duration(minutes: 2));
      return ref.watch(clansRepositoryProvider).leaderboard(id);
    });

final clanMessagesProvider = FutureProvider.autoDispose
    .family<List<ClanMessageItem>, String>((ref, id) {
      cacheFor(ref, const Duration(minutes: 1));
      return ref.watch(clansRepositoryProvider).messages(id);
    });

final clanAnnouncementsProvider = FutureProvider.autoDispose
    .family<List<ClanAnnouncementItem>, String>((ref, id) {
      cacheFor(ref, const Duration(minutes: 2));
      return ref.watch(clansRepositoryProvider).announcements(id);
    });
