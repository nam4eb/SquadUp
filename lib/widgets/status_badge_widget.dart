import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

enum ActivityStatus {
  open,
  full,
  locked,
  ongoing,
  completed,
  cancelled,
  draft,
  waitlist,
}

class StatusBadgeWidget extends StatelessWidget {
  final ActivityStatus status;
  final bool compact;

  const StatusBadgeWidget({
    required this.status,
    this.compact = false,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final config = _statusConfig(status);
    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? 8 : 10,
        vertical: compact ? 3 : 5,
      ),
      decoration: BoxDecoration(
        color: config.background,
        borderRadius: BorderRadius.circular(100),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 6,
            height: 6,
            decoration: BoxDecoration(
              color: config.dotColor,
              shape: BoxShape.circle,
            ),
          ),
          const SizedBox(width: 5),
          Text(
            config.label,
            style: GoogleFonts.dmSans(
              fontSize: compact ? 10 : 11,
              fontWeight: FontWeight.w600,
              color: config.textColor,
              letterSpacing: 0.2,
            ),
          ),
        ],
      ),
    );
  }

  _StatusConfig _statusConfig(ActivityStatus s) {
    switch (s) {
      case ActivityStatus.open:
        return _StatusConfig(
          label: 'Open',
          background: const Color(0xFFDCFCE7),
          dotColor: const Color(0xFF16A34A),
          textColor: const Color(0xFF15803D),
        );
      case ActivityStatus.full:
        return _StatusConfig(
          label: 'Full',
          background: const Color(0xFFFEF9C3),
          dotColor: const Color(0xFFCA8A04),
          textColor: const Color(0xFFB45309),
        );
      case ActivityStatus.locked:
        return _StatusConfig(
          label: 'Locked',
          background: const Color(0xFFF3F4F6),
          dotColor: const Color(0xFF6B7280),
          textColor: const Color(0xFF374151),
        );
      case ActivityStatus.ongoing:
        return _StatusConfig(
          label: 'Live',
          background: const Color(0xFFDCFCE7),
          dotColor: const Color(0xFF16A34A),
          textColor: const Color(0xFF15803D),
        );
      case ActivityStatus.completed:
        return _StatusConfig(
          label: 'Completed',
          background: const Color(0xFFEDE9FE),
          dotColor: const Color(0xFF7C3AED),
          textColor: const Color(0xFF5B21B6),
        );
      case ActivityStatus.cancelled:
        return _StatusConfig(
          label: 'Cancelled',
          background: const Color(0xFFFEE2E2),
          dotColor: const Color(0xFFDC2626),
          textColor: const Color(0xFFB91C1C),
        );
      case ActivityStatus.draft:
        return _StatusConfig(
          label: 'Draft',
          background: const Color(0xFFF3F4F6),
          dotColor: const Color(0xFF9CA3AF),
          textColor: const Color(0xFF6B7280),
        );
      case ActivityStatus.waitlist:
        return _StatusConfig(
          label: 'Waitlist',
          background: const Color(0xFFFFF7ED),
          dotColor: const Color(0xFFF97316),
          textColor: const Color(0xFFEA580C),
        );
    }
  }
}

class _StatusConfig {
  final String label;
  final Color background;
  final Color dotColor;
  final Color textColor;

  const _StatusConfig({
    required this.label,
    required this.background,
    required this.dotColor,
    required this.textColor,
  });
}
