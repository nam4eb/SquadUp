import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:file_picker/file_picker.dart';

import '../../features/activities/data/activities_repository.dart';
import '../../core/network/api_error.dart';
import '../../features/activities/domain/activity_item.dart';
import '../../features/activities/presentation/activity_thumbnail.dart';
import '../../features/friends/data/friends_repository.dart';
import '../../features/stories/data/stories_repository.dart';
import '../../routes/app_routes.dart';

class ActivityDetailScreen extends ConsumerWidget {
  final String activityId;
  const ActivityDetailScreen({required this.activityId, super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detail = ref.watch(activityDetailProvider(activityId));
    return Scaffold(
      appBar: AppBar(title: const Text('Activity')),
      body: detail.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (_, _) => Center(
          child: FilledButton.icon(
            onPressed: () => ref.invalidate(activityDetailProvider(activityId)),
            icon: const Icon(Icons.refresh),
            label: const Text('Retry'),
          ),
        ),
        data: (activity) => ListView(
          padding: const EdgeInsets.all(20),
          children: [
            ActivityThumbnail(
              activity: activity,
              height: 210,
              borderRadius: BorderRadius.circular(20),
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 8,
              children: [
                if (activity.sportName != null)
                  Chip(label: Text(activity.sportName!)),
                Chip(label: Text(activity.topicName)),
                Chip(label: Text(activity.visibility.replaceAll('_', ' '))),
                if (activity.passwordProtected)
                  const Chip(
                    avatar: Icon(Icons.lock, size: 16),
                    label: Text('Password'),
                  ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              activity.title,
              style: Theme.of(context).textTheme.headlineMedium,
            ),
            const SizedBox(height: 8),
            Text('Hosted by ${activity.hostName}'),
            const Divider(height: 32),
            _Info(icon: Icons.schedule, text: _date(activity.startsAt)),
            _Info(
              icon: Icons.location_on_outlined,
              text: activity.locationName,
            ),
            if (activity.matchFormat != null)
              _Info(
                icon: Icons.sports_tennis,
                text: 'Format: ${activity.matchFormat}',
              ),
            if (activity.skillMin != null || activity.skillMax != null)
              _Info(
                icon: Icons.equalizer,
                text:
                    'Skill: ${activity.skillMin ?? 'Any'}–${activity.skillMax ?? 'Any'}',
              ),
            if (activity.fee != null)
              _Info(
                icon: Icons.payments_outlined,
                text:
                    'Fee: ${activity.fee!.toStringAsFixed(0)} ${activity.currency ?? ''}',
              ),
            _Info(
              icon: Icons.people_outline,
              text:
                  '${activity.joinedCount} of ${activity.maxParticipants} joined',
            ),
            if (activity.requireApproval)
              const _Info(
                icon: Icons.approval_outlined,
                text: 'Host approval required',
              ),
            if (activity.description?.isNotEmpty == true) ...[
              const SizedBox(height: 20),
              Text('About', style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 8),
              Text(activity.description!),
            ],
            if (activity.rules.isNotEmpty) ...[
              const SizedBox(height: 20),
              Text('Rules', style: Theme.of(context).textTheme.titleLarge),
              ...activity.rules.map(
                (rule) => ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.check_circle_outline),
                  title: Text(rule),
                ),
              ),
            ],
            const SizedBox(height: 16),
            _SocialActions(activity: activity),
            const SizedBox(height: 20),
            Text('Participants', style: Theme.of(context).textTheme.titleLarge),
            _Participants(activity: activity),
            if (activity.status == 'completed' &&
                (activity.isHost ||
                    activity.viewerParticipation == 'attended')) ...[
              const SizedBox(height: 16),
              _RatingAction(activity: activity),
              const SizedBox(height: 8),
              _PostMatchStoryAction(activity: activity),
            ],
            if (activity.canManage) ...[
              const SizedBox(height: 20),
              _HostControls(activity: activity),
            ],
            const SizedBox(height: 28),
            _ParticipationAction(activity: activity),
          ],
        ),
      ),
    );
  }

