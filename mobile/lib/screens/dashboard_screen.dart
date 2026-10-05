import 'package:flutter/material.dart';
import '../models/dashboard_model.dart';
import '../services/api_service.dart';
import '../utils/formatters.dart';

class DashboardScreen extends StatefulWidget {
  final ApiService? apiService;

  const DashboardScreen({super.key, this.apiService});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  late final ApiService _api;
  DashboardData? _dashboardData;
  bool _isLoading = true;
  String? _errorMessage;
  String _selectedPeriod = 'today';

  @override
  void initState() {
    super.initState();
    _api = widget.apiService ?? ApiService();
    _fetchDashboard();
  }

  Future<void> _fetchDashboard() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final data = await _api.getDashboard(period: _selectedPeriod);
      if (!mounted) return;
      setState(() {
        _dashboardData = data;
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _errorMessage = e.toString();
        _isLoading = false;
      });
    }
  }

  Widget _buildPeriodChip(String label, String value) {
    final isSelected = _selectedPeriod == value;
    return ChoiceChip(
      label: Text(label),
      selected: isSelected,
      onSelected: (selected) {
        if (selected && _selectedPeriod != value) {
          setState(() {
            _selectedPeriod = value;
          });
          _fetchDashboard();
        }
      },
      selectedColor: Colors.blueAccent,
      backgroundColor: const Color(0xFF1E293B),
      labelStyle: TextStyle(
        color: isSelected ? Colors.white : Colors.blueGrey,
        fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
        fontSize: 12,
      ),
    );
  }

  Widget _buildMetricCard({
    required String title,
    required String value,
    required IconData icon,
    required Color iconColor,
    String? subtitle,
  }) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFF1E293B),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFF334155)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                title,
                style: const TextStyle(fontSize: 12, color: Colors.blueGrey),
              ),
              Icon(icon, color: iconColor, size: 20),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            value,
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.bold,
              color: Colors.white,
            ),
          ),
          if (subtitle != null) ...[
            const SizedBox(height: 4),
            Text(
              subtitle,
              style: const TextStyle(fontSize: 11, color: Colors.grey),
            ),
          ],
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      body: RefreshIndicator(
        onRefresh: _fetchDashboard,
        child: _isLoading && _dashboardData == null
            ? const Center(child: CircularProgressIndicator())
            : ListView(
                padding: const EdgeInsets.all(16.0),
                children: [
                  // Period Selection
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: [
                        _buildPeriodChip('Bugun', 'today'),
                        const SizedBox(width: 8),
                        _buildPeriodChip('Kecha', 'yesterday'),
                        const SizedBox(width: 8),
                        _buildPeriodChip('Shu hafta', 'this_week'),
                        const SizedBox(width: 8),
                        _buildPeriodChip('Shu oy', 'this_month'),
                        const SizedBox(width: 8),
                        _buildPeriodChip('Barchasi', 'all_time'),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),

                  if (_errorMessage != null) ...[
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.redAccent.withValues(alpha: 0.15),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: Colors.redAccent),
                      ),
                      child: Text(
                        _errorMessage!,
                        style: const TextStyle(color: Colors.redAccent, fontSize: 13),
                      ),
                    ),
                    const SizedBox(height: 16),
                  ],

                  if (_dashboardData != null) ...[
                    // Warnings Banner if low stock
                    if (_dashboardData!.warnings.lowStockCount > 0) ...[
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: Colors.amber.withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: Colors.amber),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.warning_amber_rounded, color: Colors.amber),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                "Kam qoldiq: ${_dashboardData!.warnings.lowStockCount} ta mahsulot minimal chegaradan kam!",
                                style: const TextStyle(color: Colors.amber, fontSize: 13),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 16),
                    ],

                    // Sales Flow Metrics
                    const Text(
                      'Davriy Oqim (Savdo & Kassa)',
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(height: 10),
                    GridView.count(
                      crossAxisCount: 2,
                      crossAxisSpacing: 10,
                      mainAxisSpacing: 10,
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      childAspectRatio: 1.5,
                      children: [
                        _buildMetricCard(
                          title: 'Jami Savdo',
                          value: Formatters.formatMoney(_dashboardData!.flow.totalSales),
                          icon: Icons.point_of_sale,
                          iconColor: Colors.blueAccent,
                          subtitle: '${_dashboardData!.flow.ordersCount} ta chek',
                        ),
                        _buildMetricCard(
                          title: 'Kassaga To\'langan',
                          value: Formatters.formatMoney(_dashboardData!.flow.paidAtPos),
                          icon: Icons.payments_outlined,
                          iconColor: Colors.greenAccent,
                        ),
                        _buildMetricCard(
                          title: 'Yangi Nasiya',
                          value: Formatters.formatMoney(_dashboardData!.flow.newDebt),
                          icon: Icons.credit_card_off,
                          iconColor: Colors.orangeAccent,
                        ),
                        _buildMetricCard(
                          title: 'Undirilgan Qarz',
                          value: Formatters.formatMoney(_dashboardData!.flow.debtCollected),
                          icon: Icons.assignment_turned_in,
                          iconColor: Colors.tealAccent,
                        ),
                        if (_dashboardData!.canViewCost &&
                            _dashboardData!.flow.grossProfit != null) ...[
                          _buildMetricCard(
                            title: 'Yalpi Foyda',
                            value: Formatters.formatMoney(_dashboardData!.flow.grossProfit!),
                            icon: Icons.trending_up,
                            iconColor: Colors.cyanAccent,
                          ),
                          _buildMetricCard(
                            title: 'Sof Operatsion',
                            value: Formatters.formatMoney(_dashboardData!.flow.netOperating ?? 0),
                            icon: Icons.account_balance_wallet,
                            iconColor: Colors.purpleAccent,
                            subtitle: 'Xarajatlar: ${Formatters.formatMoney(_dashboardData!.flow.expenses)}',
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 20),

                    // Current As-of Balances
                    const Text(
                      'Hozirgi Qoldiqlar (As-of Balans)',
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(height: 10),
                    GridView.count(
                      crossAxisCount: 2,
                      crossAxisSpacing: 10,
                      mainAxisSpacing: 10,
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      childAspectRatio: 1.5,
                      children: [
                        _buildMetricCard(
                          title: 'Kassadagi Pul',
                          value: Formatters.formatMoney(_dashboardData!.balances.cashTotal),
                          icon: Icons.account_balance,
                          iconColor: Colors.greenAccent,
                          subtitle: 'Barcha pul bitta kassada',
                        ),
                        _buildMetricCard(
                          title: 'Mijozlar Qarzi',
                          value: Formatters.formatMoney(_dashboardData!.balances.customerDebts),
                          icon: Icons.people_alt_outlined,
                          iconColor: Colors.amberAccent,
                        ),
                        _buildMetricCard(
                          title: 'Ta\'minotchilarga Qarz',
                          value: Formatters.formatMoney(_dashboardData!.balances.supplierPayables),
                          icon: Icons.local_shipping_outlined,
                          iconColor: Colors.redAccent,
                        ),
                        _buildMetricCard(
                          title: 'Ombor Mahsulotlari',
                          value: '${_dashboardData!.balances.stockTotalUnits} dona',
                          icon: Icons.inventory_2_outlined,
                          iconColor: Colors.blueAccent,
                          subtitle: _dashboardData!.canViewCost &&
                                  _dashboardData!.balances.stockCostValue != null
                              ? 'Tannarx: ${Formatters.formatMoney(_dashboardData!.balances.stockCostValue!)}'
                              : null,
                        ),
                      ],
                    ),
                  ],
                ],
              ),
      ),
    );
  }
}
