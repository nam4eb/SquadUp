import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:squadup/core/network/api_client.dart';
import 'package:squadup/features/activities/presentation/api_activity_feed.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() => FlutterSecureStorage.setMockInitialValues({}));

  test('a delayed unauthorized response cannot erase a new session', () async {
    final store = AuthTokenStore(const FlutterSecureStorage());
    await store.write('old-session');
    await store.write('new-session');
    await store.invalidateIfCurrent('Bearer old-session');
    expect(await store.read(), 'new-session');
    expect(
      await const FlutterSecureStorage().read(key: authTokenKey),
      'new-session',
    );
    store.dispose();
  });

  test(
    'current session expiry clears storage and notifies the router',
    () async {
      final store = AuthTokenStore(const FlutterSecureStorage());
      await store.write('expired-session');
      var changes = 0;
      store.addListener(() => changes++);
      await store.invalidateIfCurrent('Bearer expired-session');
      expect(await store.read(), isNull);
      expect(
        await const FlutterSecureStorage().read(key: authTokenKey),
        isNull,
      );
      expect(changes, 1);
      store.dispose();
    },
  );

  test(
    'unauthenticated request failure does not erase a saved session',
    () async {
      final store = AuthTokenStore(const FlutterSecureStorage());
      await store.write('valid-session');
      await store.invalidateIfCurrent(null);
      expect(await store.read(), 'valid-session');
      store.dispose();
    },
  );

  test(
    'feed distinguishes expired session, timeout and connectivity errors',
    () {
      final request = RequestOptions(path: '/explore');
      expect(
        activityFeedErrorMessage(
          DioException(
            requestOptions: request,
            response: Response(requestOptions: request, statusCode: 401),
          ),
        ),
        contains('sign in again'),
      );
      expect(
        activityFeedErrorMessage(
          DioException(
            requestOptions: request,
            type: DioExceptionType.receiveTimeout,
          ),
        ),
        contains('too long'),
      );
      expect(
        activityFeedErrorMessage(
          DioException(
            requestOptions: request,
            type: DioExceptionType.connectionError,
          ),
        ),
        contains('Cannot connect'),
      );
    },
  );
}
