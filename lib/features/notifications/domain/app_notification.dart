class AppNotification {
  final String id;
  final String type;
  final String? actorUsername;
  final DateTime createdAt;
  final DateTime? readAt;

  const AppNotification({
    required this.id,
    required this.type,
    required this.actorUsername,
    required this.createdAt,
    required this.readAt,
  });

  bool get isUnread => readAt == null;

  String get message => switch (type) {
    'friend_request' =>
      '@${actorUsername ?? 'Someone'} sent you a friend request.',
    'friend_accepted' =>
      '@${actorUsername ?? 'Someone'} accepted your friend request.',
    _ => 'You have a new notification.',
  };

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as Map<String, dynamic>? ?? const {};
    return AppNotification(
      id: json['id'] as String,
      type: data['type'] as String? ?? 'unknown',
      actorUsername: data['actor_username'] as String?,
      createdAt: DateTime.parse(json['created_at'] as String),
      readAt: json['read_at'] == null
          ? null
          : DateTime.parse(json['read_at'] as String),
    );
  }
}
