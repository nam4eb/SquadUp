import 'dart:async';
import 'dart:math';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:file_picker/file_picker.dart';
import 'package:cached_network_image/cached_network_image.dart';

import '../../../core/realtime/realtime_service.dart';
import '../../../core/network/api_client.dart';
import '../../../routes/app_routes.dart';
import 'package:go_router/go_router.dart';
import '../data/chat_repository.dart';
import '../data/chat_outbox.dart';
import '../domain/chat_models.dart';

class ConversationScreen extends ConsumerStatefulWidget {
  final ConversationItem conversation;

  const ConversationScreen({super.key, required this.conversation});

  @override
  ConsumerState<ConversationScreen> createState() => _ConversationScreenState();
}

class _ConversationScreenState extends ConsumerState<ConversationScreen>
    with WidgetsBindingObserver {
  final _message = TextEditingController();
  bool _sending = false;
  final _markedRead = <String>{};
  ChatMessageItem? _replyingTo;
  Timer? _typingTimer;
  String? _typingUser;
  Map<String, String> _mediaHeaders = const {};
  bool _mediaHeadersReady = false;
  final _scroll = ScrollController();
  final List<ChatMessageItem> _localMessages = [];
  final List<ChatMessageItem> _olderMessages = [];
  String? _nextCursor;
  bool _loadingOlder = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _scroll.addListener(_onScroll);
    Future.microtask(() {
      ref
          .read(realtimeServiceProvider)
          .subscribeConversation(widget.conversation.id, _onRealtimeEvent);
      _loadMediaHeaders();
      _recoverOutbox();
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _typingTimer?.cancel();
    ref
        .read(chatRepositoryProvider)
        .typing(widget.conversation.id, false)
        .ignore();
    ref
        .read(realtimeServiceProvider)
        .unsubscribeConversation(widget.conversation.id);
    _message.dispose();
    _scroll.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state != AppLifecycleState.resumed) return;
    ref.read(realtimeServiceProvider).recover();
    ref.invalidate(messagesProvider(widget.conversation.id));
    _retryOutbox();
  }

  @override
  Widget build(BuildContext context) {
    final messages = ref.watch(messagesProvider(widget.conversation.id));
    final realtimeStatus = ref.watch(realtimeConnectionProvider).value;
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.conversation.name),
        actions: [
          if (widget.conversation.type == 'group')
            IconButton(
              tooltip: 'Group settings',
              onPressed: () => context.push(
                AppRoutes.groupConversationSettings,
                extra: widget.conversation.id,
              ),
              icon: const Icon(Icons.group_outlined),
            ),
        ],
      ),
      body: Column(
        children: [
          if (realtimeStatus != RealtimeConnectionStatus.connected)
            Material(
              color: Theme.of(context).colorScheme.surfaceContainerHighest,
              child: ListTile(
                dense: true,
                leading: SizedBox.square(
                  dimension: 18,
                  child: realtimeStatus == RealtimeConnectionStatus.connecting
                      ? const CircularProgressIndicator(strokeWidth: 2)
                      : const Icon(Icons.cloud_off_outlined, size: 18),
                ),
                title: Text(
                  realtimeStatus == RealtimeConnectionStatus.connecting
                      ? 'Connecting to live chat…'
                      : 'Live updates unavailable · messages still use REST',
                ),
                trailing: realtimeStatus == RealtimeConnectionStatus.degraded
                    ? TextButton(
                        onPressed: () =>
                            ref.read(realtimeServiceProvider).recover(),
                        child: const Text('Retry'),
                      )
                    : null,
              ),
            ),
          Expanded(
            child: messages.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (_, _) => Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Text('Unable to load messages.'),
                    const SizedBox(height: 8),
                    FilledButton.tonalIcon(
                      onPressed: () => ref.invalidate(
                        messagesProvider(widget.conversation.id),
                      ),
                      icon: const Icon(Icons.refresh),
                      label: const Text('Retry'),
                    ),
                  ],
                ),
              ),
              data: (page) {
                _nextCursor ??= page.nextCursor;
                final items = _mergeMessages([
                  ...page.items,
                  ..._olderMessages,
                  ..._localMessages,
                ]);
                _markLatestRead(items);
                return ListView.builder(
                  controller: _scroll,
                  reverse: true,
                  padding: const EdgeInsets.all(16),
                  itemCount: items.length + (_loadingOlder ? 1 : 0),
                  itemBuilder: (context, index) {
                    if (index == items.length) {
                      return const Center(child: CircularProgressIndicator());
                    }
                    return _bubble(items[index]);
                  },
                );
              },
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (_replyingTo != null)
                    ListTile(
                      dense: true,
                      title: Text('Replying to ${_replyingTo!.senderName}'),
                      subtitle: Text(_replyingTo!.body ?? ''),
                      trailing: IconButton(
                        onPressed: () => setState(() => _replyingTo = null),
                        icon: const Icon(Icons.close),
                      ),
                    ),
                  if (_typingUser != null)
                    Align(
                      alignment: Alignment.centerLeft,
                      child: Padding(
                        padding: const EdgeInsets.only(bottom: 6),
                        child: Text(
                          '$_typingUser is typing…',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ),
                    ),
                  Row(
                    children: [
                      IconButton(
                        tooltip: 'Attach media or file',
                        onPressed: _sending ? null : _pickAndSendFile,
                        icon: const Icon(Icons.attach_file),
                      ),
                      Expanded(
                        child: TextField(
                          controller: _message,
                          maxLines: 4,
                          minLines: 1,
                          decoration: const InputDecoration(
                            hintText: 'Message',
                          ),
                          onChanged: _onTyping,
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
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _bubble(ChatMessageItem item) => Align(
    alignment: item.isMine ? Alignment.centerRight : Alignment.centerLeft,
    child: GestureDetector(
      onLongPress: item.isDeleted ? null : () => _messageActions(item),
      child: Card(
        color: item.isMine
            ? Theme.of(context).colorScheme.primaryContainer
            : null,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (!item.isMine) Text(item.senderName),
              ...item.media.map(_mediaPreview),
              Text(item.isDeleted ? 'Message deleted' : item.body ?? ''),
              if (item.mentions.isNotEmpty)
                Text(
                  'Mentions: ${item.mentions.map((name) => '@$name').join(', ')}',
                  style: const TextStyle(fontSize: 11),
                ),
              if (item.replyToId != null)
                const Text('↩ reply', style: TextStyle(fontSize: 11)),
              if (item.isEdited)
                const Text('edited', style: TextStyle(fontSize: 11)),
              if (item.reactions.isNotEmpty) Text(item.reactions.join(' ')),
              if (item.isMine && item.readCount > 0)
                const Text('Read', style: TextStyle(fontSize: 11)),
              if (item.isMine &&
                  item.readCount == 0 &&
                  item.deliveryStatus == ChatDeliveryStatus.sent)
                const Text('Delivered', style: TextStyle(fontSize: 11)),
              if (item.deliveryStatus == ChatDeliveryStatus.sending)
                const Text('Sending…', style: TextStyle(fontSize: 11)),
              if (item.deliveryStatus == ChatDeliveryStatus.failed)
                TextButton.icon(
                  onPressed: () => _retry(item),
                  icon: const Icon(Icons.refresh, size: 14),
                  label: const Text('Failed · tap to retry'),
                ),
            ],
          ),
        ),
      ),
    ),
  );

  Future<void> _messageActions(ChatMessageItem item) async {
    final action = await showModalBottomSheet<String>(
      context: context,
      builder: (context) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.reply),
              title: const Text('Reply'),
              onTap: () => Navigator.pop(context, 'reply'),
            ),
            ListTile(
              leading: const Text('👍'),
              title: const Text('React'),
              onTap: () => Navigator.pop(context, 'react'),
            ),
            if (item.isMine)
              ListTile(
                leading: const Icon(Icons.edit),
                title: const Text('Edit'),
                onTap: () => Navigator.pop(context, 'edit'),
              ),
            if (item.isMine)
              ListTile(
                leading: const Icon(Icons.delete_outline),
                title: const Text('Delete'),
                onTap: () => Navigator.pop(context, 'delete'),
              ),
          ],
        ),
      ),
    );
    if (action == null) return;
    final repository = ref.read(chatRepositoryProvider);
    if (action == 'reply') {
      setState(() => _replyingTo = item);
      return;
    } else if (action == 'react') {
      try {
        await repository.react(widget.conversation.id, item.id, '👍');
      } catch (error) {
        _showError(error);
        return;
      }
    } else if (action == 'delete') {
      try {
        await repository.delete(widget.conversation.id, item.id);
      } catch (error) {
        _showError(error);
        return;
      }
    } else {
      if (!mounted) return;
      final controller = TextEditingController(text: item.body);
      final body = await showDialog<String>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Edit message'),
          content: TextField(controller: controller, autofocus: true),
          actions: [
            FilledButton(
              onPressed: () => Navigator.pop(context, controller.text.trim()),
              child: const Text('Save'),
            ),
          ],
        ),
      );
      controller.dispose();
      if (body?.isNotEmpty == true) {
        try {
          await repository.edit(widget.conversation.id, item.id, body!);
        } catch (error) {
          _showError(error);
          return;
        }
      }
    }
    ref.invalidate(messagesProvider(widget.conversation.id));
    ref.invalidate(conversationsProvider);
  }

  Future<void> _send() async {
    final body = _message.text.trim();
    if (body.isEmpty || _sending) return;
    final clientMessageId = _newClientMessageId();
    final optimistic = ChatMessageItem(
      id: 'local-$clientMessageId',
      clientMessageId: clientMessageId,
      body: body,
      senderName: 'You',
      isMine: true,
      isDeleted: false,
      isEdited: false,
      replyToId: _replyingTo?.id,
      readCount: 0,
      reactions: const [],
      media: const [],
      mentions: const [],
      createdAt: DateTime.now(),
      deliveryStatus: ChatDeliveryStatus.sending,
    );
    setState(() {
      _sending = true;
      _localMessages.add(optimistic);
    });
    try {
      final sent = await ref
          .read(chatRepositoryProvider)
          .send(
            widget.conversation.id,
            body,
            clientMessageId: clientMessageId,
            replyToId: _replyingTo?.id,
          );
      await ref.read(chatOutboxProvider).remove(clientMessageId);
      _replaceLocal(clientMessageId, sent);
      _message.clear();
      _typingTimer?.cancel();
      await ref
          .read(chatRepositoryProvider)
          .typing(widget.conversation.id, false);
      setState(() => _replyingTo = null);
      ref.invalidate(messagesProvider(widget.conversation.id));
      ref.invalidate(conversationsProvider);
    } catch (error) {
      await ref
          .read(chatOutboxProvider)
          .put(
            ChatOutboxItem(
              conversationId: widget.conversation.id,
              clientMessageId: clientMessageId,
              body: body,
              replyToId: optimistic.replyToId,
              createdAt: optimistic.createdAt,
            ),
          );
      _replaceLocal(
        clientMessageId,
        optimistic.copyWith(deliveryStatus: ChatDeliveryStatus.failed),
      );
      _showError(error);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _pickAndSendFile() async {
    final result = await FilePicker.pickFiles();
    if (result == null || !mounted) return;
    final clientMessageId = _newClientMessageId();
    final createdAt = DateTime.now();
    final body = _message.text.trim();
    final replyToId = _replyingTo?.id;
    ChatMessageItem? optimistic;
    setState(() => _sending = true);
    try {
      final outbox = ref.read(chatOutboxProvider);
      final media = await outbox.stageMedia(
        result.files.single,
        clientMessageId,
      );
      final pending = ChatOutboxItem(
        conversationId: widget.conversation.id,
        clientMessageId: clientMessageId,
        body: body,
        replyToId: replyToId,
        createdAt: createdAt,
        media: media,
      );
      await outbox.put(pending);
      optimistic = ChatMessageItem(
        id: 'local-$clientMessageId',
        clientMessageId: clientMessageId,
        body: body.isEmpty ? 'Attachment: ${media.name}' : body,
        senderName: 'You',
        isMine: true,
        isDeleted: false,
        isEdited: false,
        replyToId: replyToId,
        readCount: 0,
        reactions: const [],
        media: const [],
        mentions: const [],
        createdAt: createdAt,
        deliveryStatus: ChatDeliveryStatus.sending,
      );
      setState(() => _localMessages.add(optimistic!));
      final sent = await ref
          .read(chatRepositoryProvider)
          .sendMedia(
            widget.conversation.id,
            media.toPlatformFile(),
            clientMessageId: clientMessageId,
            body: body,
            replyToId: replyToId,
          );
      await outbox.remove(clientMessageId);
      _replaceLocal(clientMessageId, sent);
      _message.clear();
      setState(() => _replyingTo = null);
      ref.invalidate(messagesProvider(widget.conversation.id));
      ref.invalidate(conversationsProvider);
    } catch (error) {
      if (optimistic != null) {
        _replaceLocal(
          clientMessageId,
          optimistic.copyWith(deliveryStatus: ChatDeliveryStatus.failed),
        );
      }
      _showError(error);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Widget _mediaPreview(ChatMediaItem media) {
    if (media.isImage) {
      if (!_mediaHeadersReady) {
        return const SizedBox(
          width: 220,
          height: 160,
          child: Center(child: CircularProgressIndicator()),
        );
      }
      return Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: CachedNetworkImage(
            imageUrl: media.url,
            httpHeaders: _mediaHeaders,
            width: 220,
            height: 160,
            fit: BoxFit.cover,
            placeholder: (_, _) => const SizedBox(
              width: 220,
              height: 160,
              child: Center(child: CircularProgressIndicator()),
            ),
            errorWidget: (_, _, _) => const Icon(Icons.broken_image_outlined),
          ),
        ),
      );
    }
    return ListTile(
      dense: true,
      contentPadding: EdgeInsets.zero,
      leading: const Icon(Icons.insert_drive_file_outlined),
      title: Text(media.originalName),
      subtitle: Text('${(media.size / 1024).ceil()} KB'),
    );
  }

  void _onTyping(String value) {
    _typingTimer?.cancel();
    final repository = ref.read(chatRepositoryProvider);
    repository.typing(widget.conversation.id, value.isNotEmpty).ignore();
    if (value.isNotEmpty) {
      _typingTimer = Timer(const Duration(seconds: 2), () {
        repository.typing(widget.conversation.id, false).ignore();
      });
    }
  }

  void _onRealtimeEvent(String event, dynamic data) {
    if (!mounted) return;
    if (event == 'conversation.typing' && data is Map) {
      final typing = data['typing'] == true;
      setState(() {
        _typingUser = typing ? data['display_name']?.toString() : null;
      });
      return;
    }
    ref.invalidate(messagesProvider(widget.conversation.id));
    if (event == 'message.created' ||
        event == 'message.updated' ||
        event == 'message.deleted') {
      ref.invalidate(conversationsProvider);
    }
  }

  List<ChatMessageItem> _mergeMessages(List<ChatMessageItem> source) {
    final merged = <String, ChatMessageItem>{};
    for (final item in source) {
      final key = item.clientMessageId ?? item.id;
      final current = merged[key];
      if (current == null || item.deliveryStatus == ChatDeliveryStatus.sent) {
        merged[key] = item;
      }
    }
    final result = merged.values.toList()
      ..sort((a, b) => b.createdAt.compareTo(a.createdAt));
    return result;
  }

  void _replaceLocal(String clientMessageId, ChatMessageItem replacement) {
    if (!mounted) return;
    setState(() {
      final index = _localMessages.indexWhere(
        (item) => item.clientMessageId == clientMessageId,
      );
      if (index < 0) {
        _localMessages.add(replacement);
      } else {
        _localMessages[index] = replacement;
      }
    });
  }

  Future<void> _retry(ChatMessageItem item) async {
    final clientId = item.clientMessageId;
    if (clientId == null) return;
    final pending = await ref.read(chatOutboxProvider).find(clientId);
    if (pending == null) return;
    _replaceLocal(
      clientId,
      item.copyWith(deliveryStatus: ChatDeliveryStatus.sending),
    );
    try {
      final repository = ref.read(chatRepositoryProvider);
      final sent = pending.media == null
          ? await repository.send(
              widget.conversation.id,
              pending.body,
              clientMessageId: clientId,
              replyToId: pending.replyToId,
            )
          : await repository.sendMedia(
              widget.conversation.id,
              pending.media!.toPlatformFile(),
              clientMessageId: clientId,
              body: pending.body,
              replyToId: pending.replyToId,
            );
      await ref.read(chatOutboxProvider).remove(clientId);
      _replaceLocal(clientId, sent);
      ref.invalidate(conversationsProvider);
    } catch (_) {
      _replaceLocal(
        clientId,
        item.copyWith(deliveryStatus: ChatDeliveryStatus.failed),
      );
    }
  }

  Future<void> _recoverOutbox() async {
    final pending = await ref
        .read(chatOutboxProvider)
        .forConversation(widget.conversation.id);
    if (!mounted) return;
    setState(() {
      for (final item in pending) {
        if (_localMessages.any(
          (message) => message.clientMessageId == item.clientMessageId,
        )) {
          continue;
        }
        _localMessages.add(
          ChatMessageItem(
            id: 'local-${item.clientMessageId}',
            clientMessageId: item.clientMessageId,
            body: item.body.isEmpty && item.media != null
                ? 'Attachment: ${item.media!.name}'
                : item.body,
            senderName: 'You',
            isMine: true,
            isDeleted: false,
            isEdited: false,
            replyToId: item.replyToId,
            readCount: 0,
            reactions: const [],
            media: const [],
            mentions: const [],
            createdAt: item.createdAt,
            deliveryStatus: ChatDeliveryStatus.failed,
          ),
        );
      }
    });
    await _retryOutbox();
  }

  Future<void> _retryOutbox() async {
    final pending = await ref
        .read(chatOutboxProvider)
        .forConversation(widget.conversation.id);
    for (final item in pending) {
      if (!mounted) return;
      final local = _localMessages
          .where((message) => message.clientMessageId == item.clientMessageId)
          .firstOrNull;
      if (local != null) await _retry(local);
    }
  }

  void _onScroll() {
    if (_scroll.hasClients &&
        _scroll.position.pixels >= _scroll.position.maxScrollExtent - 160) {
      _loadOlder();
    }
  }

  Future<void> _loadOlder() async {
    final cursor = _nextCursor;
    if (_loadingOlder || cursor == null) return;
    setState(() => _loadingOlder = true);
    try {
      final page = await ref
          .read(chatRepositoryProvider)
          .messages(widget.conversation.id, cursor: cursor);
      if (!mounted) return;
      setState(() {
        _olderMessages.addAll(page.items);
        _nextCursor = page.nextCursor;
      });
    } finally {
      if (mounted) setState(() => _loadingOlder = false);
    }
  }

  String _newClientMessageId() {
    final random = Random.secure();
    final bytes = List<int>.generate(16, (_) => random.nextInt(256));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    final hex = bytes
        .map((byte) => byte.toRadixString(16).padLeft(2, '0'))
        .join();
    return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
  }

  Future<void> _loadMediaHeaders() async {
    final token = await ref.read(authTokenStoreProvider).read();
    if (!mounted) return;
    setState(() {
      _mediaHeadersReady = true;
      _mediaHeaders = token == null || token.isEmpty
          ? const {}
          : {'Authorization': 'Bearer $token'};
    });
  }

  void _showError(Object error) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Chat action failed. Check your connection and retry.'),
      ),
    );
  }

  void _markLatestRead(List<ChatMessageItem> items) {
    final unread = items
        .where(
          (item) =>
              !item.isMine && !item.isDeleted && !_markedRead.contains(item.id),
        )
        .firstOrNull;
    if (unread == null) return;
    _markedRead.add(unread.id);
    Future.microtask(
      () => ref
          .read(chatRepositoryProvider)
          .markRead(widget.conversation.id, unread.id),
    );
  }
}
