import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../routes/app_routes.dart';
import '../application/clans_controller.dart';
import '../data/clans_repository.dart';
import '../domain/clan_item.dart';

class ClanDetailScreen extends ConsumerWidget {
  final String clanId;

  const ClanDetailScreen({super.key, required this.clanId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detail = ref.watch(clanDetailProvider(clanId));
    final members = ref.watch(clanMembersProvider(clanId));
    final roles = ref.watch(clanRolesProvider(clanId));
    final activities = ref.watch(clanActivitiesProvider(clanId));
    final statistics = ref.watch(clanStatisticsProvider(clanId));
    final leaderboard = ref.watch(clanLeaderboardProvider(clanId));
    final announcements = ref.watch(clanAnnouncementsProvider(clanId));

    return Scaffold(
      appBar: AppBar(
        title: Text(detail.asData?.value.name ?? 'Clan'),
        actions: [
          if (detail.asData?.value.isOwner == true)
            IconButton(
              tooltip: 'Create role',
              onPressed: () => _createRole(context, ref),
              icon: const Icon(Icons.admin_panel_settings_outlined),
            ),
          IconButton(
            tooltip: 'Create clan activity',
            onPressed: detail.asData == null
                ? null
                : () => context.push(
                    AppRoutes.createActivity,
                    extra: {'id': clanId, 'name': detail.asData!.value.name},
                  ),
            icon: const Icon(Icons.event_available_outlined),
          ),
          if (detail.asData?.value.viewerMembership == 'active')
            IconButton(
              tooltip: 'Clan chat',
              onPressed: () => context.push(
                AppRoutes.clanChat,
                extra: {'id': clanId, 'name': detail.asData!.value.name},
              ),
              icon: const Icon(Icons.forum_outlined),
            ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => _refresh(ref),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 40),
          children: [
            detail.when(
              loading: () => const LinearProgressIndicator(),
              error: (error, _) =>
                  _ErrorCard(message: 'Could not load clan: $error'),
              data: (clan) => _ClanSummary(clan: clan),
            ),
            const SizedBox(height: 20),
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Announcements',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                ),
                if (announcements.asData?.value.any((item) => item.canManage) ==
                        true ||
                    detail.asData?.value.isOwner == true)
                  IconButton(
                    tooltip: 'New announcement',
                    onPressed: () => _createAnnouncement(context, ref),
                    icon: const Icon(Icons.campaign_outlined),
                  ),
              ],
            ),
            announcements.when(
              loading: () => const LinearProgressIndicator(),
              error: (_, _) =>
                  const Text('Announcements are available to active members.'),
              data: (items) => Column(
                children: items
                    .map(
                      (item) => Card(
                        child: ListTile(
                          leading: Icon(
                            item.isPinned
                                ? Icons.push_pin
                                : Icons.campaign_outlined,
                          ),
                          title: Text(item.title),
                          subtitle: Text('${item.body}\n— ${item.authorName}'),
                          isThreeLine: true,
                          trailing: item.canManage
                              ? IconButton(
                                  onPressed: () async {
                                    await ref
                                        .read(clansRepositoryProvider)
                                        .deleteAnnouncement(clanId, item.id);
                                    ref.invalidate(
                                      clanAnnouncementsProvider(clanId),
                                    );
                                  },
                                  icon: const Icon(Icons.delete_outline),
                                )
                              : null,
                        ),
                      ),
                    )
                    .toList(),
              ),
            ),
            const SizedBox(height: 20),
            Text(
              'Activity overview',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 8),
            statistics.when(
              loading: () => const LinearProgressIndicator(),
              error: (_, _) => const Text('Statistics require permission.'),
              data: (item) => Row(
                children: [
                  Expanded(
                    child: _Metric(label: 'Events', value: item.totalEvents),
                  ),
                  Expanded(
                    child: _Metric(
                      label: 'Completed',
                      value: item.completedEvents,
                    ),
                  ),
                  Expanded(
                    child: _Metric(label: 'Members', value: item.activeMembers),
                  ),
                ],
              ),
            ),
            if (statistics.asData?.value.attendanceRate != null)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(
                  'Attendance: ${statistics.asData!.value.attendanceRate}% '
                  '(${statistics.asData!.value.attended} attended, '
                  '${statistics.asData!.value.absent} absent)',
                ),
              ),
            const SizedBox(height: 20),
            Text('Clan events', style: Theme.of(context).textTheme.titleLarge),
            activities.when(
              loading: () => const LinearProgressIndicator(),
              error: (_, _) => const Text('Could not load clan events.'),
              data: (items) => Column(
                children: items
                    .take(5)
                    .map(
                      (item) => ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: const Icon(Icons.event_outlined),
                        title: Text(item.title),
                        subtitle: Text(
                          '${item.topicName} • ${item.joinedCount}/${item.maxParticipants}',
                        ),
                        onTap: () => context.push(
                          AppRoutes.activityDetail,
                          extra: item.id,
                        ),
                      ),
                    )
                    .toList(),
              ),
            ),
            const SizedBox(height: 20),
            Text('Leaderboard', style: Theme.of(context).textTheme.titleLarge),
            leaderboard.when(
              loading: () => const LinearProgressIndicator(),
              error: (_, _) => const Text('Could not load leaderboard.'),
              data: (items) => Column(
                children: items
                    .take(10)
                    .map(
                      (item) => ListTile(
                        dense: true,
                        leading: CircleAvatar(child: Text('${item.rank}')),
                        title: Text(item.displayName),
                        trailing: Text('${item.score} events'),
                      ),
                    )
                    .toList(),
              ),
            ),
            const SizedBox(height: 20),
            Text('Roles', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 8),
            roles.when(
              loading: () => const LinearProgressIndicator(),
              error: (error, _) => _ErrorCard(message: 'Could not load roles'),
              data: (items) => Wrap(
                spacing: 8,
                runSpacing: 8,
                children: items
                    .map(
                      (role) => Chip(
                        avatar: role.isSystem
                            ? const Icon(Icons.verified_outlined, size: 18)
                            : null,
                        label: Text(role.name),
                      ),
                    )
                    .toList(),
              ),
            ),
            const SizedBox(height: 24),
            Text('Members', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 8),
            members.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (error, _) =>
                  _ErrorCard(message: 'Could not load members: $error'),
              data: (items) => Column(
                children: items
                    .map(
                      (member) => _MemberTile(
                        clanId: clanId,
                        member: member,
                        roles: roles.asData?.value ?? const [],
                        canManage: detail.asData?.value.isOwner == true,
                      ),
                    )
                    .toList(),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _refresh(WidgetRef ref) async {
    ref.invalidate(clanDetailProvider(clanId));
    ref.invalidate(clanMembersProvider(clanId));
    ref.invalidate(clanRolesProvider(clanId));
    ref.invalidate(clanActivitiesProvider(clanId));
    ref.invalidate(clanStatisticsProvider(clanId));
    ref.invalidate(clanLeaderboardProvider(clanId));
    ref.invalidate(clanAnnouncementsProvider(clanId));
    await ref.read(clanMembersProvider(clanId).future);
  }

  Future<void> _createAnnouncement(BuildContext context, WidgetRef ref) async {
    final title = TextEditingController();
    final body = TextEditingController();
    final submit = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('New announcement'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: title,
              decoration: const InputDecoration(labelText: 'Title'),
            ),
            TextField(
              controller: body,
              maxLines: 4,
              decoration: const InputDecoration(labelText: 'Message'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Publish'),
          ),
        ],
      ),
    );
    if (submit == true &&
        title.text.trim().isNotEmpty &&
        body.text.trim().isNotEmpty) {
      await ref
          .read(clansRepositoryProvider)
          .createAnnouncement(clanId, title.text.trim(), body.text.trim());
      ref.invalidate(clanAnnouncementsProvider(clanId));
    }
    title.dispose();
    body.dispose();
  }

  Future<void> _createRole(BuildContext context, WidgetRef ref) async {
    final input = await showDialog<_RoleInput>(
      context: context,
      builder: (_) => const _CreateRoleDialog(),
    );
    if (input == null) return;
    try {
      await ref
          .read(clansRepositoryProvider)
          .createRole(clanId, input.name, input.slug, input.permissions);
      ref.invalidate(clanRolesProvider(clanId));
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Role created')));
      }
    } catch (error) {
      if (context.mounted) _showError(context, error);
    }
  }
}

