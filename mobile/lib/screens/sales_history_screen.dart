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
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _api = widget.apiService ?? ApiService();
    _loadSales();
  }

  Future<void> _loadSales() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final list = await _api.getSalesHistory();
      if (!mounted) return;
      setState(() {
        _sales = list;
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
      backgroundColor: const Color(0xFF0F172A),
      appBar: AppBar(
        title: const Text('Savdo Tarixi (So\'nggi Cheklar)'),
        backgroundColor: const Color(0xFF1E293B),
      ),
      body: RefreshIndicator(
        onRefresh: _loadSales,
        child: Column(
          children: [
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
                      ? const Center(
                          child: Text(
                            'Hozircha savdolar mavjud emas',
                            style: TextStyle(color: Colors.blueGrey),
                          ),
                        )
                      : ListView.builder(
                          padding: const EdgeInsets.all(12),
                          itemCount: _sales.length,
                          itemBuilder: (ctx, idx) {
                      final sale = _sales[idx];
                      final hasDebt = sale.debtAmount > 0;

                      return Card(
                        color: const Color(0xFF1E293B),
                        margin: const EdgeInsets.only(bottom: 10),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                          side: const BorderSide(color: Color(0xFF334155)),
                        ),
                        child: ExpansionTile(
                          leading: Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: Colors.blueAccent.withValues(alpha: 0.15),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: const Icon(Icons.receipt_long,
                                color: Colors.cyanAccent),
                          ),
                          title: Row(
                            children: [
                              Text(
                                '#${sale.invoiceNumber}',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 14,
                                ),
                              ),
                              const SizedBox(width: 8),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 6, vertical: 2),
                                decoration: BoxDecoration(
                                  color: hasDebt
                                      ? Colors.redAccent.withValues(alpha: 0.2)
                                      : Colors.greenAccent
                                          .withValues(alpha: 0.2),
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
                                style: const TextStyle(
                                    color: Colors.blueGrey, fontSize: 11),
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
                                  horizontal: 16, vertical: 8),
                              color: const Color(0xFF0F172A),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text(
                                    'Sotilgan tovarlar:',
                                    style: TextStyle(
                                        color: Colors.blueGrey,
                                        fontSize: 11,
                                        fontWeight: FontWeight.bold),
                                  ),
                                  const SizedBox(height: 6),
                                  ...sale.items.map((it) => Padding(
                                        padding: const EdgeInsets.symmetric(
                                            vertical: 2.0),
                                        child: Row(
                                          mainAxisAlignment:
                                              MainAxisAlignment.spaceBetween,
                                          children: [
                                            Text(
                                              '${it.productName} (${it.volumeName}) x ${it.quantity}',
                                              style: const TextStyle(
                                                  color: Colors.white,
                                                  fontSize: 12),
                                            ),
                                            Text(
                                              Formatters.formatMoney(
                                                  it.totalPrice),
                                              style: const TextStyle(
                                                  color: Colors.white,
                                                  fontSize: 12,
                                                  fontWeight: FontWeight.w600),
                                            ),
                                          ],
                                        ),
                                      )),
                                ],
                              ),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
            ),
          ],
        ),
      ),
    );
  }
}