  static String _date(DateTime value) {
    final local = value.toLocal();
    return '${local.day}/${local.month}/${local.year} at ${local.hour.toString().padLeft(2, '0')}:${local.minute.toString().padLeft(2, '0')}';
  }
}

class _SocialActions extends ConsumerStatefulWidget {
  final ActivityItem activity;

  const _SocialActions({required this.activity});

  @override
  ConsumerState<_SocialActions> createState() => _SocialActionsState();
}

class _SocialActionsState extends ConsumerState<_SocialActions> {
  late bool _liked = widget.activity.likedByMe;
  late int _likes = widget.activity.likesCount;
  bool _busy = false;

  @override
  Widget build(BuildContext context) => Wrap(
    spacing: 8,
    runSpacing: 8,
    children: [
      OutlinedButton.icon(
        onPressed: _busy ? null : _toggleLike,
        icon: Icon(_liked ? Icons.favorite : Icons.favorite_border),
        label: Text('$_likes'),
      ),
      OutlinedButton.icon(
        onPressed: _comments,
        icon: const Icon(Icons.comment_outlined),
        label: Text('${widget.activity.commentsCount}'),
      ),
      if (widget.activity.hasChat || widget.activity.canManage)
        OutlinedButton.icon(
          onPressed: _chat,
          icon: const Icon(Icons.forum_outlined),
          label: Text(widget.activity.hasChat ? 'Event chat' : 'Enable chat'),
        ),
      if (!widget.activity.isHost)
        PopupMenuButton<String>(
          onSelected: (value) => value == 'hide' ? _hide() : _report(),
          itemBuilder: (_) => const [
            PopupMenuItem(value: 'hide', child: Text('Hide activity')),
            PopupMenuItem(value: 'report', child: Text('Report activity')),
          ],
        ),
    ],
  );

  Future<void> _toggleLike() async {
    final next = !_liked;
    setState(() {
      _busy = true;
      _liked = next;
      _likes += next ? 1 : -1;
    });
    try {
      await ref
          .read(activitiesRepositoryProvider)
          .setLiked(widget.activity.id, next);
      ref.invalidate(activityDetailProvider(widget.activity.id));
    } catch (_) {
      if (mounted) {
        setState(() {
          _liked = !next;
          _likes += next ? -1 : 1;
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _comments() async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _CommentsSheet(activityId: widget.activity.id),
    );
    ref.invalidate(activityDetailProvider(widget.activity.id));
  }

  Future<void> _chat() async {
    try {
      final conversation = await ref
          .read(activitiesRepositoryProvider)
          .eventChat(widget.activity.id, create: !widget.activity.hasChat);
      if (mounted) context.push(AppRoutes.conversation, extra: conversation);
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Unable to open event chat.')),
        );
      }
    }
  }

  Future<void> _hide() async {
    await ref.read(activitiesRepositoryProvider).hide(widget.activity.id);
    if (mounted) context.pop();
  }

  Future<void> _report() async {
    final details = TextEditingController();
    final reason = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Report activity'),
        content: TextField(
          controller: details,
          maxLines: 3,
          decoration: const InputDecoration(labelText: 'Details (optional)'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, 'spam'),
            child: const Text('Report spam'),
          ),
        ],
      ),
    );
    if (reason != null) {
      await ref
          .read(activitiesRepositoryProvider)
          .report(widget.activity.id, reason, details.text);
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Report submitted.')));
      }
    }
    details.dispose();
  }
}

class _CommentsSheet extends ConsumerStatefulWidget {
  final String activityId;

  const _CommentsSheet({required this.activityId});

  @override
  ConsumerState<_CommentsSheet> createState() => _CommentsSheetState();
}

