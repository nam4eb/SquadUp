import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../core/network/api_client.dart';
import '../features/authentication/data/auth_repository.dart';
import '../features/activities/data/activities_repository.dart';
import '../features/chat/data/chat_repository.dart';
import '../presentation/sign_up_login_screen/sign_up_login_screen.dart';
import '../presentation/home_feed_screen/home_feed_screen.dart';
import '../presentation/activity_detail_screen/activity_detail_screen.dart';
import '../widgets/app_scaffold.dart';
import '../features/friends/presentation/friends_screen.dart';
import '../features/notifications/presentation/notifications_screen.dart';
import '../features/notifications/presentation/notification_preferences_screen.dart';
import '../features/activities/presentation/create_activity_screen.dart';
import '../features/activities/presentation/redeem_invitation_screen.dart';
import '../features/activities/presentation/explore_screen.dart';
import '../features/activities/presentation/lobby_invite_screen.dart';
import '../features/activities/presentation/qr_join_screen.dart';
import '../features/clans/presentation/clans_screen.dart';
import '../features/clans/presentation/clan_detail_screen.dart';
import '../features/clans/presentation/clan_chat_screen.dart';
import '../features/chat/domain/chat_models.dart';
import '../features/chat/presentation/chat_screen.dart';
import '../features/chat/presentation/conversation_screen.dart';
import '../features/chat/presentation/group_conversation_settings_screen.dart';
import '../features/authentication/presentation/account_settings_screen.dart';
import '../features/profile/presentation/profile_screen.dart';
import '../features/onboarding/presentation/onboarding_screen.dart';

class AppRoutes {
  AppRoutes._();

  static const String initial = '/';
  static const String signUpLogin = '/sign-up-login-screen';
  static const String homeFeed = '/home-feed-screen';
  static const String activityDetail = '/activity-detail-screen';
  static const String friends = '/friends';
  static const String notifications = '/notifications';
  static const String notificationPreferences = '/notification-preferences';
  static const String createActivity = '/activities/create';
  static const String redeemInvitation = '/activity-invitations/redeem';
  static const String lobbyInvite = '/activities/lobby-invite';
  static const String qrJoin = '/activities/scan-qr';
  static const String clans = '/clans';
  static const String clanDetail = '/clan-detail';
  static const String clanChat = '/clan-chat';
  static const String chat = '/chat';
  static const String conversation = '/conversation';
  static const String groupConversationSettings =
      '/group-conversation-settings';
  static const String accountSettings = '/account-settings';
  static const String profile = '/profile';
  static const String explore = '/explore';
  static const String onboarding = '/onboarding';
}

