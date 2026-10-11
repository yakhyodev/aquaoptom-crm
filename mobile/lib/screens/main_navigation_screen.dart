import 'package:flutter/material.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';
import 'admin_web_screen.dart';
import 'calculator_screen.dart';
import 'catalog_screen.dart';
import 'dashboard_screen.dart';
import 'debt_screen.dart';
import 'inward_screen.dart';
import 'login_screen.dart';
import 'pos_screen.dart';
import 'reports_screen.dart';
import 'sales_history_screen.dart';
import '../services/offline_sync_service.dart';
import '../services/theme_service.dart';

class MainNavigationScreen extends StatefulWidget {
  final UserModel user;
  final ApiService? apiService;

  const MainNavigationScreen({super.key, required this.user, this.apiService});

  @override
  State<MainNavigationScreen> createState() => _MainNavigationScreenState();
}

class _MainNavigationScreenState extends State<MainNavigationScreen> {
  late final ApiService _api;
  int _currentIndex = 0;
  late final List<Widget> _screens;
  late final List<BottomNavigationBarItem> _navItems;
  late final List<String> _titles;

  @override
  void initState() {
    super.initState();
    _api = widget.apiService ?? ApiService();
    _setupRoleNavigation();
  }

  void _setupRoleNavigation() {
    final user = widget.user;

    if (user.isAdmin || user.isOwner) {
      _screens = [
        DashboardScreen(apiService: _api),
        PosScreen(apiService: _api),
        CatalogScreen(apiService: _api),
        DebtScreen(apiService: _api),
        SalesHistoryScreen(apiService: _api),
      ];
      _titles = [
        'Dashboard',
        'Sotuv qilish',
        'Katalog & Qoldiq',
        'Qarzlar',
        'Savdo Tarixi',
      ];
      _navItems = const [
        BottomNavigationBarItem(
          icon: Icon(Icons.dashboard_outlined),
          activeIcon: Icon(Icons.dashboard),
          label: 'Bosh sahifa',
        ),
        BottomNavigationBarItem(
          icon: Icon(Icons.point_of_sale_outlined),
          activeIcon: Icon(Icons.point_of_sale),
          label: 'Sotuv',
        ),
        BottomNavigationBarItem(
          icon: Icon(Icons.inventory_2_outlined),
          activeIcon: Icon(Icons.inventory_2),
          label: 'Katalog',
        ),
        BottomNavigationBarItem(
          icon: Icon(Icons.credit_card_outlined),
          activeIcon: Icon(Icons.credit_card),
          label: 'Qarzlar',
        ),
        BottomNavigationBarItem(
          icon: Icon(Icons.receipt_long_outlined),
          activeIcon: Icon(Icons.receipt_long),
          label: 'Tarix',
        ),
      ];
    } else if (user.isWarehouse) {
      _screens = [
        CatalogScreen(apiService: _api),
        InwardScreen(apiService: _api),
        CalculatorScreen(apiService: _api),
      ];
      _titles = ['Katalog & Qoldiq', 'Kirim Qilish', 'Kalkulyator'];
      _navItems = const [
        BottomNavigationBarItem(
          icon: Icon(Icons.inventory_2_outlined),
          activeIcon: Icon(Icons.inventory_2),
          label: 'Katalog',
        ),
        BottomNavigationBarItem(
          icon: Icon(Icons.add_shopping_cart_outlined),
          activeIcon: Icon(Icons.add_shopping_cart),
          label: 'Kirim',
        ),
        BottomNavigationBarItem(
          icon: Icon(Icons.calculate_outlined),
          activeIcon: Icon(Icons.calculate),
          label: 'Kalkulyator',
        ),
      ];
    } else {
      // CASHIER / SALES_MANAGER
      _screens = [
        PosScreen(apiService: _api),
        CatalogScreen(apiService: _api),
        DebtScreen(apiService: _api),
        SalesHistoryScreen(apiService: _api),
      ];
      _titles = ['Sotuv qilish', 'Katalog & Qoldiq', 'Qarzlar', 'Savdo Tarixi'];
      _navItems = const [
        BottomNavigationBarItem(
          icon: Icon(Icons.point_of_sale_outlined),
          activeIcon: Icon(Icons.point_of_sale),
          label: 'Sotuv',
        ),
        BottomNavigationBarItem(
          icon: Icon(Icons.inventory_2_outlined),
          activeIcon: Icon(Icons.inventory_2),
          label: 'Katalog',
        ),
        BottomNavigationBarItem(
          icon: Icon(Icons.credit_card_outlined),
          activeIcon: Icon(Icons.credit_card),
          label: 'Qarzlar',
        ),
        BottomNavigationBarItem(
          icon: Icon(Icons.receipt_long_outlined),
          activeIcon: Icon(Icons.receipt_long),
          label: 'Tarix',
        ),
      ];
    }
  }

