import 'package:flutter_test/flutter_test.dart';
import 'package:squadup/features/notifications/domain/app_notification.dart';

void main() {
  test('chat notification keeps navigation payload and readable preview', () {
    final notification = AppNotification.fromJson({
      'id': 'notification-1',
      'created_at': '2026-09-14T10:00:00Z',
      'read_at': null,
      'data': {
        'type': 'chat_message',
        'conversation_id': 'conversation-1',
        'actor_name': 'Alex',
        'preview': 'See you at the court',
      },
    });

    expect(notification.conversationId, 'conversation-1');
    expect(notification.isUnread, isTrue);
    expect(notification.message, 'Alex: See you at the court');
  });

  test('activity notification keeps title and destination', () {
    final notification = AppNotification.fromJson({
      'id': 'notification-2',
      'created_at': '2026-09-14T10:00:00Z',
      'read_at': '2026-09-14T10:01:00Z',
      'data': {
        'type': 'activity_invitation',
        'activity_id': 'activity-1',
        'activity_title': 'Sunday Badminton',
      },
    });

    expect(notification.activityId, 'activity-1');
    expect(notification.isUnread, isFalse);
    expect(notification.message, 'You were invited to Sunday Badminton.');
  });
}
