import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../routes/app_routes.dart';
import '../../../core/network/api_client.dart';
import '../data/auth_repository.dart';
import '../domain/auth_user.dart';

class AccountSettingsScreen extends ConsumerStatefulWidget {
  const AccountSettingsScreen({super.key});

  @override
  ConsumerState<AccountSettingsScreen> createState() =>
      _AccountSettingsScreenState();
}

class _AccountSettingsScreenState extends ConsumerState<AccountSettingsScreen> {
  AuthUser? _user;
  List<DeviceSession> _sessions = const [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _refresh();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Account & settings')),
    body: _loading
        ? const Center(child: CircularProgressIndicator())
        : ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (_user!.coverUrl != null)
                Image.network(_user!.coverUrl!, height: 140, fit: BoxFit.cover),
              CircleAvatar(
                radius: 42,
                foregroundImage: _user!.avatarUrl == null
                    ? null
                    : NetworkImage(_user!.avatarUrl!),
                child: _user!.avatarUrl == null
                    ? const Icon(Icons.person, size: 42)
                    : null,
              ),
              Center(
                child: Text(
                  _user!.displayName,
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
              ),
              Center(child: Text('@${_user!.username} · ${_user!.email}')),
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: _editProfile,
                icon: const Icon(Icons.edit_outlined),
                label: const Text('Edit profile and media'),
              ),
              OutlinedButton.icon(
                onPressed: () => context.push(AppRoutes.profile),
                icon: const Icon(Icons.person_outline),
                label: const Text('View activity profile'),
              ),
              OutlinedButton.icon(
                onPressed: () => context.push(AppRoutes.redeemInvitation),
                icon: const Icon(Icons.vpn_key_outlined),
                label: const Text('Redeem activity invitation'),
              ),
              const Divider(height: 32),
              Text(
                'Device sessions',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              ..._sessions.map(
                (session) => ListTile(
                  leading: Icon(
                    session.isCurrent ? Icons.smartphone : Icons.devices,
                  ),
                  title: Text(session.deviceName),
                  subtitle: Text(
                    session.isCurrent
                        ? 'Current session'
                        : 'Created ${session.createdAt.toLocal()}',
                  ),
                  trailing: IconButton(
                    tooltip: 'Revoke session',
                    onPressed: () => _revoke(session),
                    icon: const Icon(Icons.logout),
                  ),
                ),
              ),
              const Divider(height: 32),
              OutlinedButton(
                onPressed: _logoutAll,
                child: const Text('Sign out all devices'),
              ),
              TextButton(
                onPressed: _deactivate,
                child: Text(
                  'Deactivate account',
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            ],
          ),
  );

  Future<void> _refresh() async {
    final repository = ref.read(authRepositoryProvider);
    final values = await Future.wait([repository.me(), repository.sessions()]);
    if (mounted) {
      setState(() {
        _user = values[0] as AuthUser;
        _sessions = values[1] as List<DeviceSession>;
        _loading = false;
      });
    }
  }

  Future<void> _editProfile() async {
    final name = TextEditingController(text: _user!.displayName);
    final bio = TextEditingController(text: _user!.bio);
    final location = TextEditingController(text: _user!.location);
    PlatformFile? avatar;
    PlatformFile? cover;
    final save = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Edit profile'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: name,
                  decoration: const InputDecoration(labelText: 'Display name'),
                ),
                TextField(
                  controller: bio,
                  decoration: const InputDecoration(labelText: 'Bio'),
                ),
                TextField(
                  controller: location,
                  decoration: const InputDecoration(labelText: 'Location'),
                ),
                TextButton(
                  onPressed: () async {
                    final result = await FilePicker.pickFiles(
                      type: FileType.image,
                      withData: true,
                    );
                    if (result != null) {
                      setDialogState(() => avatar = result.files.single);
                    }
                  },
                  child: Text(avatar?.name ?? 'Choose avatar'),
                ),
                TextButton(
                  onPressed: () async {
                    final result = await FilePicker.pickFiles(
                      type: FileType.image,
                      withData: true,
                    );
                    if (result != null) {
                      setDialogState(() => cover = result.files.single);
                    }
                  },
                  child: Text(cover?.name ?? 'Choose cover'),
                ),
              ],
            ),
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
          .read(authRepositoryProvider)
          .updateProfile(
            displayName: name.text.trim(),
            bio: bio.text.trim(),
            location: location.text.trim(),
            avatar: avatar,
            cover: cover,
          );
      await _refresh();
    }
    name.dispose();
    bio.dispose();
    location.dispose();
  }

  Future<void> _revoke(DeviceSession session) async {
    await ref.read(authRepositoryProvider).revokeSession(session.id);
    if (session.isCurrent && mounted) {
      await ref.read(authTokenStoreProvider).delete();
      if (!mounted) return;
      context.go(AppRoutes.initial);
    } else {
      await _refresh();
    }
  }

  Future<void> _logoutAll() async {
    await ref.read(authRepositoryProvider).logoutAll();
    if (mounted) context.go(AppRoutes.initial);
  }

  Future<void> _deactivate() async {
    final controller = TextEditingController();
    final password = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Deactivate account?'),
        content: TextField(
          controller: controller,
          obscureText: true,
          decoration: const InputDecoration(labelText: 'Password'),
        ),
        actions: [
          FilledButton(
            onPressed: () => Navigator.pop(context, controller.text),
            child: const Text('Deactivate'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (password != null) {
      await ref.read(authRepositoryProvider).deactivate(password);
      if (mounted) context.go(AppRoutes.initial);
    }
  }
}
