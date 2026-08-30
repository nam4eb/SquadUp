import 'dart:math';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:video_player/video_player.dart';

import '../../chat/data/chat_repository.dart';
import '../data/stories_repository.dart';
import '../domain/story_item.dart';

class StoryViewerScreen extends ConsumerStatefulWidget {
  const StoryViewerScreen({
    super.key,
    required this.stories,
    this.initialIndex = 0,
  });

  final List<StoryItem> stories;
  final int initialIndex;

  @override
  ConsumerState<StoryViewerScreen> createState() => _StoryViewerScreenState();
}

class _StoryViewerScreenState extends ConsumerState<StoryViewerScreen>
    with SingleTickerProviderStateMixin {
  late final PageController _pages;
  late final AnimationController _progress;
  final _reply = TextEditingController();
  VideoPlayerController? _video;
  late int _index;
  bool _sendingReply = false;
  bool _composingReply = false;

  @override
  void initState() {
    super.initState();
    _index = widget.initialIndex.clamp(0, widget.stories.length - 1);
    _pages = PageController(initialPage: _index);
    _progress = AnimationController(vsync: this)
      ..addStatusListener((status) {
        if (status == AnimationStatus.completed) _next();
      });
    _show(_index);
  }

  Future<void> _show(int index) async {
    _progress.stop();
    _progress.value = 0;
    await _video?.dispose();
    _video = null;
    if (!mounted) return;
    setState(() => _index = index);
    final story = widget.stories[index];
    ref.read(storiesRepositoryProvider).markViewed(story.id).ignore();

    if (story.mediaType == 'video') {
      final controller = VideoPlayerController.networkUrl(
        Uri.parse(story.mediaUrl),
      );
      _video = controller;
      try {
        await controller.initialize();
        if (!mounted || _video != controller) return;
        _progress.duration = controller.value.duration;
        await controller.play();
        _progress.forward();
        setState(() {});
      } catch (_) {
        _startImageProgress();
      }
    } else {
      _startImageProgress();
    }

    if (index + 1 < widget.stories.length) {
      final next = widget.stories[index + 1];
      if (next.mediaType == 'image' && mounted) {
        precacheImage(CachedNetworkImageProvider(next.mediaUrl), context);
      }
    }
  }

  void _startImageProgress() {
    _progress.duration = const Duration(seconds: 5);
    _progress.forward();
  }

  void _next() {
    if (_index + 1 >= widget.stories.length) {
      if (mounted) Navigator.pop(context);
      return;
    }
    _pages.nextPage(
      duration: const Duration(milliseconds: 220),
      curve: Curves.easeOut,
    );
  }

  void _pause() {
    _progress.stop();
    _video?.pause();
  }

  void _resume() {
    if (_composingReply) return;
    _progress.forward();
    _video?.play();
  }

  @override
  void dispose() {
    _progress.dispose();
    _video?.dispose();
    _pages.dispose();
    _reply.dispose();
    ref.invalidate(storiesProvider);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final story = widget.stories[_index];
    return Scaffold(
      backgroundColor: Colors.black,
      body: SafeArea(
        child: GestureDetector(
          onLongPressStart: (_) => _pause(),
          onLongPressEnd: (_) => _resume(),
          onVerticalDragEnd: (details) {
            if ((details.primaryVelocity ?? 0) > 450) Navigator.pop(context);
          },
          child: Stack(
            children: [
              PageView.builder(
                controller: _pages,
                itemCount: widget.stories.length,
                onPageChanged: _show,
                itemBuilder: (_, index) => _storyPage(widget.stories[index]),
              ),
              Positioned(top: 8, left: 8, right: 8, child: _progressBars()),
              Positioned(
                top: 24,
                left: 16,
                right: 8,
                child: Row(
                  children: [
                    CircleAvatar(
                      backgroundImage: story.avatarUrl == null
                          ? null
                          : NetworkImage(story.avatarUrl!),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        story.userName,
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                    IconButton(
                      onPressed: () => Navigator.pop(context),
                      icon: const Icon(Icons.close, color: Colors.white),
                    ),
                  ],
                ),
              ),
              if (!story.isMine)
                Positioned(
                  left: 16,
                  right: 16,
                  bottom: 12,
                  child: _replyBox(story),
                ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _storyPage(StoryItem story) => GestureDetector(
    behavior: HitTestBehavior.opaque,
    onTapUp: (details) {
      if (details.localPosition.dx < MediaQuery.sizeOf(context).width / 2) {
        _pages.previousPage(
          duration: const Duration(milliseconds: 180),
          curve: Curves.easeOut,
        );
      } else {
        _next();
      }
    },
    child: Stack(
      fit: StackFit.expand,
      children: [
        if (story.mediaType == 'video' &&
            story.id == widget.stories[_index].id &&
            _video?.value.isInitialized == true)
          Center(
            child: AspectRatio(
              aspectRatio: _video!.value.aspectRatio,
              child: VideoPlayer(_video!),
            ),
          )
        else
          CachedNetworkImage(
            imageUrl: story.thumbnailUrl ?? story.mediaUrl,
            fit: BoxFit.contain,
          ),
        if (story.caption?.isNotEmpty == true)
          Align(
            alignment: Alignment.bottomCenter,
            child: Container(
              width: double.infinity,
              margin: const EdgeInsets.only(bottom: 70),
              padding: const EdgeInsets.all(20),
              color: Colors.black54,
              child: Text(
                story.caption!,
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.white),
              ),
            ),
          ),
      ],
    ),
  );

  Widget _progressBars() => Row(
    children: List.generate(
      widget.stories.length,
      (index) => Expanded(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 2),
          child: AnimatedBuilder(
            animation: _progress,
            builder: (_, _) => LinearProgressIndicator(
              value: index < _index
                  ? 1
                  : (index == _index ? _progress.value : 0),
              minHeight: 3,
              backgroundColor: Colors.white38,
              color: Colors.white,
            ),
          ),
        ),
      ),
    ),
  );

  Widget _replyBox(StoryItem story) => TextField(
    controller: _reply,
    style: const TextStyle(color: Colors.white),
    onTap: () {
      _composingReply = true;
      _pause();
    },
    decoration: InputDecoration(
      hintText: 'Reply to ${story.userName}…',
      hintStyle: const TextStyle(color: Colors.white70),
      suffixIcon: IconButton(
        onPressed: _sendingReply ? null : () => _sendReply(story),
        icon: const Icon(Icons.send, color: Colors.white),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(24),
        borderSide: const BorderSide(color: Colors.white70),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(24),
        borderSide: const BorderSide(color: Colors.white),
      ),
    ),
    onSubmitted: (_) => _sendReply(story),
  );

  Future<void> _sendReply(StoryItem story) async {
    final body = _reply.text.trim();
    if (body.isEmpty || _sendingReply) return;
    setState(() => _sendingReply = true);
    try {
      final repository = ref.read(chatRepositoryProvider);
      final conversation = await repository.direct(story.userId);
      await repository.send(
        conversation.id,
        body,
        clientMessageId: _uuid(),
        storyId: story.id,
      );
      _reply.clear();
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Story reply sent.')));
      }
    } finally {
      if (mounted) {
        setState(() {
          _sendingReply = false;
          _composingReply = false;
        });
        _resume();
      }
    }
  }

  String _uuid() {
    final random = Random.secure();
    final bytes = List<int>.generate(16, (_) => random.nextInt(256));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    final value = bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
    return '${value.substring(0, 8)}-${value.substring(8, 12)}-${value.substring(12, 16)}-${value.substring(16, 20)}-${value.substring(20)}';
  }
}
