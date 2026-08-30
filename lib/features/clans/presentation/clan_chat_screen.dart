import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:file_picker/file_picker.dart';
import 'package:cached_network_image/cached_network_image.dart';

import '../data/clans_repository.dart';
import '../domain/clan_item.dart';

class ClanChatScreen extends ConsumerStatefulWidget {
  final String clanId;
  final String clanName;

  const ClanChatScreen({
    super.key,
    required this.clanId,
    required this.clanName,
  });

  @override
  ConsumerState<ClanChatScreen> createState() => _ClanChatScreenState();
}

class _ClanChatScreenState extends ConsumerState<ClanChatScreen> {
  final _message = TextEditingController();
  bool _sending = false;

  @override
  void dispose() {
    _message.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final messages = ref.watch(clanMessagesProvider(widget.clanId));
    return Scaffold(
      appBar: AppBar(title: Text('${widget.clanName} chat')),
      body: Column(
        children: [
          Expanded(
            child: messages.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (_, _) => Center(
                child: FilledButton(
                  onPressed: _refresh,
                  child: const Text('Retry'),
                ),
              ),
              data: (items) => RefreshIndicator(
                onRefresh: _refresh,
                child: ListView.builder(
                  reverse: true,
                  padding: const EdgeInsets.all(16),
                  itemCount: items.length,
                  itemBuilder: (context, index) => _MessageBubble(
                    message: items[index],
                    onDelete: items[index].isMine
                        ? () => _delete(items[index].id)
                        : null,
                  ),
                ),
              ),
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
              child: Row(
                children: [
                  IconButton(
                    tooltip: 'Attach media or file',
                    onPressed: _sending ? null : _sendMedia,
                    icon: const Icon(Icons.attach_file),
                  ),
                  Expanded(
                    child: TextField(
                      controller: _message,
                      minLines: 1,
                      maxLines: 4,
                      decoration: const InputDecoration(
                        hintText: 'Message the clan',
                      ),
                      onSubmitted: (_) => _send(),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton.filled(
                    onPressed: _sending ? null : _send,
                    icon: const Icon(Icons.send),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _refresh() async {
    ref.invalidate(clanMessagesProvider(widget.clanId));
    await ref.read(clanMessagesProvider(widget.clanId).future);
  }

  Future<void> _send() async {
    final body = _message.text.trim();
    if (body.isEmpty || _sending) return;
    setState(() => _sending = true);
    try {
      await ref.read(clansRepositoryProvider).sendMessage(widget.clanId, body);
      _message.clear();
      await _refresh();
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Unable to send message.')),
        );
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _delete(String messageId) async {
    await ref
        .read(clansRepositoryProvider)
        .deleteMessage(widget.clanId, messageId);
    await _refresh();
  }

  Future<void> _sendMedia() async {
    final result = await FilePicker.pickFiles(withData: true);
    if (result == null || !mounted) return;
    setState(() => _sending = true);
    try {
      await ref
          .read(clansRepositoryProvider)
          .sendMediaMessage(
            widget.clanId,
            result.files.single,
            body: _message.text,
          );
      _message.clear();
      await _refresh();
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }
}

class _MessageBubble extends StatelessWidget {
  final ClanMessageItem message;
  final VoidCallback? onDelete;

  const _MessageBubble({required this.message, this.onDelete});

  @override
  Widget build(BuildContext context) => Align(
    alignment: message.isMine ? Alignment.centerRight : Alignment.centerLeft,
    child: GestureDetector(
      onLongPress: onDelete,
      child: Card(
        color: message.isMine
            ? Theme.of(context).colorScheme.primaryContainer
            : null,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (!message.isMine)
                Text(
                  message.senderName,
                  style: Theme.of(context).textTheme.labelMedium,
                ),
              ...message.media.map(
                (media) => media.isImage
                    ? Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: CachedNetworkImage(
                          imageUrl: media.url,
                          width: 220,
                          height: 160,
                          fit: BoxFit.cover,
                        ),
                      )
                    : ListTile(
                        dense: true,
                        contentPadding: EdgeInsets.zero,
                        leading: const Icon(Icons.insert_drive_file_outlined),
                        title: Text(media.originalName),
                        subtitle: Text('${(media.size / 1024).ceil()} KB'),
                      ),
              ),
              Text(message.isDeleted ? 'Message deleted' : message.body ?? ''),
              if (message.mentions.isNotEmpty)
                Text(
                  'Mentions: ${message.mentions.map((name) => '@$name').join(', ')}',
                  style: const TextStyle(fontSize: 11),
                ),
            ],
          ),
        ),
      ),
    ),
  );
}
