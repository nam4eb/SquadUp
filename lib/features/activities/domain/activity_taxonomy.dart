class ActivityTopic {
  final String id;
  final String name;
  final String slug;

  const ActivityTopic({
    required this.id,
    required this.name,
    required this.slug,
  });

  factory ActivityTopic.fromJson(Map<String, dynamic> json) => ActivityTopic(
    id: json['id'] as String,
    name: json['name'] as String,
    slug: json['slug'] as String,
  );
}

class ActivityCategory {
  final String id;
  final String name;
  final String slug;
  final String? color;
  final String? icon;
  final List<ActivityTopic> topics;

  const ActivityCategory({
    required this.id,
    required this.name,
    required this.slug,
    required this.color,
    required this.icon,
    required this.topics,
  });

  factory ActivityCategory.fromJson(Map<String, dynamic> json) =>
      ActivityCategory(
        id: json['id'] as String,
        name: json['name'] as String,
        slug: json['slug'] as String,
        color: json['color'] as String?,
        icon: json['icon'] as String?,
        topics: (json['topics'] as List? ?? const [])
            .map((item) => ActivityTopic.fromJson(item as Map<String, dynamic>))
            .toList(),
      );
}
