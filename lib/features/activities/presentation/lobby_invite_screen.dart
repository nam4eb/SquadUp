import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:share_plus/share_plus.dart';

import '../data/activities_repository.dart';

const inviteBaseUrl = String.fromEnvironment(
  'INVITE_BASE_URL',
  defaultValue: 'https://squadup.app/invite',
);

class LobbyInviteScreen extends ConsumerStatefulWidget {
  const LobbyInviteScreen({
    super.key,
    required this.activityId,
    required this.activityTitle,
  });

  final String activityId;
  final String activityTitle;

  @override
  ConsumerState<LobbyInviteScreen> createState() => _LobbyInviteScreenState();
}

class _LobbyInviteScreenState extends ConsumerState<LobbyInviteScreen> {
  String? _link;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    Future.microtask(_createLink);
  }

  Future<void> _createLink() async {
    if (mounted) setState(() => _loading = true);
    try {
      final secret = await ref
          .read(activitiesRepositoryProvider)
          .createInvitationLink(widget.activityId, maxUses: 100);
      if (mounted) {
        setState(
          () => _link =
              '$inviteBaseUrl?type=link&secret=${Uri.encodeQueryComponent(secret)}',
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Unable to create invitation link.')),
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Invite to lobby')),
    body: Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: _loading
            ? const CircularProgressIndicator()
            : _link == null
            ? FilledButton(onPressed: _createLink, child: const Text('Retry'))
            : Column(
                children: [
                  Text(
                    widget.activityTitle,
                    style: Theme.of(context).textTheme.headlineSmall,
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 20),
                  DecoratedBox(
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Padding(
                      padding: const EdgeInsets.all(18),
                      child: QrImageView(data: _link!, size: 240),
                    ),
                  ),
                  const SizedBox(height: 14),
                  const Text(
                    'Friends can scan this QR code to join the lobby.',
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 12),
                  SelectableText(
                    _link!,
                    maxLines: 2,
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  const SizedBox(height: 20),
                  Wrap(
                    alignment: WrapAlignment.center,
                    spacing: 12,
                    runSpacing: 12,
                    children: [
                      FilledButton.icon(
                        onPressed: () => SharePlus.instance.share(
                          ShareParams(
                            text:
                                'Join ${widget.activityTitle} on SquadUP: $_link',
                          ),
                        ),
                        icon: const Icon(Icons.share_outlined),
                        label: const Text('Share link'),
                      ),
                      OutlinedButton.icon(
                        onPressed: () async {
                          await Clipboard.setData(ClipboardData(text: _link!));
                          if (context.mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(
                                content: Text('Invitation link copied.'),
                              ),
                            );
                          }
                        },
                        icon: const Icon(Icons.copy_outlined),
                        label: const Text('Copy'),
                      ),
                    ],
                  ),
                ],
              ),
      ),
    ),
  );
}
