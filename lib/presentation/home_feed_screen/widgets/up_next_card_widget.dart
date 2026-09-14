import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../../features/activities/data/activities_repository.dart';
import '../../../routes/app_routes.dart';
import '../../../theme/app_theme.dart';
import '../../../widgets/custom_icon_widget.dart';

class UpNextCardWidget extends ConsumerWidget {
  const UpNextCardWidget({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final activity = ref
        .watch(activityFeedProvider)
        .asData
        ?.value
        .where(
          (item) =>
              item.startsAt.isAfter(DateTime.now()) &&
              (item.isHost || item.viewerParticipation == 'joined'),
        )
        .firstOrNull;
    if (activity == null) {
      return const SizedBox.shrink();
    }
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF1A6B5A), Color(0xFF0D4A3D)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF1A6B5A).withAlpha(77),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'UP NEXT',
                style: GoogleFonts.dmSans(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  color: Colors.white.withAlpha(166),
                  letterSpacing: 1.2,
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 4,
                ),
                decoration: BoxDecoration(
                  color: Colors.white.withAlpha(38),
                  borderRadius: BorderRadius.circular(100),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    CustomIconWidget(
                      iconName: 'access_time',
                      size: 12,
                      color: Colors.white.withAlpha(204),
                    ),
                    const SizedBox(width: 4),
                    Text(
                      _time(activity.startsAt),
                      style: GoogleFonts.dmSans(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: Colors.white.withAlpha(230),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            activity.title,
            style: GoogleFonts.dmSans(
              fontSize: 22,
              fontWeight: FontWeight.w700,
              color: Colors.white,
              height: 1.2,
            ),
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              CustomIconWidget(
                iconName: 'location_on',
                size: 14,
                color: Colors.white.withAlpha(179),
              ),
              const SizedBox(width: 4),
              Text(
                activity.locationName,
                style: GoogleFonts.dmSans(
                  fontSize: 13,
                  color: Colors.white.withAlpha(191),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              _StatChip(
                label: 'PARTICIPANTS',
                value: '${activity.joinedCount}/${activity.maxParticipants}',
                icon: 'group',
              ),
              const Spacer(),
              FilledButton(
                onPressed: () =>
                    context.push(AppRoutes.activityDetail, extra: activity.id),
                style: FilledButton.styleFrom(
                  backgroundColor: Colors.white,
                  foregroundColor: AppTheme.primary,
                ),
                child: const Text('View Lobby'),
              ),
            ],
          ),
        ],
      ),
    );
  }

  String _time(DateTime value) {
    final local = value.toLocal();
    final now = DateTime.now();
    final day =
        local.year == now.year &&
            local.month == now.month &&
            local.day == now.day
        ? 'Today'
        : '${local.day}/${local.month}';
    final hour = local.hour % 12 == 0 ? 12 : local.hour % 12;
    return '$day, $hour:${local.minute.toString().padLeft(2, '0')} ${local.hour >= 12 ? 'PM' : 'AM'}';
  }
}

class _StatChip extends StatelessWidget {
  final String label;
  final String value;
  final String icon;
  final bool isHighlighted;

  const _StatChip({
    required this.label,
    required this.value,
    required this.icon,
    this.isHighlighted = false,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: GoogleFonts.dmSans(
            fontSize: 9,
            fontWeight: FontWeight.w700,
            color: Colors.white.withAlpha(140),
            letterSpacing: 0.8,
          ),
        ),
        const SizedBox(height: 2),
        Row(
          children: [
            CustomIconWidget(
              iconName: icon,
              size: 14,
              color: isHighlighted
                  ? const Color(0xFFF4A261)
                  : Colors.white.withAlpha(230),
            ),
            const SizedBox(width: 4),
            Text(
              value,
              style: GoogleFonts.dmSans(
                fontSize: 15,
                fontWeight: FontWeight.w700,
                color: Colors.white,
              ),
            ),
          ],
        ),
      ],
    );
  }
}
