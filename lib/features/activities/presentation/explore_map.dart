import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:go_router/go_router.dart';
import '../../../core/maps/maps_loader.dart';
import '../../../routes/app_routes.dart';
import '../domain/activity_item.dart';

class ExploreMap extends StatefulWidget {
  const ExploreMap({
    super.key,
    required this.activities,
    required this.initialCenter,
    required this.onSearchArea,
    required this.radiusKm,
    this.userLocation,
  });
  final List<ActivityItem> activities;
  final LatLng initialCenter;
  final ValueChanged<LatLng> onSearchArea;
  final double radiusKm;
  final LatLng? userLocation;
  @override
  State<ExploreMap> createState() => _ExploreMapState();
}

class _ExploreMapState extends State<ExploreMap> {
  GoogleMapController? _controller;
  late LatLng _center = widget.initialCenter;
  late Future<void> _ready = loadGoogleMaps(googleMapsApiKey);
  bool _moved = false;
  ActivityItem? _selected;

  @override
  void didUpdateWidget(covariant ExploreMap oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.initialCenter != widget.initialCenter) {
      _center = widget.initialCenter;
      _moved = false;
      _controller?.animateCamera(CameraUpdate.newLatLng(_center));
    }
    if (_selected != null &&
        !widget.activities.any((a) => a.id == _selected!.id)) {
      _selected = null;
    }
  }

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final supported =
        kIsWeb ||
        defaultTargetPlatform == TargetPlatform.android ||
        defaultTargetPlatform == TargetPlatform.iOS;
    if (!supported || googleMapsApiKey.isEmpty) {
      return const Center(
        child: Text('Map is unavailable. Use List to find activities.'),
      );
    }
    return FutureBuilder<void>(
      future: _ready,
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Text('Unable to load the map. You can still use List.'),
                TextButton(
                  onPressed: () => setState(() {
                    _ready = loadGoogleMaps(googleMapsApiKey);
                  }),
                  child: const Text('Retry'),
                ),
              ],
            ),
          );
        }
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        return Column(
          children: [
            Text(
              '${widget.activities.where((a) => a.latitude != null && a.longitude != null).length} mapped activities'
              '${widget.radiusKm > 0 ? ' · ${widget.radiusKm.round()} km radius' : ' · Choose an area to search nearby'}',
            ),
            Expanded(
              child: GoogleMap(
                initialCameraPosition: CameraPosition(
                  target: widget.initialCenter,
                  zoom: 12,
                ),
                onMapCreated: (controller) => _controller = controller,
                onCameraMove: (camera) => _center = camera.target,
                onCameraIdle: () {
                  if (mounted) {
                    setState(() => _moved = _center != widget.initialCenter);
                  }
                },
                myLocationButtonEnabled: false,
                mapToolbarEnabled: false,
                circles: {
                  if (widget.radiusKm > 0)
                    Circle(
                      circleId: const CircleId('radius'),
                      center: widget.initialCenter,
                      radius: widget.radiusKm * 1000,
                      fillColor: Colors.teal.withValues(alpha: .08),
                      strokeColor: Colors.teal,
                      strokeWidth: 1,
                    ),
                },
                markers: {
                  if (widget.userLocation != null)
                    Marker(
                      markerId: const MarkerId('user'),
                      position: widget.userLocation!,
                      icon: BitmapDescriptor.defaultMarkerWithHue(
                        BitmapDescriptor.hueAzure,
                      ),
                      infoWindow: const InfoWindow(title: 'Your location'),
                    ),
                  for (final activity in widget.activities)
                    if (activity.latitude != null && activity.longitude != null)
                      Marker(
                        markerId: MarkerId(activity.id),
                        position: LatLng(
                          activity.latitude!,
                          activity.longitude!,
                        ),
                        infoWindow: InfoWindow(
                          title: activity.title,
                          snippet: activity.locationName,
                        ),
                        onTap: () => setState(() => _selected = activity),
                      ),
                },
              ),
            ),
            TextButton.icon(
              onPressed: _moved
                  ? () {
                      setState(() => _moved = false);
                      widget.onSearchArea(_center);
                    }
                  : null,
              icon: const Icon(Icons.search),
              label: const Text('Search this area'),
            ),
            if (_selected case final activity?)
              ListTile(
                title: Text(
                  activity.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                subtitle: Text(
                  '${activity.locationName} · ${activity.joinedCount}/${activity.maxParticipants} joined',
                  maxLines: 1,
                ),
                trailing: const Icon(Icons.chevron_right),
                onTap: () =>
                    context.push(AppRoutes.activityDetail, extra: activity.id),
              ),
          ],
        );
      },
    );
  }
}
