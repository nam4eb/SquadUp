import 'dart:io';
import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:path_provider/path_provider.dart';

import '../../../core/network/api_client.dart';

class ChatOutboxItem {
  const ChatOutboxItem({
    required this.conversationId,
    required this.clientMessageId,
    required this.body,
    required this.createdAt,
    this.replyToId,
    this.media,
  });

  final String conversationId;
  final String clientMessageId;
  final String body;
  final String? replyToId;
  final DateTime createdAt;
  final ChatOutboxMedia? media;

  Map<String, dynamic> toJson() => {
    'conversation_id': conversationId,
    'client_message_id': clientMessageId,
    'body': body,
    'reply_to_id': replyToId,
    'created_at': createdAt.toIso8601String(),
    'media': media?.toJson(),
  };

  factory ChatOutboxItem.fromJson(Map<String, dynamic> json) => ChatOutboxItem(
    conversationId: json['conversation_id'] as String,
    clientMessageId: json['client_message_id'] as String,
    body: json['body'] as String,
    replyToId: json['reply_to_id'] as String?,
    createdAt: DateTime.parse(json['created_at'] as String),
    media: json['media'] is Map
        ? ChatOutboxMedia.fromJson(
            Map<String, dynamic>.from(json['media'] as Map),
          )
        : null,
  );
}

class ChatOutboxMedia {
  const ChatOutboxMedia({
    required this.path,
    required this.name,
    required this.size,
  });

  final String path;
  final String name;
  final int size;

  Map<String, dynamic> toJson() => {'path': path, 'name': name, 'size': size};

  factory ChatOutboxMedia.fromJson(Map<String, dynamic> json) =>
      ChatOutboxMedia(
        path: json['path'] as String,
        name: json['name'] as String,
        size: json['size'] as int,
      );

  PlatformFile toPlatformFile() =>
      PlatformFile(name: name, size: size, path: path);
}

class ChatOutbox {
  ChatOutbox(this._storage, this._tokens);

  static const _maximumItems = 50;
  static const _maximumMediaBytes = 50 * 1024 * 1024;
  static const _maximumFileBytes = 20 * 1024 * 1024;
  final FlutterSecureStorage _storage;
  final AuthTokenStore _tokens;

  Future<String> _key() async =>
      'squadup_chat_outbox_v1.${(await _tokens.read())?.hashCode ?? 0}';

  Future<List<ChatOutboxItem>> forConversation(String conversationId) async {
    final items = await _read();
    return items
        .where((item) => item.conversationId == conversationId)
        .toList();
  }

  Future<ChatOutboxItem?> find(String clientMessageId) async {
    final items = await _read();
    return items
        .where((item) => item.clientMessageId == clientMessageId)
        .firstOrNull;
  }

  Future<ChatOutboxMedia> stageMedia(
    PlatformFile file,
    String clientMessageId,
  ) async {
    if (file.size > _maximumFileBytes) {
      throw const FileSystemException('Attachments must be 20 MB or smaller.');
    }
    final root = await getApplicationSupportDirectory();
    final directory = Directory('${root.path}/chat_outbox');
    await directory.create(recursive: true);
    await _pruneStagedFiles(directory);
    // Keep the original name only in encrypted metadata, not in the file path.
    final target = File('${directory.path}/$clientMessageId.pending');
    if (file.bytes != null) {
      await target.writeAsBytes(file.bytes!, flush: true);
    } else if (file.path != null) {
      await File(file.path!).copy(target.path);
    } else {
      throw const FileSystemException(
        'The selected attachment is unavailable.',
      );
    }
    return ChatOutboxMedia(path: target.path, name: file.name, size: file.size);
  }

  Future<void> put(ChatOutboxItem item) async {
    final items = await _read();
    items.removeWhere(
      (existing) => existing.clientMessageId == item.clientMessageId,
    );
    items.add(item);
    items.sort((a, b) => a.createdAt.compareTo(b.createdAt));
    while (items.length > _maximumItems ||
        _mediaBytes(items) > _maximumMediaBytes) {
      await _deleteMedia(items.removeAt(0));
    }
    await _write(items);
  }

  Future<void> remove(String clientMessageId) async {
    final items = await _read();
    final removed = items
        .where((item) => item.clientMessageId == clientMessageId)
        .toList();
    items.removeWhere((item) => item.clientMessageId == clientMessageId);
    for (final item in removed) {
      await _deleteMedia(item);
    }
    await _write(items);
  }

  Future<List<ChatOutboxItem>> _read() async {
    final key = await _key();
    final encoded = await _storage.read(key: key);
    if (encoded == null || encoded.isEmpty) return [];
    try {
      final decoded = (jsonDecode(encoded) as List)
          .map((item) => ChatOutboxItem.fromJson(item as Map<String, dynamic>))
          .toList();
      final cutoff = DateTime.now().subtract(const Duration(days: 7));
      final expired = decoded.where((item) => item.createdAt.isBefore(cutoff));
      for (final item in expired) {
        await _deleteMedia(item);
      }
      final active = decoded
          .where((item) => item.createdAt.isAfter(cutoff))
          .toList();
      final missing = active.where(
        (item) => item.media != null && !File(item.media!.path).existsSync(),
      );
      for (final item in missing.toList()) {
        active.remove(item);
      }
      return active;
    } catch (_) {
      await _storage.delete(key: key);
      return [];
    }
  }

  int _mediaBytes(List<ChatOutboxItem> items) =>
      items.fold(0, (total, item) => total + (item.media?.size ?? 0));

  Future<void> _deleteMedia(ChatOutboxItem item) async {
    final media = item.media;
    if (media == null) return;
    final file = File(media.path);
    if (await file.exists()) await file.delete();
  }

  Future<void> _pruneStagedFiles(Directory directory) async {
    final cutoff = DateTime.now().subtract(const Duration(days: 7));
    await for (final entity in directory.list()) {
      if (entity is! File) continue;
      final modified = await entity.lastModified();
      if (modified.isBefore(cutoff)) await entity.delete();
    }
  }

  Future<void> _write(List<ChatOutboxItem> items) async {
    final key = await _key();
    if (items.isEmpty) {
      await _storage.delete(key: key);
    } else {
      await _storage.write(
        key: key,
        value: jsonEncode(items.map((item) => item.toJson()).toList()),
      );
    }
  }
}
