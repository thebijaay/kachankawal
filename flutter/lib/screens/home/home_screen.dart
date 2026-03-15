// ─────────────────────────────────────────────
//  lib/screens/home/home_screen.dart
// ─────────────────────────────────────────────
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

// ══════════════════════════════════
//  Main Shell – Bottom nav
// ══════════════════════════════════
class MainShell extends StatelessWidget {
  final Widget child;
  const MainShell({super.key, required this.child});

  static const _tabs = [
    (icon: Icons.home_outlined,       activeIcon: Icons.home,       label: 'Home',       path: '/home'),
    (icon: Icons.campaign_outlined,   activeIcon: Icons.campaign,   label: 'Notices',    path: '/notices'),
    (icon: Icons.design_services_outlined, activeIcon: Icons.design_services, label: 'Services', path: '/services'),
    (icon: Icons.report_outlined,     activeIcon: Icons.report,     label: 'Complaints', path: '/complaints'),
    (icon: Icons.person_outline,      activeIcon: Icons.person,     label: 'Profile',    path: '/profile'),
  ];

  int _currentIndex(BuildContext ctx) {
    final location = GoRouterState.of(ctx).matchedLocation;
    for (int i = 0; i < _tabs.length; i++) {
      if (location.startsWith(_tabs[i].path)) return i;
    }
    return 0;
  }

  @override
  Widget build(BuildContext context) {
    final idx = _currentIndex(context);
    return Scaffold(
      body: child,
      bottomNavigationBar: NavigationBar(
        selectedIndex: idx,
        onDestinationSelected: (i) => context.go(_tabs[i].path),
        destinations: _tabs.map((t) => NavigationDestination(
          icon:       Icon(t.icon),
          selectedIcon: Icon(t.activeIcon),
          label:      t.label,
        )).toList(),
      ),
    );
  }
}

// ══════════════════════════════════
//  Home Screen
// ══════════════════════════════════
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});
  @override State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  Map<String,dynamic>? _user;
  List<dynamic> _recentNotices = [];
  List<dynamic> _upcomingEvents = [];
  bool _loading = true;

  @override
  void initState() { super.initState(); _loadData(); }

  Future<void> _loadData() async {
    final api = getIt<ApiService>();
    try {
      final results = await Future.wait([
        api.getMe(),
        api.getNotices(page: 1),
        api.getEvents(),
      ]);
      setState(() {
        _user           = results[0].data['user'];
        _recentNotices  = (results[1].data['data'] as List).take(3).toList();
        _upcomingEvents = (results[2].data['data'] as List).take(3).toList();
        _loading        = false;
      });
    } catch (_) {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: RefreshIndicator(
        onRefresh: _loadData,
        child: CustomScrollView(
          slivers: [
            // ── App Bar ──
            SliverAppBar(
              expandedHeight: 160,
              pinned: true,
              flexibleSpace: FlexibleSpaceBar(
                background: Container(
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      colors: [AppTheme.primaryColor, Color(0xFF008A4E)],
                      begin: Alignment.topLeft, end: Alignment.bottomRight,
                    ),
                  ),
                  child: SafeArea(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(20, 16, 20, 0),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(children: [
                            const Icon(Icons.account_balance, color: Colors.white, size: 28),
                            const SizedBox(width: 8),
                            const Text('Kachankawal Gaunpalika',
                              style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold)),
                            const Spacer(),
                            IconButton(
                              icon: const Icon(Icons.notifications_outlined, color: Colors.white),
                              onPressed: () {},
                            ),
                          ]),
                          const SizedBox(height: 8),
                          if (_user != null)
                            Text('Namaste, ${_user!['name']} 🙏',
                              style: const TextStyle(color: Colors.white70, fontSize: 14)),
                          if (_user != null && _user!['ward_no'] != null)
                            Text('Ward ${_user!['ward_no']}',
                              style: const TextStyle(color: Colors.white70, fontSize: 12)),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),

            if (_loading)
              const SliverFillRemaining(child: Center(child: CircularProgressIndicator()))
            else ...[
              // ── Quick Actions ──
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _sectionTitle(context, 'Quick Actions'),
                      const SizedBox(height: 12),
                      GridView.count(
                        crossAxisCount: 4,
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        mainAxisSpacing: 12, crossAxisSpacing: 8,
                        childAspectRatio: 0.85,
                        children: [
                          _QuickActionTile(icon: Icons.child_care,         label: 'Birth Reg.',   color: Colors.blue,   onTap: () => context.push('/services/new', extra: 'birth_registration')),
                          _QuickActionTile(icon: Icons.heart_broken,       label: 'Death Reg.',   color: Colors.grey,   onTap: () => context.push('/services/new', extra: 'death_registration')),
                          _QuickActionTile(icon: Icons.favorite,           label: 'Marriage',     color: Colors.pink,   onTap: () => context.push('/services/new', extra: 'marriage_registration')),
                          _QuickActionTile(icon: Icons.directions_walk,    label: 'Migration',    color: Colors.teal,   onTap: () => context.push('/services/new', extra: 'migration_certificate')),
                          _QuickActionTile(icon: Icons.report_problem,     label: 'Complaint',    color: Colors.orange, onTap: () => context.push('/complaints/new')),
                          _QuickActionTile(icon: Icons.campaign,           label: 'Notices',      color: Colors.green,  onTap: () => context.go('/notices')),
                          _QuickActionTile(icon: Icons.location_city,      label: 'Ward Info',    color: Colors.indigo, onTap: () => context.go('/wards')),
                          _QuickActionTile(icon: Icons.track_changes,      label: 'Track',        color: Colors.purple, onTap: () => context.go('/services')),
                        ],
                      ),
                    ],
                  ),
                ),
              ),

              // ── Recent Notices ──
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 24, 16, 0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(children: [
                        _sectionTitle(context, 'Recent Notices'),
                        const Spacer(),
                        TextButton(onPressed: () => context.go('/notices'), child: const Text('View All')),
                      ]),
                      ..._recentNotices.map((n) => _NoticeCard(notice: n)),
                      if (_recentNotices.isEmpty)
                        const Center(child: Padding(padding: EdgeInsets.all(16), child: Text('No notices', style: TextStyle(color: Colors.grey)))),
                    ],
                  ),
                ),
              ),

              // ── Upcoming Events ──
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 24, 16, 24),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _sectionTitle(context, 'Upcoming Events'),
                      const SizedBox(height: 12),
                      SizedBox(
                        height: 120,
                        child: _upcomingEvents.isEmpty
                            ? const Center(child: Text('No upcoming events', style: TextStyle(color: Colors.grey)))
                            : ListView.builder(
                                scrollDirection: Axis.horizontal,
                                itemCount: _upcomingEvents.length,
                                itemBuilder: (ctx, i) => _EventChip(event: _upcomingEvents[i]),
                              ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _sectionTitle(BuildContext ctx, String title) =>
      Text(title, style: Theme.of(ctx).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold));
}

