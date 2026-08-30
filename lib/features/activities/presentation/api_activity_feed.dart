import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../theme/app_theme.dart';
import '../data/activities_repository.dart';
import '../domain/activity_item.dart';
import 'activity_thumbnail.dart';

class ApiActivityFeed extends ConsumerWidget {
  final ValueChanged<String> onActivityTap;
  final double bottomPadding;
  const ApiActivityFeed({
    required this.onActivityTap,
    required this.bottomPadding,
    super.key,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final feed = ref.watch(activityFeedProvider);
    return feed.when(
      loading: () => const SliverToBoxAdapter(
        child: Padding(
          padding: EdgeInsets.all(48),
          child: Center(child: CircularProgressIndicator()),
        ),
      ),
      error: (_, _) => SliverToBoxAdapter(
        child: Center(
          child: TextButton.icon(
            onPressed: () => ref.invalidate(activityFeedProvider),
            icon: const Icon(Icons.refresh),
            label: const Text('Reload activities'),
          ),
        ),
      ),
      data: (items) => SliverPadding(
        padding: EdgeInsets.fromLTRB(20, 0, 20, bottomPadding),
        sliver: items.isEmpty
            ? const SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.symmetric(vertical: 48),
                  child: Center(
                    child: Text('No activities yet. Create the first one!'),
                  ),
                ),
              )
            : SliverList.builder(
                itemCount: items.length,
                itemBuilder: (context, index) => _ActivityCard(
                  activity: items[index],
                  onTap: () => onActivityTap(items[index].id),
                ),
              ),
      ),
    );
  }
}

class _ActivityCard extends StatelessWidget {
  final ActivityItem activity;
  final VoidCallback onTap;
  const _ActivityCard({required this.activity, required this.onTap});

  @override
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.only(bottom: 14),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ActivityThumbnail(activity: activity, height: 148),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Chip(label: Text(activity.topicName)),
                    const Spacer(),
                    Text(
                      activity.status.toUpperCase(),
                      style: const TextStyle(
                        color: AppTheme.primary,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  activity.title,
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 6),
                Text('Hosted by ${activity.hostName}'),
                const SizedBox(height: 12),
                Row(
                  children: [
                    const Icon(Icons.schedule, size: 18),
                    const SizedBox(width: 6),
                    Expanded(child: Text(_date(activity.startsAt))),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    const Icon(Icons.location_on_outlined, size: 18),
                    const SizedBox(width: 6),
                    Expanded(child: Text(activity.locationName)),
                    Text('${activity.joinedCount}/${activity.maxParticipants}'),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );

  static String _date(DateTime value) {
    final local = value.toLocal();
    return '${local.day}/${local.month}/${local.year} ${local.hour.toString().padLeft(2, '0')}:${local.minute.toString().padLeft(2, '0')}';
  }
}
