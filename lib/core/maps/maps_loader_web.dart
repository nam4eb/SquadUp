import 'dart:async';
import 'dart:js_interop';
import 'package:web/web.dart' as web;

@JS('squadupMapsReady')
external set _mapsReady(JSFunction callback);

Future<void>? _pending;

Future<void> loadGoogleMaps(String key) => _pending ??= _load(key);

Future<void> _load(String key) async {
  final ready = Completer<void>();
  _mapsReady = (() {
    if (!ready.isCompleted) ready.complete();
  }).toJS;
  final script = web.HTMLScriptElement()
    ..async = true
    ..src = Uri.https('maps.googleapis.com', '/maps/api/js', {
      'key': key,
      'v': 'weekly',
      'loading': 'async',
      'callback': 'squadupMapsReady',
    }).toString();
  final subscription = script.onError.listen((_) {
    if (!ready.isCompleted) {
      ready.completeError(StateError('Map connection failed'));
    }
  });
  web.document.head!.appendChild(script);
  try {
    await ready.future.timeout(const Duration(seconds: 20));
  } catch (_) {
    script.remove();
    _pending = null;
    rethrow;
  } finally {
    await subscription.cancel();
  }
}
