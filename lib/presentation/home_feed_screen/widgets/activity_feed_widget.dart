import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../../theme/app_theme.dart';
import '../../../widgets/custom_icon_widget.dart';
import '../../../widgets/custom_image_widget.dart';
import '../../../widgets/status_badge_widget.dart';

class _ActivityCardModel {
  final String id;
  final String title;
  final String category;
  final String categoryIcon;
  final String hostName;
  final String hostAvatar;
  final String hostAvatarLabel;
  final String coverUrl;
  final String coverLabel;
  final String date;
  final String time;
  final String location;
  final int joined;
  final int maxParticipants;
  final ActivityStatus status;
  final bool isPasswordProtected;
  final List<String> participantAvatars;
  final List<String> participantLabels;

  const _ActivityCardModel({
    required this.id,
    required this.title,
    required this.category,
    required this.categoryIcon,
    required this.hostName,
    required this.hostAvatar,
    required this.hostAvatarLabel,
    required this.coverUrl,
    required this.coverLabel,
    required this.date,
    required this.time,
    required this.location,
    required this.joined,
    required this.maxParticipants,
    required this.status,
    required this.isPasswordProtected,
    required this.participantAvatars,
    required this.participantLabels,
  });
}

class ActivityFeedWidget extends StatefulWidget {
  final Function(String) onActivityTap;
  final double bottomPadding;

  const ActivityFeedWidget({
    required this.onActivityTap,
    required this.bottomPadding,
    super.key,
  });

  @override
  State<ActivityFeedWidget> createState() => _ActivityFeedWidgetState();
}

class _ActivityFeedWidgetState extends State<ActivityFeedWidget> {
  final List<Map<String, dynamic>> _activityMaps = [
    {
      'id': 'act-001',
      'title': 'Sunday Morning Run — Riverside',
      'category': 'Running',
      'categoryIcon': 'directions_run',
      'hostName': 'Marcus Williams',
      'hostAvatar':
          'https://images.unsplash.com/photo-1728957567504-e12877206d5b',
      'hostAvatarLabel': 'Young Black man smiling in casual shirt',
      'coverUrl':
          'https://img.rocket.new/generatedImages/rocket_gen_img_1a95cd5bc-1767666650382.png',
      'coverLabel': 'Group of runners on riverside path during sunrise',
      'date': 'Sun, Aug 18',
      'time': '7:00 AM',
      'location': 'Riverside Park, North Gate',
      'joined': 9,
      'maxParticipants': 15,
      'status': 'open',
      'isPasswordProtected': false,
      'participantAvatars': [
        'https://images.pexels.com/photos/415829/pexels-photo-415829.jpeg?w=60',
        'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=60',
        'https://images.pexels.com/photos/1222271/pexels-photo-1222271.jpeg?w=60',
      ],
      'participantLabels': [
        'Young woman with dark hair smiling outdoors',
        'African woman with natural hair smiling confidently',
        'East Asian man with short hair in urban setting',
      ],
    },
    {
      'id': 'act-002',
      'title': 'Craft Beer Tasting — Downtown Taphouse',
      'category': 'Food & Drink',
      'categoryIcon': 'restaurant',
      'hostName': 'Priya Sharma',
      'hostAvatar':
          'https://img.rocket.new/generatedImages/rocket_gen_img_109d31244-1773133393044.png',
      'hostAvatarLabel': 'South Asian woman with long dark hair outdoors',
      'coverUrl':
          'https://images.unsplash.com/photo-1718050492408-b11b9e6e13f6',
      'coverLabel':
          'Row of craft beer glasses on bar counter with warm lighting',
      'date': 'Fri, Aug 22',
      'time': '7:30 PM',
      'location': 'The Taphouse, 14 King St',
      'joined': 8,
      'maxParticipants': 8,
      'status': 'full',
      'isPasswordProtected': false,
      'participantAvatars': [
        'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=60',
        'https://images.pexels.com/photos/415829/pexels-photo-415829.jpeg?w=60',
        'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=60',
      ],
      'participantLabels': [
        'Young man with short hair in casual shirt',
        'Young woman with dark hair smiling outdoors',
        'Young Black man smiling in casual shirt',
      ],
    },
    {
      'id': 'act-003',
      'title': 'Beach Volleyball Tournament',
      'category': 'Sports',
      'categoryIcon': 'sports',
      'hostName': 'Jake Thompson',
      'hostAvatar': 'https://images.unsplash.com/photo-1547857177-4b81a5024e64',
      'hostAvatarLabel': 'White man wearing beanie hat looking sideways',
      'coverUrl':
          'https://images.unsplash.com/photo-1612872087720-bb876e2e67d1',
      'coverLabel':
          'Players jumping for volleyball at beach during golden hour',
      'date': 'Sat, Aug 23',
      'time': '2:00 PM',
      'location': 'Bondi Beach, South End',
      'joined': 12,
      'maxParticipants': 24,
      'status': 'open',
      'isPasswordProtected': true,
      'participantAvatars': [
        'https://images.pexels.com/photos/1239291/pexels-photo-1239291.jpeg?w=60',
        'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=60',
        'https://images.pexels.com/photos/1222271/pexels-photo-1222271.jpeg?w=60',
      ],
      'participantLabels': [
        'South Asian woman with long dark hair outdoors',
        'African woman with natural hair smiling confidently',
        'East Asian man with short hair in urban setting',
      ],
    },
    {
      'id': 'act-004',
      'title': 'Night Hiking — Bluff Trail',
      'category': 'Outdoors',
      'categoryIcon': 'terrain',
      'hostName': 'Amara Osei',
      'hostAvatar':
          'https://img.rocket.new/generatedImages/rocket_gen_img_185d73bc8-1772147601277.png',
      'hostAvatarLabel': 'African woman with natural hair smiling confidently',
      'coverUrl':
          'https://images.unsplash.com/photo-1485871811272-aa71f1f55124',
      'coverLabel':
          'Hikers with headlamps on mountain trail at night with stars',
      'date': 'Sat, Aug 30',
      'time': '8:00 PM',
      'location': 'Bluff Trail Head, West Ridge',
      'joined': 5,
      'maxParticipants': 10,
      'status': 'open',
      'isPasswordProtected': false,
      'participantAvatars': [
        'https://images.pexels.com/photos/415829/pexels-photo-415829.jpeg?w=60',
        'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=60',
      ],
      'participantLabels': [
        'Young woman with dark hair smiling outdoors',
        'Young man with short hair in casual shirt',
      ],
    },
  ];

