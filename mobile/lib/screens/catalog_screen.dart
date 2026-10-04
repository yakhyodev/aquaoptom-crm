import 'package:flutter/material.dart';
import '../models/product_model.dart';
import '../services/api_service.dart';
import '../services/session_service.dart';
import '../utils/formatters.dart';
import 'inward_screen.dart';

class CatalogScreen extends StatefulWidget {
  final ApiService? apiService;

  const CatalogScreen({super.key, this.apiService});

  @override
  State<CatalogScreen> createState() => _CatalogScreenState();
}

class _CatalogScreenState extends State<CatalogScreen> {
  late final ApiService _api;
  List<Product> _products = [];
  bool _isLoading = true;
  String? _errorMessage;
  String _searchQuery = '';
  final _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _api = widget.apiService ?? ApiService();
    _fetchProducts();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _fetchProducts() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final list = await _api.getProducts();
      if (!mounted) return;
      setState(() {
        _products = list;
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

  List<Product> get _filteredProducts {
    if (_searchQuery.trim().isEmpty) return _products;
    final q = _searchQuery.toLowerCase();
    return _products.where((p) {
      return p.name.toLowerCase().contains(q) ||
          p.code.toLowerCase().contains(q) ||
          p.variants.any((v) => v.displayVolume.toLowerCase().contains(q));
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    final user = SessionService().currentUser;
    final canReceiveStock = user?.canReceiveStock ?? false;

    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      body: RefreshIndicator(
        onRefresh: _fetchProducts,
        child: Column(
          children: [
            // Search Header
            Padding(
              padding: const EdgeInsets.all(16.0),
              child: TextField(
                controller: _searchController,
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  hintText: 'Mahsulot nomi yoki kodini qidiring...',
                  hintStyle: const TextStyle(color: Colors.blueGrey),
                  prefixIcon: const Icon(Icons.search, color: Colors.blueAccent),
                  suffixIcon: _searchQuery.isNotEmpty
                      ? IconButton(
                          icon: const Icon(Icons.clear, color: Colors.grey),
                          onPressed: () {
                            _searchController.clear();
                            setState(() => _searchQuery = '');
                          },
                        )
                      : null,
                  filled: true,
                  fillColor: const Color(0xFF1E293B),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: Color(0xFF334155)),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: Color(0xFF334155)),
                  ),
                ),
                onChanged: (val) {
                  setState(() => _searchQuery = val);
                },
              ),
            ),

            if (_errorMessage != null)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16.0),
                child: Container(
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
              ),

            // Product List
            Expanded(
              child: _isLoading
                  ? const Center(child: CircularProgressIndicator())
                  : _filteredProducts.isEmpty
                      ? const Center(
                          child: Text(
                            'Mahsulotlar topilmadi',
                            style: TextStyle(color: Colors.blueGrey),
                          ),
                        )
                      : ListView.builder(
                          padding: const EdgeInsets.symmetric(horizontal: 16.0),
                          itemCount: _filteredProducts.length,
                          itemBuilder: (ctx, idx) {
                            final prod = _filteredProducts[idx];
                            return Card(
                              color: const Color(0xFF1E293B),
                              margin: const EdgeInsets.only(bottom: 12),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                                side: const BorderSide(color: Color(0xFF334155)),
                              ),
                              child: Padding(
                                padding: const EdgeInsets.all(14.0),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    // Product Name & Code
                                    Row(
                                      mainAxisAlignment:
                                          MainAxisAlignment.spaceBetween,
                                      children: [
                                        Text(
                                          prod.name,
                                          style: const TextStyle(
                                            fontSize: 16,
                                            fontWeight: FontWeight.bold,
                                            color: Colors.white,
                                          ),
                                        ),
                                        if (prod.code.isNotEmpty)
                                          Container(
                                            padding: const EdgeInsets.symmetric(
                                                horizontal: 8, vertical: 3),
                                            decoration: BoxDecoration(
                                              color: const Color(0xFF0F172A),
                                              borderRadius:
                                                  BorderRadius.circular(6),
                                            ),
                                            child: Text(
                                              prod.code,
                                              style: const TextStyle(
                                                  color: Colors.blueGrey,
                                                  fontSize: 11),
                                            ),
                                          ),
                                      ],
                                    ),
                                    const SizedBox(height: 10),

                                    // Variants List
                                    ...prod.variants.map((v) {
                                      final isLowStock = v.stockQty <= 10;
                                      return Container(
                                        margin: const EdgeInsets.only(top: 8),
                                        padding: const EdgeInsets.all(10),
                                        decoration: BoxDecoration(
                                          color: const Color(0xFF0F172A),
                                          borderRadius:
                                              BorderRadius.circular(8),
                                        ),
                                        child: Row(
                                          children: [
                                            // Volume Badge
                                            Container(
                                              padding:
                                                  const EdgeInsets.symmetric(
                                                      horizontal: 8, vertical: 4),
                                              decoration: BoxDecoration(
                                                color: Colors.blueAccent
                                                    .withValues(alpha: 0.2),
                                                borderRadius:
                                                    BorderRadius.circular(6),
                                              ),
                                              child: Text(
                                                v.displayVolume,
                                                style: const TextStyle(
                                                  color: Colors.cyanAccent,
                                                  fontWeight: FontWeight.bold,
                                                  fontSize: 12,
                                                ),
                                              ),
                                            ),
                                            const SizedBox(width: 12),

                                            // Stock Qty
                                            Expanded(
                                              child: Column(
                                                crossAxisAlignment:
                                                    CrossAxisAlignment.start,
                                                children: [
                                                  Row(
                                                    children: [
                                                      Text(
                                                        'Qoldiq: ${v.stockQty} dona',
                                                        style: TextStyle(
                                                          color: isLowStock
                                                              ? Colors.amberAccent
                                                              : Colors.white,
                                                          fontWeight:
                                                              FontWeight.w600,
                                                          fontSize: 13,
                                                        ),
                                                      ),
                                                      if (isLowStock) ...[
                                                        const SizedBox(width: 4),
                                                        const Icon(
                                                          Icons.warning_amber_rounded,
                                                          color: Colors.amberAccent,
                                                          size: 14,
                                                        ),
                                                      ],
                                                    ],
                                                  ),
                                                  if (v.costPrice != null)
                                                    Text(
                                                      'Tannarx: ${Formatters.formatMoney(v.costPrice!)}',
                                                      style: const TextStyle(
                                                        color: Colors.blueGrey,
                                                        fontSize: 11,
                                                      ),
                                                    ),
                                                ],
                                              ),
                                            ),

                                            // Sale Price
                                            Column(
                                              crossAxisAlignment:
                                                  CrossAxisAlignment.end,
                                              children: [
                                                Text(
                                                  Formatters.formatMoney(
                                                      v.defaultSalePrice),
                                                  style: const TextStyle(
                                                    color: Colors.greenAccent,
                                                    fontWeight: FontWeight.bold,
                                                    fontSize: 14,
                                                  ),
                                                ),
                                                const Text(
                                                  'sotuv narxi',
                                                  style: TextStyle(
                                                    color: Colors.grey,
                                                    fontSize: 10,
                                                  ),
                                                ),
                                              ],
                                            ),
                                          ],
                                        ),
                                      );
                                    }),
                                  ],
                                ),
                              ),
                            );
                          },
                        ),
            ),
          ],
        ),
      ),
      floatingActionButton: canReceiveStock
          ? FloatingActionButton.extended(
              onPressed: () async {
                final result = await Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => InwardScreen(apiService: _api),
                  ),
                );
                if (result == true) {
                  _fetchProducts();
                }
              },
              backgroundColor: Colors.blueAccent,
              icon: const Icon(Icons.add_shopping_cart, color: Colors.white),
              label: const Text(
                'Kirim qilish',
                style: TextStyle(color: Colors.white),
              ),
            )
          : null,
    );
  }
}
