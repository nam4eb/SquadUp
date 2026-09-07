class SportOption {
  final String id;
  final String slug;
  final String name;
  final String? icon;
  final int minPlayers;
  final int maxPlayers;

  const SportOption({
    required this.id,
    required this.slug,
    required this.name,
    required this.icon,
    required this.minPlayers,
    required this.maxPlayers,
  });

  factory SportOption.fromJson(Map<String, dynamic> json) => SportOption(
    id: json['id'] as String,
    slug: json['slug'] as String,
    name: json['name'] as String,
    icon: json['icon'] as String?,
    minPlayers: json['min_players'] as int,
    maxPlayers: json['max_players'] as int,
  );
}

class SportSelection {
  final SportOption sport;
  final String level;

  const SportSelection({required this.sport, required this.level});

  factory SportSelection.fromJson(Map<String, dynamic> json) => SportSelection(
    sport: SportOption.fromJson(json['sport'] as Map<String, dynamic>),
    level: json['self_declared_level'] as String,
  );
}
