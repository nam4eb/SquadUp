import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:squadup/core/config/app_config.dart';

void main() {
  test('localhost resolves to 10.0.2.2 on Android emulator', () {
    expect(
      resolveHostForPlatform('127.0.0.1', platform: TargetPlatform.android),
      '10.0.2.2',
    );
    expect(
      resolveHostForPlatform('localhost', platform: TargetPlatform.android),
      '10.0.2.2',
    );
    expect(
      resolveHostForPlatform('localhost', platform: TargetPlatform.windows),
      'localhost',
    );
  });

  test(
    'API base URL keeps path and port while resolving Android emulator host',
    () {
      final resolved = buildApiBaseUrl(
        'http://127.0.0.1:8000/api/v1',
        platform: TargetPlatform.android,
      );
      expect(resolved.toString(), 'http://10.0.2.2:8000/api/v1');
    },
  );
}