class _Metric extends StatelessWidget {
  final String label;
  final int value;

  const _Metric({required this.label, required this.value});

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.symmetric(vertical: 14),
      child: Column(
        children: [
          Text('$value', style: Theme.of(context).textTheme.titleLarge),
          Text(label),
        ],
      ),
    ),
  );
}

class _ClanSummary extends StatelessWidget {
  final ClanItem clan;

  const _ClanSummary({required this.clan});

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 24,
                child: Text(clan.name.characters.first.toUpperCase()),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      clan.name,
                      style: Theme.of(context).textTheme.headlineSmall,
                    ),
                    Text('@${clan.slug}'),
                  ],
                ),
              ),
              if (clan.isOwner) const Chip(label: Text('Owner')),
            ],
          ),
          if (clan.description?.isNotEmpty == true) ...[
            const SizedBox(height: 14),
            Text(clan.description!),
          ],
          const SizedBox(height: 14),
          Text(
            '${clan.memberCount} members • ${clan.visibility} • '
            '${clan.joinPolicy.replaceAll('_', ' ')}',
          ),
        ],
      ),
    ),
  );
}

class _MemberTile extends ConsumerWidget {
  final String clanId;
  final ClanMemberItem member;
  final List<ClanRoleItem> roles;
  final bool canManage;