class _CommentsSheetState extends ConsumerState<_CommentsSheet> {
  final _controller = TextEditingController();
  List<ActivityCommentItem>? _comments;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
    child: SizedBox(
      height: MediaQuery.of(context).size.height * .7,
      child: Column(
        children: [
          const Padding(
            padding: EdgeInsets.all(16),
            child: Text(
              'Comments',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
            ),
          ),
          Expanded(
            child: _comments == null
                ? const Center(child: CircularProgressIndicator())
                : ListView(
                    children: _comments!
                        .map(
                          (item) => ListTile(
                            title: Text(item.userName),
                            subtitle: Text(item.body),
                            trailing: item.canDelete
                                ? IconButton(
                                    onPressed: () => _delete(item.id),
                                    icon: const Icon(Icons.delete_outline),
                                  )
                                : null,
                          ),
                        )
                        .toList(),
                  ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _controller,
                      decoration: const InputDecoration(
                        hintText: 'Write a comment',
                      ),
                    ),
                  ),
                  IconButton(onPressed: _send, icon: const Icon(Icons.send)),
                ],
              ),
            ),
          ),
        ],
      ),
    ),
  );

  Future<void> _load() async {
    final items = await ref
        .read(activitiesRepositoryProvider)
        .comments(widget.activityId);
    if (mounted) setState(() => _comments = items);
  }

  Future<void> _send() async {
    final body = _controller.text.trim();
    if (body.isEmpty) return;
    await ref
        .read(activitiesRepositoryProvider)
        .comment(widget.activityId, body);
    _controller.clear();
    await _load();
  }

  Future<void> _delete(String id) async {
    await ref
        .read(activitiesRepositoryProvider)
        .deleteComment(widget.activityId, id);
    await _load();
  }
}

class _HostControls extends ConsumerStatefulWidget {
  final ActivityItem activity;
  const _HostControls({required this.activity});

  @override
  ConsumerState<_HostControls> createState() => _HostControlsState();
}

class _HostControlsState extends ConsumerState<_HostControls> {
  bool _loading = false;

  @override
  Widget build(BuildContext context) {
    final transitions = switch (widget.activity.status) {
      'open' || 'full' => const {
        'locked': 'Lock',
        'ongoing': 'Start',
        'cancelled': 'Cancel',
      },
      'locked' => const {
        'open': 'Unlock',
        'ongoing': 'Start',
        'cancelled': 'Cancel',
      },
      'ongoing' => const {'completed': 'Complete', 'cancelled': 'Cancel'},
      _ => const <String, String>{},
    };
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          widget.activity.isHost ? 'Host controls' : 'Clan event controls',
          style: Theme.of(context).textTheme.titleLarge,
        ),
        const SizedBox(height: 10),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            OutlinedButton.icon(
              onPressed: _loading ? null : _editLobby,
              icon: const Icon(Icons.edit_outlined),
              label: const Text('Edit lobby'),
            ),
            if (widget.activity.isHost) ...[
              OutlinedButton.icon(
                onPressed: _loading ? null : _inviteFriend,
                icon: const Icon(Icons.person_add_outlined),
                label: const Text('Invite friend'),
              ),
              OutlinedButton.icon(
                onPressed: _loading ? null : _generateCode,
                icon: const Icon(Icons.key),
                label: const Text('Create join code'),
              ),
              OutlinedButton.icon(
                onPressed: _loading
                    ? null
                    : () => context.push(
                        AppRoutes.lobbyInvite,
                        extra: {
                          'id': widget.activity.id,
                          'title': widget.activity.title,
                        },
                      ),
                icon: const Icon(Icons.qr_code_2),
                label: const Text('QR & share link'),
              ),
            ],
            ...transitions.entries.map(
              (entry) => FilledButton.tonal(
                onPressed: _loading ? null : () => _transition(entry.key),
                child: Text(entry.value),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Future<void> _generateCode() async {
    setState(() => _loading = true);
    try {
      final code = await ref
          .read(activitiesRepositoryProvider)
          .createInvitationCode(widget.activity.id);
      if (!mounted) {
        return;
      }
      await showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Invitation code'),
          content: SelectableText(
            code,
            style: Theme.of(context).textTheme.headlineMedium,
          ),
          actions: [
            TextButton(
              onPressed: () async {
                await Clipboard.setData(ClipboardData(text: code));
                if (context.mounted) {
                  Navigator.pop(context);
                }
              },
              child: const Text('Copy'),
            ),
          ],
        ),
      );
    } catch (error) {
      _showError(error);
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _editLobby() async {
    final title = TextEditingController(text: widget.activity.title);
    final location = TextEditingController(text: widget.activity.locationName);
    final capacity = TextEditingController(
      text: widget.activity.maxParticipants.toString(),
    );
    final values = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Edit lobby'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: title,
                decoration: const InputDecoration(labelText: 'Title'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: location,
                decoration: const InputDecoration(labelText: 'Location'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: capacity,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Capacity'),
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
            onPressed: () => Navigator.pop(context, {
              'title': title.text.trim(),
              'location_name': location.text.trim(),
              'max_participants': int.tryParse(capacity.text),
            }),
            child: const Text('Save'),
          ),
        ],
      ),
    );
    title.dispose();
    location.dispose();
    capacity.dispose();
    if (values == null ||
        values.values.any((value) => value == null || value == '')) {
      return;
    }
    await _runHostAction(
      () => ref
          .read(activitiesRepositoryProvider)
          .update(widget.activity.id, values),
    );
  }

