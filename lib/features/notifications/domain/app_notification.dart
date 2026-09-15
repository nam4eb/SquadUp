class AppNotification {
  final String id;
  final String type;
  final String? actorUsername;
  final String? actorName;
  final String? activityId;
  final String? activityTitle;
  final String? conversationId;
  final String? clanId;
  final String? clanName;
  final String? preview;
  final DateTime createdAt;
  final DateTime? readAt;

  const AppNotification({
    required this.id,
    required this.type,
    required this.actorUsername,
    required this.actorName,
    required this.activityId,
    required this.activityTitle,
    required this.conversationId,
    required this.clanId,
    required this.clanName,
    required this.preview,
    required this.createdAt,
    required this.readAt,
  });

  bool get isUnread => readAt == null;

  String get message => switch (type) {
    'friend_request' =>
      '@${actorUsername ?? 'Someone'} sent you a friend request.',
    'friend_accepted' =>
      '@${actorUsername ?? 'Someone'} accepted your friend request.',
    'activity_invitation' =>
      'You were invited to ${activityTitle ?? 'an activity'}.',
    'activity_participant_joined' =>
      '@${actorUsername ?? 'Someone'} joined ${activityTitle ?? 'your activity'}.',
    'activity_starting_soon' =>
      '${activityTitle ?? 'Your activity'} is starting soon.',
    'activity_cancelled' => '${activityTitle ?? 'An activity'} was cancelled.',
    'chat_message' =>
      '${actorName ?? 'Someone'}: ${preview ?? 'sent a message'}',
    'mention' => '${actorName ?? 'Someone'} mentioned you in a message.',
    'clan_invitation' => 'You were invited to ${clanName ?? 'a clan'}.',
    'clan_role_changed' => 'Your role changed in ${clanName ?? 'a clan'}.',
    _ => 'You have a new notification.',
  };

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as Map<String, dynamic>? ?? const {};
    return AppNotification(
      id: json['id'] as String,
      type: data['type'] as String? ?? 'unknown',
      actorUsername: data['actor_username'] as String?,
      actorName: data['actor_name'] as String?,
      activityId: data['activity_id'] as String?,
      activityTitle: data['activity_title'] as String?,
      conversationId: data['conversation_id'] as String?,
      clanId: data['clan_id'] as String?,
      clanName: data['clan_name'] as String?,
      preview: data['preview'] as String?,
      createdAt: DateTime.parse(json['created_at'] as String),
      readAt: json['read_at'] == null
          ? null
          : DateTime.parse(json['read_at'] as String),
    );
  }
}
