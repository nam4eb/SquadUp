import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/friends_repository.dart';
import '../domain/social_user.dart';

class FriendsState {
  final List<SocialUser> discover;
  final List<SocialUser> friends;
  final List<FriendRequestItem> incoming;
  final List<SocialUser> blocked;

  const FriendsState({
    required this.discover,
    required this.friends,
    required this.incoming,
    required this.blocked,
  });
}

class FriendsController extends AsyncNotifier<FriendsState> {
  FriendsRepository get _repository => ref.read(friendsRepositoryProvider);

  @override
  Future<FriendsState> build() => _load();

  Future<FriendsState> _load([String query = '']) async {
    final results = await Future.wait([
      _repository.search(query),
      _repository.friends(),
      _repository.incoming(),
      _repository.blocked(),
    ]);
    return FriendsState(
      discover: results[0] as List<SocialUser>,
      friends: results[1] as List<SocialUser>,
      incoming: results[2] as List<FriendRequestItem>,
      blocked: results[3] as List<SocialUser>,
    );
  }

  Future<void> refresh([String query = '']) async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() => _load(query));
  }

  Future<void> send(String userId) async {
    await _repository.send(userId);
    await refresh();
  }

  Future<void> accept(String id) async {
    await _repository.accept(id);
    await refresh();
  }

  Future<void> reject(String id) async {
    await _repository.reject(id);
    await refresh();
  }

  Future<void> remove(String id) async {
    await _repository.remove(id);
    await refresh();
  }

  Future<void> block(String id) async {
    await _repository.block(id);
    await refresh();
  }

  Future<void> unblock(String id) async {
    await _repository.unblock(id);
    await refresh();
  }
}

final friendsControllerProvider =
    AsyncNotifierProvider<FriendsController, FriendsState>(
      FriendsController.new,
    );
