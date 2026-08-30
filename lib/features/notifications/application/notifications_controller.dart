import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/notifications_repository.dart';
import '../domain/app_notification.dart';

class NotificationsState {
  final List<AppNotification> items;
  final bool unreadOnly;

  const NotificationsState({required this.items, required this.unreadOnly});
}

class NotificationsController extends AsyncNotifier<NotificationsState> {
  NotificationsRepository get _repository =>
      ref.read(notificationsRepositoryProvider);

  @override
  Future<NotificationsState> build() => _load(false);

  Future<NotificationsState> _load(bool unreadOnly) async => NotificationsState(
    items: await _repository.list(unreadOnly: unreadOnly),
    unreadOnly: unreadOnly,
  );

  Future<void> refresh({bool? unreadOnly}) async {
    final filter = unreadOnly ?? state.asData?.value.unreadOnly ?? false;
    state = const AsyncLoading();
    state = await AsyncValue.guard(() => _load(filter));
  }

  Future<void> markRead(String id) async {
    await _repository.markRead(id);
    await refresh();
  }

  Future<void> markAllRead() async {
    await _repository.markAllRead();
    await refresh();
  }
}

final notificationsControllerProvider =
    AsyncNotifierProvider<NotificationsController, NotificationsState>(
      NotificationsController.new,
    );
