class SocialUser {
  final String id;
  final String username;
  final String displayName;
  final String? avatarUrl;
  final String? bio;
  final List<String> sports;

  const SocialUser({
    required this.id,
    required this.username,
    required this.displayName,
    this.avatarUrl,
    this.bio,
    this.sports = const [],
  });

  factory SocialUser.fromJson(Map<String, dynamic> json) => SocialUser(
    id: json['id'] as String,
    username: json['username'] as String,
    displayName: json['display_name'] as String,
    avatarUrl: json['avatar_url'] as String?,
    bio: json['bio'] as String?,
    sports: (json['sports'] as List? ?? const []).map((item) {
      final profile = item as Map<String, dynamic>;
      final sport = profile['sport'] as Map<String, dynamic>? ?? const {};
      final name = sport['name'] as String? ?? 'Sport';
      final rating = profile['skill_rating'] as int?;
      return rating == null ? name : '$name · $rating';
    }).toList(),
  );
}

class FriendRequestItem {
  final String id;
  final SocialUser sender;

  const FriendRequestItem({required this.id, required this.sender});

  factory FriendRequestItem.fromJson(Map<String, dynamic> json) =>
      FriendRequestItem(
        id: json['id'] as String,
        sender: SocialUser.fromJson(json['sender'] as Map<String, dynamic>),
      );
}
