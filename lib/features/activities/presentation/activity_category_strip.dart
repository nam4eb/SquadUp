import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../routes/app_routes.dart';

import '../application/activity_taxonomy_controller.dart';

class ActivityCategoryStrip extends ConsumerWidget {
  const ActivityCategoryStrip({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final taxonomy = ref.watch(activityTaxonomyControllerProvider);
    return taxonomy.when(
      loading: () => const SizedBox(
        height: 48,
        child: Center(child: LinearProgressIndicator()),
      ),
      error: (_, _) => SizedBox(
        height: 48,
        child: Align(
          alignment: Alignment.centerLeft,
          child: TextButton.icon(
            onPressed: () =>
                ref.read(activityTaxonomyControllerProvider.notifier).refresh(),
            icon: const Icon(Icons.refresh),
            label: const Text('Reload categories'),
          ),
        ),
      ),
      data: (categories) => SizedBox(
        height: 48,
        child: ListView.separated(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          scrollDirection: Axis.horizontal,
          itemCount: categories.length,
          separatorBuilder: (_, _) => const SizedBox(width: 8),
          itemBuilder: (context, index) {
            final category = categories[index];
            return ActionChip(
              avatar: CircleAvatar(
                backgroundColor: _parseColor(category.color),
                radius: 7,
              ),
              label: Text(category.name),
              tooltip: '${category.topics.length} topics',
              onPressed: () => context.push(
                Uri(
                  path: AppRoutes.explore,
                  queryParameters: {'category_id': category.id},
                ).toString(),
              ),
            );
          },
        ),
      ),
    );
  }

  Color _parseColor(String? value) {
    if (value == null || !RegExp(r'^#[0-9A-Fa-f]{6}$').hasMatch(value)) {
      return Colors.grey;
    }
    return Color(int.parse('FF${value.substring(1)}', radix: 16));
  }
}