  const _MemberTile({
    required this.clanId,
    required this.member,
    required this.roles,
    required this.canManage,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) => Card(
    child: ListTile(
      leading: CircleAvatar(
        child: Text(member.userName.characters.first.toUpperCase()),
      ),
      title: Text(member.userName),
      subtitle: Text(
        member.status == 'active'
            ? member.role?.name ?? 'Member'
            : member.status.replaceAll('_', ' '),
      ),
      trailing: canManage ? _actions(context, ref) : null,
    ),
  );

  Widget? _actions(BuildContext context, WidgetRef ref) {
    if (member.status == 'requested') {
      return Wrap(
        spacing: 4,
        children: [
          IconButton(
            tooltip: 'Reject',
            onPressed: () => _review(context, ref, false),
            icon: const Icon(Icons.close),
          ),
          IconButton(
            tooltip: 'Accept',
            onPressed: () => _review(context, ref, true),
            icon: const Icon(Icons.check),
          ),
        ],
      );
    }
    if (member.status != 'active' || member.role?.slug == 'owner') return null;
    return PopupMenuButton<_MemberAction>(
      onSelected: (action) => _handle(context, ref, action),
      itemBuilder: (_) => const [
        PopupMenuItem(
          value: _MemberAction.assignRole,
          child: Text('Assign role'),
        ),
        PopupMenuItem(
          value: _MemberAction.transfer,
          child: Text('Transfer ownership'),
        ),
        PopupMenuItem(value: _MemberAction.remove, child: Text('Remove')),
        PopupMenuItem(value: _MemberAction.ban, child: Text('Ban')),
      ],
    );
  }

  Future<void> _handle(
    BuildContext context,
    WidgetRef ref,
    _MemberAction action,
  ) async {
    if (action == _MemberAction.assignRole) {
      final available = roles.where((role) => role.slug != 'owner').toList();
      final role = await showDialog<ClanRoleItem>(
        context: context,
        builder: (context) => SimpleDialog(
          title: const Text('Assign role'),
          children: available
              .map(
                (item) => SimpleDialogOption(
                  onPressed: () => Navigator.pop(context, item),
                  child: Text(item.name),
                ),
              )
              .toList(),
        ),
      );
      if (role == null || !context.mounted) return;
      await _execute(
        context,
        ref,
        () => ref
            .read(clansRepositoryProvider)
            .assignRole(clanId, member.id, role.id),
        'Role updated',
      );
      return;
    }

    final label = switch (action) {
      _MemberAction.transfer => 'transfer ownership to',
      _MemberAction.remove => 'remove',
      _MemberAction.ban => 'ban',
      _ => '',
    };
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Confirm action'),
        content: Text('Do you want to $label ${member.userName}?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Confirm'),
          ),
        ],
      ),
    );
    if (confirmed != true || !context.mounted) return;
    if (action == _MemberAction.transfer) {
      await _execute(
        context,
        ref,
        () => ref.read(clansRepositoryProvider).transfer(clanId, member.userId),
        'Ownership transferred',
      );
    } else {
      await _execute(
        context,
        ref,
        () => ref
            .read(clansRepositoryProvider)
            .remove(clanId, member.id, action == _MemberAction.ban),
        action == _MemberAction.ban ? 'Member banned' : 'Member removed',
      );
    }
  }

  Future<void> _review(BuildContext context, WidgetRef ref, bool accept) =>
      _execute(
        context,
        ref,
        () =>
            ref.read(clansRepositoryProvider).review(clanId, member.id, accept),
        accept ? 'Request accepted' : 'Request rejected',
      );

  Future<void> _execute(
    BuildContext context,
    WidgetRef ref,
    Future<void> Function() operation,
    String success,
  ) async {
    try {
      await operation();
      ref.invalidate(clanMembersProvider(clanId));
      ref.invalidate(clanDetailProvider(clanId));
      ref.invalidate(clansControllerProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(success)));
      }
    } catch (error) {
      if (context.mounted) _showError(context, error);
    }
  }
}

