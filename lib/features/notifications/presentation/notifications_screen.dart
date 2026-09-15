import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/notifications_controller.dart';
import '../domain/app_notification.dart';
import '../../../routes/app_routes.dart';
import '../../../widgets/content_state_widgets.dart';
import '../../chat/data/chat_repository.dart';
import 'package:go_router/go_router.dart';

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(notificationsControllerProvider);
    final hasUnread = state.value?.items.any((item) => item.isUnread) == true;
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
            onPressed: hasUnread
                ? () => ref
                      .read(notificationsControllerProvider.notifier)
                      .markAllRead()
                : null,
            child: const Text('Read all'),
          ),
        ],
      ),
      body: state.when(
        loading: () =>
            const ContentLoadingState(label: 'Loading notifications'),
        error: (_, _) => ContentErrorState(
          title: 'Notifications unavailable',
          message: 'We could not refresh your notifications right now.',
          onRetry: () =>
              ref.read(notificationsControllerProvider.notifier).refresh(),
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
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 48),
                  child: Semantics(
                    label: data.unreadOnly
                        ? 'No unread notifications'
                        : 'No notifications yet',
                    child: Column(
                      children: [
                        Icon(
                          Icons.notifications_none_rounded,
                          size: 52,
                          color: Theme.of(context).colorScheme.primary,
                        ),
                        const SizedBox(height: 14),
                        Text(
                          data.unreadOnly
                              ? 'You’re all caught up'
                              : 'No notifications yet',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: 6),
                        Text(
                          data.unreadOnly
                              ? 'New updates will appear here.'
                              : 'Activity invites, messages and friend updates will appear here.',
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.bodyMedium
                              ?.copyWith(
                                color: Theme.of(
                                  context,
                                ).colorScheme.onSurfaceVariant,
                              ),
                        ),
                      ],
                    ),
                  ),
                ),
              ...data.items.map(
                (item) => _NotificationTile(
                  item: item,
                  onTap: () => _openNotification(context, ref, item),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _openNotification(
    BuildContext context,
    WidgetRef ref,
    AppNotification item,
  ) async {
    if (item.isUnread) {
      await ref
          .read(notificationsControllerProvider.notifier)
          .markRead(item.id);
    }
    if (!context.mounted) return;
    try {
      if (item.activityId != null) {
        context.push(AppRoutes.activityDetail, extra: item.activityId);
      } else if (item.conversationId != null) {
        final conversation = await ref
            .read(chatRepositoryProvider)
            .conversation(item.conversationId!);
        if (context.mounted) {
          context.push(AppRoutes.conversation, extra: conversation);
        }
      } else if (item.clanId != null) {
        context.push(AppRoutes.clanDetail, extra: item.clanId);
      } else if (item.type == 'friend_request' ||
          item.type == 'friend_accepted') {
        context.go(AppRoutes.friends);
      }
    } catch (_) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('This notification is no longer available.'),
          ),
        );
      }
    }
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
      leading: Icon(switch (item.type) {
        'friend_request' || 'friend_accepted' => Icons.person_add_outlined,
        'chat_message' || 'mention' => Icons.chat_bubble_outline,
        'clan_invitation' || 'clan_role_changed' => Icons.groups_2_outlined,
        _ => Icons.event_outlined,
      }),
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
