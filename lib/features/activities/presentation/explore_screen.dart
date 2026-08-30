import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:geolocator/geolocator.dart';

import '../../../routes/app_routes.dart';
import '../application/activity_taxonomy_controller.dart';
import '../data/activities_repository.dart';
import '../domain/activity_item.dart';
import 'activity_thumbnail.dart';

class ExploreScreen extends ConsumerStatefulWidget {
  const ExploreScreen({super.key});

  @override
  ConsumerState<ExploreScreen> createState() => _ExploreScreenState();
}

class _ExploreScreenState extends ConsumerState<ExploreScreen> {
  final _search = TextEditingController();
  final _latitude = TextEditingController();
  final _longitude = TextEditingController();
  String? _categoryId;
  double _radius = 20;
  Position? _position;
  String? _locationState;
  int _page = 1;
  bool _hasMore = false;
  List<ActivityItem> _results = const [];
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _locateAndLoad();
  }

  @override
  void dispose() {
    _search.dispose();
    _latitude.dispose();
    _longitude.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final categories = ref.watch(activityTaxonomyControllerProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Explore activities')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            SearchBar(
              controller: _search,
              hintText: 'Search title, description or location',
              leading: const Icon(Icons.search),
              trailing: [
                IconButton(
                  onPressed: _load,
                  icon: const Icon(Icons.arrow_forward),
                ),
              ],
              onSubmitted: (_) => _load(),
            ),
            const SizedBox(height: 12),
            categories.when(
              data: (items) => SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: [
                    ChoiceChip(
                      label: const Text('All'),
                      selected: _categoryId == null,
                      onSelected: (_) => _selectCategory(null),
                    ),
                    ...items.map(
                      (item) => Padding(
                        padding: const EdgeInsets.only(left: 8),
                        child: ChoiceChip(
                          label: Text(item.name),
                          selected: _categoryId == item.id,
                          onSelected: (_) => _selectCategory(item.id),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              loading: () => const LinearProgressIndicator(),
              error: (_, _) => const SizedBox.shrink(),
            ),
            ExpansionTile(
              title: const Text('Nearby radius'),
              subtitle: Text(
                _locationState ??
                    (_position == null
                        ? 'Choose location'
                        : 'Using your current location'),
              ),
              children: [
                Wrap(
                  spacing: 8,
                  children: [3, 5, 10, 25, 50]
                      .map(
                        (radius) => ChoiceChip(
                          label: Text('$radius km'),
                          selected: _radius == radius,
                          onSelected: (_) {
                            setState(() => _radius = radius.toDouble());
                            _load();
                          },
                        ),
                      )
                      .toList(),
                ),
                TextButton.icon(
                  onPressed: _locateAndLoad,
                  icon: const Icon(Icons.my_location),
                  label: const Text('Use current location'),
                ),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _latitude,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                          signed: true,
                        ),
                        decoration: const InputDecoration(
                          labelText: 'Latitude',
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: TextField(
                        controller: _longitude,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                          signed: true,
                        ),
                        decoration: const InputDecoration(
                          labelText: 'Longitude',
                        ),
                      ),
                    ),
                  ],
                ),
                FilledButton.icon(
                  onPressed: _load,
                  icon: const Icon(Icons.near_me_outlined),
                  label: Text('Search within ${_radius.round()} km'),
                ),
              ],
            ),
            if (_loading) const LinearProgressIndicator(),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.all(24),
                child: Text(_error!, textAlign: TextAlign.center),
              )
            else if (!_loading && _results.isEmpty)
              const Padding(
                padding: EdgeInsets.all(32),
                child: Center(child: Text('No matching activities.')),
              )
            else
              ..._results.map(
                (activity) => Card(
                  child: ListTile(
                    contentPadding: const EdgeInsets.all(10),
                    leading: ActivityThumbnail(
                      activity: activity,
                      width: 88,
                      height: 72,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    title: Text(activity.title),
                    subtitle: Text(
                      '${activity.topicName} · ${activity.locationName}${activity.distanceKm == null ? '' : ' · ${_distance(activity.distanceKm!)}'}',
                    ),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => context.push(
                      AppRoutes.activityDetail,
                      extra: activity.id,
                    ),
                  ),
                ),
              ),
            if (_hasMore && !_loading)
              TextButton(
                onPressed: () => _load(loadMore: true),
                child: const Text('Load more'),
              ),
          ],
        ),
      ),
    );
  }

  void _selectCategory(String? id) {
    setState(() => _categoryId = id);
    _load();
  }

  Future<void> _load({bool loadMore = false}) async {
    final latitude =
        _position?.latitude ?? double.tryParse(_latitude.text.trim());
    final longitude =
        _position?.longitude ?? double.tryParse(_longitude.text.trim());
    if ((latitude == null) != (longitude == null)) {
      setState(() => _error = 'Enter both latitude and longitude.');
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      if (latitude != null && longitude != null) {
        final nextPage = loadMore ? _page + 1 : 1;
        final result = await ref
            .read(activitiesRepositoryProvider)
            .nearby(
              query: _search.text,
              categoryId: _categoryId,
              latitude: latitude,
              longitude: longitude,
              radiusKm: _radius,
              page: nextPage,
            );
        if (mounted) {
          setState(() {
            _results = loadMore
                ? _dedupe([..._results, ...result.items])
                : result.items;
            _page = result.currentPage;
            _hasMore = result.hasMore;
          });
        }
      } else {
        final results = await ref
            .read(activitiesRepositoryProvider)
            .list(query: _search.text, categoryId: _categoryId);
        if (mounted) {
          setState(() {
            _results = results;
            _hasMore = false;
          });
        }
      }
    } catch (_) {
      if (mounted) setState(() => _error = 'Unable to search activities.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _locateAndLoad() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      setState(
        () => _locationState = 'GPS is disabled. Enter a location below.',
      );
      await _load();
      return;
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      setState(
        () => _locationState = permission == LocationPermission.deniedForever
            ? 'Location blocked in settings. Enter a location below.'
            : 'Location denied. Enter a location below.',
      );
      await _load();
      return;
    }
    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.medium,
          timeLimit: Duration(seconds: 10),
        ),
      );
      if (!mounted) return;
      setState(() {
        _position = position;
        _locationState = 'Using current location';
      });
    } catch (_) {
      if (mounted) {
        setState(
          () => _locationState = 'Unable to get GPS. Enter a location below.',
        );
      }
    }
    await _load();
  }

  List<ActivityItem> _dedupe(List<ActivityItem> items) {
    final byId = <String, ActivityItem>{
      for (final item in items) item.id: item,
    };
    return byId.values.toList();
  }

  String _distance(double km) => km < 1
      ? '${(km * 1000).round()} m'
      : '${km.toStringAsFixed(km < 10 ? 1 : 0)} km';
}
