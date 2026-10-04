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

class MainNavigationScreen extends StatefulWidget {
  final UserModel user;
  final ApiService? apiService;

  const MainNavigationScreen({
    super.key,
    required this.user,
    this.apiService,
  });

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
      _titles = ['Dashboard', 'POS / Savdo', 'Katalog & Qoldiq', 'Qarzlar', 'Savdo Tarixi'];
      _navItems = const [
        BottomNavigationBarItem(
          icon: Icon(Icons.dashboard_outlined),
          activeIcon: Icon(Icons.dashboard),
          label: 'Dashboard',
        ),
        BottomNavigationBarItem(
          icon: Icon(Icons.point_of_sale_outlined),
          activeIcon: Icon(Icons.point_of_sale),
          label: 'POS',
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
      _titles = ['POS / Savdo', 'Katalog & Qoldiq', 'Qarzlar', 'Savdo Tarixi'];
      _navItems = const [
        BottomNavigationBarItem(
          icon: Icon(Icons.point_of_sale_outlined),
          activeIcon: Icon(Icons.point_of_sale),
          label: 'POS',
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
        backgroundColor: const Color(0xFF1E293B),
        title: const Text('Chiqish', style: TextStyle(color: Colors.white)),
        content: const Text(
          'Haqiqatan ham tizimdan chiqmoqchimisiz?',
          style: TextStyle(color: Colors.blueGrey),
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

  @override
  Widget build(BuildContext context) {
    final user = widget.user;

    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      appBar: AppBar(
        title: Text(_titles[_currentIndex]),
        backgroundColor: const Color(0xFF1E293B),
        elevation: 0,
        actions: [
          // Role Badge
          Center(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: Colors.blueAccent.withValues(alpha: 0.2),
                borderRadius: BorderRadius.circular(6),
                border: Border.all(color: Colors.blueAccent.withValues(alpha: 0.4)),
              ),
              child: Text(
                user.role,
                style: const TextStyle(
                  color: Colors.cyanAccent,
                  fontWeight: FontWeight.bold,
                  fontSize: 11,
                ),
              ),
            ),
          ),
          const SizedBox(width: 8),

          // Logout Button
          IconButton(
            icon: const Icon(Icons.logout, color: Colors.blueGrey),
            tooltip: 'Chiqish',
            onPressed: _handleLogout,
          ),
        ],
      ),
      drawer: Drawer(
        backgroundColor: const Color(0xFF1E293B),
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            UserAccountsDrawerHeader(
              decoration: const BoxDecoration(color: Color(0xFF0F172A)),
              accountName: Text(
                user.name,
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
              ),
              accountEmail: Text(
                user.email,
                style: const TextStyle(color: Colors.blueGrey),
              ),
              currentAccountPicture: CircleAvatar(
                backgroundColor: Colors.blueAccent,
                child: Text(
                  user.name.isNotEmpty ? user.name[0].toUpperCase() : 'U',
                  style: const TextStyle(fontSize: 22, color: Colors.white),
                ),
              ),
            ),

            if (user.isAdmin || user.isOwner) ...[
              ListTile(
                leading: const Icon(Icons.dashboard, color: Colors.cyanAccent),
                title: const Text('Dashboard', style: TextStyle(color: Colors.white)),
                onTap: () {
                  Navigator.pop(context);
                  setState(() => _currentIndex = 0);
                },
              ),
              ListTile(
                leading: const Icon(Icons.bar_chart, color: Colors.blueAccent),
                title: const Text('Hisobotlar', style: TextStyle(color: Colors.white)),
                onTap: () {
                  Navigator.pop(context);
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => ReportsScreen(apiService: _api)),
                  );
                },
              ),
              ListTile(
                leading: const Icon(Icons.calculate, color: Colors.purpleAccent),
                title: const Text('Ombor Kalkulyatori', style: TextStyle(color: Colors.white)),
                onTap: () {
                  Navigator.pop(context);
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => CalculatorScreen(apiService: _api)),
                  );
                },
              ),
              ListTile(
                leading: const Icon(Icons.add_shopping_cart, color: Colors.tealAccent),
                title: const Text('Kirim Qilish', style: TextStyle(color: Colors.white)),
                onTap: () {
                  Navigator.pop(context);
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => InwardScreen(apiService: _api)),
                  );
                },
              ),
              ListTile(
                leading: const Icon(Icons.shield_outlined, color: Colors.amberAccent),
                title: const Text('Admin Web Linklar', style: TextStyle(color: Colors.white)),
                onTap: () {
                  Navigator.pop(context);
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const AdminWebScreen()),
                  );
                },
              ),
            ],

            const Divider(color: Color(0xFF334155)),
            ListTile(
              leading: const Icon(Icons.logout, color: Colors.redAccent),
              title: const Text('Tizimdan chiqish', style: TextStyle(color: Colors.redAccent)),
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
        backgroundColor: const Color(0xFF1E293B),
        selectedItemColor: Colors.cyanAccent,
        unselectedItemColor: Colors.blueGrey,
        type: BottomNavigationBarType.fixed,
        items: _navItems,
      ),
    );
  }
}
