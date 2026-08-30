import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../features/stories/data/stories_repository.dart';
import '../../../features/stories/domain/story_item.dart';
import '../../../features/stories/presentation/story_viewer_screen.dart';
import '../../../theme/app_theme.dart';
import '../../../widgets/custom_image_widget.dart';

class FriendsStoriesWidget extends ConsumerWidget {
  const FriendsStoriesWidget({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final stories = ref.watch(storiesProvider);
    return SizedBox(
      height: 82,
      child: stories.when(
        loading: () => const Center(child: LinearProgressIndicator()),
        error: (_, _) => Align(
          alignment: Alignment.centerLeft,
          child: TextButton.icon(
            onPressed: () => ref.invalidate(storiesProvider),
            icon: const Icon(Icons.refresh),
            label: const Text('Reload stories'),
          ),
        ),
        data: (items) {
          final groups = <String, List<StoryItem>>{};
          final friendStories = items.where((story) => !story.isMine).toList();
          for (final story in friendStories) {
            groups.putIfAbsent(story.userId, () => []).add(story);
          }
          return ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: groups.length + 1,
            separatorBuilder: (_, _) => const SizedBox(width: 14),
            itemBuilder: (context, index) {
              if (index == 0) {
                return _StoryAvatar(
                  name: 'You',
                  isAdd: true,
                  onTap: () => _createStory(context, ref),
                );
              }
              final stories = groups.values.elementAt(index - 1);
              final first = stories.first;
              final initialIndex = friendStories.indexWhere(
                (story) => story.id == first.id,
              );
              return _StoryAvatar(
                name: first.userName,
                imageUrl: first.avatarUrl,
                viewed: stories.every((story) => story.viewedByMe),
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => StoryViewerScreen(
                      stories: friendStories,
                      initialIndex: initialIndex,
                    ),
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }

  Future<void> _createStory(BuildContext context, WidgetRef ref) async {
    final result = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: const [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'gif',
        'mp4',
        'mov',
        'webm',
      ],
      withData: true,
    );
    if (result == null || !context.mounted) return;
    final caption = TextEditingController();
    final submit = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Add to your story'),
        content: TextField(
          controller: caption,
          maxLength: 500,
          decoration: const InputDecoration(labelText: 'Caption (optional)'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Share story'),
          ),
        ],
      ),
    );
    if (submit != true) {
      caption.dispose();
      return;
    }
    try {
      await ref
          .read(storiesRepositoryProvider)
          .create(result.files.single, caption: caption.text);
      ref.invalidate(storiesProvider);
    } catch (_) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Unable to publish story.')),
        );
      }
    } finally {
      caption.dispose();
    }
  }
}

class _StoryAvatar extends StatelessWidget {
  const _StoryAvatar({
    required this.name,
    required this.onTap,
    this.imageUrl,
    this.isAdd = false,
    this.viewed = false,
  });

  final String name;
  final String? imageUrl;
  final bool isAdd;
  final bool viewed;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(30),
    child: SizedBox(
      width: 58,
      child: Column(
        children: [
          Container(
            width: 54,
            height: 54,
            padding: const EdgeInsets.all(2.5),
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: isAdd ? const Color(0xFFE5E7EB) : null,
              gradient: isAdd || viewed
                  ? null
                  : const LinearGradient(
                      colors: [Color(0xFF1A6B5A), Color(0xFF4DB6AC)],
                    ),
            ),
            child: CircleAvatar(
              backgroundColor: Colors.white,
              child: isAdd
                  ? const Icon(Icons.add, color: AppTheme.primary)
                  : imageUrl == null
                  ? const Icon(Icons.person)
                  : ClipOval(
                      child: CustomImageWidget(
                        imageUrl: imageUrl!,
                        width: 46,
                        height: 46,
                        fit: BoxFit.cover,
                        semanticLabel: '$name story',
                      ),
                    ),
            ),
          ),
          const SizedBox(height: 4),
          Text(
            name,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontSize: 11),
          ),
        ],
      ),
    ),
  );
}
