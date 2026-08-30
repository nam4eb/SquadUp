import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

void cacheFor(Ref ref, Duration duration) {
  final link = ref.keepAlive();
  final timer = Timer(duration, link.close);
  ref.onDispose(timer.cancel);
}
