import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../../theme/app_theme.dart';
import '../../../widgets/custom_icon_widget.dart';
import '../../../widgets/custom_image_widget.dart';

class FriendsStoriesWidget extends StatelessWidget {
  const FriendsStoriesWidget({super.key});

  static final List<Map<String, dynamic>> _friendsMaps = [
    {
      'name': 'You',
      'imageUrl':
          'https://images.pexels.com/photos/415829/pexels-photo-415829.jpeg?w=100',
      'semanticLabel': 'Your profile avatar',
      'isAdd': true,
      'isOnline': false,
    },
    {
      'name': 'Marcus',
      'imageUrl':
          'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100',
      'semanticLabel': 'Young Black man smiling in casual shirt',
      'isAdd': false,
      'isOnline': true,
    },
    {
      'name': 'Priya',
      'imageUrl':
          'https://images.pexels.com/photos/1239291/pexels-photo-1239291.jpeg?w=100',
      'semanticLabel': 'South Asian woman with long dark hair outdoors',
      'isAdd': false,
      'isOnline': true,
    },
    {
      'name': 'Jake',
      'imageUrl':
          'https://images.pixabay.com/photo/2017/08/01/01/33/beanie-2562646_640.jpg',
      'semanticLabel': 'White man wearing beanie hat looking sideways',
      'isAdd': false,
      'isOnline': false,
    },
    {
      'name': 'Amara',
      'imageUrl':
          'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=100',
      'semanticLabel': 'African woman with natural hair smiling confidently',
      'isAdd': false,
      'isOnline': true,
    },
    {
      'name': 'Kenji',
      'imageUrl':
          'https://images.pexels.com/photos/1222271/pexels-photo-1222271.jpeg?w=100',
      'semanticLabel': 'East Asian man with short hair in urban setting',
      'isAdd': false,
      'isOnline': false,
    },
  ];

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 80,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: _friendsMaps.length,
        separatorBuilder: (_, __) => const SizedBox(width: 14),
        itemBuilder: (context, i) {
          final f = _friendsMaps[i];
          return _StoryAvatar(
            name: f['name'] as String,
            imageUrl: f['imageUrl'] as String,
            semanticLabel: f['semanticLabel'] as String,
            isAdd: f['isAdd'] as bool,
            isOnline: f['isOnline'] as bool,
          );
        },
      ),
    );
  }
}

class _StoryAvatar extends StatelessWidget {
  final String name;
  final String imageUrl;
  final String semanticLabel;
  final bool isAdd;
  final bool isOnline;

  const _StoryAvatar({
    required this.name,
    required this.imageUrl,
    required this.semanticLabel,
    required this.isAdd,
    required this.isOnline,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () {},
      child: Column(
        children: [
          Stack(
            children: [
              Container(
                width: 52,
                height: 52,
                padding: const EdgeInsets.all(2),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: isAdd
                      ? null
                      : const LinearGradient(
                          colors: [Color(0xFF1A6B5A), Color(0xFF4DB6AC)],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                  color: isAdd ? const Color(0xFFE5E7EB) : null,
                ),
                child: Container(
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    color: Colors.white,
                  ),
                  padding: const EdgeInsets.all(2),
                  child: isAdd
                      ? Container(
                          decoration: BoxDecoration(
                            color: AppTheme.primary.withOpacity(0.1),
                            shape: BoxShape.circle,
                          ),
                          child: Center(
                            child: CustomIconWidget(
                              iconName: 'add',
                              size: 20,
                              color: AppTheme.primary,
                            ),
                          ),
                        )
                      : ClipOval(
                          child: CustomImageWidget(
                            imageUrl: imageUrl,
                            fit: BoxFit.cover,
                            semanticLabel: semanticLabel,
                          ),
                        ),
                ),
              ),
              if (isOnline)
                Positioned(
                  bottom: 2,
                  right: 2,
                  child: Container(
                    width: 12,
                    height: 12,
                    decoration: BoxDecoration(
                      color: AppTheme.onlineGreen,
                      shape: BoxShape.circle,
                      border: Border.all(color: Colors.white, width: 2),
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            name,
            style: GoogleFonts.dmSans(
              fontSize: 11,
              fontWeight: FontWeight.w500,
              color: const Color(0xFF374151),
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }
}