final appRouterProvider = Provider<GoRouter>((ref) {
  final tokens = ref.watch(authTokenStoreProvider);
  void resetSessionData() {
    ref.invalidate(currentUserProvider);
    ref.invalidate(activityFeedProvider);
    ref.invalidate(activityDetailProvider);
    ref.invalidate(activityParticipantsProvider);
    ref.invalidate(conversationsProvider);
    ref.invalidate(messagesProvider);
  }

  tokens.addListener(resetSessionData);
  final router = GoRouter(
    refreshListenable: tokens,
    redirect: (context, state) async {
      final token = await tokens.read();
      final isLogin =
          state.matchedLocation == AppRoutes.initial ||
          state.matchedLocation == AppRoutes.signUpLogin;
      if (token == null) {
        if (isLogin) return null;
        final returnTo = Uri.encodeQueryComponent(state.uri.toString());
        return '${AppRoutes.signUpLogin}?returnTo=$returnTo';
      }
      if (isLogin) {
        try {
          final user = await ref.read(currentUserProvider.future);
          if (!user.onboardingCompleted) return AppRoutes.onboarding;
          final returnTo = state.uri.queryParameters['returnTo'];
          if (returnTo != null &&
              returnTo.startsWith('/') &&
              !returnTo.startsWith('//')) {
            return returnTo;
          }
          return AppRoutes.homeFeed;
        } catch (_) {
          // Keep login available when a stored session cannot be restored.
          return null;
        }
      }
      return null;
    },
    initialLocation: AppRoutes.initial,
    routes: [
      GoRoute(
        path: AppRoutes.initial,
        pageBuilder: (context, state) => CustomTransitionPage(
          key: state.pageKey,
          child: const SignUpLoginScreen(),
          transitionDuration: const Duration(milliseconds: 280),
          transitionsBuilder: (context, animation, secondaryAnimation, child) {
            return FadeTransition(
              opacity: CurvedAnimation(
                parent: animation,
                curve: Curves.easeOutCubic,
              ),
              child: child,
            );
          },
        ),
      ),
      GoRoute(
        path: AppRoutes.signUpLogin,
        pageBuilder: (context, state) => CustomTransitionPage(
          key: state.pageKey,
          child: const SignUpLoginScreen(),
          transitionDuration: const Duration(milliseconds: 280),
          transitionsBuilder: (context, animation, secondaryAnimation, child) {
            return SlideTransition(
              position:
                  Tween<Offset>(
                    begin: const Offset(0.04, 0),
                    end: Offset.zero,
                  ).animate(
                    CurvedAnimation(
                      parent: animation,
                      curve: Curves.easeOutCubic,
                    ),
                  ),
              child: FadeTransition(opacity: animation, child: child),
            );
          },
        ),
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) {
          return AppScaffold(navigationShell: navigationShell);
        },
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.homeFeed,
                pageBuilder: (context, state) =>
                    const NoTransitionPage(child: HomeFeedScreen()),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.friends,
                pageBuilder: (context, state) =>
                    const NoTransitionPage(child: FriendsScreen()),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.chat,
                pageBuilder: (context, state) =>
                    const NoTransitionPage(child: ChatScreen()),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.clans,
                pageBuilder: (context, state) =>
                    const NoTransitionPage(child: ClansScreen()),
              ),
            ],
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.onboarding,
        builder: (context, state) => const OnboardingScreen(),
      ),
      GoRoute(
        path: AppRoutes.redeemInvitation,
        builder: (context, state) => const RedeemInvitationScreen(),
      ),
      GoRoute(
        path: '/invite',
        builder: (_, state) => RedeemInvitationScreen(
          initialSecret: state.uri.queryParameters['secret'],
          initialType: state.uri.queryParameters['type'] ?? 'link',
        ),
      ),
      GoRoute(path: AppRoutes.qrJoin, builder: (_, _) => const QrJoinScreen()),
      GoRoute(
        path: AppRoutes.lobbyInvite,
        builder: (_, state) {
          final activity = state.extra! as Map<String, String>;
          return LobbyInviteScreen(
            activityId: activity['id']!,
            activityTitle: activity['title']!,
          );
        },
      ),
      GoRoute(
        path: AppRoutes.createActivity,
        builder: (context, state) {
          final clan = state.extra as Map<String, String>?;
          return CreateActivityScreen(
            clanId: clan?['id'],
            clanName: clan?['name'],
          );
        },
      ),
      GoRoute(
        path: AppRoutes.notifications,
        builder: (context, state) => const NotificationsScreen(),
      ),
      GoRoute(
        path: AppRoutes.notificationPreferences,
        builder: (context, state) => const NotificationPreferencesScreen(),
      ),
      GoRoute(
        path: AppRoutes.accountSettings,
        builder: (context, state) => const AccountSettingsScreen(),
      ),
      GoRoute(
        path: AppRoutes.profile,
        builder: (context, state) => const ProfileScreen(),
      ),
      GoRoute(
        path: AppRoutes.explore,
        builder: (context, state) => ExploreScreen(
          initialCategoryId: state.uri.queryParameters['category_id'],
        ),
      ),
      GoRoute(
        path: AppRoutes.clanDetail,
        builder: (context, state) =>
            ClanDetailScreen(clanId: state.extra as String? ?? ''),
      ),
      GoRoute(
        path: AppRoutes.clanChat,
        builder: (context, state) {
          final clan = state.extra! as Map<String, String>;
          return ClanChatScreen(clanId: clan['id']!, clanName: clan['name']!);
        },
      ),
      GoRoute(
        path: AppRoutes.conversation,
        builder: (context, state) =>
            ConversationScreen(conversation: state.extra! as ConversationItem),
      ),
      GoRoute(
        path: AppRoutes.groupConversationSettings,
        builder: (context, state) => GroupConversationSettingsScreen(
          conversationId: state.extra! as String,
        ),
      ),
      GoRoute(
        path: AppRoutes.activityDetail,
        pageBuilder: (context, state) => CustomTransitionPage(
          key: state.pageKey,
          child: ActivityDetailScreen(activityId: state.extra as String? ?? ''),
          transitionDuration: const Duration(milliseconds: 280),
          transitionsBuilder: (context, animation, secondaryAnimation, child) {
            return SlideTransition(
              position:
                  Tween<Offset>(
                    begin: const Offset(0.04, 0),
                    end: Offset.zero,
                  ).animate(
                    CurvedAnimation(
                      parent: animation,
                      curve: Curves.easeOutCubic,
                    ),
                  ),
              child: FadeTransition(opacity: animation, child: child),
            );
          },
        ),
      ),
    ],
  );
  ref.onDispose(() {
    tokens.removeListener(resetSessionData);
    router.dispose();
  });
  return router;
});
