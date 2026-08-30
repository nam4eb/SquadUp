import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../routes/app_routes.dart';
import '../../theme/app_theme.dart';
import '../../features/activities/application/activity_taxonomy_controller.dart';
import '../../features/activities/data/activities_repository.dart';
import '../../features/activities/presentation/api_activity_feed.dart';
import '../../features/activities/presentation/activity_category_strip.dart';
import './widgets/friends_stories_widget.dart';
import './widgets/home_app_bar_widget.dart';
import './widgets/section_header_widget.dart';
import './widgets/up_next_card_widget.dart';

class HomeFeedScreen extends ConsumerStatefulWidget {
  const HomeFeedScreen({super.key});

  @override
  ConsumerState<HomeFeedScreen> createState() => _HomeFeedScreenState();
}

class _HomeFeedScreenState extends ConsumerState<HomeFeedScreen> {
  final ScrollController _scrollController = ScrollController();

  Future<void> _onRefresh() async {
    await Future.wait([
      ref.read(activityTaxonomyControllerProvider.notifier).refresh(),
      ref.refresh(activityFeedProvider.future),
    ]);
  }

  void _onActivityTap(String activityId) {
    context.push(AppRoutes.activityDetail, extra: activityId);
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bottomPadding = MediaQuery.of(context).padding.bottom;

    return Scaffold(
      backgroundColor: AppTheme.backgroundLight,
      body: SafeArea(
        bottom: false,
        child: RefreshIndicator(
          onRefresh: _onRefresh,
          color: AppTheme.primary,
          backgroundColor: Colors.white,
          displacement: 60,
          child: CustomScrollView(
            controller: _scrollController,
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const HomeAppBarWidget(),
                    const SizedBox(height: 20),
                    _buildGreeting(),
                    const SizedBox(height: 20),
                    const ActivityCategoryStrip(),
                    const SizedBox(height: 20),
                    const Padding(
                      padding: EdgeInsets.symmetric(horizontal: 20),
                      child: UpNextCardWidget(),
                    ),
                    const SizedBox(height: 24),
                    const Padding(
                      padding: EdgeInsets.symmetric(horizontal: 20),
                      child: FriendsStoriesWidget(),
                    ),
                    const SizedBox(height: 24),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 20),
                      child: SectionHeaderWidget(
                        title: 'Upcoming Activities',
                        onViewAll: () {},
                      ),
                    ),
                    const SizedBox(height: 12),
                  ],
                ),
              ),
              ApiActivityFeed(
                onActivityTap: _onActivityTap,
                bottomPadding: bottomPadding + 100,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildGreeting() {
    final hour = DateTime.now().hour;
    final greeting = hour < 12
        ? 'Good Morning'
        : hour < 17
        ? 'Good Afternoon'
        : 'Good Evening';
    final emoji = hour < 12
        ? '☀️'
        : hour < 17
        ? '👋'
        : '🌙';

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          RichText(
            text: TextSpan(
              style: const TextStyle(
                fontSize: 22,
                fontWeight: FontWeight.w700,
                color: Color(0xFF1A1A1A),
                fontFamily: 'DMSans',
              ),
              children: [
                TextSpan(text: '$greeting, '),
                const TextSpan(
                  text: 'Alex',
                  style: TextStyle(color: AppTheme.primary),
                ),
                TextSpan(text: ' $emoji'),
              ],
            ),
          ),
          const SizedBox(height: 4),
          const Text(
            '3 activities this week • 2 friends active now',
            style: TextStyle(
              fontSize: 13,
              color: Color(0xFF6B7280),
              fontFamily: 'DMSans',
            ),
          ),
        ],
      ),
    );
  }
}
