class ConversationItem {
  final String id;
  final String type;
  final String name;
  final String? latestMessage;
  final String? description;
  final String? avatarUrl;
  final String? currentMemberRole;
  final List<ConversationMemberItem> members;
  final int unreadCount;

  const ConversationItem({
    required this.id,
    required this.type,
    required this.name,
    required this.latestMessage,
    this.description,
    this.avatarUrl,
    this.currentMemberRole,
    this.members = const [],
    this.unreadCount = 0,
  });

  factory ConversationItem.fromJson(Map<String, dynamic> json) {
    final latest = json['latest_message'] as Map<String, dynamic>?;
    return ConversationItem(
      id: json['id'] as String,
      type: json['type'] as String,
      name: json['name'] as String? ?? 'Conversation',
      latestMessage: latest?['body'] as String?,
      description: json['description'] as String?,
      avatarUrl: json['avatar_url'] as String?,
      currentMemberRole: json['current_member_role'] as String?,
      unreadCount: json['unread_count'] as int? ?? 0,
      members: (json['members'] as List? ?? const [])
          .map(
            (item) =>
                ConversationMemberItem.fromJson(item as Map<String, dynamic>),
          )
          .toList(),
    );
  }
}

class ConversationMemberItem {
  final String id;
  final String userId;
  final String displayName;
  final String role;

  const ConversationMemberItem({
    required this.id,
    required this.userId,
    required this.displayName,
    required this.role,
  });

  factory ConversationMemberItem.fromJson(Map<String, dynamic> json) {
    final user = json['user'] as Map<String, dynamic>;
    return ConversationMemberItem(
      id: json['id'] as String,
      userId: user['id'] as String,
      displayName: user['display_name'] as String,
      role: json['role'] as String,
    );
  }
}

class ChatMessageItem {
  final String id;
  final String? clientMessageId;
  final String? body;
  final String senderName;
  final bool isMine;
  final bool isDeleted;
  final bool isEdited;
  final String? replyToId;
  final int readCount;
  final List<String> reactions;
  final List<ChatMediaItem> media;
  final List<String> mentions;
  final DateTime createdAt;
  final ChatDeliveryStatus deliveryStatus;

  const ChatMessageItem({
    required this.id,
    required this.clientMessageId,
    required this.body,
    required this.senderName,
    required this.isMine,
    required this.isDeleted,
    required this.isEdited,
    required this.replyToId,
    required this.readCount,
    required this.reactions,
    required this.media,
    required this.mentions,
    required this.createdAt,
    this.deliveryStatus = ChatDeliveryStatus.sent,
  });

  factory ChatMessageItem.fromJson(Map<String, dynamic> json) {
    final sender = json['sender'] as Map<String, dynamic>? ?? const {};
    return ChatMessageItem(
      id: json['id'] as String,
      clientMessageId: json['client_message_id'] as String?,
      body: json['body'] as String?,
      senderName: sender['display_name'] as String? ?? 'Unknown user',
      isMine: json['is_mine'] as bool? ?? false,
      isDeleted: json['deleted_at'] != null,
      isEdited: json['edited_at'] != null,
      replyToId: json['reply_to_id'] as String?,
      readCount: json['read_count'] as int? ?? 0,
      reactions: (json['reactions'] as List? ?? const [])
          .map((item) => (item as Map<String, dynamic>)['reaction'] as String)
          .toList(),
      media: (json['media'] as List? ?? const [])
          .map((item) => ChatMediaItem.fromJson(item as Map<String, dynamic>))
          .toList(),
      mentions: (json['mentions'] as List? ?? const [])
          .map((item) => (item as Map<String, dynamic>)['username'] as String)
          .toList(),
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }

  ChatMessageItem copyWith({
    String? id,
    int? readCount,
    ChatDeliveryStatus? deliveryStatus,
  }) => ChatMessageItem(
    id: id ?? this.id,
    clientMessageId: clientMessageId,
    body: body,
    senderName: senderName,
    isMine: isMine,
    isDeleted: isDeleted,
    isEdited: isEdited,
    replyToId: replyToId,
    readCount: readCount ?? this.readCount,
    reactions: reactions,
    media: media,
    mentions: mentions,
    createdAt: createdAt,
    deliveryStatus: deliveryStatus ?? this.deliveryStatus,
  );
}

enum ChatDeliveryStatus { sending, sent, failed }

class ChatMediaItem {
  final String id;
  final String url;
  final String originalName;
  final String mimeType;
  final int size;

  const ChatMediaItem({
    required this.id,
    required this.url,
    required this.originalName,
    required this.mimeType,
    required this.size,
  });

  bool get isImage => mimeType.startsWith('image/');

  factory ChatMediaItem.fromJson(Map<String, dynamic> json) => ChatMediaItem(
    id: json['id'] as String,
    url: json['url'] as String,
    originalName: json['original_name'] as String,
    mimeType: json['mime_type'] as String,
    size: json['size'] as int,
  );
}
