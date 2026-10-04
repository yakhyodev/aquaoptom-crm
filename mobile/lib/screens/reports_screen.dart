import 'package:flutter/material.dart';
import '../models/report_model.dart';
import '../services/api_service.dart';
import '../utils/formatters.dart';

class ReportsScreen extends StatefulWidget {
  final ApiService? apiService;

  const ReportsScreen({super.key, this.apiService});

  @override
  State<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends State<ReportsScreen> {
  late final ApiService _api;
  ReportsData? _reportsData;
  bool _isLoading = true;
  String? _errorMessage;
  String _selectedPeriod = 'today';

  @override
  void initState() {
    super.initState();
    _api = widget.apiService ?? ApiService();
    _loadReports();
  }

  Future<void> _loadReports() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final data = await _api.getReports(period: _selectedPeriod);
      if (!mounted) return;
      setState(() {
        _reportsData = data;
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
          setState(() => _selectedPeriod = value);
          _loadReports();
        }
      },
      selectedColor: Colors.blueAccent,
      backgroundColor: const Color(0xFF1E293B),
      labelStyle: TextStyle(
        color: isSelected ? Colors.white : Colors.blueGrey,
        fontSize: 12,
      ),
    );
  }

  Widget _buildReportRow(String label, String value, {Color? valueColor}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4.0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label,
              style: const TextStyle(color: Colors.blueGrey, fontSize: 13)),
          Text(
            value,
            style: TextStyle(
              color: valueColor ?? Colors.white,
              fontWeight: FontWeight.bold,
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      appBar: AppBar(
        title: const Text('Hisobotlar (Savdo & Kassa)'),
        backgroundColor: const Color(0xFF1E293B),
      ),
      body: RefreshIndicator(
        onRefresh: _loadReports,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // Period Selector
            Row(
              children: [
                _buildPeriodChip('Bugun', 'today'),
                const SizedBox(width: 8),
                _buildPeriodChip('Kecha', 'yesterday'),
                const SizedBox(width: 8),
                _buildPeriodChip('Shu hafta', 'this_week'),
                const SizedBox(width: 8),
                _buildPeriodChip('Shu oy', 'this_month'),
              ],
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
                child: Text(_errorMessage!,
                    style: const TextStyle(color: Colors.redAccent)),
              ),
              const SizedBox(height: 16),
            ],

            if (_isLoading && _reportsData == null)
              const Center(child: CircularProgressIndicator())
            else if (_reportsData != null) ...[
              // Sales Summary Card
              Card(
                color: const Color(0xFF1E293B),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                  side: const BorderSide(color: Color(0xFF334155)),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Row(
                        children: [
                          Icon(Icons.bar_chart, color: Colors.blueAccent),
                          SizedBox(width: 8),
                          Text(
                            'Savdo Xulosasi',
                            style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                              fontSize: 16,
                            ),
                          ),
                        ],
                      ),
                      const Divider(color: Color(0xFF334155), height: 20),
                      _buildReportRow(
                        'Jami Sotuv:',
                        Formatters.formatMoney(
                            _reportsData!.sales.totalGrossSales),
                        valueColor: Colors.greenAccent,
                      ),
                      _buildReportRow(
                        'Buyurtmalar soni:',
                        '${_reportsData!.sales.totalOrdersCount} ta chek',
                      ),
                      _buildReportRow(
                        'Sotilgan tovarlar:',
                        '${_reportsData!.sales.totalUnitsSold} dona',
                      ),
                      _buildReportRow(
                        'Kassaga tushgan:',
                        Formatters.formatMoney(
                            _reportsData!.sales.totalInitialPaid),
                      ),
                      _buildReportRow(
                        'Nasiyaga berilgan:',
                        Formatters.formatMoney(
                            _reportsData!.sales.totalInitialDebt),
                        valueColor: Colors.amberAccent,
                      ),
                      _buildReportRow(
                        'Naqd:',
                        Formatters.formatMoney(_reportsData!.sales.cashAtPos),
                      ),
                      _buildReportRow(
                        'Karta:',
                        Formatters.formatMoney(_reportsData!.sales.cardAtPos),
                      ),
                      _buildReportRow(
                        'Bank:',
                        Formatters.formatMoney(_reportsData!.sales.bankAtPos),
                      ),
                      if (_reportsData!.sales.totalReturnsAmount > 0)
                        _buildReportRow(
                          'Qaytarishlar (Return):',
                          Formatters.formatMoney(
                              _reportsData!.sales.totalReturnsAmount),
                          valueColor: Colors.redAccent,
                        ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // Cash Summary Card
              Card(
                color: const Color(0xFF1E293B),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                  side: const BorderSide(color: Color(0xFF334155)),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Row(
                        children: [
                          Icon(Icons.account_balance_wallet,
                              color: Colors.cyanAccent),
                          SizedBox(width: 8),
                          Text(
                            'Kassa Harakati',
                            style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                              fontSize: 16,
                            ),
                          ),
                        ],
                      ),
                      const Divider(color: Color(0xFF334155), height: 20),
                      _buildReportRow(
                        'Boshlang\'ich qoldiq:',
                        Formatters.formatMoney(
                            _reportsData!.cash.initialBalance),
                      ),
                      _buildReportRow(
                        'Jami Kirim:',
                        Formatters.formatMoney(_reportsData!.cash.totalInflow),
                        valueColor: Colors.greenAccent,
                      ),
                      _buildReportRow(
                        'Jami Chiqim:',
                        Formatters.formatMoney(_reportsData!.cash.totalOutflow),
                        valueColor: Colors.redAccent,
                      ),
                      _buildReportRow(
                        'Yakuniy qoldiq:',
                        Formatters.formatMoney(
                            _reportsData!.cash.closingBalance),
                        valueColor: Colors.cyanAccent,
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
}
