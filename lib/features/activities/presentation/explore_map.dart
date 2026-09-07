import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../../routes/app_routes.dart';
import '../domain/activity_item.dart';
import 'package:go_router/go_router.dart';

class ExploreMap extends StatefulWidget {
  const ExploreMap({
    super.key,
    required this.activities,
    required this.initialCenter,
    required this.onSearchArea,
  });

  final List<ActivityItem> activities;
  final LatLng initialCenter;
  final ValueChanged<LatLng> onSearchArea;

  @override
  State<ExploreMap> createState() => _ExploreMapState();
}

class _ExploreMapState extends State<ExploreMap> {
  final _controller = MapController();
  double _zoom = 13;
  bool _moved = false;

  @override
  Widget build(BuildContext context) {
    final groups = _clusters(widget.activities, _zoom);
    return Stack(
      children: [
        FlutterMap(
          mapController: _controller,
          options: MapOptions(
            initialCenter: widget.initialCenter,
            initialZoom: _zoom,
            minZoom: 3,
            maxZoom: 19,
            onPositionChanged: (camera, hasGesture) {
              _zoom = camera.zoom;
              if (hasGesture && !_moved) setState(() => _moved = true);
              if (hasGesture) setState(() {});
            },
          ),
          children: [
            TileLayer(
              urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
              userAgentPackageName: 'com.squadup.app',
            ),
            MarkerLayer(
              markers: groups.map((group) {
                final multiple = group.items.length > 1;
                return Marker(
                  point: group.center,
                  width: multiple ? 48 : 42,
                  height: multiple ? 48 : 42,
                  child: Semantics(
                    button: true,
                    label: multiple
                        ? '${group.items.length} activities'
                        : group.items.first.title,
                    child: GestureDetector(
                      onTap: () {
                        if (multiple) {
                          _controller.move(
                            group.center,
                            (_zoom + 2).clamp(3, 19),
                          );
                        } else {
                          context.push(
                            AppRoutes.activityDetail,
                            extra: group.items.first.id,
                          );
                        }
                      },
                      child: DecoratedBox(
                        decoration: BoxDecoration(
                          color: Theme.of(context).colorScheme.primary,
                          shape: BoxShape.circle,
                          border: Border.all(color: Colors.white, width: 3),
                          boxShadow: const [
                            BoxShadow(blurRadius: 6, color: Colors.black26),
                          ],
                        ),
                        child: Center(
                          child: multiple
                              ? Text(
                                  '${group.items.length}',
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.bold,
                                  ),
                                )
                              : const Icon(
                                  Icons.sports,
                                  color: Colors.white,
                                  size: 21,
                                ),
                        ),
                      ),
                    ),
                  ),
                );
              }).toList(),
            ),
            RichAttributionWidget(
              attributions: const [
                TextSourceAttribution('OpenStreetMap contributors'),
              ],
            ),
          ],
        ),
        if (_moved)
          Positioned(
            top: 12,
            left: 0,
            right: 0,
            child: Center(
              child: FilledButton.icon(
                onPressed: () {
                  setState(() => _moved = false);
                  widget.onSearchArea(_controller.camera.center);
                },
                icon: const Icon(Icons.search, size: 18),
                label: const Text('Search this area'),
              ),
            ),
          ),
      ],
    );
  }

  List<_ActivityCluster> _clusters(List<ActivityItem> activities, double zoom) {
    final precision = zoom >= 15
        ? 1000.0
        : zoom >= 12
        ? 200.0
        : zoom >= 9
        ? 40.0
        : 8.0;
    final grouped = <String, List<ActivityItem>>{};
    for (final activity in activities) {
      final lat = activity.latitude;
      final lng = activity.longitude;
      if (lat == null || lng == null) continue;
      final key = '${(lat * precision).round()}:${(lng * precision).round()}';
      grouped.putIfAbsent(key, () => []).add(activity);
    }
    return grouped.values.map((items) {
      final latitude =
          items.map((item) => item.latitude!).reduce((a, b) => a + b) /
          items.length;
      final longitude =
          items.map((item) => item.longitude!).reduce((a, b) => a + b) /
          items.length;
      return _ActivityCluster(LatLng(latitude, longitude), items);
    }).toList();
  }
}

class _ActivityCluster {
  const _ActivityCluster(this.center, this.items);
  final LatLng center;
  final List<ActivityItem> items;
}