enum _MemberAction { assignRole, transfer, remove, ban }

class _RoleInput {
  final String name;
  final String slug;
  final List<String> permissions;

  const _RoleInput(this.name, this.slug, this.permissions);
}

class _CreateRoleDialog extends StatefulWidget {
  const _CreateRoleDialog();

  @override
  State<_CreateRoleDialog> createState() => _CreateRoleDialogState();
}

class _CreateRoleDialogState extends State<_CreateRoleDialog> {
  static const _availablePermissions = [
    'create_events',
    'invite_members',
    'manage_members',
    'manage_events',
    'manage_chat',
    'view_statistics',
  ];

  final _name = TextEditingController();
  final _slug = TextEditingController();
  final _selected = <String>{};

  @override
  void dispose() {
    _name.dispose();
    _slug.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Create custom role'),
    content: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(
            controller: _name,
            decoration: const InputDecoration(labelText: 'Name'),
          ),
          TextField(
            controller: _slug,
            decoration: const InputDecoration(labelText: 'Slug'),
          ),
          const SizedBox(height: 12),
          ..._availablePermissions.map(
            (permission) => CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              value: _selected.contains(permission),
              title: Text(permission.replaceAll('_', ' ')),
              onChanged: (checked) => setState(() {
                if (checked == true) {
                  _selected.add(permission);
                } else {
                  _selected.remove(permission);
                }
              }),
            ),
          ),
        ],
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Cancel'),
      ),
      FilledButton(
        onPressed: () {
          if (_name.text.trim().isEmpty || _slug.text.trim().isEmpty) return;
          Navigator.pop(
            context,
            _RoleInput(
              _name.text.trim(),
              _slug.text.trim(),
              _selected.toList(),
            ),
          );
        },
        child: const Text('Create'),
      ),
    ],
  );
}

class _ErrorCard extends StatelessWidget {
  final String message;

  const _ErrorCard({required this.message});

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(padding: const EdgeInsets.all(16), child: Text(message)),
  );
}

void _showError(BuildContext context, Object error) {
  ScaffoldMessenger.of(
    context,
  ).showSnackBar(SnackBar(content: Text('Action failed: $error')));
}
