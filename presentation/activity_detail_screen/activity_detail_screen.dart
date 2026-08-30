import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../theme/app_theme.dart';
import '../../widgets/custom_icon_widget.dart';
import '../../widgets/custom_image_widget.dart';
import '../../widgets/status_badge_widget.dart';
import 'widgets/activity_info_widget.dart';
import 'widgets/participants_widget.dart';
import 'widgets/activity_rules_widget.dart';
import 'widgets/activity_action_bar_widget.dart';

class ActivityDetailScreen extends StatefulWidget {
  final String activityId;

  const ActivityDetailScreen({required this.activityId, super.key});

  @override
  State<ActivityDetailScreen> createState() => _ActivityDetailScreenState();
}

class _ActivityDetailScreenState extends State<ActivityDetailScreen> {
  // TODO: Replace with Riverpod ActivityDetailProvider for production
  late _ActivityDetail _activity;
  bool _isJoined = false;
  bool _isLoading = false;

  static final List<Map<String, dynamic>> _activityMaps = [
    {
      'id': 'act-001',
      'title': 'Sunday Morning Run — Riverside',
      'category': 'Running',
      'categoryIcon': 'directions_run',
      'status': 'open',
      'hostName': 'Marcus Williams',
      'hostAvatar':
          'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100',
      'hostAvatarLabel': 'Young Black man smiling in casual shirt',
      'hostUsername': '@marcus_runs',
      'coverUrl':
          'https://images.pexels.com/photos/2402777/pexels-photo-2402777.jpeg',
      'coverLabel': 'Group of runners on riverside path during sunrise',
      'date': 'Sunday, August 18, 2026',
      'time': '7:00 AM',
      'duration': '~2 hours',
      'location': 'Riverside Park, North Gate',
      'locationAddress': 'Riverside Park, North Gate, Sydney NSW',
      'description':
          'Join us for a refreshing morning run along the riverside trail. All paces welcome — we run as a group and no one gets left behind. Bring water and comfortable shoes. We usually grab coffee after at the park café.',
      'joined': 9,
      'maxParticipants': 15,
      'minParticipants': 3,
      'isPasswordProtected': false,
      'requiresApproval': false,
      'hasWaitingList': true,
      'ageRestriction': null,
      'clanName': 'Sydney Runners Guild',
      'clanAvatar':
          'https://images.pexels.com/photos/3621104/pexels-photo-3621104.jpeg',
      'clanAvatarLabel': 'Running group logo with sunrise and track',
      'rules': [
        'Arrive 10 minutes early for warm-up',
        'Bring your own water bottle',
        'Notify the host if you need to cancel',
        'No headphones — group run is social!',
      ],
      'participants': [
        {
          'name': 'Alex Chen',
          'avatar':
              'https://images.pexels.com/photos/415829/pexels-photo-415829.jpeg?w=80',
          'semanticLabel': 'Young woman with dark hair smiling outdoors',
          'isHost': false,
          'status': 'joined',
        },
        {
          'name': 'Priya Sharma',
          'avatar':
              'https://images.pexels.com/photos/1239291/pexels-photo-1239291.jpeg?w=80',
          'semanticLabel': 'South Asian woman with long dark hair outdoors',
          'isHost': false,
          'status': 'joined',
        },
        {
          'name': 'Jake T.',
          'avatar':
              'https://images.pixabay.com/photo/2017/08/01/01/33/beanie-2562646_640.jpg',
          'semanticLabel': 'White man wearing beanie hat looking sideways',
          'isHost': false,
          'status': 'joined',
        },
        {
          'name': 'Amara O.',
          'avatar':
              'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=80',
          'semanticLabel':
              'African woman with natural hair smiling confidently',
          'isHost': false,
          'status': 'joined',
        },
        {
          'name': 'Kenji M.',
          'avatar':
              'https://images.pexels.com/photos/1222271/pexels-photo-1222271.jpeg?w=80',
          'semanticLabel': 'East Asian man with short hair in urban setting',
          'isHost': false,
          'status': 'joined',
        },
      ],
    },
    {
      'id': 'act-003',
      'title': 'Beach Volleyball Tournament',
      'category': 'Sports',
      'categoryIcon': 'sports',
      'status': 'open',
      'hostName': 'Jake Thompson',
      'hostAvatar':
          'https://images.pixabay.com/photo/2017/08/01/01/33/beanie-2562646_640.jpg',
      'hostAvatarLabel': 'White man wearing beanie hat looking sideways',
      'coverUrl':
          'https://images.unsplash.com/photo-1612872087720-bb876e2e67d1?w=600',
      'coverLabel':
          'Players jumping for volleyball at beach during golden hour',
      'date': 'Saturday, August 23, 2026',
      'time': '2:00 PM',
      'duration': '~4 hours',
      'location': 'Bondi Beach, South End',
      'locationAddress': 'Bondi Beach, South End, Sydney NSW 2026',
      'description':
          'Competitive beach volleyball tournament — teams of 4. Round robin format with finals. All skill levels welcome but some volleyball experience recommended. Bring sunscreen, water, and good vibes. Teams will be assigned on arrival.',
      'joined': 12,
      'maxParticipants': 24,
      'minParticipants': 8,
      'isPasswordProtected': true,
      'requiresApproval': true,
      'hasWaitingList': true,
      'ageRestriction': 18,
      'clanName': 'Beach Squad AU',
      'clanAvatar':
          'https://images.pexels.com/photos/1032110/pexels-photo-1032110.jpeg',
      'clanAvatarLabel':
          'Beach volleyball net with ocean in background at sunset',
      'rules': [
        'Teams of 4 — no pre-formed teams, assigned on arrival',
        'Age 18+ only',
        'Bring your own water (2L minimum)',
        'Sunscreen mandatory — no excuses!',
        'Password required: BEACH2026',
      ],
      'participants': [
        {
          'name': 'Priya S.',
          'avatar':
              'https://images.pexels.com/photos/1239291/pexels-photo-1239291.jpeg?w=80',
          'semanticLabel': 'South Asian woman with long dark hair outdoors',
          'isHost': false,
          'status': 'joined',
        },
        {
          'name': 'Marcus W.',
          'avatar':
              'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=80',
          'semanticLabel': 'Young Black man smiling in casual shirt',
          'isHost': false,
          'status': 'joined',
        },
        {
          'name': 'Amara O.',
          'avatar':
              'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=80',
          'semanticLabel':
              'African woman with natural hair smiling confidently',
          'isHost': false,
          'status': 'joined',
        },
        {
          'name': 'Kenji M.',
          'avatar':
              'https://images.pexels.com/photos/1222271/pexels-photo-1222271.jpeg?w=80',
          'semanticLabel': 'East Asian man with short hair in urban setting',
          'isHost': false,
          'status': 'waitlisted',
        },
      ],
    },
  ];

