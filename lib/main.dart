import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:sizer/sizer.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'dart:async';

import 'core/app_export.dart';
import 'core/notifications/push_notification_service.dart';
import 'features/chat/data/chat_repository.dart';
import 'widgets/custom_error_widget.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  bool hasShownError = false;

  // 🚨 CRITICAL: Custom error handling - DO NOT REMOVE
  ErrorWidget.builder = (FlutterErrorDetails details) {
    if (!hasShownError) {
      hasShownError = true;

      // Reset flag after 3 seconds to allow error widget on new screens
      Future.delayed(Duration(seconds: 5), () {
        hasShownError = false;
      });

      return CustomErrorWidget(errorDetails: details);
    }
    return SizedBox.shrink();
  };

  // 🚨 CRITICAL: Device orientation lock - DO NOT REMOVE
  Future.wait([
    SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]),
  ]).then((value) {
    GoRouter.optionURLReflectsImperativeAPIs = true;
    runApp(const ProviderScope(child: SquadUpApp()));
  });
}

class SquadUpApp extends ConsumerStatefulWidget {
  const SquadUpApp({super.key});

  @override
  ConsumerState<SquadUpApp> createState() => _SquadUpAppState();
}

class _SquadUpAppState extends ConsumerState<SquadUpApp> {
  StreamSubscription<Map<String, dynamic>>? _notificationSubscription;

  @override
  void initState() {
    super.initState();
    _notificationSubscription = ref
        .read(pushNotificationServiceProvider)
        .opened
        .listen(_openNotification);
  }

  @override
  void dispose() {
    _notificationSubscription?.cancel();
    super.dispose();
  }

  Future<void> _openNotification(Map<String, dynamic> payload) async {
    final conversationId = payload['conversation_id']?.toString();
    if (conversationId != null && conversationId.isNotEmpty) {
      try {
        final conversation = await ref
            .read(chatRepositoryProvider)
            .conversation(conversationId);
        appRouter.push(AppRoutes.conversation, extra: conversation);
      } catch (_) {
        appRouter.go(AppRoutes.chat);
      }
      return;
    }
    final activityId = payload['activity_id']?.toString();
    if (activityId != null && activityId.isNotEmpty) {
      appRouter.push(AppRoutes.activityDetail, extra: activityId);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Sizer(
      builder: (context, orientation, screenType) {
        return MaterialApp.router(
          title: 'squadup',
          theme: AppTheme.lightTheme,
          darkTheme: AppTheme.darkTheme,
          themeMode: ThemeMode.light,
          // Respect the user's system text-size preference for accessibility.
          builder: (context, child) => child ?? const SizedBox.shrink(),
          debugShowCheckedModeBanner: false,
          routerConfig: appRouter,
        );
      },
    );
  }
}
