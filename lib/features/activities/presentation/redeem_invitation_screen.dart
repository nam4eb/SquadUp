import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../routes/app_routes.dart';
import '../data/activities_repository.dart';

class RedeemInvitationScreen extends ConsumerStatefulWidget {
  const RedeemInvitationScreen({
    super.key,
    this.initialSecret,
    this.initialType = 'code',
  });

  final String? initialSecret;
  final String initialType;

  @override
  ConsumerState<RedeemInvitationScreen> createState() =>
      _RedeemInvitationScreenState();
}

class _RedeemInvitationScreenState
    extends ConsumerState<RedeemInvitationScreen> {
  final _code = TextEditingController();
  final _password = TextEditingController();
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _code.text = widget.initialSecret ?? '';
    if (_code.text.isNotEmpty) Future.microtask(_redeem);
  }

  @override
  void dispose() {
    _code.dispose();
    _password.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Join with code')),
    body: ListView(
      padding: const EdgeInsets.all(20),
      children: [
        TextField(
          controller: _code,
          textCapitalization: TextCapitalization.characters,
          decoration: const InputDecoration(
            labelText: 'Invitation code',
            prefixIcon: Icon(Icons.key),
          ),
        ),
        const SizedBox(height: 14),
        TextField(
          controller: _password,
          obscureText: true,
          decoration: const InputDecoration(
            labelText: 'Activity password (optional)',
            prefixIcon: Icon(Icons.lock_outline),
          ),
        ),
        const SizedBox(height: 24),
        FilledButton(
          onPressed: _loading ? null : _redeem,
          child: Text(_loading ? 'Joining…' : 'Join activity'),
        ),
      ],
    ),
  );

  Future<void> _redeem() async {
    final code = _code.text.trim();
    if (code.isEmpty) {
      return;
    }
    setState(() => _loading = true);
    try {
      final activityId = await ref
          .read(activitiesRepositoryProvider)
          .redeemInvitation(widget.initialType, code, password: _password.text);
      ref.invalidate(activityFeedProvider);
      if (mounted) {
        context.go(AppRoutes.activityDetail, extra: activityId);
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'The invitation is invalid, expired, or unavailable.',
            ),
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }
}
