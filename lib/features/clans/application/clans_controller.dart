import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/clans_repository.dart';
import '../domain/clan_item.dart';

class ClansController extends AsyncNotifier<List<ClanItem>> {
  ClansRepository get _repository => ref.read(clansRepositoryProvider);

  @override
  Future<List<ClanItem>> build() => _repository.list();

  Future<void> refresh() async {
    state = await AsyncValue.guard(_repository.list);
  }

  Future<void> create(String name, String slug) async {
    await _repository.create(name: name, slug: slug);
    await refresh();
  }

  Future<void> join(String id) async {
    await _repository.join(id);
    await refresh();
  }

  Future<void> leave(String id) async {
    await _repository.leave(id);
    await refresh();
  }
}

final clansControllerProvider =
    AsyncNotifierProvider<ClansController, List<ClanItem>>(ClansController.new);
