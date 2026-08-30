import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/auth_repository.dart';
import '../domain/auth_user.dart';
import '../../../core/notifications/push_notification_service.dart';

class AuthState {
  final AuthUser? user;
  final bool isLoading;
  final String? error;

  const AuthState({this.user, this.isLoading = false, this.error});
}

class AuthController extends Notifier<AuthState> {
  @override
  AuthState build() => const AuthState();

  Future<AuthUser> authenticate({
    required bool isLogin,
    required String email,
    required String password,
    String? username,
    String? displayName,
  }) async {
    state = const AuthState(isLoading: true);
    try {
      final repository = ref.read(authRepositoryProvider);
      final user = isLogin
          ? await repository.login(email: email, password: password)
          : await repository.register(
              username: username!,
              displayName: displayName!,
              email: email,
              password: password,
            );
      state = AuthState(user: user);
      ref.read(pushNotificationServiceProvider).start();
      return user;
    } on AuthFailure catch (error) {
      state = AuthState(error: error.message);
      rethrow;
    }
  }
}

final authControllerProvider = NotifierProvider<AuthController, AuthState>(
  AuthController.new,
);
