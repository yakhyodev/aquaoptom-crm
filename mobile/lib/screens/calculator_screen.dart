import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../models/product.dart';
import '../services/api_service.dart';

class CalculatorScreen extends StatefulWidget {
  const CalculatorScreen({super.key});

  @override
  State<CalculatorScreen> createState() => _CalculatorScreenState();
}

class _CalculatorScreenState extends State<CalculatorScreen> {
  List<Product> _products = [];
  final Set<int> _selectedVariantIds = {};
  final _moneyFormat = NumberFormat('#,###', 'uz_UZ');

  @override
  void initState() {
    super.initState();
    _loadProducts();
  }

  Future<void> _loadProducts() async {
    final prods = await ApiService.getProducts();
    setState(() {
      _products = prods;
      // Boshida hammasini tanlab qo'yish
      for (var p in prods) {
        for (var v in p.variants) {
          _selectedVariantIds.add(v.id);
        }
      }
    });
  }

  void _selectAll(bool select) {
    setState(() {
      if (select) {
        for (var p in _products) {
          for (var v in p.variants) {
            _selectedVariantIds.add(v.id);
          }
        }
      } else {
        _selectedVariantIds.clear();
      }
    });
  }

  double get _totalCost {
    double sum = 0;
    for (var p in _products) {
      for (var v in p.variants) {
        if (_selectedVariantIds.contains(v.id)) {
          sum += (v.stockQty * v.costPrice);
        }
      }
    }
    return sum;
  }

  double get _totalRetail {
    double sum = 0;
    for (var p in _products) {
      for (var v in p.variants) {
        if (_selectedVariantIds.contains(v.id)) {
          sum += (v.stockQty * v.retailPrice);
        }
      }
    }
    return sum;
  }

  int get _totalQty {
    int sum = 0;
    for (var p in _products) {
      for (var v in p.variants) {
        if (_selectedVariantIds.contains(v.id)) {
          sum += v.stockQty;
        }
      }
    }
    return sum;
  }

  @override
  Widget build(BuildContext context) {
    final profit = _totalRetail - _totalCost;
    final margin = _totalRetail > 0 ? ((profit / _totalRetail) * 100).round() : 0;

    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      appBar: AppBar(
        title: const Text('Ombor Kalkulyatori', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        backgroundColor: const Color(0xFF1E293B),
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.select_all),
            tooltip: 'Hammasini tanlash',
            onPressed: () => _selectAll(true),
          ),
          IconButton(
            icon: const Icon(Icons.deselect),
            tooltip: 'Tozalash',
            onPressed: () => _selectAll(false),
          ),
        ],
      ),
      body: Column(
        children: [
          // Banner metrics
          Container(
            margin: const EdgeInsets.all(16),
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: const Color(0xFF1E293B),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: Colors.blueAccent.withValues(alpha: 0.3)),
            ),
            child: Column(
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Kirim sarmoyasi:', style: TextStyle(color: Colors.grey, fontSize: 11)),
                        Text('${_moneyFormat.format(_totalCost)} so\'m', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14)),
                      ],
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        const Text('Kutilayotgan sotuv:', style: TextStyle(color: Colors.grey, fontSize: 11)),
                        Text('${_moneyFormat.format(_totalRetail)} so\'m', style: const TextStyle(color: Colors.cyanAccent, fontWeight: FontWeight.bold, fontSize: 14)),
                      ],
                    ),
                  ],
                ),
                const Divider(color: Colors.white10, height: 24),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Kutilayotgan SOF FOYDA:', style: TextStyle(color: Colors.greenAccent, fontSize: 11, fontWeight: FontWeight.bold)),
                        Text('+${_moneyFormat.format(profit)} so\'m', style: const TextStyle(color: Colors.greenAccent, fontWeight: FontWeight.bold, fontSize: 16)),
                      ],
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: Colors.purple.withValues(alpha: 0.2),
                        borderRadius: BorderRadius.circular(8),
                      ),

                      child: Text('Marja: $margin% | $_totalQty dona', style: const TextStyle(color: Colors.purpleAccent, fontSize: 11, fontWeight: FontWeight.bold)),
                    ),
                  ],
                ),
              ],
            ),
          ),

          // List of Products with Litre Checkboxes
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              itemCount: _products.length,
              itemBuilder: (ctx, idx) {
                final prod = _products[idx];
                final allChecked = prod.variants.every((v) => _selectedVariantIds.contains(v.id));

                return Card(
                  color: const Color(0xFF1E293B),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  margin: const EdgeInsets.only(bottom: 12),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Checkbox(
                              value: allChecked,
                              activeColor: Colors.blueAccent,
                              onChanged: (val) {
                                setState(() {
                                  if (val ?? false) {
                                    for (var v in prod.variants) {
                                      _selectedVariantIds.add(v.id);
                                    }
                                  } else {
                                    for (var v in prod.variants) {
                                      _selectedVariantIds.remove(v.id);
                                    }
                                  }
                                });
                              },
                            ),
                            Text(prod.name, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15)),
                          ],
                        ),
                        const SizedBox(height: 4),

                        // Litrlar bo'yicha checkboxlar
                        Column(
                          children: prod.variants.map((v) {
                            final isChecked = _selectedVariantIds.contains(v.id);
                            final vTotal = v.stockQty * v.retailPrice;
                            return Container(
                              margin: const EdgeInsets.only(bottom: 6),
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                              decoration: BoxDecoration(
                                color: isChecked ? Colors.blueAccent.withValues(alpha: 0.1) : Colors.transparent,
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(color: isChecked ? Colors.blueAccent.withValues(alpha: 0.4) : Colors.white10),
                              ),

                              child: Row(
                                children: [
                                  Checkbox(
                                    value: isChecked,
                                    activeColor: Colors.blueAccent,
                                    onChanged: (val) {
                                      setState(() {
                                        if (val ?? false) {
                                          _selectedVariantIds.add(v.id);
                                        } else {
                                          _selectedVariantIds.remove(v.id);
                                        }
                                      });
                                    },
                                  ),
                                  Text('${v.litres}L', style: const TextStyle(color: Colors.cyanAccent, fontWeight: FontWeight.bold, fontSize: 13)),
                                  const SizedBox(width: 8),
                                  Text('(${v.stockQty} dona)', style: const TextStyle(color: Colors.grey, fontSize: 11)),
                                  const Spacer(),
                                  Text('${_moneyFormat.format(vTotal)} so\'m', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12)),
                                ],
                              ),
                            );
                          }).toList(),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
