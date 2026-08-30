class ClanItem {
  final String id;
  final String name;
  final String slug;
  final String? description;
  final String visibility;
  final String joinPolicy;
  final int memberCount;
  final String? viewerMembership;
  final bool isOwner;
  final String? avatarUrl;
  final String? coverUrl;

  const ClanItem({
    required this.id,
    required this.name,
    required this.slug,
    required this.description,
    required this.visibility,
    required this.joinPolicy,
    required this.memberCount,
    required this.viewerMembership,
    required this.isOwner,
    required this.avatarUrl,
    required this.coverUrl,
  });

  factory ClanItem.fromJson(Map<String, dynamic> json) => ClanItem(
    id: json['id'] as String,
    name: json['name'] as String,
    slug: json['slug'] as String,
    description: json['description'] as String?,
    visibility: json['visibility'] as String,
    joinPolicy: json['join_policy'] as String,
    memberCount: json['active_members_count'] as int? ?? 0,
    viewerMembership: json['viewer_membership'] as String?,
    isOwner: json['is_owner'] as bool? ?? false,
    avatarUrl: json['avatar_url'] as String?,
    coverUrl: json['cover_url'] as String?,
  );
}

class ClanAnnouncementItem {
  final String id;
  final String title;
  final String body;
  final String authorName;
  final bool isPinned;
  final bool canManage;

  const ClanAnnouncementItem({
    required this.id,
    required this.title,
    required this.body,
    required this.authorName,
    required this.isPinned,
    required this.canManage,
  });

  factory ClanAnnouncementItem.fromJson(Map<String, dynamic> json) {
    final author = json['author'] as Map<String, dynamic>? ?? const {};
    return ClanAnnouncementItem(
      id: json['id'] as String,
      title: json['title'] as String,
      body: json['body'] as String,
      authorName: author['display_name'] as String? ?? 'Clan manager',
      isPinned: json['is_pinned'] as bool? ?? false,
      canManage: json['can_manage'] as bool? ?? false,
    );
  }
}

class ClanRoleItem {
  final String id;
  final String name;
  final String slug;
  final bool isSystem;
  final List<String> permissions;

  const ClanRoleItem({
    required this.id,
    required this.name,
    required this.slug,
    required this.isSystem,
    required this.permissions,
  });

  factory ClanRoleItem.fromJson(Map<String, dynamic> json) => ClanRoleItem(
    id: json['id'] as String,
    name: json['name'] as String,
    slug: json['slug'] as String,
    isSystem: json['is_system'] as bool? ?? false,
    permissions: (json['permissions'] as List? ?? const []).cast<String>(),
  );
}

class ClanMemberItem {
  final String id;
  final String userId;
  final String userName;
  final String status;
  final ClanRoleItem? role;

  const ClanMemberItem({
    required this.id,
    required this.userId,
    required this.userName,
    required this.status,
    required this.role,
  });

  factory ClanMemberItem.fromJson(Map<String, dynamic> json) {
    final user = json['user'] as Map<String, dynamic>;
    final role = json['role'] as Map<String, dynamic>?;
    return ClanMemberItem(
      id: json['id'] as String,
      userId: user['id'] as String,
      userName: user['display_name'] as String,
      status: json['status'] as String,
      role: role == null
          ? null
          : ClanRoleItem(
              id: role['id'] as String,
              name: role['name'] as String,
              slug: role['slug'] as String,
              isSystem: true,
              permissions: const [],
            ),
    );
  }
}

class ClanStatistics {
  final int totalEvents;
  final int completedEvents;
  final int activeMembers;
  final int attended;
  final int absent;
  final num? attendanceRate;

  const ClanStatistics({
    required this.totalEvents,
    required this.completedEvents,
    required this.activeMembers,
    required this.attended,
    required this.absent,
    required this.attendanceRate,
  });

  factory ClanStatistics.fromJson(Map<String, dynamic> json) {
    final attendance = json['attendance'] as Map<String, dynamic>? ?? const {};
    return ClanStatistics(
      totalEvents: json['total_events'] as int,
      completedEvents: json['completed_events'] as int,
      activeMembers: json['active_members'] as int,
      attended: attendance['attended'] as int? ?? 0,
      absent: attendance['absent'] as int? ?? 0,
      attendanceRate: attendance['rate'] as num?,
    );
  }
}

class ClanLeaderboardEntry {
  final int rank;
  final String displayName;
  final num score;

  const ClanLeaderboardEntry({
    required this.rank,
    required this.displayName,
    required this.score,
  });

  factory ClanLeaderboardEntry.fromJson(Map<String, dynamic> json) =>
      ClanLeaderboardEntry(
        rank: json['rank'] as int,
        displayName: json['display_name'] as String,
        score: json['score'] as num,
      );
}

class ClanMessageMediaItem {
  final String url;
  final String originalName;
  final String mimeType;
  final int size;

  const ClanMessageMediaItem({
    required this.url,
    required this.originalName,
    required this.mimeType,
    required this.size,
  });

  bool get isImage => mimeType.startsWith('image/');

  factory ClanMessageMediaItem.fromJson(Map<String, dynamic> json) =>
      ClanMessageMediaItem(
        url: json['url'] as String,
        originalName: json['original_name'] as String,
        mimeType: json['mime_type'] as String,
        size: json['size'] as int,
      );
}

class ClanMessageItem {
  final String id;
  final String? body;
  final String senderName;
  final bool isMine;
  final bool isDeleted;
  final DateTime createdAt;
  final List<ClanMessageMediaItem> media;
  final List<String> mentions;

  const ClanMessageItem({
    required this.id,
    required this.body,
    required this.senderName,
    required this.isMine,
    required this.isDeleted,
    required this.createdAt,
    required this.media,
    required this.mentions,
  });

  factory ClanMessageItem.fromJson(Map<String, dynamic> json) {
    final sender = json['sender'] as Map<String, dynamic>? ?? const {};
    return ClanMessageItem(
      id: json['id'] as String,
      body: json['body'] as String?,
      senderName: sender['display_name'] as String? ?? 'Unknown member',
      isMine: json['is_mine'] as bool? ?? false,
      isDeleted: json['deleted_at'] != null,
      createdAt: DateTime.parse(json['created_at'] as String),
      media: (json['media'] as List? ?? const [])
          .map(
            (item) =>
                ClanMessageMediaItem.fromJson(item as Map<String, dynamic>),
          )
          .toList(),
      mentions: (json['mentions'] as List? ?? const [])
          .map((item) => (item as Map<String, dynamic>)['username'] as String)
          .toList(),
    );
  }
}
