import 'package:flutter/material.dart';
import '../models/product_model.dart';
import '../services/api_service.dart';
import '../services/session_service.dart';
import '../utils/formatters.dart';

class CalculatorScreen extends StatefulWidget {
  final ApiService? apiService;

  const CalculatorScreen({super.key, this.apiService});

  @override
  State<CalculatorScreen> createState() => _CalculatorScreenState();
}

class _CalculatorScreenState extends State<CalculatorScreen> {
  late final ApiService _api;
  List<Product> _products = [];
  final Set<int> _selectedVariantIds = {};
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _api = widget.apiService ?? ApiService();
    _loadProducts();
  }

  Future<void> _loadProducts() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final list = await _api.getProducts();
      if (!mounted) return;
      setState(() {
        _products = list;
        for (final p in list) {
          for (final v in p.variants) {
            _selectedVariantIds.add(v.id);
          }
        }
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

  void _selectAll(bool select) {
    setState(() {
      if (select) {
        for (final p in _products) {
          for (final v in p.variants) {
            _selectedVariantIds.add(v.id);
          }
        }
      } else {
        _selectedVariantIds.clear();
      }
    });
  }

  // Exact integer money totals
  int get _totalCost {
    int sum = 0;
    for (final p in _products) {
      for (final v in p.variants) {
        if (_selectedVariantIds.contains(v.id) && v.costPrice != null) {
          sum += (v.stockQty * v.costPrice!);
        }
      }
    }
    return sum;
  }

  int get _totalRetail {
    int sum = 0;
    for (final p in _products) {
      for (final v in p.variants) {
        if (_selectedVariantIds.contains(v.id)) {
          sum += (v.stockQty * v.defaultSalePrice);
        }
      }
    }
    return sum;
  }

  int get _totalQty {
    int sum = 0;
    for (final p in _products) {
      for (final v in p.variants) {
        if (_selectedVariantIds.contains(v.id)) {
          sum += v.stockQty;
        }
      }
    }
    return sum;
  }

  int get _expectedProfit {
    return _totalRetail - _totalCost;
  }

  @override
  Widget build(BuildContext context) {
    final user = SessionService().currentUser;
    final canViewCost = user?.canViewCost ?? false;

    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      appBar: AppBar(
        title: const Text('Ombor Rentabellik Kalkulyatori'),
        backgroundColor: const Color(0xFF1E293B),
        actions: [
          IconButton(
            icon: const Icon(Icons.select_all, color: Colors.cyanAccent),
            tooltip: 'Barchasini tanlash',
            onPressed: () => _selectAll(true),
          ),
          IconButton(
            icon: const Icon(Icons.deselect, color: Colors.blueGrey),
            tooltip: 'Tozalash',
            onPressed: () => _selectAll(false),
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                if (_errorMessage != null)
                  Padding(
                    padding: const EdgeInsets.all(12),
                    child: Text(_errorMessage!,
                        style: const TextStyle(color: Colors.redAccent)),
                  ),

                // Calculation Summary Cards
                Container(
                  padding: const EdgeInsets.all(16),
                  color: const Color(0xFF1E293B),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          _buildSummaryItem(
                            'Tanlangan Qoldiq',
                            '$_totalQty dona',
                            Colors.blueAccent,
                          ),
                          _buildSummaryItem(
                            'Sotuv Qiymati',
                            Formatters.formatMoney(_totalRetail),
                            Colors.greenAccent,
                          ),
                        ],
                      ),
                      if (canViewCost) ...[
                        const Divider(color: Color(0xFF334155), height: 16),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            _buildSummaryItem(
                              'Jami Tannarx',
                              Formatters.formatMoney(_totalCost),
                              Colors.amberAccent,
                            ),
                            _buildSummaryItem(
                              'Kutilayotgan Yalpi Foyda',
                              Formatters.formatMoney(_expectedProfit),
                              Colors.cyanAccent,
                            ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),

                // Product Variants Checkbox List
                Expanded(
                  child: ListView.builder(
                    padding: const EdgeInsets.all(12),
                    itemCount: _products.length,
                    itemBuilder: (ctx, idx) {
                      final prod = _products[idx];
                      return Card(
                        color: const Color(0xFF1E293B),
                        margin: const EdgeInsets.only(bottom: 10),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                          side: const BorderSide(color: Color(0xFF334155)),
                        ),
                        child: ExpansionTile(
                          initiallyExpanded: true,
                          title: Text(
                            prod.name,
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          subtitle: Text(
                            '${prod.variants.length} ta hajm',
                            style: const TextStyle(
                                color: Colors.blueGrey, fontSize: 12),
                          ),
                          children: prod.variants.map((v) {
                            final isChecked =
                                _selectedVariantIds.contains(v.id);
                            return CheckboxListTile(
                              value: isChecked,
                              onChanged: (val) {
                                setState(() {
                                  if (val == true) {
                                    _selectedVariantIds.add(v.id);
                                  } else {
                                    _selectedVariantIds.remove(v.id);
                                  }
                                });
                              },
                              activeColor: Colors.blueAccent,
                              title: Text(
                                '${v.displayVolume} — ${v.stockQty} dona',
                                style: const TextStyle(color: Colors.white),
                              ),
                              subtitle: Text(
                                'Sotuv: ${Formatters.formatMoney(v.defaultSalePrice)} ${canViewCost && v.costPrice != null ? "| Tannarx: ${Formatters.formatMoney(v.costPrice!)}" : ""}',
                                style: const TextStyle(
                                    color: Colors.blueGrey, fontSize: 12),
                              ),
                            );
                          }).toList(),
                        ),
                      );
                    },
                  ),
                ),
              ],
            ),
    );
  }

  Widget _buildSummaryItem(String label, String value, Color color) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(color: Colors.blueGrey, fontSize: 11)),
        const SizedBox(height: 2),
        Text(
          value,
          style: TextStyle(
            color: color,
            fontWeight: FontWeight.bold,
            fontSize: 14,
          ),
        ),
      ],
    );
  }
}