  Future<void> _inviteFriend() async {
    setState(() => _loading = true);
    try {
      final friends = await ref.read(friendsRepositoryProvider).friends();
      if (!mounted) {
        return;
      }
      final userId = await showDialog<String>(
        context: context,
        builder: (context) => SimpleDialog(
          title: const Text('Invite a friend'),
          children: friends.isEmpty
              ? [
                  const Padding(
                    padding: EdgeInsets.all(20),
                    child: Text('No friends available.'),
                  ),
                ]
              : friends
                    .map(
                      (friend) => SimpleDialogOption(
                        onPressed: () => Navigator.pop(context, friend.id),
                        child: Text(friend.displayName),
                      ),
                    )
                    .toList(),
        ),
      );
      if (userId != null) {
        await ref
            .read(activitiesRepositoryProvider)
            .invite(widget.activity.id, userId);
        if (mounted) {
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(const SnackBar(content: Text('Invitation sent.')));
        }
      }
    } catch (error) {
      _showError(error);
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _runHostAction(Future<dynamic> Function() action) async {
    setState(() => _loading = true);
    try {
      await action();
      ref.invalidate(activityDetailProvider(widget.activity.id));
      ref.invalidate(activityFeedProvider);
    } catch (error) {
      _showError(error);
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _transition(String status) async {
    if (['ongoing', 'completed', 'cancelled'].contains(status)) {
      final labels = {
        'ongoing': (
          'Start activity?',
          'Participants will see this activity as ongoing.',
          'Start',
        ),
        'completed': (
          'Complete activity?',
          'Attendance can still be corrected after completion.',
          'Complete',
        ),
        'cancelled': (
          'Cancel activity?',
          'Participants will no longer be able to join.',
          'Cancel activity',
        ),
      };
      final copy = labels[status]!;
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text(copy.$1),
          content: Text(copy.$2),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Back'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: Text(copy.$3),
            ),
          ],
        ),
      );
      if (confirmed != true) {
        return;
      }
    }
    setState(() => _loading = true);
    try {
      await ref
          .read(activitiesRepositoryProvider)
          .transition(widget.activity.id, status);
      ref.invalidate(activityDetailProvider(widget.activity.id));
      ref.invalidate(activityFeedProvider);
    } catch (error) {
      _showError(error);
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  void _showError([Object? error]) {
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            error == null
                ? 'Unable to update the activity.'
                : apiErrorMessage(
                    error,
                    fallback: 'Unable to update the activity.',
                  ),
          ),
        ),
      );
    }
  }
}

