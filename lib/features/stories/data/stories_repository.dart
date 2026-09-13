import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/application/provider_cache.dart';
import '../../../core/network/api_client.dart';
import '../domain/story_item.dart';

class StoriesRepository {
  StoriesRepository(this._dio);

  final Dio _dio;

  Future<List<StoryItem>> list() async {
    final response = await _dio.get<Map<String, dynamic>>('/stories');
    return (response.data!['data'] as List)
        .map((item) => StoryItem.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<void> create(
    PlatformFile file, {
    String? caption,
    String? activityId,
  }) async {
    final upload = file.bytes != null
        ? MultipartFile.fromBytes(file.bytes!, filename: file.name)
        : await MultipartFile.fromFile(file.path!, filename: file.name);
    await _dio.post(
      '/stories',
      data: FormData.fromMap({
        'media': upload,
        if (caption?.trim().isNotEmpty == true) 'caption': caption!.trim(),
        if (activityId != null) 'activity_id': activityId,
        'visibility': 'friends',
      }),
    );
  }

  Future<void> markViewed(String id) => _dio.post('/stories/$id/view');

  Future<void> delete(String id) => _dio.delete('/stories/$id');

  Future<void> report(String id, {String reason = 'spam'}) => _dio.post(
    '/reports',
    data: {'target_type': 'story', 'target_id': id, 'reason': reason},
  );
}

final storiesRepositoryProvider = Provider<StoriesRepository>(
  (ref) => StoriesRepository(ref.watch(dioProvider)),
);

final storiesProvider = FutureProvider.autoDispose<List<StoryItem>>((ref) {
  cacheFor(ref, const Duration(minutes: 1));
  return ref.watch(storiesRepositoryProvider).list();
});
