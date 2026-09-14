import 'dart:io';
import 'package:squadup/features/activities/domain/activity_item.dart';

// Runs with Dart alone when the host cannot execute flutter_tester.
void main() {
  for (final coordinates in [
    {'latitude': '13.7280000', 'longitude': '100.5620000'},
    {'latitude': 13.728, 'longitude': 100.562},
    {'latitude': null, 'longitude': null},
  ]) {
    final item = ActivityItem.fromJson({
      'id': 'fixture',
      'title': 'PostgreSQL decimal fixture',
      'starts_at': '2026-09-14T08:33:47Z',
      'max_participants': 12,
      'status': 'open',
      'visibility': 'public',
      'location': coordinates,
      'fee': '12.50',
      'distance_km': '1.25',
    });
    final expectedLatitude = coordinates['latitude'] == null ? null : 13.728;
    final expectedLongitude = coordinates['longitude'] == null ? null : 100.562;
    if (item.latitude != expectedLatitude ||
        item.longitude != expectedLongitude ||
        item.fee != 12.5 ||
        item.distanceKm != 1.25) {
      throw StateError('Activity JSON contract regression: $coordinates');
    }
  }
  stdout.writeln(
    'PASS: activity decimals accept PostgreSQL strings, numbers and nulls.',
  );
}
