import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../../routes/app_routes.dart';
import '../data/activities_repository.dart';

class QrJoinScreen extends ConsumerStatefulWidget {
  const QrJoinScreen({super.key});

  @override
  ConsumerState<QrJoinScreen> createState() => _QrJoinScreenState();
}

class _QrJoinScreenState extends ConsumerState<QrJoinScreen> {
  final _scanner = MobileScannerController();
  bool _processing = false;
  DateTime? _lastInvalidNotice;

  @override
  void dispose() {
    _scanner.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Scan lobby QR')),
    body: Stack(
      fit: StackFit.expand,
      children: [
        MobileScanner(controller: _scanner, onDetect: _detected),
        Center(
          child: Container(
            width: 250,
            height: 250,
            decoration: BoxDecoration(
              border: Border.all(color: Colors.white, width: 3),
              borderRadius: BorderRadius.circular(20),
            ),
          ),
        ),
        Align(
          alignment: Alignment.bottomCenter,
          child: SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text(
                    'Place the SquadUP lobby QR inside the frame',
                    style: TextStyle(color: Colors.white, fontSize: 16),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      FilledButton.tonalIcon(
                        onPressed: _processing ? null : _scanner.toggleTorch,
                        icon: const Icon(Icons.flashlight_on_outlined),
                        label: const Text('Torch'),
                      ),
                      const SizedBox(width: 12),
                      FilledButton.tonalIcon(
                        onPressed: _processing
                            ? null
                            : () => context.push(AppRoutes.redeemInvitation),
                        icon: const Icon(Icons.keyboard_outlined),
                        label: const Text('Enter code'),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
        if (_processing) const Center(child: CircularProgressIndicator()),
      ],
    ),
  );

  Future<void> _detected(BarcodeCapture capture) async {
    if (_processing || capture.barcodes.isEmpty) return;
    final raw = capture.barcodes.first.rawValue;
    if (raw == null) return;
    final uri = Uri.tryParse(raw);
    final secret = uri?.queryParameters['secret'];
    final type = uri?.queryParameters['type'] ?? 'link';
    if (secret == null || !['link', 'code'].contains(type)) {
      final now = DateTime.now();
      if (_lastInvalidNotice == null ||
          now.difference(_lastInvalidNotice!) > const Duration(seconds: 3)) {
        _lastInvalidNotice = now;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('This is not a SquadUP lobby QR.')),
        );
      }
      return;
    }
    setState(() => _processing = true);
    await _scanner.stop();
    try {
      final activityId = await ref
          .read(activitiesRepositoryProvider)
          .redeemInvitation(type, secret);
      ref.invalidate(activityFeedProvider);
      if (mounted) context.go(AppRoutes.activityDetail, extra: activityId);
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('This QR invitation is invalid, expired, or full.'),
          ),
        );
        setState(() => _processing = false);
        await _scanner.start();
      }
    }
  }
}