class _Participants extends ConsumerWidget {
  final ActivityItem activity;
  const _Participants({required this.activity});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final participants = ref.watch(activityParticipantsProvider(activity.id));
    return participants.when(
      loading: () => const LinearProgressIndicator(),
      error: (_, _) => TextButton(
        onPressed: () =>
            ref.invalidate(activityParticipantsProvider(activity.id)),
        child: const Text('Reload participants'),
      ),
      data: (items) => Column(
        children: items
            .map(
              (item) => ListTile(
                contentPadding: EdgeInsets.zero,
                leading: CircleAvatar(
                  child: Text(item.userName.characters.first.toUpperCase()),
                ),
                title: Text(item.userName),
                subtitle: Text(item.role == 'host' ? 'Host' : item.status),
                trailing: _trailing(context, ref, item),
              ),
            )
            .toList(),
      ),
    );
  }

  Widget? _trailing(
    BuildContext context,
    WidgetRef ref,
    ActivityParticipantItem item,
  ) {
    if (activity.canManage && item.status == 'requested') {
      return Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            tooltip: 'Reject',
            onPressed: () => _review(ref, item.id, false),
            icon: const Icon(Icons.close),
          ),
          IconButton(
            tooltip: 'Accept',
            onPressed: () => _review(ref, item.id, true),
            icon: const Icon(Icons.check),
          ),
        ],
      );
    }
    if (activity.canManage &&
        item.role != 'host' &&
        ['joined', 'attended', 'absent'].contains(item.status)) {
      final attendanceOpen = ['ongoing', 'completed'].contains(activity.status);
      return PopupMenuButton<String>(
        tooltip: 'Manage participant',
        onSelected: (action) => _manage(context, ref, item, action),
        itemBuilder: (_) => [
          if (attendanceOpen)
            const PopupMenuItem(
              value: 'attended',
              child: Text('Mark attended'),
            ),
          if (attendanceOpen)
            const PopupMenuItem(value: 'absent', child: Text('Mark absent')),
          if (activity.isHost)
            const PopupMenuItem(value: 'transfer', child: Text('Make host')),
          const PopupMenuItem(value: 'remove', child: Text('Remove')),
          const PopupMenuItem(value: 'ban', child: Text('Ban')),
        ],
      );
    }
    return item.status == 'waitlisted'
        ? Text('#${item.waitlistPosition}')
        : null;
  }

  Future<void> _review(WidgetRef ref, String participantId, bool accept) async {
    final repository = ref.read(activitiesRepositoryProvider);
    if (accept) {
      await repository.accept(activity.id, participantId);
    } else {
      await repository.reject(activity.id, participantId);
    }
    ref.invalidate(activityParticipantsProvider(activity.id));
    ref.invalidate(activityDetailProvider(activity.id));
    ref.invalidate(activityFeedProvider);
  }

  Future<void> _manage(
    BuildContext context,
    WidgetRef ref,
    ActivityParticipantItem item,
    String action,
  ) async {
    if (action == 'attended' || action == 'absent') {
      try {
        await ref
            .read(activitiesRepositoryProvider)
            .markAttendance(activity.id, item.id, action);
        ref.invalidate(activityParticipantsProvider(activity.id));
        ref.invalidate(activityDetailProvider(activity.id));
      } catch (_) {
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Unable to mark attendance.')),
          );
        }
      }
      return;
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(
          action == 'transfer'
              ? 'Transfer ownership?'
              : action == 'ban'
              ? 'Ban participant?'
              : 'Remove participant?',
        ),
        content: Text('Apply this action to ${item.userName}?'),
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
    if (confirmed != true) {
      return;
    }
    final repository = ref.read(activitiesRepositoryProvider);
    try {
      if (action == 'transfer') {
        await repository.transferOwnership(activity.id, item.userId);
      } else {
        await repository.removeParticipant(
          activity.id,
          item.id,
          ban: action == 'ban',
        );
      }
      ref.invalidate(activityParticipantsProvider(activity.id));
      ref.invalidate(activityDetailProvider(activity.id));
      ref.invalidate(activityFeedProvider);
    } catch (_) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Unable to manage this participant.')),
        );
      }
    }
  }
}

