import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../routes/app_routes.dart';
import '../application/clans_controller.dart';
import '../domain/clan_item.dart';

class ClansScreen extends ConsumerWidget {
  const ClansScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final clans = ref.watch(clansControllerProvider);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Clans'),
        actions: [
          IconButton(
            tooltip: 'Create clan',
            onPressed: () => _create(context, ref),
            icon: const Icon(Icons.add),
          ),
        ],
      ),
      body: clans.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (_, _) => Center(
          child: FilledButton(
            onPressed: () =>
                ref.read(clansControllerProvider.notifier).refresh(),
            child: const Text('Retry'),
          ),
        ),
        data: (items) => RefreshIndicator(
          onRefresh: () => ref.read(clansControllerProvider.notifier).refresh(),
          child: ListView.builder(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 120),
            itemCount: items.length,
            itemBuilder: (context, index) => _ClanCard(clan: items[index]),
          ),
        ),
      ),
    );
  }

  Future<void> _create(BuildContext context, WidgetRef ref) async {
    final name = TextEditingController();
    final slug = TextEditingController();
    final values = await showDialog<List<String>>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Create clan'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: name,
              decoration: const InputDecoration(labelText: 'Name'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: slug,
              decoration: const InputDecoration(labelText: 'Slug'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () =>
                Navigator.pop(context, [name.text.trim(), slug.text.trim()]),
            child: const Text('Create'),
          ),
        ],
      ),
    );
    name.dispose();
    slug.dispose();
    if (values == null || values.any((value) => value.isEmpty)) return;
    await ref
        .read(clansControllerProvider.notifier)
        .create(values[0], values[1]);
  }
}

class _ClanCard extends ConsumerWidget {
  final ClanItem clan;
  const _ClanCard({required this.clan});

  @override
  Widget build(BuildContext context, WidgetRef ref) => Card(
    margin: const EdgeInsets.only(bottom: 12),
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                child: Text(clan.name.characters.first.toUpperCase()),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  clan.name,
                  style: Theme.of(context).textTheme.titleLarge,
                ),
              ),
            ],
          ),
          if (clan.description?.isNotEmpty == true) ...[
            const SizedBox(height: 10),
            Text(clan.description!),
          ],
          const SizedBox(height: 12),
          Text(
            '${clan.memberCount} members • ${clan.joinPolicy.replaceAll('_', ' ')}',
          ),
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              TextButton(
                onPressed: () =>
                    context.push(AppRoutes.clanDetail, extra: clan.id),
                child: const Text('View'),
              ),
              const SizedBox(width: 8),
              _action(ref),
            ],
          ),
        ],
      ),
    ),
  );

  Widget _action(WidgetRef ref) {
    final controller = ref.read(clansControllerProvider.notifier);
    if (clan.isOwner) return const Chip(label: Text('Owner'));
    if (clan.viewerMembership == 'active') {
      return OutlinedButton(
        onPressed: () => controller.leave(clan.id),
        child: const Text('Leave'),
      );
    }
    if (clan.viewerMembership == 'requested') {
      return const Chip(label: Text('Requested'));
    }
    return FilledButton.tonal(
      onPressed: () => controller.join(clan.id),
      child: Text(
        clan.viewerMembership == 'invited'
            ? 'Accept invite'
            : clan.joinPolicy == 'approval'
            ? 'Request to join'
            : 'Join',
      ),
    );
  }
}
