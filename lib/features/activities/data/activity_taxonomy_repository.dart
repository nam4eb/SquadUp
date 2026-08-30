import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../domain/activity_taxonomy.dart';

class ActivityTaxonomyRepository {
  final Dio _dio;
  ActivityTaxonomyRepository(this._dio);

  Future<List<ActivityCategory>> categories() async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/activity-categories',
    );
    return (response.data!['data'] as List)
        .map((item) => ActivityCategory.fromJson(item as Map<String, dynamic>))
        .toList();
  }
}

final activityTaxonomyRepositoryProvider = Provider<ActivityTaxonomyRepository>(
  (ref) => ActivityTaxonomyRepository(ref.watch(dioProvider)),
);
