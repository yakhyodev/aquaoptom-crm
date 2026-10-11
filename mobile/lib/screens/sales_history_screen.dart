import 'package:flutter/material.dart';
import '../models/sale_model.dart';
import '../services/api_service.dart';
import '../utils/formatters.dart';

class SalesHistoryScreen extends StatefulWidget {
  final ApiService? apiService;

  const SalesHistoryScreen({super.key, this.apiService});

  @override
  State<SalesHistoryScreen> createState() => _SalesHistoryScreenState();
}

class _SalesHistoryScreenState extends State<SalesHistoryScreen> {
  late final ApiService _api;
  List<SaleRecord> _sales = [];
  bool _isLoading = true;
  final _search = TextEditingController();
  int _page = 1;
  bool _hasMore = true;
  DateTimeRange? _dates;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _api = widget.apiService ?? ApiService();
    _loadSales();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _loadSales({bool more = false}) async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final page = more ? _page + 1 : 1;
      final list = await _api.getSalesHistory(
        page: page,
        search: _search.text.trim(),
        fromDate: _dates?.start.toIso8601String().substring(0, 10),
        toDate: _dates?.end.toIso8601String().substring(0, 10),
      );
      if (!mounted) return;
      setState(() {
        _sales = more ? [..._sales, ...list] : list;
        _page = page;
        _hasMore = list.length == 50;
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: const Text('Savdolar tarixi'),
        backgroundColor: Theme.of(context).colorScheme.surface,
      ),
      body: RefreshIndicator(
        onRefresh: _loadSales,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(12),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _search,
                      onSubmitted: (_) => _loadSales(),
                      decoration: const InputDecoration(
                        hintText: 'Mijoz, telefon yoki chekni qidiring',
                        prefixIcon: Icon(Icons.search),
                        border: OutlineInputBorder(),
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: 'Sanani tanlash',
                    icon: const Icon(Icons.date_range),
                    onPressed: () async {
                      final dates = await showDateRangePicker(
                        context: context,
                        firstDate: DateTime(2020),
                        lastDate: DateTime.now(),
                        initialDateRange: _dates,
                      );
                      if (dates != null && mounted) {
                        setState(() => _dates = dates);
                        await _loadSales();
                      }
                    },
                  ),
                ],
              ),
            ),
            if (_dates != null)
              TextButton(
                onPressed: () {
                  setState(() => _dates = null);
                  _loadSales();
                },
                child: Text(
                  '${_dates!.start.toIso8601String().substring(0, 10)} — ${_dates!.end.toIso8601String().substring(0, 10)} · Tozalash',
                ),
              ),
            if (_errorMessage != null)
              Container(
                margin: const EdgeInsets.all(12),
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
            Expanded(
              child: _isLoading
                  ? const Center(child: CircularProgressIndicator())
                  : _sales.isEmpty
                  ? Center(
                      child: Text(
                        'Hozircha savdolar mavjud emas',
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.onSurfaceVariant,
                        ),
                      ),
                    )
                  : ListView.builder(
                      padding: const EdgeInsets.all(12),
                      itemCount: _sales.length,
                      itemBuilder: (ctx, idx) {
                        final sale = _sales[idx];
                        final hasDebt = sale.debtAmount > 0;

                        return Card(
                          color: Theme.of(context).colorScheme.surface,
                          margin: const EdgeInsets.only(bottom: 10),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                            side: BorderSide(
                              color: Theme.of(
                                context,
                              ).colorScheme.outlineVariant,
                            ),
                          ),
                          child: ExpansionTile(
                            leading: Container(
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: Colors.blueAccent.withValues(
                                  alpha: 0.15,
                                ),
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: Icon(
                                Icons.receipt_long,
                                color: Theme.of(context).colorScheme.primary,
                              ),
                            ),
                            title: Row(
                              children: [
                                Text(
                                  '#${sale.invoiceNumber}',
                                  style: TextStyle(
                                    color: Theme.of(
                                      context,
                                    ).colorScheme.onSurface,
                                    fontWeight: FontWeight.bold,
                                    fontSize: 14,
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 6,
                                    vertical: 2,
                                  ),
                                  decoration: BoxDecoration(
                                    color: hasDebt
                                        ? Colors.redAccent.withValues(
                                            alpha: 0.2,
                                          )
                                        : Colors.greenAccent.withValues(
                                            alpha: 0.2,
                                          ),
                                    borderRadius: BorderRadius.circular(4),
                                  ),
                                  child: Text(
                                    sale.paymentType,
                                    style: TextStyle(
                                      color: hasDebt
                                          ? Colors.redAccent
                                          : Colors.greenAccent,
                                      fontSize: 10,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            subtitle: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '${sale.customerName} • ${Formatters.formatDateTime(sale.createdAt)}',
                                  style: TextStyle(
                                    color: Theme.of(
                                      context,
                                    ).colorScheme.onSurfaceVariant,
                                    fontSize: 11,
                                  ),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  'Jami: ${Formatters.formatMoney(sale.totalAmount)} ${hasDebt ? "| Nasiya: ${Formatters.formatMoney(sale.debtAmount)}" : ""}',
                                  style: TextStyle(
                                    color: hasDebt
                                        ? Colors.amberAccent
                                        : Colors.greenAccent,
                                    fontWeight: FontWeight.w600,
                                    fontSize: 12,
                                  ),
                                ),
                              ],
                            ),
                            children: [
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 16,
                                  vertical: 8,
                                ),
                                color: Theme.of(
                                  context,
                                ).scaffoldBackgroundColor,
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'Sotilgan tovarlar:',
                                      style: TextStyle(
                                        color: Theme.of(
                                          context,
                                        ).colorScheme.onSurfaceVariant,
                                        fontSize: 11,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                    const SizedBox(height: 6),
                                    ...sale.items.map(
                                      (it) => Padding(
                                        padding: const EdgeInsets.symmetric(
                                          vertical: 2.0,
                                        ),
                                        child: Row(
                                          mainAxisAlignment:
                                              MainAxisAlignment.spaceBetween,
                                          children: [
                                            Text(
                                              '${it.productName} (${it.volumeName}) x ${it.quantity}',
                                              style: TextStyle(
                                                color: Theme.of(
                                                  context,
                                                ).colorScheme.onSurface,
                                                fontSize: 12,
                                              ),
                                            ),
                                            Text(
                                              Formatters.formatMoney(
                                                it.totalPrice,
                                              ),
                                              style: TextStyle(
                                                color: Theme.of(
                                                  context,
                                                ).colorScheme.onSurface,
                                                fontSize: 12,
                                                fontWeight: FontWeight.w600,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        );
                      },
                    ),
            ),
            if (_hasMore && _sales.isNotEmpty)
              TextButton(
                onPressed: _isLoading ? null : () => _loadSales(more: true),
                child: Text(
                  _isLoading ? 'Yuklanmoqda…' : 'Yana savdolarni ko‘rsatish',
                ),
              ),
          ],
        ),
      ),
    );
  }
}
