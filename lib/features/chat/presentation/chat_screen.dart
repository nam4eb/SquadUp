import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../routes/app_routes.dart';
import '../../../core/realtime/realtime_service.dart';
import '../../../widgets/content_state_widgets.dart';
import '../../friends/data/friends_repository.dart';
import '../data/chat_repository.dart';

class ChatScreen extends ConsumerStatefulWidget {
  const ChatScreen({super.key});

  @override
  ConsumerState<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends ConsumerState<ChatScreen>
    with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    Future.microtask(() {
      ref.read(realtimeServiceProvider).connect();
      ref.read(realtimeServiceProvider).startPresence();
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final realtime = ref.read(realtimeServiceProvider);
    if (state == AppLifecycleState.resumed) {
      realtime.startPresence();
      realtime.recover();
      ref.read(conversationsProvider.notifier).refresh(silent: true);
    } else if (state == AppLifecycleState.inactive ||
        state == AppLifecycleState.paused) {
      realtime.heartbeat('away');
    } else if (state == AppLifecycleState.detached) {
      realtime.stopPresence();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final conversations = ref.watch(conversationsProvider);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Chat'),
        actions: [
          IconButton(
            tooltip: 'New group',
            onPressed: () => _createGroup(context, ref),
            icon: const Icon(Icons.group_add_outlined),
          ),
          IconButton(
            tooltip: 'New message',
            onPressed: () => _startDirect(context, ref),
            icon: const Icon(Icons.edit_square),
          ),
        ],
      ),
      body: conversations.when(
        loading: () => const ContentLoadingState(label: 'Loading chats'),
        error: (_, _) => ContentErrorState(
          title: 'Chats could not be loaded',
          message: 'Check your connection and try again.',
          onRetry: () => ref.read(conversationsProvider.notifier).refresh(),
        ),
        data: (items) => items.isEmpty
            ? RefreshableEmptyState(
                icon: Icons.forum_outlined,
                title: 'Start a conversation',
                message: 'Message a friend or create a group for your squad.',
                actionLabel: 'New message',
                onAction: () => _startDirect(context, ref),
                onRefresh: () =>
                    ref.read(conversationsProvider.notifier).refresh(),
              )
            : RefreshIndicator(
                onRefresh: () async {
                  await ref.read(conversationsProvider.notifier).refresh();
                },
                child: ListView.builder(
                  padding: const EdgeInsets.only(bottom: 110),
                  itemCount: items.length,
                  itemBuilder: (context, index) {
                    final item = items[index];
                    return ListTile(
                      leading: CircleAvatar(
                        child: Icon(
                          item.type == 'clan' ? Icons.groups : Icons.person,
                        ),
                      ),
                      title: Text(item.name),
                      subtitle: Text(item.latestMessage ?? 'No messages yet'),
                      trailing: item.unreadCount > 0
                          ? Badge(label: Text('${item.unreadCount}'))
                          : null,
                      onTap: () =>
                          context.push(AppRoutes.conversation, extra: item),
                    );
                  },
                ),
              ),
      ),
    );
  }

  Future<void> _startDirect(BuildContext context, WidgetRef ref) async {
    final friends = await ref.read(friendsRepositoryProvider).friends();
    if (!context.mounted) return;
    final userId = await showDialog<String>(
      context: context,
      builder: (context) => SimpleDialog(
        title: const Text('Message a friend'),
        children: friends
            .map(
              (friend) => SimpleDialogOption(
                onPressed: () => Navigator.pop(context, friend.id),
                child: Text(friend.displayName),
              ),
            )
            .toList(),
      ),
    );
    if (userId == null || !context.mounted) return;
    final conversation = await ref.read(chatRepositoryProvider).direct(userId);
    ref.invalidate(conversationsProvider);
    if (context.mounted) {
      context.push(AppRoutes.conversation, extra: conversation);
    }
  }

  Future<void> _createGroup(BuildContext context, WidgetRef ref) async {
    final friends = await ref.read(friendsRepositoryProvider).friends();
    if (!context.mounted) return;
    final name = TextEditingController();
    final selected = <String>{};
    final result = await showDialog<(String, List<String>)>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Create group'),
          content: SizedBox(
            width: 360,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextField(
                    controller: name,
                    decoration: const InputDecoration(labelText: 'Group name'),
                    onChanged: (_) => setDialogState(() {}),
                  ),
                  const SizedBox(height: 12),
                  ...friends.map(
                    (friend) => CheckboxListTile(
                      contentPadding: EdgeInsets.zero,
                      value: selected.contains(friend.id),
                      title: Text(friend.displayName),
                      onChanged: (checked) => setDialogState(() {
                        if (checked == true) {
                          selected.add(friend.id);
                        } else {
                          selected.remove(friend.id);
                        }
                      }),
                    ),
                  ),
                ],
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: name.text.trim().length < 2 || selected.isEmpty
                  ? null
                  : () => Navigator.pop(context, (
                      name.text.trim(),
                      selected.toList(),
                    )),
              child: const Text('Create'),
            ),
          ],
        ),
      ),
    );
    name.dispose();
    if (result == null || !context.mounted) return;
    final conversation = await ref
        .read(chatRepositoryProvider)
        .group(result.$1, result.$2);
    ref.invalidate(conversationsProvider);
    if (context.mounted) {
      context.push(AppRoutes.conversation, extra: conversation);
    }
  }
}
