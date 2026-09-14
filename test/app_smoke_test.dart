import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:squadup/main.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:squadup/routes/app_routes.dart';

void main() {
  testWidgets('SquadUP opens the authentication screen', (tester) async {
    FlutterSecureStorage.setMockInitialValues({});
    await tester.pumpWidget(const ProviderScope(child: SquadUpApp()));
    await tester.pump(const Duration(milliseconds: 800));

    expect(find.text('Demo credentials'), findsOneWidget);
    expect(find.text('Sign in to SquadUp'), findsOneWidget);
    final context = tester.element(find.byType(SquadUpApp));
    ProviderScope.containerOf(
      context,
    ).read(appRouterProvider).go(AppRoutes.homeFeed);
    await tester.pumpAndSettle();
    expect(find.text('Sign in to SquadUp'), findsOneWidget);
    expect(find.text('Upcoming Activities'), findsNothing);
  });
}
