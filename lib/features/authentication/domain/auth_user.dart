class AuthUser {
  final String id;
  final String username;
  final String displayName;
  final String email;
  final String? avatarUrl;
  final String? coverUrl;
  final String? bio;
  final String? location;

  const AuthUser({
    required this.id,
    required this.username,
    required this.displayName,
    required this.email,
    this.avatarUrl,
    this.coverUrl,
    this.bio,
    this.location,
  });

  factory AuthUser.fromJson(Map<String, dynamic> json) {
    return AuthUser(
      id: json['id'] as String,
      username: json['username'] as String,
      displayName: json['display_name'] as String,
      email: json['email'] as String? ?? '',
      avatarUrl: json['avatar_url'] as String?,
      coverUrl: json['cover_url'] as String?,
      bio: json['bio'] as String?,
      location: json['location'] as String?,
    );
  }
}

class DeviceSession {
  final String id;
  final String deviceName;
  final bool isCurrent;
  final DateTime? lastUsedAt;
  final DateTime createdAt;

  const DeviceSession({
    required this.id,
    required this.deviceName,
    required this.isCurrent,
    required this.lastUsedAt,
    required this.createdAt,
  });

  factory DeviceSession.fromJson(Map<String, dynamic> json) => DeviceSession(
    id: json['id'] as String,
    deviceName: json['device_name'] as String,
    isCurrent: json['is_current'] as bool,
    lastUsedAt: json['last_used_at'] == null
        ? null
        : DateTime.parse(json['last_used_at'] as String),
    createdAt: DateTime.parse(json['created_at'] as String),
  );
}
