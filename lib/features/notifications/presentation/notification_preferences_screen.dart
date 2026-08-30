import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/notifications_repository.dart';
import '../domain/notification_preference.dart';

class NotificationPreferencesScreen extends ConsumerStatefulWidget {
  const NotificationPreferencesScreen({super.key});

  @override
  ConsumerState<NotificationPreferencesScreen> createState() =>
      _NotificationPreferencesScreenState();
}

class _NotificationPreferencesScreenState
    extends ConsumerState<NotificationPreferencesScreen> {
  List<NotificationPreference>? _items;
  bool _saving = false;

  @override
  Widget build(BuildContext context) {
    final preferences = ref.watch(notificationPreferencesProvider);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Notification preferences'),
        actions: [
          TextButton(
            onPressed: _saving || _items == null ? null : _save,
            child: const Text('Save'),
          ),
        ],
      ),
      body: preferences.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (_, _) =>
            const Center(child: Text('Unable to load preferences.')),
        data: (data) {
          _items ??= data;
          return ListView.builder(
            itemCount: _items!.length,
            itemBuilder: (context, index) {
              final item = _items![index];
              return Card(
                margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                child: Column(
                  children: [
                    ListTile(title: Text(_label(item.type))),
                    SwitchListTile(
                      title: const Text('In-app'),
                      value: item.inAppEnabled,
                      onChanged: (value) =>
                          _replace(index, item.copyWith(inAppEnabled: value)),
                    ),
                    SwitchListTile(
                      title: const Text('Push'),
                      subtitle: const Text('Used when FCM is configured'),
                      value: item.pushEnabled,
                      onChanged: (value) =>
                          _replace(index, item.copyWith(pushEnabled: value)),
                    ),
                  ],
                ),
              );
            },
          );
        },
      ),
    );
  }

  void _replace(int index, NotificationPreference value) {
    setState(() => _items![index] = value);
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      _items = await ref
          .read(notificationsRepositoryProvider)
          .updatePreferences(_items!);
      ref.invalidate(notificationPreferencesProvider);
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Preferences saved.')));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  String _label(String type) => type
      .split('_')
      .map((word) => '${word[0].toUpperCase()}${word.substring(1)}')
      .join(' ');
}
