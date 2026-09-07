import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../domain/sport_option.dart';

class OnboardingSnapshot {
  final String step;
  final bool completed;
  final String? city;
  final List<SportSelection> selections;

  const OnboardingSnapshot({
    required this.step,
    required this.completed,
    required this.city,
    required this.selections,
  });

  factory OnboardingSnapshot.fromJson(Map<String, dynamic> json) {
    final area = json['default_area'] as Map<String, dynamic>? ?? const {};
    return OnboardingSnapshot(
      step: json['step'] as String? ?? 'profile',
      completed: json['completed'] as bool? ?? false,
      city: area['city'] as String?,
      selections: (json['sport_profiles'] as List? ?? const [])
          .map((item) => SportSelection.fromJson(item as Map<String, dynamic>))
          .toList(),
    );
  }
}

class OnboardingRepository {
  final Dio _dio;

  OnboardingRepository(this._dio);

  Future<List<SportOption>> sports() async {
    final response = await _dio.get<Map<String, dynamic>>('/sports');
    return (response.data!['data'] as List)
        .map((item) => SportOption.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<OnboardingSnapshot> status() async {
    final response = await _dio.get<Map<String, dynamic>>('/onboarding');
    return OnboardingSnapshot.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<void> saveSports(Map<String, String> levels) => _dio.put(
    '/users/me/sports',
    data: {
      'sports': levels.entries
          .map((entry) => {'sport_id': entry.key, 'level': entry.value})
          .toList(),
    },
  );

  Future<void> saveProgress({
    required String step,
    String? city,
    double? latitude,
    double? longitude,
  }) => _dio.put(
    '/onboarding',
    data: {
      'step': step,
      if (city != null) 'default_city': city,
      if (latitude != null) 'default_area_latitude': latitude,
      if (longitude != null) 'default_area_longitude': longitude,
      'timezone': DateTime.now().timeZoneName == 'ICT' ? 'Asia/Bangkok' : 'UTC',
    },
  );
}

final onboardingRepositoryProvider = Provider<OnboardingRepository>(
  (ref) => OnboardingRepository(ref.watch(dioProvider)),
);

final sportsProvider = FutureProvider<List<SportOption>>((ref) {
  return ref.watch(onboardingRepositoryProvider).sports();
});
