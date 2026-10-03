import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../models/product.dart';
import '../models/sale.dart';
import '../services/api_service.dart';

class PosScreen extends StatefulWidget {
  const PosScreen({super.key});

  @override
  State<PosScreen> createState() => _PosScreenState();
}

class _PosScreenState extends State<PosScreen> {
  final _customerController = TextEditingController(text: 'Farhod aka (Chorsu)');
  List<Product> _products = [];
  final List<CartItem> _cart = [];
  bool _isLoading = false;
  String _paymentType = 'cash'; // 'cash', 'card', 'debt'
  final _moneyFormat = NumberFormat('#,###', 'uz_UZ');

  @override
  void initState() {
    super.initState();
    _loadProducts();
  }

  Future<void> _loadProducts() async {
    final prods = await ApiService.getProducts();
    setState(() => _products = prods);
  }

  void _addToCart(Product product, ProductVariant variant) {
    if (variant.stockQty <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Omborda ushbu tovar qolmagan!')),
      );
      return;
    }

    final index = _cart.indexWhere((item) => item.variantId == variant.id);
    if (index != -1) {
      setState(() {
        if (_cart[index].quantity + 10 <= variant.stockQty) {
          _cart[index].quantity += 10;
        } else {
          _cart[index].quantity = variant.stockQty;
        }
      });
    } else {
      setState(() {
        _cart.add(
          CartItem(
            variantId: variant.id,
            productName: product.name,
            litres: variant.litres,
            quantity: 10,
            costPrice: variant.costPrice,
            retailPrice: variant.retailPrice,
            defaultRetail: variant.retailPrice,
            isSystemPrice: true,
          ),
        );
      });
    }
  }

  double get _totalRetail => _cart.fold(0, (sum, i) => sum + i.totalPrice);
  double get _totalProfit => _cart.fold(0, (sum, i) => sum + i.profit);

  Future<void> _checkout() async {
    if (_cart.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Savat bo\'sh! Mahsulot tanlang.')),
      );
      return;
    }

    setState(() => _isLoading = true);
    final success = await ApiService.checkoutSale(
      customerName: _customerController.text.trim(),
      items: _cart,
      paymentType: _paymentType,
    );
    setState(() => _isLoading = false);

    if (success && mounted) {
      showDialog(
        context: context,
        builder: (ctx) => AlertDialog(
          backgroundColor: const Color(0xFF1E293B),
          title: const Text('✓ Savdo Yakunlandi', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Jami summa: ${_moneyFormat.format(_totalRetail)} so\'m', style: const TextStyle(color: Colors.cyanAccent, fontWeight: FontWeight.bold)),
              const SizedBox(height: 6),
              Text('Ushbu chekdan SOF FOYDA: +${_moneyFormat.format(_totalProfit)} so\'m', style: const TextStyle(color: Colors.greenAccent, fontWeight: FontWeight.bold)),
              const SizedBox(height: 6),
              const Text('Telegram kanalga xabarnoma yuborildi!', style: TextStyle(color: Colors.grey, fontSize: 12)),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(ctx);
                setState(() => _cart.clear());
                _loadProducts();
              },
              child: const Text('OK', style: TextStyle(color: Colors.blueAccent)),
            ),
          ],
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      appBar: AppBar(
        title: const Text('Optom Savdo (POS)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        backgroundColor: const Color(0xFF1E293B),
        elevation: 0,
      ),
      body: Column(
        children: [
          // Xaridor ismi va To'lov turi
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
            child: Column(
              children: [
                TextField(
                  controller: _customerController,
                  style: const TextStyle(color: Colors.white, fontSize: 13),
                  decoration: InputDecoration(
                    prefixIcon: const Icon(Icons.person, color: Colors.blueAccent, size: 20),
                    hintText: 'Xaridor nomi...',
                    filled: true,
                    fillColor: const Color(0xFF1E293B),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                    contentPadding: const EdgeInsets.symmetric(vertical: 0, horizontal: 12),
                  ),
                ),
                const SizedBox(height: 6),
                Row(
                  children: [
                    const Text("To'lov: ", style: TextStyle(color: Colors.grey, fontSize: 11)),
                    const SizedBox(width: 4),
                    ChoiceChip(
                      label: const Text('Naqd', style: TextStyle(fontSize: 11)),
                      selected: _paymentType == 'cash',
                      selectedColor: Colors.blueAccent,
                      onSelected: (_) => setState(() => _paymentType = 'cash'),
                    ),
                    const SizedBox(width: 4),
                    ChoiceChip(
                      label: const Text('Karta', style: TextStyle(fontSize: 11)),
                      selected: _paymentType == 'card',
                      selectedColor: Colors.purpleAccent,
                      onSelected: (_) => setState(() => _paymentType = 'card'),
                    ),
                    const SizedBox(width: 4),
                    ChoiceChip(
                      label: const Text('Qarz', style: TextStyle(fontSize: 11)),
                      selected: _paymentType == 'debt',
                      selectedColor: Colors.amberAccent,
                      onSelected: (_) => setState(() => _paymentType = 'debt'),
                    ),
                  ],
                ),
              ],
            ),
          ),


          // Mahsulotlar ro'yxati (Yuqori qism)
          Expanded(
            flex: 4,
            child: ListView.builder(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              itemCount: _products.length,
              itemBuilder: (ctx, idx) {
                final p = _products[idx];
                return Card(
                  color: const Color(0xFF1E293B),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  margin: const EdgeInsets.only(bottom: 8),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(p.name, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14)),
                        const SizedBox(height: 8),
                        Wrap(
                          spacing: 6,
                          runSpacing: 6,
                          children: p.variants.map((v) {
                            return InkWell(
                              onTap: () => _addToCart(p, v),
                              borderRadius: BorderRadius.circular(8),
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                decoration: BoxDecoration(
                                  color: const Color(0xFF0F172A),
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(color: Colors.blueGrey[800]!),
                                ),
                                child: Column(
                                  children: [
                                    Text('${v.litres}L', style: const TextStyle(color: Colors.cyanAccent, fontWeight: FontWeight.bold, fontSize: 12)),
                                    Text('${_moneyFormat.format(v.retailPrice)} so\'m', style: const TextStyle(color: Colors.white, fontSize: 10)),
                                    Text('Qoldiq: ${v.stockQty}', style: const TextStyle(color: Colors.grey, fontSize: 9)),
                                  ],
                                ),
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

          // Savat (Pastki qism)
          Container(
            padding: const EdgeInsets.all(16),
            decoration: const BoxDecoration(
              color: Color(0xFF1E293B),
              borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
              boxShadow: [BoxShadow(color: Colors.black45, blurRadius: 10, offset: Offset(0, -2))],
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Savat (Chek)', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
                    if (_cart.isNotEmpty)
                      GestureDetector(
                        onTap: () => setState(() => _cart.clear()),
                        child: const Text('Tozalash', style: TextStyle(color: Colors.redAccent, fontSize: 11)),
                      ),
                  ],
                ),
                const SizedBox(height: 8),

                // Savat ro'yxati
                ConstrainedBox(
                  constraints: const BoxConstraints(maxHeight: 180),
                  child: _cart.isEmpty
                      ? const Center(
                          child: Padding(
                            padding: EdgeInsets.all(16),
                            child: Text('Savat bo\'sh. Tovar tanlang.', style: TextStyle(color: Colors.grey, fontSize: 12)),
                          ),
                        )
                      : ListView.separated(
                          shrinkWrap: true,
                          itemCount: _cart.length,
                          separatorBuilder: (_, _) => const Divider(color: Colors.white10),
                          itemBuilder: (ctx, i) {
                            final item = _cart[i];
                            return Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    Text('${item.productName} (${item.litres}L)', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12)),
                                    IconButton(
                                      icon: const Icon(Icons.close, color: Colors.grey, size: 16),
                                      onPressed: () => setState(() => _cart.removeAt(i)),
                                    ),
                                  ],
                                ),
                                Row(
                                  children: [
                                    // Soni
                                    SizedBox(
                                      width: 70,
                                      height: 35,
                                      child: TextField(
                                        keyboardType: TextInputType.number,
                                        style: const TextStyle(color: Colors.white, fontSize: 12),
                                        controller: TextEditingController(text: item.quantity.toString()),
                                        onChanged: (val) {
                                          final q = int.tryParse(val) ?? 0;
                                          setState(() => item.quantity = q);
                                        },
                                        decoration: InputDecoration(
                                          contentPadding: const EdgeInsets.symmetric(horizontal: 8),
                                          filled: true,
                                          fillColor: const Color(0xFF0F172A),
                                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: BorderSide.none),
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 8),

                                    // Narx
                                    Expanded(
                                      child: SizedBox(
                                        height: 35,
                                        child: TextField(
                                          keyboardType: TextInputType.number,
                                          enabled: !item.isSystemPrice,
                                          style: TextStyle(
                                            color: item.isSystemPrice ? Colors.grey : Colors.cyanAccent,
                                            fontWeight: FontWeight.bold,
                                            fontSize: 12,
                                          ),
                                          controller: TextEditingController(text: item.retailPrice.toInt().toString()),
                                          onChanged: (val) {
                                            final p = double.tryParse(val) ?? 0;
                                            setState(() => item.retailPrice = p);
                                          },
                                          decoration: InputDecoration(
                                            contentPadding: const EdgeInsets.symmetric(horizontal: 8),
                                            filled: true,
                                            fillColor: const Color(0xFF0F172A),
                                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: BorderSide.none),
                                          ),
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 8),

                                    // [x] Tizim narxi Checkboxi
                                    Row(
                                      children: [
                                        Checkbox(
                                          value: item.isSystemPrice,
                                          activeColor: Colors.blueAccent,
                                          onChanged: (val) {
                                            setState(() {
                                              item.isSystemPrice = val ?? true;
                                              if (item.isSystemPrice) {
                                                item.retailPrice = item.defaultRetail;
                                              }
                                            });
                                          },
                                        ),
                                        const Text('Tizim', style: TextStyle(color: Colors.grey, fontSize: 11)),
                                      ],
                                    ),
                                  ],
                                ),
                              ],
                            );
                          },
                        ),
                ),
                const SizedBox(height: 10),

                // Jami va Checkout
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Jami: ${_moneyFormat.format(_totalRetail)} so\'m', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16)),
                        Text('Foyda: +${_moneyFormat.format(_totalProfit)} so\'m', style: const TextStyle(color: Colors.greenAccent, fontSize: 11, fontWeight: FontWeight.bold)),
                      ],
                    ),
                    ElevatedButton(
                      onPressed: _isLoading ? null : _checkout,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.teal[600],
                        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      child: _isLoading ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)) : const Text('SOTISH (CHIQIM)', style: TextStyle(fontWeight: FontWeight.bold)),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
