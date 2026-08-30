import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../friends/data/friends_repository.dart';
import '../data/chat_repository.dart';
import '../domain/chat_models.dart';

class GroupConversationSettingsScreen extends ConsumerStatefulWidget {
  final String conversationId;

  const GroupConversationSettingsScreen({
    super.key,
    required this.conversationId,
  });

  @override
  ConsumerState<GroupConversationSettingsScreen> createState() =>
      _GroupConversationSettingsScreenState();
}

class _GroupConversationSettingsScreenState
    extends ConsumerState<GroupConversationSettingsScreen> {
  ConversationItem? _group;
  bool _loading = true;

  bool get _isOwner => _group?.currentMemberRole == 'owner';
  bool get _canManage => _isOwner || _group?.currentMemberRole == 'admin';

  @override
  void initState() {
    super.initState();
    _refresh();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Group settings'),
      actions: [
        if (_canManage)
          IconButton(
            tooltip: 'Edit group',
            onPressed: _edit,
            icon: const Icon(Icons.edit_outlined),
          ),
      ],
    ),
    floatingActionButton: _canManage
        ? FloatingActionButton(
            onPressed: _addMember,
            child: const Icon(Icons.person_add_outlined),
          )
        : null,
    body: _loading
        ? const Center(child: CircularProgressIndicator())
        : ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (_group!.avatarUrl != null)
                CircleAvatar(
                  radius: 42,
                  backgroundImage: NetworkImage(_group!.avatarUrl!),
                ),
              Center(
                child: Text(
                  _group!.name,
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
              ),
              if (_group!.description?.isNotEmpty == true)
                Center(child: Text(_group!.description!)),
              const SizedBox(height: 20),
              ..._group!.members.map(_memberTile),
              const SizedBox(height: 24),
              OutlinedButton.icon(
                onPressed: _isOwner ? null : _leave,
                icon: const Icon(Icons.logout),
                label: Text(
                  _isOwner
                      ? 'Transfer ownership before leaving'
                      : 'Leave group',
                ),
              ),
            ],
          ),
  );

  Widget _memberTile(ConversationMemberItem member) => ListTile(
    leading: const CircleAvatar(child: Icon(Icons.person_outline)),
    title: Text(member.displayName),
    subtitle: Text(member.role),
    trailing: !_canManage || member.role == 'owner'
        ? null
        : PopupMenuButton<String>(
            onSelected: (action) => _memberAction(member, action),
            itemBuilder: (_) => [
              if (_isOwner)
                PopupMenuItem(
                  value: member.role == 'admin' ? 'member' : 'admin',
                  child: Text(
                    member.role == 'admin'
                        ? 'Demote to member'
                        : 'Promote to admin',
                  ),
                ),
              if (_isOwner)
                const PopupMenuItem(
                  value: 'owner',
                  child: Text('Transfer ownership'),
                ),
              const PopupMenuItem(value: 'remove', child: Text('Remove')),
            ],
          ),
  );

  Future<void> _refresh() async {
    final group = await ref
        .read(chatRepositoryProvider)
        .conversation(widget.conversationId);
    if (mounted) {
      setState(() {
        _group = group;
        _loading = false;
      });
    }
  }

  Future<void> _edit() async {
    final name = TextEditingController(text: _group!.name);
    final description = TextEditingController(text: _group!.description);
    PlatformFile? avatar;
    final save = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Edit group'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: name,
                decoration: const InputDecoration(labelText: 'Name'),
              ),
              TextField(
                controller: description,
                decoration: const InputDecoration(labelText: 'Description'),
              ),
              TextButton.icon(
                onPressed: () async {
                  final picked = await FilePicker.pickFiles(
                    type: FileType.image,
                    withData: true,
                  );
                  if (picked != null) {
                    setDialogState(() => avatar = picked.files.single);
                  }
                },
                icon: const Icon(Icons.image_outlined),
                label: Text(avatar?.name ?? 'Choose avatar'),
              ),
            ],
          ),
          actions: [
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Save'),
            ),
          ],
        ),
      ),
    );
    if (save == true) {
      await ref
          .read(chatRepositoryProvider)
          .updateGroup(
            widget.conversationId,
            name: name.text.trim(),
            description: description.text.trim(),
            avatar: avatar,
          );
      ref.invalidate(conversationsProvider);
      await _refresh();
    }
    name.dispose();
    description.dispose();
  }

  Future<void> _addMember() async {
    final existing = _group!.members.map((item) => item.userId).toSet();
    final friends = (await ref.read(friendsRepositoryProvider).friends())
        .where((friend) => !existing.contains(friend.id))
        .toList();
    if (!mounted) return;
    final id = await showDialog<String>(
      context: context,
      builder: (context) => SimpleDialog(
        title: const Text('Add member'),
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
    if (id != null) {
      await ref
          .read(chatRepositoryProvider)
          .addGroupMember(widget.conversationId, id);
      await _refresh();
    }
  }

  Future<void> _memberAction(
    ConversationMemberItem member,
    String action,
  ) async {
    final repository = ref.read(chatRepositoryProvider);
    if (action == 'remove') {
      await repository.removeGroupMember(widget.conversationId, member.id);
    } else if (action == 'owner') {
      await repository.transferGroupOwnership(widget.conversationId, member.id);
    } else {
      await repository.setGroupRole(widget.conversationId, member.id, action);
    }
    await _refresh();
  }

  Future<void> _leave() async {
    await ref.read(chatRepositoryProvider).leaveGroup(widget.conversationId);
    ref.invalidate(conversationsProvider);
    if (mounted) context.pop();
  }
}
