import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../../theme/app_theme.dart';
import '../../../widgets/custom_icon_widget.dart';
import '../../../widgets/status_badge_widget.dart';
import '../activity_detail_screen.dart';

class ActivityActionBarWidget extends StatelessWidget {
  final _ActivityDetail activity;
  final bool isJoined;
  final bool isLoading;
  final VoidCallback onJoin;
  final VoidCallback onInvite;
  final VoidCallback onChat;

  const ActivityActionBarWidget({
    required this.activity,
    required this.isJoined,
    required this.isLoading,
    required this.onJoin,
    required this.onInvite,
    required this.onChat,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    final bottomPadding = MediaQuery.of(context).padding.bottom;
    final spotsLeft = activity.maxParticipants - activity.joined;
    final isFull = spotsLeft <= 0;

    return Container(
      padding: EdgeInsets.fromLTRB(20, 16, 20, bottomPadding + 16),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.08),
            blurRadius: 20,
            offset: const Offset(0, -4),
          ),
        ],
        borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Row(
        children: [
          // Invite button
          _ActionIconButton(
            iconName: 'people_outline',
            label: 'Invite',
            onTap: onInvite,
          ),
          const SizedBox(width: 12),
          // Chat button
          _ActionIconButton(
            iconName: 'chat_bubble_outline',
            label: 'Chat',
            onTap: onChat,
            hasBadge: true,
          ),
          const SizedBox(width: 16),
          // Primary join / lobby button
          Expanded(
            child: _PrimaryActionButton(
              activity: activity,
              isJoined: isJoined,
              isLoading: isLoading,
              isFull: isFull,
              spotsLeft: spotsLeft,
              onJoin: onJoin,
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionIconButton extends StatelessWidget {
  final String iconName;
  final String label;
  final VoidCallback onTap;
  final bool hasBadge;

  const _ActionIconButton({
    required this.iconName,
    required this.label,
    required this.onTap,
    this.hasBadge = false,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 56,
        height: 56,
        decoration: BoxDecoration(
          color: const Color(0xFFF3F4F6),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Stack(
          children: [
            Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  CustomIconWidget(
                    iconName: iconName,
                    size: 20,
                    color: const Color(0xFF374151),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    label,
                    style: GoogleFonts.dmSans(
                      fontSize: 10,
                      fontWeight: FontWeight.w500,
                      color: const Color(0xFF6B7280),
                    ),
                  ),
                ],
              ),
            ),
            if (hasBadge)
              Positioned(
                top: 8,
                right: 8,
                child: Container(
                  width: 8,
                  height: 8,
                  decoration: const BoxDecoration(
                    color: Color(0xFF1A6B5A),
                    shape: BoxShape.circle,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _PrimaryActionButton extends StatefulWidget {
  final _ActivityDetail activity;
  final bool isJoined;
  final bool isLoading;
  final bool isFull;
  final int spotsLeft;
  final VoidCallback onJoin;

  const _PrimaryActionButton({
    required this.activity,
    required this.isJoined,
    required this.isLoading,
    required this.isFull,
    required this.spotsLeft,
    required this.onJoin,
  });

  @override
  State<_PrimaryActionButton> createState() => _PrimaryActionButtonState();
}

class _PrimaryActionButtonState extends State<_PrimaryActionButton>
    with SingleTickerProviderStateMixin {
  late AnimationController _scaleController;
  late Animation<double> _scaleAnim;

  @override
  void initState() {
    super.initState();
    _scaleController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 120),
      lowerBound: 0.96,
      upperBound: 1.0,
    )..value = 1.0;
    _scaleAnim = _scaleController;
  }

  @override
  void dispose() {
    _scaleController.dispose();
    super.dispose();
  }

  String get _buttonLabel {
    if (widget.isJoined) return 'View Lobby';
    if (widget.isFull && widget.activity.hasWaitingList) return 'Join Waitlist';
    if (widget.isFull) return 'Activity Full';
    if (widget.activity.requiresApproval) return 'Request to Join';
    return 'Join Activity';
  }

  Color get _buttonColor {
    if (widget.isJoined) return AppTheme.success;
    if (widget.isFull && !widget.activity.hasWaitingList)
      return const Color(0xFF9CA3AF);
    if (widget.isFull) return AppTheme.warning;
    return AppTheme.primary;
  }

  String get _buttonIcon {
    if (widget.isJoined) return 'groups';
    if (widget.isFull && widget.activity.hasWaitingList) return 'schedule';
    if (widget.isFull) return 'block';
    if (widget.activity.requiresApproval) return 'send';
    return 'flash_on';
  }

  @override
  Widget build(BuildContext context) {
    final isDisabled =
        widget.isFull && !widget.activity.hasWaitingList && !widget.isJoined;

    return GestureDetector(
      onTapDown: isDisabled ? null : (_) => _scaleController.reverse(),
      onTapUp: isDisabled
          ? null
          : (_) {
              _scaleController.forward();
              widget.onJoin();
            },
      onTapCancel: () => _scaleController.forward(),
      child: ScaleTransition(
        scale: _scaleAnim,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 300),
          height: 56,
          decoration: BoxDecoration(
            color: isDisabled ? const Color(0xFFE5E7EB) : _buttonColor,
            borderRadius: BorderRadius.circular(16),
            boxShadow: isDisabled
                ? []
                : [
                    BoxShadow(
                      color: _buttonColor.withOpacity(0.35),
                      blurRadius: 16,
                      offset: const Offset(0, 4),
                    ),
                  ],
          ),
          child: Center(
            child: AnimatedSwitcher(
              duration: const Duration(milliseconds: 200),
              child: widget.isLoading
                  ? const SizedBox(
                      key: ValueKey('loading'),
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.5,
                        valueColor: AlwaysStoppedAnimation(Colors.white),
                      ),
                    )
                  : Row(
                      key: ValueKey(_buttonLabel),
                      mainAxisAlignment: MainAxisAlignment.center,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        CustomIconWidget(
                          iconName: _buttonIcon,
                          size: 18,
                          color: isDisabled
                              ? const Color(0xFF9CA3AF)
                              : Colors.white,
                        ),
                        const SizedBox(width: 8),
                        Text(
                          _buttonLabel,
                          style: GoogleFonts.dmSans(
                            fontSize: 15,
                            fontWeight: FontWeight.w700,
                            color: isDisabled
                                ? const Color(0xFF9CA3AF)
                                : Colors.white,
                          ),
                        ),
                      ],
                    ),
            ),
          ),
        ),
      ),
    );
  }
}
