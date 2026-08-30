import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../../theme/app_theme.dart';
import '../../../widgets/custom_icon_widget.dart';
import '../../../widgets/custom_image_widget.dart';

class HomeAppBarWidget extends StatelessWidget {
  const HomeAppBarWidget({super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          _buildAvatarStack(),
          Row(
            children: [
              _NotificationBell(),
              const SizedBox(width: 8),
              _SettingsButton(),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildAvatarStack() {
    final avatars = [
      (
        url:
            'https://images.pexels.com/photos/415829/pexels-photo-415829.jpeg?w=100',
        label: 'Young woman with dark hair smiling outdoors',
      ),
      (
        url:
            'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100',
        label: 'Young man with short hair in casual shirt',
      ),
      (
        url:
            'https://images.pixabay.com/photo/2016/11/21/12/42/beard-1845166_640.jpg',
        label: 'Man with beard and glasses looking at camera',
      ),
    ];

    return SizedBox(
      width: 80,
      height: 36,
      child: Stack(
        children: List.generate(avatars.length, (i) {
          return Positioned(
            left: i * 22.0,
            child: Container(
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: AppTheme.backgroundLight, width: 2),
              ),
              child: ClipOval(
                child: CustomImageWidget(
                  imageUrl: avatars[i].url,
                  width: 36,
                  height: 36,
                  fit: BoxFit.cover,
                  semanticLabel: avatars[i].label,
                ),
              ),
            ),
          );
        }),
      ),
    );
  }
}

class _NotificationBell extends StatefulWidget {
  @override
  State<_NotificationBell> createState() => _NotificationBellState();
}

class _NotificationBellState extends State<_NotificationBell>
    with SingleTickerProviderStateMixin {
  late AnimationController _ringController;
  late Animation<double> _ringAnim;

  @override
  void initState() {
    super.initState();
    _ringController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 400),
    );
    _ringAnim = Tween<double>(begin: -0.05, end: 0.05).animate(
      CurvedAnimation(parent: _ringController, curve: Curves.elasticIn),
    );
    Future.delayed(const Duration(seconds: 2), () {
      if (mounted)
        _ringController.repeat(
          reverse: true,
          period: const Duration(milliseconds: 300),
        );
      Future.delayed(const Duration(milliseconds: 900), () {
        if (mounted) _ringController.stop();
      });
    });
  }

  @override
  void dispose() {
    _ringController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () {},
      child: Container(
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.06),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Stack(
          children: [
            Center(
              child: AnimatedBuilder(
                animation: _ringAnim,
                builder: (context, child) =>
                    Transform.rotate(angle: _ringAnim.value, child: child),
                child: CustomIconWidget(
                  iconName: 'notifications',
                  size: 20,
                  color: const Color(0xFF374151),
                ),
              ),
            ),
            Positioned(
              top: 8,
              right: 8,
              child: Container(
                width: 8,
                height: 8,
                decoration: const BoxDecoration(
                  color: Color(0xFFEF4444),
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

class _SettingsButton extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () {},
      child: Container(
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.06),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Center(
          child: CustomIconWidget(
            iconName: 'settings',
            size: 20,
            color: const Color(0xFF374151),
          ),
        ),
      ),
    );
  }
}