  Future<void> _handleLogout() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: Theme.of(context).colorScheme.surface,
        title: Text(
          'Chiqish',
          style: TextStyle(color: Theme.of(context).colorScheme.onSurface),
        ),
        content: Text(
          'Haqiqatan ham tizimdan chiqmoqchimisiz?',
          style: TextStyle(
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Bekor qilish'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(backgroundColor: Colors.redAccent),
            child: const Text('Chiqish'),
          ),
        ],
      ),
    );

    if (confirm == true) {
      await _api.logout();
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => LoginScreen(apiService: _api)),
      );
    }
  }

  void _showSyncModal() {
    showModalBottomSheet(
      context: context,
      backgroundColor: Theme.of(context).colorScheme.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setModalState) {
          final syncService = OfflineSyncService();
          return FutureBuilder<SyncStatusSummary>(
            future: syncService.getStatusSummary(),
            builder: (context, snapshot) {
              final summary = snapshot.data ?? const SyncStatusSummary();
              return Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            Icon(
                              Icons.sync,
                              color: Theme.of(context).colorScheme.primary,
                            ),
                            SizedBox(width: 8),
                            Text(
                              'Offline & Sinxronlash',
                              style: TextStyle(
                                color: Theme.of(context).colorScheme.onSurface,
                                fontWeight: FontWeight.bold,
                                fontSize: 16,
                              ),
                            ),
                          ],
                        ),
                        IconButton(
                          icon: Icon(
                            Icons.close,
                            color: Theme.of(
                              context,
                            ).colorScheme.onSurfaceVariant,
                          ),
                          onPressed: () => Navigator.pop(ctx),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceAround,
                      children: [
                        _buildStatusBadge(
                          'Kutilmoqda',
                          summary.pendingCount,
                          Colors.blueAccent,
                        ),
                        _buildStatusBadge(
                          'Yuborildi',
                          summary.acknowledgedCount,
                          Colors.greenAccent,
                        ),
                        _buildStatusBadge(
                          'Tekshiruv',
                          summary.needsReviewCount,
                          Colors.amberAccent,
                        ),
                        _buildStatusBadge(
                          'Mojaro',
                          summary.conflictCount,
                          Colors.redAccent,
                        ),
                      ],
                    ),
                    if (summary.lastError != null) ...[
                      const SizedBox(height: 12),
                      Text(
                        'Xatolik: ${summary.lastError}',
                        style: const TextStyle(
                          color: Colors.redAccent,
                          fontSize: 12,
                        ),
                      ),
                    ],
                    const SizedBox(height: 20),
                    Row(
                      children: [
                        Expanded(
                          child: ElevatedButton.icon(
                            onPressed: summary.isSyncing
                                ? null
                                : () async {
                                    setModalState(() {});
                                    await syncService.syncNow();
                                    if (ctx.mounted) setModalState(() {});
                                  },
                            icon: const Icon(Icons.cloud_upload),
                            label: Text(
                              summary.isSyncing
                                  ? 'Sinxronlanmoqda...'
                                  : 'Hozir Sinxronlash',
                            ),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.blueAccent,
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        OutlinedButton.icon(
                          onPressed: summary.isSyncing
                              ? null
                              : () async {
                                  try {
                                    await syncService.bootstrap();
                                    if (ctx.mounted) {
                                      ScaffoldMessenger.of(ctx).showSnackBar(
                                        const SnackBar(
                                          content: Text(
                                            'Bootstrap muvaffaqiyatli yuklandi!',
                                          ),
                                        ),
                                      );
                                      setModalState(() {});
                                    }
                                  } catch (e) {
                                    if (ctx.mounted) {
                                      ScaffoldMessenger.of(ctx).showSnackBar(
                                        SnackBar(
                                          content: Text('Bootstrap xatosi: $e'),
                                          backgroundColor: Colors.redAccent,
                                        ),
                                      );
                                    }
                                  }
                                },
                          icon: const Icon(Icons.download),
                          label: const Text('Bootstrap'),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: Theme.of(
                              context,
                            ).colorScheme.primary,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              );
            },
          );
        },
      ),
    );
  }

  Widget _buildStatusBadge(String title, int count, Color color) {
    return Column(
      children: [
        Text(
          count.toString(),
          style: TextStyle(
            color: color,
            fontSize: 20,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          title,
          style: TextStyle(
            color: Theme.of(context).colorScheme.onSurfaceVariant,
            fontSize: 11,
          ),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = widget.user;

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(_titles[_currentIndex]),
        backgroundColor: Theme.of(context).colorScheme.surface,
        elevation: 0,
        actions: [
          IconButton(
            icon: Icon(
              Theme.of(context).brightness == Brightness.dark
                  ? Icons.light_mode
                  : Icons.dark_mode,
            ),
            tooltip: 'Kunduzgi / tungi rejim',
            onPressed: () =>
                ThemeService.instance.toggle(Theme.of(context).brightness),
          ),
          // Sync & Offline Status Button
          IconButton(
            icon: Icon(
              Icons.sync,
              color: Theme.of(context).colorScheme.primary,
            ),
            tooltip: 'Sinxronlash holati',
            onPressed: _showSyncModal,
          ),

          // Role Badge
          Center(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: Colors.blueAccent.withValues(alpha: 0.2),
                borderRadius: BorderRadius.circular(6),
                border: Border.all(
                  color: Colors.blueAccent.withValues(alpha: 0.4),
                ),
              ),
              child: Text(
                user.role,
                style: TextStyle(
                  color: Theme.of(context).colorScheme.primary,
                  fontWeight: FontWeight.bold,
                  fontSize: 11,
                ),
              ),
            ),
          ),
          const SizedBox(width: 8),

          // Logout Button
          IconButton(
            icon: Icon(
              Icons.logout,
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
            tooltip: 'Chiqish',
            onPressed: _handleLogout,
          ),
        ],
      ),
      drawer: Drawer(
        backgroundColor: Theme.of(context).colorScheme.surface,
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            UserAccountsDrawerHeader(
              decoration: BoxDecoration(
                color: Theme.of(context).scaffoldBackgroundColor,
              ),
              accountName: Text(
                user.name,
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 16,
                ),
              ),
              accountEmail: Text(
                user.email,
                style: TextStyle(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
              ),
              currentAccountPicture: CircleAvatar(
                backgroundColor: Colors.blueAccent,
                child: Text(
                  user.name.isNotEmpty ? user.name[0].toUpperCase() : 'U',
                  style: TextStyle(
                    fontSize: 22,
                    color: Theme.of(context).colorScheme.onSurface,
                  ),
                ),
              ),
            ),

            if (user.isAdmin || user.isOwner) ...[
              ListTile(
                leading: Icon(
                  Icons.dashboard,
                  color: Theme.of(context).colorScheme.primary,
                ),
                title: Text(
                  'Dashboard',
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.onSurface,
                  ),
                ),
                onTap: () {
                  Navigator.pop(context);
                  setState(() => _currentIndex = 0);
                },
              ),
              ListTile(
                leading: const Icon(Icons.bar_chart, color: Colors.blueAccent),
                title: Text(
                  'Hisobotlar',
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.onSurface,
                  ),
                ),
                onTap: () {
                  Navigator.pop(context);
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => ReportsScreen(apiService: _api),
                    ),
                  );
                },
              ),
              ListTile(
                leading: const Icon(
                  Icons.calculate,
                  color: Colors.purpleAccent,
                ),
                title: Text(
                  'Ombor Kalkulyatori',
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.onSurface,
                  ),
                ),
                onTap: () {
                  Navigator.pop(context);
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => CalculatorScreen(apiService: _api),
                    ),
                  );
                },
              ),
              ListTile(
                leading: const Icon(
                  Icons.add_shopping_cart,
                  color: Colors.tealAccent,
                ),
                title: Text(
                  'Kirim Qilish',
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.onSurface,
                  ),
                ),
                onTap: () {
                  Navigator.pop(context);
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => InwardScreen(apiService: _api),
                    ),
                  );
                },
              ),
              ListTile(
                leading: const Icon(
                  Icons.shield_outlined,
                  color: Colors.amberAccent,
                ),
                title: Text(
                  'Admin Web Linklar',
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.onSurface,
                  ),
                ),
                onTap: () {
                  Navigator.pop(context);
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const AdminWebScreen()),
                  );
                },
              ),
            ],

            Divider(color: Theme.of(context).colorScheme.outlineVariant),
            ListTile(
              leading: const Icon(Icons.logout, color: Colors.redAccent),
              title: const Text(
                'Tizimdan chiqish',
                style: TextStyle(color: Colors.redAccent),
              ),
              onTap: () {
                Navigator.pop(context);
                _handleLogout();
              },
            ),
          ],
        ),
      ),
      body: _screens[_currentIndex],
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentIndex,
        onTap: (idx) => setState(() => _currentIndex = idx),
        backgroundColor: Theme.of(context).colorScheme.surface,
        selectedItemColor: Theme.of(context).colorScheme.primary,
        unselectedItemColor: Theme.of(context).colorScheme.onSurfaceVariant,
        type: BottomNavigationBarType.fixed,
        items: _navItems,
      ),
    );
  }
}
