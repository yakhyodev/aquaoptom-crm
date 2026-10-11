import 'package:flutter/material.dart';
import '../models/report_model.dart';
import '../services/api_service.dart';
import '../utils/formatters.dart';
import '../services/session_service.dart';
import '../utils/search_picker.dart';

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
  bool _isExporting = false;

  Future<void> _downloadExcel() async {
    const reports = {
      'sales': 'Savdolar',
      'profit_loss': 'Foyda va xarajatlar',
      'purchases': 'Mahsulot kirimi',
      'inventory': 'Ombor qiymati',
      'statements': 'Mijozlar hisobi',
      'cash': 'Kassa',
      'staff': 'Xodimlar',
      'sync': 'Qurilmalar',
    };
    final types = reports.keys
        .where((type) => type != 'cash' || (_reportsData?.canViewCash ?? false))
        .toList();
    final type = await chooseFromList(
      context,
      title: 'Qaysi hisobot kerak?',
      items: types,
      labelFor: (type) => reports[type]!,
    );
    if (type == null || !mounted || _isExporting) return;
    setState(() => _isExporting = true);
    try {
      await _api.downloadReport(type: type, period: _selectedPeriod);
      if (mounted)
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Excel yuklanmoqda. Telefonning Yuklamalar bo‘limidan oching.',
            ),
          ),
        );
    } catch (e) {
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('$e')));
    } finally {
      if (mounted) setState(() => _isExporting = false);
    }
  }

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
      backgroundColor: Theme.of(context).colorScheme.surface,
      labelStyle: TextStyle(
        color: isSelected
            ? Theme.of(context).colorScheme.onSurface
            : Theme.of(context).colorScheme.onSurfaceVariant,
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
          Text(
            label,
            style: TextStyle(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
              fontSize: 13,
            ),
          ),
          Text(
            value,
            style: TextStyle(
              color: valueColor ?? Theme.of(context).colorScheme.onSurface,
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
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        actions: [
          if (SessionService().currentUser?.hasPermission('export_reports') ??
              false)
            IconButton(
              onPressed: _isExporting ? null : _downloadExcel,
              tooltip: 'Excel yuklash',
              icon: const Icon(Icons.download),
            ),
        ],
        title: const Text('Hisobotlar (Savdo & Kassa)'),
        backgroundColor: Theme.of(context).colorScheme.surface,
      ),
      body: RefreshIndicator(
        onRefresh: _loadReports,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // Period Selector
            Wrap(
              spacing: 8,
              runSpacing: 8,
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
                child: Text(
                  _errorMessage!,
                  style: const TextStyle(color: Colors.redAccent),
                ),
              ),
              const SizedBox(height: 16),
            ],

            if (_isLoading && _reportsData == null)
              const Center(child: CircularProgressIndicator())
            else if (_reportsData != null) ...[
              // Sales Summary Card
              Card(
                color: Theme.of(context).colorScheme.surface,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                  side: BorderSide(
                    color: Theme.of(context).colorScheme.outlineVariant,
                  ),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Icon(Icons.bar_chart, color: Colors.blueAccent),
                          SizedBox(width: 8),
                          Text(
                            'Savdo Xulosasi',
                            style: TextStyle(
                              color: Theme.of(context).colorScheme.onSurface,
                              fontWeight: FontWeight.bold,
                              fontSize: 16,
                            ),
                          ),
                        ],
                      ),
                      Divider(
                        color: Theme.of(context).colorScheme.outlineVariant,
                        height: 20,
                      ),
                      _buildReportRow(
                        'Jami Sotuv:',
                        Formatters.formatMoney(
                          _reportsData!.sales.totalGrossSales,
                        ),
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
                          _reportsData!.sales.totalInitialPaid,
                        ),
                      ),
                      _buildReportRow(
                        'Nasiyaga berilgan:',
                        Formatters.formatMoney(
                          _reportsData!.sales.totalInitialDebt,
                        ),
                        valueColor: Colors.amberAccent,
                      ),
                      _buildReportRow(
                        'Sotuvda to‘langan pul:',
                        Formatters.formatMoney(
                          _reportsData!.sales.cashAtPos +
                              _reportsData!.sales.cardAtPos +
                              _reportsData!.sales.bankAtPos,
                        ),
                      ),
                      if (_reportsData!.sales.totalReturnsAmount > 0)
                        _buildReportRow(
                          'Qaytarishlar (Return):',
                          Formatters.formatMoney(
                            _reportsData!.sales.totalReturnsAmount,
                          ),
                          valueColor: Colors.redAccent,
                        ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // Cash Summary Card
              if (_reportsData!.canViewCash)
                Card(
                  color: Theme.of(context).colorScheme.surface,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                    side: BorderSide(
                      color: Theme.of(context).colorScheme.outlineVariant,
                    ),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(
                              Icons.account_balance_wallet,
                              color: Theme.of(context).colorScheme.primary,
                            ),
                            SizedBox(width: 8),
                            Text(
                              'Kassa Harakati',
                              style: TextStyle(
                                color: Theme.of(context).colorScheme.onSurface,
                                fontWeight: FontWeight.bold,
                                fontSize: 16,
                              ),
                            ),
                          ],
                        ),
                        Divider(
                          color: Theme.of(context).colorScheme.outlineVariant,
                          height: 20,
                        ),
                        _buildReportRow(
                          'Boshlang\'ich qoldiq:',
                          Formatters.formatMoney(
                            _reportsData!.cash.initialBalance,
                          ),
                        ),
                        _buildReportRow(
                          'Jami Kirim:',
                          Formatters.formatMoney(
                            _reportsData!.cash.totalInflow,
                          ),
                          valueColor: Colors.greenAccent,
                        ),
                        _buildReportRow(
                          'Jami Chiqim:',
                          Formatters.formatMoney(
                            _reportsData!.cash.totalOutflow,
                          ),
                          valueColor: Colors.redAccent,
                        ),
                        _buildReportRow(
                          'Yakuniy qoldiq:',
                          Formatters.formatMoney(
                            _reportsData!.cash.closingBalance,
                          ),
                          valueColor: Theme.of(context).colorScheme.primary,
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
