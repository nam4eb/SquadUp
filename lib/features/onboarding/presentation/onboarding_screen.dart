import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:go_router/go_router.dart';

import '../../../routes/app_routes.dart';
import '../data/onboarding_repository.dart';
import '../domain/sport_option.dart';

class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key});

  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen> {
  static const levels = <String, String>{
    'beginner': 'Beginner',
    'recreational': 'Recreational',
    'intermediate': 'Intermediate',
    'upper_intermediate': 'Upper intermediate',
    'advanced': 'Advanced',
    'competitive': 'Competitive',
  };

  final _city = TextEditingController();
  List<SportOption> _sports = const [];
  final Map<String, String> _selected = {};
  int _step = 0;
  bool _loading = true;
  String? _error;
  Position? _position;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _city.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Set up SquadUp')),
    body: SafeArea(
      child: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
          ? Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(_error!),
                  TextButton(onPressed: _load, child: const Text('Try again')),
                ],
              ),
            )
          : Stepper(
              currentStep: _step,
              onStepContinue: _continue,
              onStepCancel: _step == 0 ? null : () => setState(() => _step--),
              controlsBuilder: (context, details) => Padding(
                padding: const EdgeInsets.only(top: 20),
                child: Row(
                  children: [
                    FilledButton(
                      onPressed: details.onStepContinue,
                      child: Text(_step == 2 ? 'Start exploring' : 'Continue'),
                    ),
                    if (_step > 0)
                      TextButton(
                        onPressed: details.onStepCancel,
                        child: const Text('Back'),
                      ),
                  ],
                ),
              ),
              steps: [
                Step(
                  title: const Text('Choose sports'),
                  isActive: _step >= 0,
                  content: Column(children: _sports.map(_sportTile).toList()),
                ),
                Step(
                  title: const Text('Default area'),
                  isActive: _step >= 1,
                  content: Column(
                    children: [
                      TextField(
                        controller: _city,
                        decoration: const InputDecoration(
                          labelText: 'City or area',
                        ),
                      ),
                      const SizedBox(height: 12),
                      OutlinedButton.icon(
                        onPressed: _requestLocation,
                        icon: const Icon(Icons.my_location),
                        label: Text(
                          _position == null
                              ? 'Use current location'
                              : 'Location selected',
                        ),
                      ),
                      const Text(
                        'Optional — you can enter an area without sharing live location.',
                      ),
                    ],
                  ),
                ),
                const Step(
                  title: Text('Notifications'),
                  isActive: true,
                  content: Text(
                    'Enable reminders and chat alerts. SquadUp remains usable if you skip this permission.',
                  ),
                ),
              ],
            ),
    ),
  );

  Widget _sportTile(SportOption sport) {
    final selected = _selected.containsKey(sport.id);
    return Card(
      child: Column(
        children: [
          CheckboxListTile(
            value: selected,
            title: Text(sport.name),
            subtitle: Text('${sport.minPlayers}–${sport.maxPlayers} players'),
            onChanged: (value) => setState(() {
              if (value == true) {
                _selected[sport.id] = 'beginner';
              } else {
                _selected.remove(sport.id);
              }
            }),
          ),
          if (selected)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
              child: DropdownButtonFormField<String>(
                initialValue: _selected[sport.id],
                decoration: const InputDecoration(labelText: 'Your level'),
                items: levels.entries
                    .map(
                      (entry) => DropdownMenuItem(
                        value: entry.key,
                        child: Text(entry.value),
                      ),
                    )
                    .toList(),
                onChanged: (value) =>
                    setState(() => _selected[sport.id] = value!),
              ),
            ),
        ],
      ),
    );
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final repository = ref.read(onboardingRepositoryProvider);
      final values = await Future.wait([
        repository.sports(),
        repository.status(),
      ]);
      final snapshot = values[1] as OnboardingSnapshot;
      if (!mounted) return;
      setState(() {
        _sports = values[0] as List<SportOption>;
        _selected.addEntries(
          snapshot.selections.map(
            (item) => MapEntry(item.sport.id, item.level),
          ),
        );
        _city.text = snapshot.city ?? '';
        _step = switch (snapshot.step) {
          'location' => 1,
          'notifications' => 2,
          _ => 0,
        };
        _loading = false;
      });
      if (snapshot.completed && mounted) {
        context.go(AppRoutes.homeFeed);
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'Unable to load onboarding.';
        });
      }
    }
  }

  Future<void> _continue() async {
    final repository = ref.read(onboardingRepositoryProvider);
    try {
      if (_step == 0) {
        if (_selected.isEmpty) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Choose at least one sport.')),
          );
          return;
        }
        await repository.saveSports(_selected);
        await repository.saveProgress(step: 'location');
        if (mounted) {
          setState(() => _step = 1);
        }
      } else if (_step == 1) {
        await repository.saveProgress(
          step: 'notifications',
          city: _city.text.trim().isEmpty ? null : _city.text.trim(),
          latitude: _position?.latitude,
          longitude: _position?.longitude,
        );
        if (mounted) {
          setState(() => _step = 2);
        }
      } else {
        try {
          await FirebaseMessaging.instance.requestPermission(provisional: true);
        } catch (_) {}
        await repository.saveProgress(step: 'completed');
        if (mounted) {
          context.go(AppRoutes.homeFeed);
        }
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not save. Please retry.')),
        );
      }
    }
  }

  Future<void> _requestLocation() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      return;
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      return;
    }
    final value = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.medium,
        timeLimit: Duration(seconds: 10),
      ),
    );
    if (mounted) {
      setState(() => _position = value);
    }
  }
}
