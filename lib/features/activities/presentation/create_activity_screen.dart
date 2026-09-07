import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../routes/app_routes.dart';
import '../application/activity_taxonomy_controller.dart';
import '../data/activities_repository.dart';
import '../../onboarding/data/onboarding_repository.dart';

class CreateActivityScreen extends ConsumerStatefulWidget {
  final String? clanId;
  final String? clanName;

  const CreateActivityScreen({super.key, this.clanId, this.clanName});

  @override
  ConsumerState<CreateActivityScreen> createState() =>
      _CreateActivityScreenState();
}

class _CreateActivityScreenState extends ConsumerState<CreateActivityScreen> {
  final _formKey = GlobalKey<FormState>();
  final _title = TextEditingController();
  final _description = TextEditingController();
  final _location = TextEditingController();
  final _capacity = TextEditingController(text: '10');
  final _fee = TextEditingController();
  String? _categoryId;
  String? _topicId;
  String? _sportId;
  String? _venueId;
  String _matchFormat = 'open';
  RangeValues _skillRange = const RangeValues(1000, 1600);
  String _visibility = 'public';
  DateTime _startsAt = DateTime.now().add(const Duration(days: 1));
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    if (widget.clanId != null) _visibility = 'clan';
  }

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    _location.dispose();
    _capacity.dispose();
    _fee.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final taxonomy = ref.watch(activityTaxonomyControllerProvider);
    final sports = ref.watch(sportsProvider);
    final venues = ref.watch(venuesProvider);
    return Scaffold(
      appBar: AppBar(
        title: Text(
          widget.clanName == null
              ? 'Create activity'
              : 'Create for ${widget.clanName}',
        ),
      ),
      body: taxonomy.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (_, _) =>
            const Center(child: Text('Unable to load activity categories.')),
        data: (categories) {
          final selected = categories
              .where((item) => item.id == _categoryId)
              .firstOrNull;
          return Form(
            key: _formKey,
            child: ListView(
              padding: const EdgeInsets.all(20),
              children: [
                TextFormField(
                  controller: _title,
                  decoration: const InputDecoration(labelText: 'Title'),
                  validator: _required,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _description,
                  decoration: const InputDecoration(labelText: 'Description'),
                  maxLines: 3,
                ),
                const SizedBox(height: 14),
                sports.when(
                  data: (items) => DropdownButtonFormField<String>(
                    initialValue: _sportId,
                    decoration: const InputDecoration(labelText: 'Sport'),
                    items: items
                        .map(
                          (item) => DropdownMenuItem(
                            value: item.id,
                            child: Text(item.name),
                          ),
                        )
                        .toList(),
                    validator: (value) =>
                        value == null ? 'Select a sport.' : null,
                    onChanged: (value) => setState(() => _sportId = value),
                  ),
                  loading: () => const LinearProgressIndicator(),
                  error: (_, _) => const Text('Unable to load sports.'),
                ),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  initialValue: _categoryId,
                  decoration: const InputDecoration(labelText: 'Category'),
                  items: categories
                      .map(
                        (item) => DropdownMenuItem(
                          value: item.id,
                          child: Text(item.name),
                        ),
                      )
                      .toList(),
                  validator: (value) =>
                      value == null ? 'Select a category.' : null,
                  onChanged: (value) => setState(() {
                    _categoryId = value;
                    _topicId = null;
                  }),
                ),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  initialValue: _topicId,
                  decoration: const InputDecoration(labelText: 'Topic'),
                  items: (selected?.topics ?? const [])
                      .map(
                        (item) => DropdownMenuItem(
                          value: item.id,
                          child: Text(item.name),
                        ),
                      )
                      .toList(),
                  validator: (value) =>
                      value == null ? 'Select a topic.' : null,
                  onChanged: (value) => setState(() => _topicId = value),
                ),
                const SizedBox(height: 14),
                venues.when(
                  data: (items) => DropdownButtonFormField<String?>(
                    initialValue: _venueId,
                    decoration: const InputDecoration(
                      labelText: 'Verified venue (optional)',
                    ),
                    items: [
                      const DropdownMenuItem(
                        value: null,
                        child: Text('Enter location manually'),
                      ),
                      ...items.map(
                        (item) => DropdownMenuItem(
                          value: item.id,
                          child: Text(
                            item.city == null
                                ? item.name
                                : '${item.name} · ${item.city}',
                          ),
                        ),
                      ),
                    ],
                    onChanged: (value) => setState(() {
                      _venueId = value;
                      if (value != null) {
                        final venue = items.firstWhere(
                          (item) => item.id == value,
                        );
                        _location.text = venue.name;
                      }
                    }),
                  ),
                  loading: () => const LinearProgressIndicator(),
                  error: (_, _) => const SizedBox.shrink(),
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _location,
                  decoration: const InputDecoration(labelText: 'Location'),
                  enabled: _venueId == null,
                  validator: (value) =>
                      _venueId == null ? _required(value) : null,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _capacity,
                  decoration: const InputDecoration(
                    labelText: 'Maximum participants',
                  ),
                  keyboardType: TextInputType.number,
                  validator: (value) => (int.tryParse(value ?? '') ?? 0) < 1
                      ? 'Enter a valid capacity.'
                      : null,
                ),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  initialValue: _matchFormat,
                  decoration: const InputDecoration(labelText: 'Match format'),
                  items: const [
                    DropdownMenuItem(value: 'open', child: Text('Open play')),
                    DropdownMenuItem(value: 'singles', child: Text('Singles')),
                    DropdownMenuItem(value: 'doubles', child: Text('Doubles')),
                    DropdownMenuItem(value: 'team', child: Text('Team')),
                  ],
                  onChanged: (value) => setState(() => _matchFormat = value!),
                ),
                const SizedBox(height: 14),
                Text(
                  'Skill range: ${_skillRange.start.round()}–${_skillRange.end.round()}',
                ),
                RangeSlider(
                  min: 800,
                  max: 2400,
                  divisions: 16,
                  labels: RangeLabels(
                    _skillRange.start.round().toString(),
                    _skillRange.end.round().toString(),
                  ),
                  values: _skillRange,
                  onChanged: (value) => setState(() => _skillRange = value),
                ),
                TextFormField(
                  controller: _fee,
                  decoration: const InputDecoration(
                    labelText: 'Fee in VND (optional)',
                  ),
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  validator: (value) =>
                      value?.trim().isNotEmpty == true &&
                          double.tryParse(value!) == null
                      ? 'Enter a valid fee.'
                      : null,
                ),
                const SizedBox(height: 14),
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Starts'),
                  subtitle: Text(
                    _startsAt.toLocal().toString().substring(0, 16),
                  ),
                  trailing: const Icon(Icons.edit_calendar),
                  onTap: _pickStart,
                ),
                DropdownButtonFormField<String>(
                  initialValue: _visibility,
                  decoration: const InputDecoration(labelText: 'Visibility'),
                  items: const [
                    DropdownMenuItem(value: 'public', child: Text('Public')),
                    DropdownMenuItem(value: 'friends', child: Text('Friends')),
                    DropdownMenuItem(value: 'clan', child: Text('Clan')),
                    DropdownMenuItem(
                      value: 'invite_only',
                      child: Text('Invite only'),
                    ),
                    DropdownMenuItem(value: 'private', child: Text('Private')),
                  ],
                  onChanged: (value) => setState(() => _visibility = value!),
                ),
                const SizedBox(height: 24),
                FilledButton(
                  onPressed: _submitting ? null : _submit,
                  child: Text(_submitting ? 'Creating…' : 'Create activity'),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  String? _required(String? value) =>
      value == null || value.trim().isEmpty ? 'This field is required.' : null;

  Future<void> _pickStart() async {
    final date = await showDatePicker(
      context: context,
      initialDate: _startsAt,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 730)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(_startsAt),
    );
    if (time == null) return;
    setState(
      () => _startsAt = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      ),
    );
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _submitting = true);
    try {
      final activity = await ref.read(activitiesRepositoryProvider).create({
        'category_id': _categoryId,
        'topic_id': _topicId,
        'sport_id': _sportId,
        if (_venueId != null) 'venue_id': _venueId,
        if (widget.clanId != null) 'clan_id': widget.clanId,
        'title': _title.text.trim(),
        'description': _description.text.trim(),
        'starts_at': _startsAt.toUtc().toIso8601String(),
        'timezone': 'UTC',
        'location_name': _location.text.trim(),
        'skill_min': _skillRange.start.round(),
        'skill_max': _skillRange.end.round(),
        'match_format': _matchFormat,
        if (_fee.text.trim().isNotEmpty) 'fee': double.parse(_fee.text.trim()),
        if (_fee.text.trim().isNotEmpty) 'currency': 'VND',
        'min_participants': 1,
        'max_participants': int.parse(_capacity.text),
        'visibility': _visibility,
      });
      ref.invalidate(activityFeedProvider);
      if (mounted) {
        context.go(AppRoutes.activityDetail, extra: activity.id);
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Unable to create activity. Check the details and try again.',
            ),
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }
}
