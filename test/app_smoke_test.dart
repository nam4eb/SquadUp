import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:squadup/main.dart';

void main() {
  testWidgets('SquadUP opens the authentication screen', (tester) async {
    await tester.pumpWidget(const ProviderScope(child: SquadUpApp()));
    await tester.pump(const Duration(milliseconds: 800));

    expect(find.text('Demo credentials'), findsOneWidget);
    expect(find.text('Sign in to SquadUp'), findsOneWidget);
  });
}
