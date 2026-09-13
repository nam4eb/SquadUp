import 'dart:async';
import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

class PerformanceTelemetry {
  static const _key = 'performance.api_samples.v1';
  static const _maximumSamples = 100;

  final List<Map<String, Object?>> _pending = [];
  Timer? _flushTimer;

  void record({
    required String method,
    required String path,
    required int durationMs,
    required int? statusCode,
    required bool cacheHit,
  }) {
    _pending.add({
      'method': method,
      'route': _sanitize(path),
      'duration_ms': durationMs,
      'status': statusCode,
      'cache_hit': cacheHit,
      'at': DateTime.now().toUtc().toIso8601String(),
    });
    _flushTimer ??= Timer(const Duration(seconds: 5), _flush);
  }

  Future<void> _flush() async {
    _flushTimer = null;
    if (_pending.isEmpty) return;
    final pending = List<Map<String, Object?>>.of(_pending);
    _pending.clear();
    final preferences = await SharedPreferences.getInstance();
    final existing = preferences.getString(_key);
    List<dynamic> samples;
    try {
      samples = existing == null ? [] : jsonDecode(existing) as List;
    } catch (_) {
      samples = [];
    }
    samples.addAll(pending);
    if (samples.length > _maximumSamples) {
      samples = samples.sublist(samples.length - _maximumSamples);
    }
    await preferences.setString(_key, jsonEncode(samples));
  }

  String _sanitize(String path) => path
      .replaceAll(RegExp(r'[0-9a-fA-F]{8}-[0-9a-fA-F-]{27,}'), ':id')
      .replaceAll(RegExp(r'/\d+(?=/|$)'), '/:id');
}
