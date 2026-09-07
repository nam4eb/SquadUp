class ActivityItem {
  final String id;
  final String title;
  final String? description;
  final String? coverUrl;
  final String hostName;
  final bool isHost;
  final bool canManage;
  final String? viewerParticipation;
  final String topicName;
  final String categoryName;
  final String categorySlug;
  final DateTime startsAt;
  final DateTime? endsAt;
  final String locationName;
  final int joinedCount;
  final int maxParticipants;
  final String status;
  final String visibility;
  final bool passwordProtected;
  final bool requireApproval;
  final List<String> rules;
  final int likesCount;
  final int commentsCount;
  final bool likedByMe;
  final bool hasChat;
  final double? distanceKm;
  final String? sportId;
  final String? sportName;
  final String? sportSlug;
  final double? latitude;
  final double? longitude;
  final int? skillMin;
  final int? skillMax;
  final String? matchFormat;
  final double? fee;
  final String? currency;

  const ActivityItem({
    required this.id,
    required this.title,
    required this.description,
    required this.coverUrl,
    required this.hostName,
    required this.isHost,
    required this.canManage,
    required this.viewerParticipation,
    required this.topicName,
    required this.categoryName,
    required this.categorySlug,
    required this.startsAt,
    required this.endsAt,
    required this.locationName,
    required this.joinedCount,
    required this.maxParticipants,
    required this.status,
    required this.visibility,
    required this.passwordProtected,
    required this.requireApproval,
    required this.rules,
    required this.likesCount,
    required this.commentsCount,
    required this.likedByMe,
    required this.hasChat,
    required this.distanceKm,
    required this.sportId,
    required this.sportName,
    required this.sportSlug,
    required this.latitude,
    required this.longitude,
    required this.skillMin,
    required this.skillMax,
    required this.matchFormat,
    required this.fee,
    required this.currency,
  });

  factory ActivityItem.fromJson(Map<String, dynamic> json) {
    final host = json['host'] as Map<String, dynamic>? ?? const {};
    final topic = json['topic'] as Map<String, dynamic>? ?? const {};
    final category = json['category'] as Map<String, dynamic>? ?? const {};
    final location = json['location'] as Map<String, dynamic>? ?? const {};
    final sport = json['sport'] as Map<String, dynamic>? ?? const {};
    final skillRange = json['skill_range'] as Map<String, dynamic>? ?? const {};
    return ActivityItem(
      id: json['id'] as String,
      title: json['title'] as String,
      description: json['description'] as String?,
      coverUrl: json['cover_url'] as String?,
      hostName: host['display_name'] as String? ?? 'Unknown host',
      isHost: json['is_host'] as bool? ?? false,
      canManage: json['can_manage'] as bool? ?? false,
      viewerParticipation: json['viewer_participation'] as String?,
      topicName: topic['name'] as String? ?? 'Activity',
      categoryName: category['name'] as String? ?? 'Other',
      categorySlug: category['slug'] as String? ?? 'other',
      startsAt: DateTime.parse(json['starts_at'] as String),
      endsAt: json['ends_at'] == null
          ? null
          : DateTime.parse(json['ends_at'] as String),
      locationName: location['name'] as String? ?? '',
      joinedCount: json['joined_count'] as int? ?? 0,
      maxParticipants: json['max_participants'] as int,
      status: json['status'] as String,
      visibility: json['visibility'] as String,
      passwordProtected: json['is_password_protected'] as bool? ?? false,
      requireApproval: json['require_approval'] as bool? ?? false,
      rules: (json['rules'] as List? ?? const []).cast<String>(),
      likesCount: json['likes_count'] as int? ?? 0,
      commentsCount: json['comments_count'] as int? ?? 0,
      likedByMe: json['liked_by_me'] as bool? ?? false,
      hasChat: json['has_chat'] as bool? ?? false,
      distanceKm: (json['distance_km'] as num?)?.toDouble(),
      sportId: sport['id'] as String?,
      sportName: sport['name'] as String?,
      sportSlug: sport['slug'] as String?,
      latitude: (location['latitude'] as num?)?.toDouble(),
      longitude: (location['longitude'] as num?)?.toDouble(),
      skillMin: skillRange['min'] as int?,
      skillMax: skillRange['max'] as int?,
      matchFormat: json['match_format'] as String?,
      fee: (json['fee'] as num?)?.toDouble(),
      currency: json['currency'] as String?,
    );
  }
}

class ActivityCommentItem {
  final String id;
  final String body;
  final String userName;
  final bool canDelete;

  const ActivityCommentItem({
    required this.id,
    required this.body,
    required this.userName,
    required this.canDelete,
  });

  factory ActivityCommentItem.fromJson(Map<String, dynamic> json) {
    final user = json['user'] as Map<String, dynamic>? ?? const {};
    return ActivityCommentItem(
      id: json['id'] as String,
      body: json['body'] as String,
      userName: user['display_name'] as String? ?? 'Unknown user',
      canDelete: json['can_delete'] as bool? ?? false,
    );
  }
}

class ActivityParticipantItem {
  final String id;
  final String userId;
  final String userName;
  final String role;
  final String status;
  final int? waitlistPosition;

  const ActivityParticipantItem({
    required this.id,
    required this.userId,
    required this.userName,
    required this.role,
    required this.status,
    required this.waitlistPosition,
  });

  factory ActivityParticipantItem.fromJson(Map<String, dynamic> json) {
    final user = json['user'] as Map<String, dynamic>? ?? const {};
    return ActivityParticipantItem(
      id: json['id'] as String,
      userId: user['id'] as String,
      userName: user['display_name'] as String? ?? 'Unknown user',
      role: json['role'] as String,
      status: json['status'] as String,
      waitlistPosition: json['waitlist_position'] as int?,
    );
  }
}