class _ParticipationAction extends ConsumerStatefulWidget {
  final ActivityItem activity;
  const _ParticipationAction({required this.activity});

  @override
  ConsumerState<_ParticipationAction> createState() =>
      _ParticipationActionState();
}

class _ParticipationActionState extends ConsumerState<_ParticipationAction> {
  bool _loading = false;

  @override
  Widget build(BuildContext context) {
    final activity = widget.activity;
    if (activity.isHost) {
      return const FilledButton(
        onPressed: null,
        child: Text('You are the host'),
      );
    }
    final participation = activity.viewerParticipation;
    if (participation == 'joined' ||
        participation == 'requested' ||
        participation == 'waitlisted') {
      return Column(
        children: [
          Text('Status: ${participation!.toUpperCase()}'),
          const SizedBox(height: 8),
          OutlinedButton(
            onPressed: _loading ? null : _leave,
            child: const Text('Leave activity'),
          ),
        ],
      );
    }
    final joinable =
        ['open', 'full'].contains(activity.status) &&
        activity.startsAt.isAfter(DateTime.now());
    return FilledButton(
      onPressed: _loading || !joinable ? null : _join,
      child: Text(
        !joinable
            ? 'Activity is not accepting participants'
            : _loading
            ? 'Joining…'
            : activity.requireApproval
            ? 'Request to join'
            : 'Join activity',
      ),
    );
  }

  Future<void> _join() async {
    String? password;
    if (widget.activity.passwordProtected) {
      final controller = TextEditingController();
      password = await showDialog<String>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Activity password'),
          content: TextField(
            controller: controller,
            obscureText: true,
            autofocus: true,
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, controller.text),
              child: const Text('Join'),
            ),
          ],
        ),
      );
      controller.dispose();
      if (password == null) {
        return;
      }
    }
    await _run(
      () => ref
          .read(activitiesRepositoryProvider)
          .join(widget.activity.id, password: password),
    );
  }

  Future<void> _leave() async {
    await _run(
      () => ref.read(activitiesRepositoryProvider).leave(widget.activity.id),
    );
  }

  Future<void> _run(Future<dynamic> Function() action) async {
    setState(() => _loading = true);
    try {
      await action();
      ref.invalidate(activityDetailProvider(widget.activity.id));
      ref.invalidate(activityFeedProvider);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              apiErrorMessage(
                error,
                fallback: 'Unable to update participation.',
              ),
            ),
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }
}

class _Info extends StatelessWidget {
  final IconData icon;
  final String text;
  const _Info({required this.icon, required this.text});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 7),
    child: Row(
      children: [
        Icon(icon),
        const SizedBox(width: 12),
        Expanded(child: Text(text)),
      ],
    ),
  );
}

class _RatingAction extends ConsumerStatefulWidget {
  const _RatingAction({required this.activity});
  final ActivityItem activity;

  @override
  ConsumerState<_RatingAction> createState() => _RatingActionState();
}

class _RatingActionState extends ConsumerState<_RatingAction> {
  bool _loading = false;

  @override
  Widget build(BuildContext context) => OutlinedButton.icon(
    onPressed: _loading ? null : _open,
    icon: const Icon(Icons.star_outline),
    label: Text(_loading ? 'Loading…' : 'Rate players'),
  );

