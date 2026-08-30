import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../routes/app_routes.dart';
import './custom_icon_widget.dart';

class _TabSpec {
  final String label;
  final String icon;
  final String selectedIcon;
  final int? branchIndex;
  final bool isCenterFab;

  const _TabSpec({
    required this.label,
    required this.icon,
    required this.selectedIcon,
    this.branchIndex,
    this.isCenterFab = false,
  });
}

class AppNavigation extends StatefulWidget {
  final StatefulNavigationShell navigationShell;

  const AppNavigation({required this.navigationShell, super.key});

  @override
  State<AppNavigation> createState() => _AppNavigationState();
}

class _AppNavigationState extends State<AppNavigation>
    with SingleTickerProviderStateMixin {
  late AnimationController _capsuleController;
  int _selectedVisualIndex = 0;

  final List<_TabSpec> _tabs = const [
    _TabSpec(
      label: 'Home',
      icon: 'home_outlined',
      selectedIcon: 'home',
      branchIndex: 0,
    ),
    _TabSpec(
      label: 'Friends',
      icon: 'people_outline',
      selectedIcon: 'people',
      branchIndex: 1,
    ),
    _TabSpec(
      label: 'Create',
      icon: 'add',
      selectedIcon: 'add',
      isCenterFab: true,
    ),
    _TabSpec(
      label: 'Chat',
      icon: 'chat_bubble_outline',
      selectedIcon: 'chat_bubble',
      branchIndex: 2,
    ),
    _TabSpec(
      label: 'Clans',
      icon: 'groups_outlined',
      selectedIcon: 'groups',
      branchIndex: 3,
    ),
  ];

  @override
  void initState() {
    super.initState();
    _capsuleController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 300),
    );
  }

  @override
  void dispose() {
    _capsuleController.dispose();
    super.dispose();
  }

  void _onTabTap(int visualIndex) {
    final tab = _tabs[visualIndex];
    if (tab.isCenterFab) return;
    if (tab.branchIndex == null) return;
    setState(() => _selectedVisualIndex = visualIndex);
    widget.navigationShell.goBranch(
      tab.branchIndex!,
      initialLocation: tab.branchIndex == widget.navigationShell.currentIndex,
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final bottomPadding = MediaQuery.of(context).padding.bottom;

    return Container(
      margin: EdgeInsets.fromLTRB(20, 0, 20, bottomPadding + 16),
      decoration: BoxDecoration(
        color: theme.colorScheme.surface,
        borderRadius: BorderRadius.circular(32),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withAlpha(26),
            blurRadius: 24,
            offset: const Offset(0, 8),
          ),
          BoxShadow(
            color: Colors.black.withAlpha(10),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceAround,
          children: List.generate(_tabs.length, (index) {
            final tab = _tabs[index];
            if (tab.isCenterFab) {
              return _CenterFabButton(
                onTap: () => context.push(AppRoutes.createActivity),
                primaryColor: theme.colorScheme.primary,
              );
            }
            final isActive = _selectedVisualIndex == index;
            final isStub = tab.branchIndex == null;
            return _TabItem(
              tab: tab,
              isActive: isActive,
              isStub: isStub,
              onTap: () => _onTabTap(index),
              activeColor: theme.colorScheme.primary,
              inactiveColor: theme.colorScheme.onSurfaceVariant,
            );
          }),
        ),
      ),
    );
  }
}

class _TabItem extends StatelessWidget {
  final _TabSpec tab;
  final bool isActive;
  final bool isStub;
  final VoidCallback onTap;
  final Color activeColor;
  final Color inactiveColor;

  const _TabItem({
    required this.tab,
    required this.isActive,
    required this.isStub,
    required this.onTap,
    required this.activeColor,
    required this.inactiveColor,
  });

  @override
  Widget build(BuildContext context) {
    return Opacity(
      opacity: isStub ? 0.4 : 1.0,
      child: GestureDetector(
        onTap: isStub ? null : onTap,
        behavior: HitTestBehavior.opaque,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeInOutCubic,
          padding: EdgeInsets.symmetric(
            horizontal: isActive ? 16 : 12,
            vertical: 8,
          ),
          decoration: BoxDecoration(
            color: isActive ? activeColor.withAlpha(31) : Colors.transparent,
            borderRadius: BorderRadius.circular(24),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              CustomIconWidget(
                iconName: isActive ? tab.selectedIcon : tab.icon,
                color: isActive ? activeColor : inactiveColor,
                size: 22,
              ),
              AnimatedSize(
                duration: const Duration(milliseconds: 250),
                curve: Curves.easeOutCubic,
                child: isActive
                    ? Padding(
                        padding: const EdgeInsets.only(left: 6),
                        child: Text(
                          tab.label,
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: activeColor,
                          ),
                        ),
                      )
                    : const SizedBox.shrink(),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CenterFabButton extends StatefulWidget {
  final VoidCallback onTap;
  final Color primaryColor;

  const _CenterFabButton({required this.onTap, required this.primaryColor});

  @override
  State<_CenterFabButton> createState() => _CenterFabButtonState();
}

class _CenterFabButtonState extends State<_CenterFabButton>
    with SingleTickerProviderStateMixin {
  late AnimationController _scaleController;
  late Animation<double> _scaleAnim;

  @override
  void initState() {
    super.initState();
    _scaleController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 150),
      lowerBound: 0.92,
      upperBound: 1.0,
    )..value = 1.0;
    _scaleAnim = _scaleController;
  }

  @override
  void dispose() {
    _scaleController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTapDown: (_) => _scaleController.reverse(),
      onTapUp: (_) {
        _scaleController.forward();
        widget.onTap();
      },
      onTapCancel: () => _scaleController.forward(),
      child: ScaleTransition(
        scale: _scaleAnim,
        child: Container(
          width: 52,
          height: 52,
          decoration: BoxDecoration(
            color: widget.primaryColor,
            shape: BoxShape.circle,
            boxShadow: [
              BoxShadow(
                color: widget.primaryColor.withAlpha(89),
                blurRadius: 16,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: const Center(
            child: CustomIconWidget(
              iconName: 'add',
              color: Colors.white,
              size: 26,
            ),
          ),
        ),
      ),
    );
  }
}
