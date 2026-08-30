import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../../theme/app_theme.dart';
import '../../../widgets/custom_icon_widget.dart';
import '../../../widgets/custom_image_widget.dart';
import '../activity_detail_screen.dart';

class ParticipantsWidget extends StatelessWidget {
  final List<_Participant> participants;
  final int joined;
  final int maxParticipants;
  final int spotsLeft;

  const ParticipantsWidget({
    required this.participants,
    required this.joined,
    required this.maxParticipants,
    required this.spotsLeft,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final fillRatio = joined / maxParticipants;
    final isAlmostFull = spotsLeft <= 3 && spotsLeft > 0;
    final isFull = spotsLeft <= 0;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              'Participants',
              style: GoogleFonts.dmSans(
                fontSize: 15,
                fontWeight: FontWeight.w700,
                color: const Color(0xFF1A1A1A),
              ),
            ),
            GestureDetector(
              onTap: () {},
              child: Text(
                'View All',
                style: GoogleFonts.dmSans(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: AppTheme.primary,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        // Progress bar
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    CustomIconWidget(
                      iconName: 'group',
                      size: 14,
                      color: const Color(0xFF6B7280),
                    ),
                    const SizedBox(width: 5),
                    Text(
                      '$joined / $maxParticipants joined',
                      style: GoogleFonts.dmSans(
                        fontSize: 13,
                        fontWeight: FontWeight.w500,
                        color: const Color(0xFF6B7280),
                      ),
                    ),
                  ],
                ),
                Text(
                  isFull
                      ? 'Activity Full'
                      : isAlmostFull
                      ? '$spotsLeft spots left!'
                      : '$spotsLeft spots available',
                  style: GoogleFonts.dmSans(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: isFull
                        ? AppTheme.error
                        : isAlmostFull
                        ? AppTheme.warning
                        : AppTheme.success,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            ClipRRect(
              borderRadius: BorderRadius.circular(100),
              child: LinearProgressIndicator(
                value: fillRatio,
                minHeight: 6,
                backgroundColor: const Color(0xFFE5E7EB),
                valueColor: AlwaysStoppedAnimation(
                  isFull
                      ? AppTheme.error
                      : isAlmostFull
                      ? AppTheme.warning
                      : AppTheme.primary,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        // Participant grid
        GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 5,
            mainAxisSpacing: 12,
            crossAxisSpacing: 8,
            childAspectRatio: 0.75,
          ),
          itemCount: participants.length + (spotsLeft > 0 ? 1 : 0),
          itemBuilder: (context, i) {
            if (i == participants.length) {
              return _EmptySlot(spotsLeft: spotsLeft);
            }
            return _ParticipantTile(participant: participants[i]);
          },
        ),
      ],
    );
  }
}

class _ParticipantTile extends StatelessWidget {
  final _Participant participant;

  const _ParticipantTile({required this.participant});

  @override
  Widget build(BuildContext context) {
    final isWaitlisted = participant.status == 'waitlisted';
    return Column(
      children: [
        Stack(
          children: [
            Container(
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(
                  color: isWaitlisted
                      ? AppTheme.warning
                      : AppTheme.primaryContainer,
                  width: 2,
                ),
              ),
              child: Opacity(
                opacity: isWaitlisted ? 0.6 : 1.0,
                child: ClipOval(
                  child: CustomImageWidget(
                    imageUrl: participant.avatar,
                    width: 48,
                    height: 48,
                    fit: BoxFit.cover,
                    semanticLabel: participant.semanticLabel,
                  ),
                ),
              ),
            ),
            if (isWaitlisted)
              Positioned(
                bottom: 0,
                right: 0,
                child: Container(
                  width: 16,
                  height: 16,
                  decoration: BoxDecoration(
                    color: AppTheme.warning,
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white, width: 1.5),
                  ),
                  child: const Center(
                    child: CustomIconWidget(
                      iconName: 'schedule',
                      size: 9,
                      color: Colors.white,
                    ),
                  ),
                ),
              ),
          ],
        ),
        const SizedBox(height: 4),
        Text(
          participant.name.split(' ').first,
          style: GoogleFonts.dmSans(
            fontSize: 10,
            fontWeight: FontWeight.w500,
            color: const Color(0xFF6B7280),
          ),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          textAlign: TextAlign.center,
        ),
      ],
    );
  }
}

class _EmptySlot extends StatelessWidget {
  final int spotsLeft;

  const _EmptySlot({required this.spotsLeft});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Container(
          width: 48,
          height: 48,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(
              color: const Color(0xFFE5E7EB),
              width: 2,
              style: BorderStyle.solid,
            ),
          ),
          child: Center(
            child: Text(
              '+${spotsLeft - 1}',
              style: GoogleFonts.dmSans(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: const Color(0xFF9CA3AF),
              ),
            ),
          ),
        ),
        const SizedBox(height: 4),
        Text(
          'Open',
          style: GoogleFonts.dmSans(
            fontSize: 10,
            color: const Color(0xFF9CA3AF),
          ),
        ),
      ],
    );
  }
}