  late List<_ActivityCardModel> _activities;
  final Set<String> _likedIds = {};

  @override
  void initState() {
    super.initState();
    _activities = _activityMaps.map(_fromMap).toList();
  }

  static ActivityStatus _statusFromString(String v) {
    switch (v) {
      case 'open':
        return ActivityStatus.open;
      case 'full':
        return ActivityStatus.full;
      case 'locked':
        return ActivityStatus.locked;
      case 'ongoing':
        return ActivityStatus.ongoing;
      case 'completed':
        return ActivityStatus.completed;
      case 'cancelled':
        return ActivityStatus.cancelled;
      case 'waitlist':
        return ActivityStatus.waitlist;
      default:
        return ActivityStatus.open;
    }
  }

  static _ActivityCardModel _fromMap(Map<String, dynamic> m) {
    return _ActivityCardModel(
      id: m['id'] as String,
      title: m['title'] as String,
      category: m['category'] as String,
      categoryIcon: m['categoryIcon'] as String,
      hostName: m['hostName'] as String,
      hostAvatar: m['hostAvatar'] as String,
      hostAvatarLabel: m['hostAvatarLabel'] as String,
      coverUrl: m['coverUrl'] as String,
      coverLabel: m['coverLabel'] as String,
      date: m['date'] as String,
      time: m['time'] as String,
      location: m['location'] as String,
      joined: m['joined'] as int,
      maxParticipants: m['maxParticipants'] as int,
      status: _statusFromString(m['status'] as String),
      isPasswordProtected: m['isPasswordProtected'] as bool,
      participantAvatars: List<String>.from(m['participantAvatars']),
      participantLabels: List<String>.from(m['participantLabels']),
    );
  }

