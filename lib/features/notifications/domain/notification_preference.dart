class NotificationPreference {
  final String type;
  final bool inAppEnabled;
  final bool pushEnabled;

  const NotificationPreference({
    required this.type,
    required this.inAppEnabled,
    required this.pushEnabled,
  });

  factory NotificationPreference.fromJson(Map<String, dynamic> json) =>
      NotificationPreference(
        type: json['type'] as String,
        inAppEnabled: json['in_app_enabled'] as bool,
        pushEnabled: json['push_enabled'] as bool,
      );

  NotificationPreference copyWith({bool? inAppEnabled, bool? pushEnabled}) =>
      NotificationPreference(
        type: type,
        inAppEnabled: inAppEnabled ?? this.inAppEnabled,
        pushEnabled: pushEnabled ?? this.pushEnabled,
      );

  Map<String, dynamic> toJson() => {
    'type': type,
    'in_app_enabled': inAppEnabled,
    'push_enabled': pushEnabled,
  };
}
