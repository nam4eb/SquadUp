import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../routes/app_routes.dart';
import '../application/activity_taxonomy_controller.dart';
import '../../onboarding/data/onboarding_repository.dart';
import '../data/activities_repository.dart';
import '../domain/activity_item.dart';
import 'activity_thumbnail.dart';
import 'explore_map.dart';

class ExploreScreen extends ConsumerStatefulWidget {
  const ExploreScreen({this.initialCategoryId, super.key});
  final String? initialCategoryId;

  @override
  ConsumerState<ExploreScreen> createState() => _ExploreScreenState();
}

class _ExploreScreenState extends ConsumerState<ExploreScreen> {
  final _search = TextEditingController();
  final _latitude = TextEditingController();
  final _longitude = TextEditingController();
  String? _categoryId;
  String? _sportId;
  String? _matchFormat;
  double _skill = 1400;
  bool _filterSkill = false;
  bool _openSlots = true;
  bool _showMap = false;
  double _radius = 20;
  Position? _position;
  LatLng? _areaCenter;
  String? _locationState;
  int _page = 1;
  bool _hasMore = false;
  List<ActivityItem> _results = const [];
  bool _loading = false;
  bool _locating = false;
  int _loadGeneration = 0;
  String? _error;

  @override
  void initState() {
    super.initState();
    _categoryId = widget.initialCategoryId;
    _restoreFilters();
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
    final sports = ref.watch(sportsProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Explore activities')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Row(
              children: [
                Expanded(
                  child: SegmentedButton<bool>(
                    segments: const [
                      ButtonSegment(
                        value: false,
                        icon: Icon(Icons.view_list),
                        label: Text('List'),
                      ),
                      ButtonSegment(
                        value: true,
                        icon: Icon(Icons.map_outlined),
                        label: Text('Map'),
                      ),
                    ],
                    selected: {_showMap},
                    onSelectionChanged: (value) {
                      setState(() => _showMap = value.first);
                      _saveFilters();
                    },
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
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
            sports.when(
              data: (items) => SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: [
                    ChoiceChip(
                      label: const Text('All sports'),
                      selected: _sportId == null,
                      onSelected: (_) => _selectSport(null),
                    ),
                    ...items.map(
                      (sport) => Padding(
                        padding: const EdgeInsets.only(left: 8),
                        child: ChoiceChip(
                          label: Text(sport.name),
                          selected: _sportId == sport.id,
                          onSelected: (_) => _selectSport(sport.id),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              loading: () => const LinearProgressIndicator(),
              error: (_, _) => const SizedBox.shrink(),
            ),
            const SizedBox(height: 8),
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
              title: const Text('Match filters'),
              children: [
                DropdownButtonFormField<String?>(
                  initialValue: _matchFormat,
                  decoration: const InputDecoration(labelText: 'Format'),
                  items: const [
                    DropdownMenuItem(value: null, child: Text('Any format')),
                    DropdownMenuItem(value: 'singles', child: Text('Singles')),
                    DropdownMenuItem(value: 'doubles', child: Text('Doubles')),
                    DropdownMenuItem(value: 'team', child: Text('Team')),
                    DropdownMenuItem(value: 'open', child: Text('Open play')),
                  ],
                  onChanged: (value) => setState(() => _matchFormat = value),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Only matches with open slots'),
                  value: _openSlots,
                  onChanged: (value) => setState(() => _openSlots = value),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Match my skill'),
                  subtitle: Text('Rating ${_skill.round()}'),
                  value: _filterSkill,
                  onChanged: (value) => setState(() => _filterSkill = value),
                ),
                if (_filterSkill)
                  Slider(
                    min: 800,
                    max: 2400,
                    divisions: 16,
                    label: _skill.round().toString(),
                    value: _skill,
                    onChanged: (value) => setState(() => _skill = value),
                  ),
                Align(
                  alignment: Alignment.centerRight,
                  child: FilledButton(
                    onPressed: _load,
                    child: const Text('Apply filters'),
                  ),
                ),
              ],
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
                  children: [3, 5, 10, 20, 25, 50]
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
                  onPressed: _locating ? null : _locateAndLoad,
                  icon: const Icon(Icons.my_location),
                  label: Text(
                    _locating ? 'Finding location...' : 'Use current location',
                  ),
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
              ),
            if (!_showMap && !_loading && _results.isEmpty && _error == null)
              const Padding(
                padding: EdgeInsets.all(32),
                child: Center(child: Text('No matching activities.')),
              )
            else if (_showMap)
              SizedBox(
                height: MediaQuery.sizeOf(context).height * .55,
                child: ExploreMap(
                  activities: _results,
                  radiusKm: _areaCenter == null ? 0 : _radius,
                  userLocation: _position == null
                      ? null
                      : LatLng(_position!.latitude, _position!.longitude),
                  initialCenter: _areaCenter ?? const LatLng(10.7769, 106.7009),
                  onSearchArea: (center) {
                    _locationState = 'Using selected map area';
                    _latitude.text = center.latitude.toStringAsFixed(6);
                    _longitude.text = center.longitude.toStringAsFixed(6);
                    _load();
                  },
                ),
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
    _saveFilters();
    _load();
  }

  void _selectSport(String? id) {
    setState(() => _sportId = id);
    _saveFilters();
    _load();
  }

  Future<void> _load({bool loadMore = false}) async {
    final generation = ++_loadGeneration;
    await _saveFilters();
    if (!mounted || generation != _loadGeneration) return;
    final latitude = double.tryParse(_latitude.text.trim());
    final longitude = double.tryParse(_longitude.text.trim());
    if ((_latitude.text.trim().isNotEmpty ||
            _longitude.text.trim().isNotEmpty) &&
        (latitude == null ||
            longitude == null ||
            !latitude.isFinite ||
            !longitude.isFinite ||
            latitude.abs() > 90 ||
            longitude.abs() > 180)) {
      setState(() {
        _loading = false;
        _error =
            'Enter valid latitude (-90 to 90) and longitude (-180 to 180).';
      });
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
      _areaCenter = latitude == null || longitude == null
          ? null
          : LatLng(latitude, longitude);
      if (!loadMore) {
        _results = const [];
        _hasMore = false;
      }
    });
    try {
      if (latitude != null && longitude != null) {
        final nextPage = loadMore ? _page + 1 : 1;
        final result = await ref
            .read(activitiesRepositoryProvider)
            .nearby(
              query: _search.text,
              categoryId: _categoryId,
              sportId: _sportId,
              skill: _filterSkill ? _skill.round() : null,
              matchFormat: _matchFormat,
              openSlots: _openSlots,
              latitude: latitude,
              longitude: longitude,
              radiusKm: _radius,
              page: nextPage,
            );
        if (mounted && generation == _loadGeneration) {
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
            .list(
              query: _search.text,
              categoryId: _categoryId,
              sportId: _sportId,
              skill: _filterSkill ? _skill.round() : null,
              matchFormat: _matchFormat,
              openSlots: _openSlots,
            );
        if (mounted && generation == _loadGeneration) {
          setState(() {
            _results = results;
            _hasMore = false;
          });
        }
      }
    } catch (_) {
      if (mounted && generation == _loadGeneration) {
        setState(() => _error = 'Unable to search activities.');
      }
    } finally {
      if (mounted && generation == _loadGeneration) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _restoreFilters() async {
    final preferences = await SharedPreferences.getInstance();
    if (!mounted) return;
    setState(() {
      _sportId = preferences.getString('explore.sport_id');
      _matchFormat = preferences.getString('explore.match_format');
      _radius = preferences.getDouble('explore.radius') ?? 20;
      _skill = preferences.getDouble('explore.skill') ?? 1400;
      _filterSkill = preferences.getBool('explore.filter_skill') ?? false;
      _openSlots = preferences.getBool('explore.open_slots') ?? true;
      _showMap = preferences.getBool('explore.show_map') ?? false;
    });
    // Listing activities must not wait for a GPS permission prompt. The
    // explicit location button enables nearby search when the user wants it.
    await _load();
  }

  Future<void> _saveFilters() async {
    final preferences = await SharedPreferences.getInstance();
    if (_sportId == null) {
      await preferences.remove('explore.sport_id');
    } else {
      await preferences.setString('explore.sport_id', _sportId!);
    }
    if (_matchFormat == null) {
      await preferences.remove('explore.match_format');
    } else {
      await preferences.setString('explore.match_format', _matchFormat!);
    }
    await preferences.setDouble('explore.radius', _radius);
    await preferences.setDouble('explore.skill', _skill);
    await preferences.setBool('explore.filter_skill', _filterSkill);
    await preferences.setBool('explore.open_slots', _openSlots);
    await preferences.setBool('explore.show_map', _showMap);
  }

  Future<void> _locateAndLoad() async {
    if (_locating) return;
    final generation = _loadGeneration;
    setState(() => _locating = true);
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        throw StateError(
          'Location services are disabled. Choose an area on the map.',
        );
      }
      var permission = await Geolocator.checkPermission();
      if (!mounted) return;
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        throw StateError(
          'Location permission is blocked. Enable it in settings or choose a map area.',
        );
      }
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.medium,
          timeLimit: Duration(seconds: 10),
        ),
      );
      if (!mounted || generation != _loadGeneration) return;
      setState(() {
        _position = position;
        _latitude.text = position.latitude.toString();
        _longitude.text = position.longitude.toString();
        _locationState = 'Using current location';
      });
      await _load();
    } catch (error) {
      if (mounted) {
        setState(
          () => _locationState = error is StateError
              ? error.message.toString()
              : 'Unable to get location. Choose an area on the map.',
        );
      }
    } finally {
      if (mounted) setState(() => _locating = false);
    }
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
