import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../domain/activity_item.dart';

class ActivityThumbnail extends StatelessWidget {
  const ActivityThumbnail({
    super.key,
    required this.activity,
    this.width = double.infinity,
    this.height = 150,
    this.borderRadius = BorderRadius.zero,
  });

  final ActivityItem activity;
  final double width;
  final double height;
  final BorderRadius borderRadius;

  static const _assets = <String, String>{
    'sports': 'assets/images/activity_categories/sports.png',
    'entertainment': 'assets/images/activity_categories/entertainment.png',
    'food-drink': 'assets/images/activity_categories/food-drink.png',
    'outdoors': 'assets/images/activity_categories/outdoors.png',
    'fitness': 'assets/images/activity_categories/fitness.png',
    'learning': 'assets/images/activity_categories/learning.png',
    'arts': 'assets/images/activity_categories/arts.png',
    'travel': 'assets/images/activity_categories/travel.png',
    'community': 'assets/images/activity_categories/community.png',
    'other': 'assets/images/activity_categories/other.png',
  };

  @override
  Widget build(BuildContext context) {
    final cover = activity.coverUrl;
    final image = cover != null && cover.isNotEmpty
        ? CachedNetworkImage(
            imageUrl: cover,
            width: width,
            height: height,
            fit: BoxFit.cover,
            errorWidget: (_, _, _) => _fallback(),
          )
        : _fallback();

    return ClipRRect(borderRadius: borderRadius, child: image);
  }

  Widget _fallback() => Image.asset(
    _assets[activity.categorySlug] ?? _assets['other']!,
    width: width,
    height: height,
    fit: BoxFit.cover,
    cacheWidth: 900,
  );
}
