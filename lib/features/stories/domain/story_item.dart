class StoryItem {
  final String id;
  final String userId;
  final String userName;
  final String? avatarUrl;
  final String mediaUrl;
  final String mediaType;
  final String? thumbnailUrl;
  final int? durationMs;
  final String? caption;
  final bool viewedByMe;
  final bool isMine;
  final DateTime expiresAt;

  const StoryItem({
    required this.id,
    required this.userId,
    required this.userName,
    required this.avatarUrl,
    required this.mediaUrl,
    required this.mediaType,
    required this.thumbnailUrl,
    required this.durationMs,
    required this.caption,
    required this.viewedByMe,
    required this.isMine,
    required this.expiresAt,
  });

  factory StoryItem.fromJson(Map<String, dynamic> json) {
    final user = json['user'] as Map<String, dynamic>? ?? const {};
    return StoryItem(
      id: json['id'] as String,
      userId: user['id'] as String,
      userName: user['display_name'] as String? ?? 'Story',
      avatarUrl: user['avatar_url'] as String?,
      mediaUrl: json['media_url'] as String,
      mediaType: json['media_type'] as String? ?? 'image',
      thumbnailUrl: json['thumbnail_url'] as String?,
      durationMs: json['duration_ms'] as int?,
      caption: json['caption'] as String?,
      viewedByMe: json['viewed_by_me'] as bool? ?? false,
      isMine: json['is_mine'] as bool? ?? false,
      expiresAt: DateTime.parse(json['expires_at'] as String),
    );
  }
}
