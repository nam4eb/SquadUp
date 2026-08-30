import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../../theme/app_theme.dart';
import '../../../widgets/custom_image_widget.dart';

class AuthHeaderWidget extends StatelessWidget {
  final bool isLogin;

  const AuthHeaderWidget({required this.isLogin, super.key});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 280,
      child: Stack(
        children: [
          _buildImageCollage(),
          _buildGradientOverlay(),
          _buildLogoAndText(),
        ],
      ),
    );
  }

  Widget _buildImageCollage() {
    final images = [
      (
        url:
            'https://images.pexels.com/photos/1547813/pexels-photo-1547813.jpeg',
        label: 'Group of friends playing basketball outdoors on a sunny day',
        left: 0.0,
        top: 0.0,
        width: 0.42,
        height: 0.55,
      ),
      (
        url:
            'https://images.unsplash.com/photo-1517649763962-0c623066013b?w=400',
        label: 'Athletes running together in a city park during morning run',
        left: 0.44,
        top: 0.0,
        width: 0.56,
        height: 0.38,
      ),
      (
        url:
            'https://images.pixabay.com/photo/2016/11/29/02/05/friends-1867890_640.jpg',
        label:
            'Diverse group of young adults laughing and socializing at outdoor event',
        left: 0.0,
        top: 0.57,
        width: 0.55,
        height: 0.43,
      ),
      (
        url:
            'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?w=400',
        label: 'Friends hiking on mountain trail with backpacks on a clear day',
        left: 0.57,
        top: 0.40,
        width: 0.43,
        height: 0.60,
      ),
    ];

    return LayoutBuilder(
      builder: (context, constraints) {
        final w = constraints.maxWidth;
        final h = constraints.maxHeight;
        return Stack(
          children: images.map((img) {
            return Positioned(
              left: img.left * w + 3,
              top: img.top * h + 3,
              width: img.width * w - 6,
              height: img.height * h - 6,
              child: ClipRRect(
                borderRadius: BorderRadius.circular(16),
                child: CustomImageWidget(
                  imageUrl: img.url,
                  fit: BoxFit.cover,
                  semanticLabel: img.label,
                ),
              ),
            );
          }).toList(),
        );
      },
    );
  }

  Widget _buildGradientOverlay() {
    return Positioned.fill(
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              Colors.transparent,
              AppTheme.backgroundLight.withAlpha(179),
              AppTheme.backgroundLight,
            ],
            stops: const [0.4, 0.75, 1.0],
          ),
        ),
      ),
    );
  }

  Widget _buildLogoAndText() {
    return Positioned(
      bottom: 16,
      left: 24,
      right: 24,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: AppTheme.primary,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Center(
                  child: Text(
                    'S',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                      fontSize: 20,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              RichText(
                text: TextSpan(
                  children: [
                    TextSpan(
                      text: 'Squad',
                      style: GoogleFonts.dmSans(
                        fontSize: 22,
                        fontWeight: FontWeight.w800,
                        color: const Color(0xFF1A1A1A),
                      ),
                    ),
                    TextSpan(
                      text: 'Up',
                      style: GoogleFonts.dmSans(
                        fontSize: 22,
                        fontWeight: FontWeight.w400,
                        color: AppTheme.primary,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            isLogin ? 'Welcome back!' : 'Plan life together.',
            style: GoogleFonts.dmSans(
              fontSize: 26,
              fontWeight: FontWeight.w700,
              color: const Color(0xFF1A1A1A),
              height: 1.2,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            isLogin
                ? 'Sign in to see what your squad is up to.'
                : 'Organize activities, build clans, compete.',
            style: GoogleFonts.dmSans(
              fontSize: 14,
              color: const Color(0xFF6B7280),
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }
}