  @override
  void initState() {
    super.initState();
    final match = _activityMaps.firstWhere(
      (m) => m['id'] == widget.activityId,
      orElse: () => _activityMaps.first,
    );
    _activity = _fromMap(match);
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

  static _ActivityDetail _fromMap(Map<String, dynamic> m) {
    final participantMaps = (m['participants'] as List)
        .cast<Map<String, dynamic>>();
    return _ActivityDetail(
      id: m['id'] as String,
      title: m['title'] as String,
      category: m['category'] as String,
      categoryIcon: m['categoryIcon'] as String,
      status: _statusFromString(m['status'] as String),
      hostName: m['hostName'] as String,
      hostAvatar: m['hostAvatar'] as String,
      hostAvatarLabel: m['hostAvatarLabel'] as String,
      hostUsername: m['hostUsername'] as String? ?? '',
      coverUrl: m['coverUrl'] as String,
      coverLabel: m['coverLabel'] as String,
      date: m['date'] as String,
      time: m['time'] as String,
      duration: m['duration'] as String,
      location: m['location'] as String,
      locationAddress: m['locationAddress'] as String,
      description: m['description'] as String,
      joined: m['joined'] as int,
      maxParticipants: m['maxParticipants'] as int,
      minParticipants: m['minParticipants'] as int,
      isPasswordProtected: m['isPasswordProtected'] as bool,
      requiresApproval: m['requiresApproval'] as bool,
      hasWaitingList: m['hasWaitingList'] as bool,
      ageRestriction: m['ageRestriction'] as int?,
      clanName: m['clanName'] as String,
      clanAvatar: m['clanAvatar'] as String,
      clanAvatarLabel: m['clanAvatarLabel'] as String,
      rules: List<String>.from(m['rules']),
      participants: participantMaps.map(_participantFromMap).toList(),
    );
  }

  static _Participant _participantFromMap(Map<String, dynamic> m) {
    return _Participant(
      name: m['name'] as String,
      avatar: m['avatar'] as String,
      semanticLabel: m['semanticLabel'] as String,
      isHost: m['isHost'] as bool,
      status: m['status'] as String,
    );
  }

  Future<void> _onJoin() async {
    setState(() => _isLoading = true);
    // TODO: Replace with Riverpod ActivityJoinNotifier — calls join API
    await Future.delayed(const Duration(milliseconds: 1200));
    if (!mounted) return;
    setState(() {
      _isLoading = false;
      _isJoined = true;
    });
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          "You've joined ${_activity.title}!",
          style: GoogleFonts.dmSans(fontWeight: FontWeight.w500),
        ),
        backgroundColor: AppTheme.success,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        margin: const EdgeInsets.fromLTRB(16, 0, 16, 100),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final spotsLeft = _activity.maxParticipants - _activity.joined;

    return Scaffold(
      backgroundColor: AppTheme.backgroundLight,
      extendBodyBehindAppBar: true,
      body: Stack(
        children: [
          CustomScrollView(
            slivers: [
              _buildSliverAppBar(context),
              SliverToBoxAdapter(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SizedBox(height: 20),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 20),
                      child: ActivityInfoWidget(activity: _activity),
                    ),
                    const SizedBox(height: 24),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 20),
                      child: ParticipantsWidget(
                        participants: _activity.participants,
                        joined: _activity.joined,
                        maxParticipants: _activity.maxParticipants,
                        spotsLeft: spotsLeft,
                      ),
                    ),
                    const SizedBox(height: 24),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 20),
                      child: ActivityRulesWidget(rules: _activity.rules),
                    ),
                    const SizedBox(height: 24),
                    _buildClanSection(),
                    const SizedBox(height: 120),
                  ],
                ),
              ),
            ],
          ),
          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            child: ActivityActionBarWidget(
              activity: _activity,
              isJoined: _isJoined,
              isLoading: _isLoading,
              onJoin: _onJoin,
              onInvite: () {},
              onChat: () {},
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSliverAppBar(BuildContext context) {
    return SliverAppBar(
      expandedHeight: 280,
      pinned: true,
      backgroundColor: AppTheme.backgroundLight,
      elevation: 0,
      scrolledUnderElevation: 0,
      leading: Padding(
        padding: const EdgeInsets.all(8),
        child: GestureDetector(
          onTap: () => Navigator.of(context).pop(),
          child: Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: Colors.black.withOpacity(0.35),
              shape: BoxShape.circle,
            ),
            child: const Center(
              child: CustomIconWidget(
                iconName: 'arrow_back',
                size: 18,
                color: Colors.white,
              ),
            ),
          ),
        ),
      ),
      actions: [
        Padding(
          padding: const EdgeInsets.only(right: 8),
          child: _CircleActionButton(iconName: 'share', onTap: () {}),
        ),
        Padding(
          padding: const EdgeInsets.only(right: 12),
          child: _CircleActionButton(iconName: 'more_vert', onTap: () {}),
        ),
      ],
      flexibleSpace: FlexibleSpaceBar(
        background: Stack(
          fit: StackFit.expand,
          children: [
            Hero(
              tag: 'activity-cover-${_activity.id}',
              child: CustomImageWidget(
                imageUrl: _activity.coverUrl,
                fit: BoxFit.cover,
                semanticLabel: _activity.coverLabel,
              ),
            ),
            DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [Colors.transparent, Colors.black.withOpacity(0.5)],
                  stops: const [0.5, 1.0],
                ),
              ),
            ),
            Positioned(
              bottom: 16,
              left: 20,
              right: 20,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        StatusBadgeWidget(status: _activity.status),
                        const SizedBox(height: 8),
                        Text(
                          _activity.title,
                          style: GoogleFonts.dmSans(
                            fontSize: 22,
                            fontWeight: FontWeight.w700,
                            color: Colors.white,
                            height: 1.2,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            CustomIconWidget(
                              iconName: activity_categoryIcon_getter(_activity),
                              size: 13,
                              color: Colors.white.withOpacity(0.8),
                            ),
                            const SizedBox(width: 4),
                            Text(
                              _activity.category,
                              style: GoogleFonts.dmSans(
                                fontSize: 13,
                                color: Colors.white.withOpacity(0.85),
                                fontWeight: FontWeight.w500,
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
          ],
        ),
      ),
    );
  }

  String activity_categoryIcon_getter(_ActivityDetail a) => a.categoryIcon;

  Widget _buildClanSection() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Organized by Clan',
            style: GoogleFonts.dmSans(
              fontSize: 15,
              fontWeight: FontWeight.w700,
              color: const Color(0xFF1A1A1A),
            ),
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.05),
                  blurRadius: 12,
                  offset: const Offset(0, 3),
                ),
              ],
            ),
            child: Row(
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: CustomImageWidget(
                    imageUrl: _activity.clanAvatar,
                    width: 48,
                    height: 48,
                    fit: BoxFit.cover,
                    semanticLabel: _activity.clanAvatarLabel,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _activity.clanName,
                        style: GoogleFonts.dmSans(
                          fontSize: 15,
                          fontWeight: FontWeight.w600,
                          color: const Color(0xFF1A1A1A),
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Tap to view clan profile',
                        style: GoogleFonts.dmSans(
                          fontSize: 12,
                          color: const Color(0xFF9CA3AF),
                        ),
                      ),
                    ],
                  ),
                ),
                CustomIconWidget(
                  iconName: 'chevron_right',
                  size: 20,
                  color: const Color(0xFF9CA3AF),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ActivityDetail {
  final String id;
  final String title;
  final String category;
  final String categoryIcon;
  final ActivityStatus status;
  final String hostName;
  final String hostAvatar;
  final String hostAvatarLabel;
  final String hostUsername;
  final String coverUrl;
  final String coverLabel;
  final String date;
  final String time;
  final String duration;
  final String location;
  final String locationAddress;
  final String description;
  final int joined;
  final int maxParticipants;
  final int minParticipants;
  final bool isPasswordProtected;
  final bool requiresApproval;
  final bool hasWaitingList;
  final int? ageRestriction;
  final String clanName;
  final String clanAvatar;
  final String clanAvatarLabel;
  final List<String> rules;
  final List<_Participant> participants;

  const _ActivityDetail({
    required this.id,
    required this.title,
    required this.category,
    required this.categoryIcon,
    required this.status,
    required this.hostName,
    required this.hostAvatar,
    required this.hostAvatarLabel,
    required this.hostUsername,
    required this.coverUrl,
    required this.coverLabel,
    required this.date,
    required this.time,
    required this.duration,
    required this.location,
    required this.locationAddress,
    required this.description,
    required this.joined,
    required this.maxParticipants,
    required this.minParticipants,
    required this.isPasswordProtected,
    required this.requiresApproval,
    required this.hasWaitingList,
    required this.ageRestriction,
    required this.clanName,
    required this.clanAvatar,
    required this.clanAvatarLabel,
    required this.rules,
    required this.participants,
  });
}

class _Participant {
  final String name;
  final String avatar;
  final String semanticLabel;
  final bool isHost;
  final String status;

  const _Participant({
    required this.name,
    required this.avatar,
    required this.semanticLabel,
    required this.isHost,
    required this.status,
  });
}

class _CircleActionButton extends StatelessWidget {
  final String iconName;
  final VoidCallback onTap;

  const _CircleActionButton({required this.iconName, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 36,
        height: 36,
        decoration: BoxDecoration(
          color: Colors.black.withOpacity(0.35),
          shape: BoxShape.circle,
        ),
        child: Center(
          child: CustomIconWidget(
            iconName: iconName,
            size: 18,
            color: Colors.white,
          ),
        ),
      ),
    );
  }
}
