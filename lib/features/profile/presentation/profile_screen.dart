import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../routes/app_routes.dart';
import '../../activities/data/activities_repository.dart';
import '../../activities/domain/activity_item.dart';
import '../../authentication/data/auth_repository.dart';
import '../../authentication/domain/auth_user.dart';

class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});

  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  static const _scopes = <String, String>{
    'upcoming': 'Upcoming',
    'created': 'Created',
    'joined': 'Joined',
    'completed': 'Completed',
    'cancelled': 'Cancelled',
  };
  String _scope = 'upcoming';
  AuthUser? _user;
  List<ActivityItem> _activities = const [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Profile'),
      actions: [
        IconButton(
          tooltip: 'Settings',
          onPressed: () => context.push(AppRoutes.accountSettings),
          icon: const Icon(Icons.settings_outlined),
        ),
      ],
    ),
    body: RefreshIndicator(
      onRefresh: _load,
      child: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
          ? ListView(
              children: [
                const SizedBox(height: 160),
                Center(child: Text(_error!)),
                TextButton(onPressed: _load, child: const Text('Try again')),
              ],
            )
          : ListView(
              padding: const EdgeInsets.only(bottom: 24),
              children: [
                _ProfileHeader(user: _user!),
                const SizedBox(height: 16),
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: Row(
                    children: _scopes.entries
                        .map(
                          (entry) => Padding(
                            padding: const EdgeInsets.only(right: 8),
                            child: ChoiceChip(
                              label: Text(entry.value),
                              selected: _scope == entry.key,
                              onSelected: (_) => _changeScope(entry.key),
                            ),
                          ),
                        )
                        .toList(),
                  ),
                ),
                if (_activities.isEmpty)
                  const Padding(
                    padding: EdgeInsets.all(32),
                    child: Center(
                      child: Text('No activities in this section.'),
                    ),
                  )
                else
                  ..._activities.map(
                    (activity) => Card(
                      margin: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                      child: ListTile(
                        leading: CircleAvatar(
                          child: Text('${activity.joinedCount}'),
                        ),
                        title: Text(activity.title),
                        subtitle: Text(
                          '${activity.topicName} · ${_date(activity.startsAt)}\n${activity.locationName}',
                        ),
                        isThreeLine: true,
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () => context.push(
                          AppRoutes.activityDetail,
                          extra: activity.id,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
    ),
  );

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final values = await Future.wait([
        ref.read(authRepositoryProvider).me(),
        ref.read(activitiesRepositoryProvider).history(_scope),
      ]);
      if (mounted) {
        setState(() {
          _user = values[0] as AuthUser;
          _activities = values[1] as List<ActivityItem>;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = 'Unable to load your profile.';
          _loading = false;
        });
      }
    }
  }

  Future<void> _changeScope(String value) async {
    if (_scope == value) return;
    setState(() => _scope = value);
    await _load();
  }

  static String _date(DateTime value) =>
      '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')}/${value.year}';
}

class _ProfileHeader extends StatelessWidget {
  final AuthUser user;

  const _ProfileHeader({required this.user});

  @override
  Widget build(BuildContext context) => Column(
    children: [
      SizedBox(
        height: 190,
        child: Stack(
          alignment: Alignment.bottomCenter,
          children: [
            Align(
              alignment: Alignment.topCenter,
              child: Container(
                height: 145,
                width: double.infinity,
                color: Theme.of(context).colorScheme.primaryContainer,
                child: user.coverUrl == null
                    ? null
                    : Image.network(user.coverUrl!, fit: BoxFit.cover),
              ),
            ),
            CircleAvatar(
              radius: 52,
              backgroundColor: Theme.of(context).colorScheme.surface,
              child: CircleAvatar(
                radius: 48,
                foregroundImage: user.avatarUrl == null
                    ? null
                    : NetworkImage(user.avatarUrl!),
                child: user.avatarUrl == null
                    ? const Icon(Icons.person, size: 48)
                    : null,
              ),
            ),
          ],
        ),
      ),
      Text(user.displayName, style: Theme.of(context).textTheme.headlineSmall),
      Text('@${user.username}'),
      if (user.bio?.isNotEmpty == true)
        Padding(
          padding: const EdgeInsets.fromLTRB(24, 8, 24, 0),
          child: Text(user.bio!, textAlign: TextAlign.center),
        ),
      if (user.location?.isNotEmpty == true)
        Padding(
          padding: const EdgeInsets.only(top: 6),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.location_on_outlined, size: 16),
              const SizedBox(width: 4),
              Text(user.location!),
            ],
          ),
        ),
    ],
  );
}
