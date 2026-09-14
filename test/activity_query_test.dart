import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:squadup/core/network/api_client.dart';
import 'package:squadup/features/activities/data/activities_repository.dart';

class RecordingAdapter implements HttpClientAdapter {
  final requests = <RequestOptions>[];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    return ResponseBody.fromString(
      '{"data":[],"meta":{"current_page":1,"last_page":1}}',
      200,
      headers: {
        Headers.contentTypeHeader: ['application/json'],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  test(
    'list and nearby encode open slots for Laravel boolean validation',
    () async {
      FlutterSecureStorage.setMockInitialValues({});
      final adapter = RecordingAdapter();
      final dio = Dio()..httpClientAdapter = adapter;
      final tokens = AuthTokenStore(const FlutterSecureStorage());
      final repository = ActivitiesRepository(dio, tokens);
      await repository.list(openSlots: true);
      await repository.nearby(
        latitude: 10,
        longitude: 106,
        radiusKm: 20,
        openSlots: true,
      );
      expect(adapter.requests, hasLength(2));
      for (final request in adapter.requests) {
        expect(request.uri.queryParameters['open_slots'], '1');
      }
      tokens.dispose();
      dio.close();
    },
  );
}
