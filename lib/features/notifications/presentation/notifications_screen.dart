import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/notifications_controller.dart';
import '../domain/app_notification.dart';
import '../../../routes/app_routes.dart';
import 'package:go_router/go_router.dart';

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(notificationsControllerProvider);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          IconButton(
            tooltip: 'Preferences',
            onPressed: () => context.push(AppRoutes.notificationPreferences),
            icon: const Icon(Icons.tune),
          ),
          TextButton(
            onPressed: () => ref
                .read(notificationsControllerProvider.notifier)
                .markAllRead(),
            child: const Text('Read all'),
          ),
        ],
      ),
      body: state.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (_, _) => Center(
          child: FilledButton(
            onPressed: () =>
                ref.read(notificationsControllerProvider.notifier).refresh(),
            child: const Text('Retry'),
          ),
        ),
        data: (data) => RefreshIndicator(
          onRefresh: () =>
              ref.read(notificationsControllerProvider.notifier).refresh(),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
            children: [
              SegmentedButton<bool>(
                segments: const [
                  ButtonSegment(value: false, label: Text('All')),
                  ButtonSegment(value: true, label: Text('Unread')),
                ],
                selected: {data.unreadOnly},
                onSelectionChanged: (value) => ref
                    .read(notificationsControllerProvider.notifier)
                    .refresh(unreadOnly: value.first),
              ),
              const SizedBox(height: 16),
              if (data.items.isEmpty)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 48),
                  child: Center(child: Text('No notifications.')),
                ),
              ...data.items.map(
                (item) => _NotificationTile(
                  item: item,
                  onTap: item.isUnread
                      ? () => ref
                            .read(notificationsControllerProvider.notifier)
                            .markRead(item.id)
                      : null,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _NotificationTile extends StatelessWidget {
  final AppNotification item;
  final VoidCallback? onTap;
  const _NotificationTile({required this.item, required this.onTap});

  @override
  Widget build(BuildContext context) => Card(
    color: item.isUnread
        ? Theme.of(context).colorScheme.primaryContainer
        : null,
    child: ListTile(
      onTap: onTap,
      leading: Icon(
        item.type == 'friend_request' ? Icons.person_add : Icons.people,
      ),
      title: Text(item.message),
      subtitle: Text(_relativeTime(item.createdAt)),
      trailing: item.isUnread
          ? const Icon(Icons.circle, size: 10)
          : const Icon(Icons.done, size: 18),
    ),
  );

  String _relativeTime(DateTime value) {
    final difference = DateTime.now().difference(value.toLocal());
    if (difference.inMinutes < 1) return 'Just now';
    if (difference.inHours < 1) return '${difference.inMinutes}m ago';
    if (difference.inDays < 1) return '${difference.inHours}h ago';
    return '${difference.inDays}d ago';
  }
}
