import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../routes/app_routes.dart';
import '../../../theme/app_theme.dart';
import '../application/friends_controller.dart';
import '../domain/social_user.dart';

class FriendsScreen extends ConsumerWidget {
  const FriendsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(friendsControllerProvider);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Friends'),
        actions: [
          IconButton(
            tooltip: 'Notifications',
            onPressed: () => context.push(AppRoutes.notifications),
            icon: const Icon(Icons.notifications_outlined),
          ),
        ],
      ),
      body: state.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => _ErrorState(
          onRetry: () => ref.read(friendsControllerProvider.notifier).refresh(),
        ),
        data: (data) => RefreshIndicator(
          onRefresh: () =>
              ref.read(friendsControllerProvider.notifier).refresh(),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 120),
            children: [
              SearchBar(
                hintText: 'Search people',
                leading: const Icon(Icons.search),
                onSubmitted: (query) =>
                    ref.read(friendsControllerProvider.notifier).refresh(query),
              ),
              if (data.incoming.isNotEmpty) ...[
                const _SectionTitle('Friend requests'),
                ...data.incoming.map((item) => _RequestTile(item: item)),
              ],
              const _SectionTitle('Your friends'),
              if (data.friends.isEmpty) const _EmptyLabel('No friends yet.'),
              ...data.friends.map(
                (user) => _UserTile(user: user, isFriend: true),
              ),
              const _SectionTitle('Discover people'),
              ...data.discover.map(
                (user) => _UserTile(
                  user: user,
                  isFriend: data.friends.any((friend) => friend.id == user.id),
                ),
              ),
              const _SectionTitle('Blocked users'),
              if (data.blocked.isEmpty) const _EmptyLabel('No blocked users.'),
              ...data.blocked.map((user) => _BlockedUserTile(user: user)),
            ],
          ),
        ),
      ),
    );
  }
}

class _RequestTile extends ConsumerWidget {
  final FriendRequestItem item;
  const _RequestTile({required this.item});

  @override
  Widget build(BuildContext context, WidgetRef ref) => ListTile(
    contentPadding: EdgeInsets.zero,
    leading: _Avatar(item.sender),
    title: Text(item.sender.displayName),
    subtitle: Text('@${item.sender.username}'),
    trailing: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        IconButton(
          tooltip: 'Reject',
          onPressed: () =>
              ref.read(friendsControllerProvider.notifier).reject(item.id),
          icon: const Icon(Icons.close),
        ),
        FilledButton(
          onPressed: () =>
              ref.read(friendsControllerProvider.notifier).accept(item.id),
          child: const Text('Accept'),
        ),
      ],
    ),
  );
}

class _UserTile extends ConsumerWidget {
  final SocialUser user;
  final bool isFriend;
  const _UserTile({required this.user, required this.isFriend});

  @override
  Widget build(BuildContext context, WidgetRef ref) => ListTile(
    contentPadding: EdgeInsets.zero,
    leading: _Avatar(user),
    title: Text(user.displayName),
    subtitle: Text(
      user.bio?.isNotEmpty == true ? user.bio! : '@${user.username}',
    ),
    trailing: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (isFriend)
          TextButton(
            onPressed: () =>
                ref.read(friendsControllerProvider.notifier).remove(user.id),
            child: const Text('Remove'),
          )
        else
          FilledButton.tonal(
            onPressed: () =>
                ref.read(friendsControllerProvider.notifier).send(user.id),
            child: const Text('Add'),
          ),
        PopupMenuButton<String>(
          tooltip: 'More actions',
          onSelected: (value) {
            if (value == 'block') {
              ref.read(friendsControllerProvider.notifier).block(user.id);
            }
          },
          itemBuilder: (_) => const [
            PopupMenuItem(value: 'block', child: Text('Block')),
          ],
        ),
      ],
    ),
  );
}

class _BlockedUserTile extends ConsumerWidget {
  final SocialUser user;
  const _BlockedUserTile({required this.user});

  @override
  Widget build(BuildContext context, WidgetRef ref) => ListTile(
    contentPadding: EdgeInsets.zero,
    leading: _Avatar(user),
    title: Text(user.displayName),
    subtitle: Text('@${user.username}'),
    trailing: TextButton(
      onPressed: () =>
          ref.read(friendsControllerProvider.notifier).unblock(user.id),
      child: const Text('Unblock'),
    ),
  );
}

class _Avatar extends StatelessWidget {
  final SocialUser user;
  const _Avatar(this.user);

  @override
  Widget build(BuildContext context) => CircleAvatar(
    backgroundColor: AppTheme.primaryContainer,
    foregroundImage: user.avatarUrl == null
        ? null
        : NetworkImage(user.avatarUrl!),
    child: Text(user.displayName.characters.first.toUpperCase()),
  );
}

class _SectionTitle extends StatelessWidget {
  final String text;
  const _SectionTitle(this.text);
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 24, bottom: 8),
    child: Text(text, style: Theme.of(context).textTheme.titleLarge),
  );
}

class _EmptyLabel extends StatelessWidget {
  final String text;
  const _EmptyLabel(this.text);
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 16),
    child: Text(
      text,
      style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant),
    ),
  );
}

class _ErrorState extends StatelessWidget {
  final VoidCallback onRetry;
  const _ErrorState({required this.onRetry});
  @override
  Widget build(BuildContext context) => Center(
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Text('Unable to load friends.'),
        const SizedBox(height: 12),
        FilledButton(onPressed: onRetry, child: const Text('Retry')),
      ],
    ),
  );
}
