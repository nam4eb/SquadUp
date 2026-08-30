import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/activity_taxonomy_repository.dart';
import '../domain/activity_taxonomy.dart';

class ActivityTaxonomyController extends AsyncNotifier<List<ActivityCategory>> {
  @override
  Future<List<ActivityCategory>> build() =>
      ref.read(activityTaxonomyRepositoryProvider).categories();

  Future<void> refresh() async {
    state = await AsyncValue.guard(
      () => ref.read(activityTaxonomyRepositoryProvider).categories(),
    );
  }
}

final activityTaxonomyControllerProvider =
    AsyncNotifierProvider<ActivityTaxonomyController, List<ActivityCategory>>(
      ActivityTaxonomyController.new,
    );
