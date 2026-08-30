import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:file_picker/file_picker.dart';

import '../../../core/network/api_client.dart';
import '../../../core/application/provider_cache.dart';
import '../domain/chat_models.dart';

class ChatRepository {
  final Dio _dio;
  ChatRepository(this._dio);

  Future<List<ConversationItem>> conversations() async {
    final response = await _dio.get<Map<String, dynamic>>('/conversations');
    return (response.data!['data'] as List)
        .map((item) => ConversationItem.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<ConversationItem> direct(String userId) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/conversations/direct',
      data: {'user_id': userId},
    );
    return ConversationItem.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<ConversationItem> group(String name, List<String> memberIds) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/conversations/group',
      data: {'name': name, 'member_ids': memberIds},
    );
    return ConversationItem.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<ConversationItem> conversation(String id) async {
    final response = await _dio.get<Map<String, dynamic>>('/conversations/$id');
    return ConversationItem.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<ConversationItem> updateGroup(
    String id, {
    required String name,
    String? description,
    PlatformFile? avatar,
  }) async {
    final data = <String, dynamic>{
      '_method': 'PATCH',
      'name': name,
      'description': description,
    };
    if (avatar != null) {
      data['avatar'] = avatar.bytes != null
          ? MultipartFile.fromBytes(avatar.bytes!, filename: avatar.name)
          : await MultipartFile.fromFile(avatar.path!, filename: avatar.name);
    }
    final response = await _dio.post<Map<String, dynamic>>(
      '/conversations/$id/group',
      data: FormData.fromMap(data),
    );
    return ConversationItem.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<void> addGroupMember(String id, String userId) =>
      _dio.post('/conversations/$id/members', data: {'user_id': userId});

  Future<void> setGroupRole(String id, String memberId, String role) => _dio
      .patch('/conversations/$id/members/$memberId/role', data: {'role': role});

  Future<void> removeGroupMember(String id, String memberId) =>
      _dio.delete('/conversations/$id/members/$memberId');

  Future<void> transferGroupOwnership(String id, String memberId) =>
      _dio.post('/conversations/$id/transfer-ownership/$memberId');

  Future<void> leaveGroup(String id) => _dio.post('/conversations/$id/leave');

  Future<ChatMessagePage> messages(
    String conversationId, {
    String? cursor,
  }) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/conversations/$conversationId/messages',
      queryParameters: {'limit': 30, if (cursor != null) 'cursor': cursor},
    );
    final items = (response.data!['data'] as List)
        .map((item) => ChatMessageItem.fromJson(item as Map<String, dynamic>))
        .toList();
    final meta = response.data!['meta'] as Map<String, dynamic>?;
    return ChatMessagePage(items, meta?['next_cursor'] as String?);
  }

  Future<ChatMessageItem> send(
    String conversationId,
    String body, {
    required String clientMessageId,
    String? replyToId,
    String? storyId,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/conversations/$conversationId/messages',
      data: {
        'body': body,
        'client_message_id': clientMessageId,
        if (replyToId != null) 'reply_to_id': replyToId,
        if (storyId != null) ...{'type': 'story_reply', 'story_id': storyId},
      },
    );
    return ChatMessageItem.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<void> sendMedia(
    String conversationId,
    PlatformFile file, {
    String? body,
    String? replyToId,
    required String clientMessageId,
  }) async {
    final upload = file.bytes != null
        ? MultipartFile.fromBytes(file.bytes!, filename: file.name)
        : await MultipartFile.fromFile(file.path!, filename: file.name);
    await _dio.post(
      '/conversations/$conversationId/messages',
      data: FormData.fromMap({
        'file': upload,
        'client_message_id': clientMessageId,
        if (body?.trim().isNotEmpty == true) 'body': body!.trim(),
        if (replyToId != null) 'reply_to_id': replyToId,
      }),
    );
  }

  Future<void> edit(String conversationId, String messageId, String body) =>
      _dio.patch(
        '/conversations/$conversationId/messages/$messageId',
        data: {'body': body},
      );

  Future<void> delete(String conversationId, String messageId) =>
      _dio.delete('/conversations/$conversationId/messages/$messageId');

  Future<void> react(
    String conversationId,
    String messageId,
    String reaction,
  ) => _dio.post(
    '/conversations/$conversationId/messages/$messageId/reactions',
    data: {'reaction': reaction},
  );

  Future<void> markRead(String conversationId, String messageId) =>
      _dio.post('/conversations/$conversationId/messages/$messageId/read');

  Future<void> typing(String conversationId, bool typing) => _dio.post(
    '/conversations/$conversationId/typing',
    data: {'typing': typing},
  );
}

final chatRepositoryProvider = Provider<ChatRepository>(
  (ref) => ChatRepository(ref.watch(dioProvider)),
);

final conversationsProvider =
    FutureProvider.autoDispose<List<ConversationItem>>((ref) {
      cacheFor(ref, const Duration(minutes: 2));
      return ref.watch(chatRepositoryProvider).conversations();
    });

class ChatMessagePage {
  const ChatMessagePage(this.items, this.nextCursor);
  final List<ChatMessageItem> items;
  final String? nextCursor;
}

final messagesProvider = FutureProvider.autoDispose
    .family<ChatMessagePage, String>((ref, id) {
      cacheFor(ref, const Duration(minutes: 1));
      return ref.watch(chatRepositoryProvider).messages(id);
    });