// ── Quick Action Tile ──
class _QuickActionTile extends StatelessWidget {
  final IconData icon; final String label; final Color color; final VoidCallback onTap;
  const _QuickActionTile({required this.icon, required this.label, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Container(
          width: 52, height: 52,
          decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(14)),
          child: Icon(icon, color: color, size: 26),
        ),
        const SizedBox(height: 6),
        Text(label, textAlign: TextAlign.center, style: const TextStyle(fontSize: 11), maxLines: 2, overflow: TextOverflow.ellipsis),
      ]),
    );
  }
}

// ── Notice Card ──
class _NoticeCard extends StatelessWidget {
  final Map<String,dynamic> notice;
  const _NoticeCard({required this.notice});

  Color get _typeColor {
    return switch (notice['type']) {
      'emergency' => Colors.red,
      'tender'    => Colors.orange,
      'meeting'   => Colors.blue,
      _           => AppTheme.primaryColor,
    };
  }

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: _typeColor.withOpacity(0.1),
          child: Icon(Icons.campaign, color: _typeColor, size: 20),
        ),
        title: Text(notice['title'] ?? '', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Text(notice['type']?.toString().replaceAll('_', ' ').toUpperCase() ?? '',
          style: TextStyle(color: _typeColor, fontSize: 11, fontWeight: FontWeight.w600)),
        trailing: const Icon(Icons.arrow_forward_ios, size: 14),
        onTap: () => context.push('/notices/${notice['id']}'),
      ),
    );
  }
}

// ── Event Chip ──
class _EventChip extends StatelessWidget {
  final Map<String,dynamic> event;
  const _EventChip({required this.event});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 180,
      margin: const EdgeInsets.only(right: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppTheme.primaryColor.withOpacity(0.06),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.primaryColor.withOpacity(0.2)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(event['title'] ?? '', maxLines: 2, overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
          const Spacer(),
          Row(children: [
            const Icon(Icons.calendar_today, size: 12, color: AppTheme.primaryColor),
            const SizedBox(width: 4),
            Text(event['starts_at']?.toString().substring(0,10) ?? '',
              style: const TextStyle(fontSize: 11, color: AppTheme.primaryColor)),
          ]),
          if (event['venue'] != null) ...[
            const SizedBox(height: 2),
            Row(children: [
              const Icon(Icons.location_on, size: 12, color: Colors.grey),
              const SizedBox(width: 4),
              Expanded(child: Text(event['venue'], style: const TextStyle(fontSize: 11, color: Colors.grey), overflow: TextOverflow.ellipsis)),
            ]),
          ],
        ],
      ),
    );
  }
}