  @override
  Widget build(BuildContext context) {
    return SliverPadding(
      padding: EdgeInsets.fromLTRB(20, 0, 20, widget.bottomPadding),
      sliver: SliverList(
        delegate: SliverChildBuilderDelegate((context, index) {
          return _AnimatedFeedCard(
            activity: _activities[index],
            isLiked: _likedIds.contains(_activities[index].id),
            onLike: () => setState(() {
              final id = _activities[index].id;
              if (_likedIds.contains(id)) {
                _likedIds.remove(id);
              } else {
                _likedIds.add(id);
              }
            }),
            onTap: () => widget.onActivityTap(_activities[index].id),
            index: index,
          );
        }, childCount: _activities.length),
      ),
    );
  }
}

class _AnimatedFeedCard extends StatefulWidget {
  final _ActivityCardModel activity;
  final bool isLiked;
  final VoidCallback onLike;
  final VoidCallback onTap;
  final int index;

  const _AnimatedFeedCard({
    required this.activity,
    required this.isLiked,
    required this.onLike,
    required this.onTap,
    required this.index,
  });

  @override
  State<_AnimatedFeedCard> createState() => _AnimatedFeedCardState();
}

class _AnimatedFeedCardState extends State<_AnimatedFeedCard>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _fadeAnim;
  late Animation<Offset> _slideAnim;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 400),
    );
    _fadeAnim = CurvedAnimation(parent: _controller, curve: Curves.easeOut);
    _slideAnim = Tween<Offset>(
      begin: const Offset(0, 0.08),
      end: Offset.zero,
    ).animate(CurvedAnimation(parent: _controller, curve: Curves.easeOutCubic));

    final delay = Duration(milliseconds: widget.index * 80);
    Future.delayed(delay, () {
      if (mounted) _controller.forward();
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: _fadeAnim,
      child: SlideTransition(
        position: _slideAnim,
        child: Padding(
          padding: const EdgeInsets.only(bottom: 16),
          child: _ActivityFeedCard(
            activity: widget.activity,
            isLiked: widget.isLiked,
            onLike: widget.onLike,
            onTap: widget.onTap,
          ),
        ),
      ),
    );
  }
}

class _ActivityFeedCard extends StatelessWidget {
  final _ActivityCardModel activity;
  final bool isLiked;
  final VoidCallback onLike;
  final VoidCallback onTap;

