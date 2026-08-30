import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../../theme/app_theme.dart';
import '../../../widgets/custom_icon_widget.dart';
import '../../../widgets/custom_image_widget.dart';
import '../activity_detail_screen.dart';

class ActivityInfoWidget extends StatelessWidget {
  final _ActivityDetail activity;

  const ActivityInfoWidget({required this.activity, super.key});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _buildHostRow(),
        const SizedBox(height: 20),
        _buildMetaRow(),
        const SizedBox(height: 20),
        _buildDescription(),
        const SizedBox(height: 16),
        _buildBadgeRow(),
      ],
    );
  }

  Widget _buildHostRow() {
    return Row(
      children: [
        Container(
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(color: AppTheme.primaryContainer, width: 2),
          ),
          child: ClipOval(
            child: CustomImageWidget(
              imageUrl: activity.hostAvatar,
              width: 40,
              height: 40,
              fit: BoxFit.cover,
              semanticLabel: activity.hostAvatarLabel,
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Hosted by',
                style: GoogleFonts.dmSans(
                  fontSize: 11,
                  color: const Color(0xFF9CA3AF),
                  fontWeight: FontWeight.w500,
                ),
              ),
              Text(
                activity.hostName,
                style: GoogleFonts.dmSans(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: const Color(0xFF1A1A1A),
                ),
              ),
            ],
          ),
        ),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          decoration: BoxDecoration(
            color: AppTheme.primary.withOpacity(0.08),
            borderRadius: BorderRadius.circular(100),
          ),
          child: Text(
            'View Profile',
            style: GoogleFonts.dmSans(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: AppTheme.primary,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildMetaRow() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.04),
            blurRadius: 12,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        children: [
          _MetaRow(
            iconName: 'calendar_today',
            label: 'Date',
            value: activity.date,
          ),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 10),
            child: Divider(height: 1, color: Color(0xFFF3F4F6)),
          ),
          _MetaRow(
            iconName: 'access_time',
            label: 'Time',
            value: '${activity.time} · ${activity.duration}',
          ),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 10),
            child: Divider(height: 1, color: Color(0xFFF3F4F6)),
          ),
          _MetaRow(
            iconName: 'location_on_filled',
            label: 'Location',
            value: activity.locationAddress,
            isHighlighted: true,
          ),
        ],
      ),
    );
  }

  Widget _buildDescription() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'About this activity',
          style: GoogleFonts.dmSans(
            fontSize: 15,
            fontWeight: FontWeight.w700,
            color: const Color(0xFF1A1A1A),
          ),
        ),
        const SizedBox(height: 8),
        Text(
          activity.description,
          style: GoogleFonts.dmSans(
            fontSize: 14,
            color: const Color(0xFF4B5563),
            height: 1.6,
          ),
        ),
      ],
    );
  }

  Widget _buildBadgeRow() {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        if (activity.isPasswordProtected)
          _InfoBadge(iconName: 'lock', label: 'Password Protected'),
        if (activity.requiresApproval)
          _InfoBadge(iconName: 'check_circle', label: 'Approval Required'),
        if (activity.hasWaitingList)
          _InfoBadge(iconName: 'schedule', label: 'Waiting List'),
        if (activity.ageRestriction != null)
          _InfoBadge(
            iconName: 'badge',
            label: '${activity.ageRestriction}+ only',
          ),
      ],
    );
  }
}

class _MetaRow extends StatelessWidget {
  final String iconName;
  final String label;
  final String value;
  final bool isHighlighted;

  const _MetaRow({
    required this.iconName,
    required this.label,
    required this.value,
    this.isHighlighted = false,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 36,
          height: 36,
          decoration: BoxDecoration(
            color: AppTheme.primary.withOpacity(0.08),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Center(
            child: CustomIconWidget(
              iconName: iconName,
              size: 18,
              color: AppTheme.primary,
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: GoogleFonts.dmSans(
                  fontSize: 11,
                  color: const Color(0xFF9CA3AF),
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                value,
                style: GoogleFonts.dmSans(
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                  color: isHighlighted
                      ? AppTheme.primary
                      : const Color(0xFF1A1A1A),
                ),
              ),
            ],
          ),
        ),
        if (isHighlighted)
          CustomIconWidget(
            iconName: 'chevron_right',
            size: 16,
            color: AppTheme.primary,
          ),
      ],
    );
  }
}

class _InfoBadge extends StatelessWidget {
  final String iconName;
  final String label;

  const _InfoBadge({required this.iconName, required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: const Color(0xFFF3F4F6),
        borderRadius: BorderRadius.circular(100),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          CustomIconWidget(
            iconName: iconName,
            size: 13,
            color: const Color(0xFF6B7280),
          ),
          const SizedBox(width: 5),
          Text(
            label,
            style: GoogleFonts.dmSans(
              fontSize: 12,
              fontWeight: FontWeight.w500,
              color: const Color(0xFF6B7280),
            ),
          ),
        ],
      ),
    );
  }
}