  Future<void> _open() async {
    setState(() => _loading = true);
    try {
      final targets = await ref
          .read(activitiesRepositoryProvider)
          .ratingTargets(widget.activity.id);
      if (!mounted) return;
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        builder: (context) => SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Rate players',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 12),
                if (targets.isEmpty) const Text('No eligible players to rate.'),
                ...targets.map(
                  (target) => ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text(target.displayName),
                    trailing: target.rated
                        ? const Chip(label: Text('Rated'))
                        : FilledButton.tonal(
                            onPressed: () => _rate(context, target),
                            child: const Text('Rate'),
                          ),
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              apiErrorMessage(error, fallback: 'Unable to load ratings.'),
            ),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _rate(
    BuildContext sheetContext,
    ActivityRatingTarget target,
  ) async {
    var sportsmanship = 5.0;
    var skill = 5.0;
    var reliability = 5.0;
    final comment = TextEditingController();
    final submitted = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text('Rate ${target.displayName}'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                _score(
                  'Sportsmanship',
                  sportsmanship,
                  (value) => setDialogState(() => sportsmanship = value),
                ),
                _score(
                  'Skill',
                  skill,
                  (value) => setDialogState(() => skill = value),
                ),
                _score(
                  'Reliability',
                  reliability,
                  (value) => setDialogState(() => reliability = value),
                ),
                TextField(
                  controller: comment,
                  maxLength: 500,
                  decoration: const InputDecoration(
                    labelText: 'Comment (optional)',
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Submit'),
            ),
          ],
        ),
      ),
    );
    if (submitted == true) {
      try {
        await ref
            .read(activitiesRepositoryProvider)
            .rate(
              widget.activity.id,
              target.id,
              sportsmanship: sportsmanship.round(),
              skill: skill.round(),
              reliability: reliability.round(),
              comment: comment.text,
            );
        if (sheetContext.mounted) Navigator.pop(sheetContext);
        if (mounted) {
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(const SnackBar(content: Text('Rating submitted.')));
        }
      } catch (error) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                apiErrorMessage(error, fallback: 'Unable to submit rating.'),
              ),
            ),
          );
        }
      }
    }
    comment.dispose();
  }

  Widget _score(String label, double value, ValueChanged<double> onChanged) =>
      Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('$label: ${value.round()}/5'),
          Slider(
            value: value,
            min: 1,
            max: 5,
            divisions: 4,
            onChanged: onChanged,
          ),
        ],
      );
}

class _PostMatchStoryAction extends ConsumerStatefulWidget {
  const _PostMatchStoryAction({required this.activity});
  final ActivityItem activity;

  @override
  ConsumerState<_PostMatchStoryAction> createState() =>
      _PostMatchStoryActionState();
}

class _PostMatchStoryActionState extends ConsumerState<_PostMatchStoryAction> {
  bool _uploading = false;

  @override
  Widget build(BuildContext context) => OutlinedButton.icon(
    onPressed: _uploading ? null : _create,
    icon: const Icon(Icons.auto_stories_outlined),
    label: Text(_uploading ? 'Publishing…' : 'Share match story'),
  );

  Future<void> _create() async {
    final picked = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: const [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'gif',
        'mp4',
        'mov',
        'webm',
      ],
      withData: true,
    );
    if (picked == null || !mounted) return;
    final caption = TextEditingController(text: widget.activity.title);
    final submit = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Share match story'),
        content: TextField(
          controller: caption,
          maxLength: 500,
          decoration: const InputDecoration(labelText: 'Caption'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Share'),
          ),
        ],
      ),
    );
    if (submit != true) {
      caption.dispose();
      return;
    }
    setState(() => _uploading = true);
    try {
      await ref
          .read(storiesRepositoryProvider)
          .create(
            picked.files.single,
            caption: caption.text,
            activityId: widget.activity.id,
          );
      ref.invalidate(storiesProvider);
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Match story published.')));
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              apiErrorMessage(error, fallback: 'Unable to publish story.'),
            ),
          ),
        );
      }
    } finally {
      caption.dispose();
      if (mounted) setState(() => _uploading = false);
    }
  }
}