  const _ActivityFeedCard({
    required this.activity,
    required this.isLiked,
    required this.onLike,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final spotsLeft = activity.maxParticipants - activity.joined;
    return GestureDetector(
      onTap: onTap,
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withAlpha(15),
              blurRadius: 16,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                ClipRRect(
                  borderRadius: const BorderRadius.vertical(
                    top: Radius.circular(20),
                  ),
                  child: AspectRatio(
                    aspectRatio: 16 / 9,
                    child: CustomImageWidget(
                      imageUrl: activity.coverUrl,
                      fit: BoxFit.cover,
                      semanticLabel: activity.coverLabel,
                    ),
                  ),
                ),
                Positioned(
                  top: 12,
                  left: 12,
                  child: StatusBadgeWidget(status: activity.status),
                ),
                Positioned(
                  top: 12,
                  right: 12,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 5,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.black.withAlpha(140),
                      borderRadius: BorderRadius.circular(100),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        CustomIconWidget(
                          iconName: activity.categoryIcon,
                          size: 12,
                          color: Colors.white,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          activity.category,
                          style: GoogleFonts.dmSans(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: Colors.white,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                if (activity.isPasswordProtected)
                  Positioned(
                    bottom: 12,
                    right: 12,
                    child: Container(
                      padding: const EdgeInsets.all(6),
                      decoration: BoxDecoration(
                        color: Colors.black.withAlpha(140),
                        shape: BoxShape.circle,
                      ),
                      child: const CustomIconWidget(
                        iconName: 'lock',
                        size: 12,
                        color: Colors.white,
                      ),
                    ),
                  ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      ClipOval(
                        child: CustomImageWidget(
                          imageUrl: activity.hostAvatar,
                          width: 24,
                          height: 24,
                          fit: BoxFit.cover,
                          semanticLabel: activity.hostAvatarLabel,
                        ),
                      ),
                      const SizedBox(width: 6),
                      Text(
                        activity.hostName,
                        style: GoogleFonts.dmSans(
                          fontSize: 12,
                          fontWeight: FontWeight.w500,
                          color: const Color(0xFF6B7280),
                        ),
                      ),
                      const Spacer(),
                      Text(
                        '${activity.date} • ${activity.time}',
                        style: GoogleFonts.dmSans(
                          fontSize: 11,
                          color: const Color(0xFF9CA3AF),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    activity.title,
                    style: GoogleFonts.dmSans(
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                      color: const Color(0xFF1A1A1A),
                      height: 1.3,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      const CustomIconWidget(
                        iconName: 'location_on',
                        size: 13,
                        color: Color(0xFF9CA3AF),
                      ),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          activity.location,
                          style: GoogleFonts.dmSans(
                            fontSize: 12,
                            color: const Color(0xFF9CA3AF),
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      _ParticipantAvatarStack(
                        avatars: activity.participantAvatars,
                        labels: activity.participantLabels,
                        total: activity.joined,
                      ),
                      const SizedBox(width: 8),
                      Text(
                        spotsLeft > 0 ? '$spotsLeft spots left' : 'Full',
                        style: GoogleFonts.dmSans(
                          fontSize: 12,
                          fontWeight: FontWeight.w500,
                          color: spotsLeft <= 2 && spotsLeft > 0
                              ? AppTheme.warning
                              : spotsLeft == 0
                              ? AppTheme.error
                              : const Color(0xFF6B7280),
                        ),
                      ),
                      const Spacer(),
                      GestureDetector(
                        onTap: onLike,
                        child: AnimatedSwitcher(
                          duration: const Duration(milliseconds: 200),
                          transitionBuilder: (child, anim) =>
                              ScaleTransition(scale: anim, child: child),
                          child: CustomIconWidget(
                            key: ValueKey(isLiked),
                            iconName: isLiked ? 'favorite' : 'favorite_border',
                            size: 20,
                            color: isLiked
                                ? AppTheme.error
                                : const Color(0xFF9CA3AF),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      if (activity.status == ActivityStatus.open)
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 16,
                            vertical: 8,
                          ),
                          decoration: BoxDecoration(
                            color: AppTheme.primary,
                            borderRadius: BorderRadius.circular(100),
                          ),
                          child: Text(
                            'Join',
                            style: GoogleFonts.dmSans(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                              color: Colors.white,
                            ),
                          ),
                        )
                      else if (activity.status == ActivityStatus.full)
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 16,
                            vertical: 8,
                          ),
                          decoration: BoxDecoration(
                            color: const Color(0xFFFEF9C3),
                            borderRadius: BorderRadius.circular(100),
                          ),
                          child: Text(
                            'Waitlist',
                            style: GoogleFonts.dmSans(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                              color: AppTheme.warning,
                            ),
                          ),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ParticipantAvatarStack extends StatelessWidget {
  final List<String> avatars;
  final List<String> labels;
  final int total;

  const _ParticipantAvatarStack({
    required this.avatars,
    required this.labels,
    required this.total,
  });

  @override
  Widget build(BuildContext context) {
    final visible = avatars.take(3).toList();
    return SizedBox(
      width: visible.length * 18.0 + 24,
      height: 28,
      child: Stack(
        children: List.generate(visible.length, (i) {
          return Positioned(
            left: i * 18.0,
            child: Container(
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: Colors.white, width: 2),
              ),
              child: ClipOval(
                child: CustomImageWidget(
                  imageUrl: visible[i],
                  width: 24,
                  height: 24,
                  fit: BoxFit.cover,
                  semanticLabel: i < labels.length
                      ? labels[i]
                      : 'Participant avatar',
                ),
              ),
            ),
          );
        }),
      ),
    );
  }
}
