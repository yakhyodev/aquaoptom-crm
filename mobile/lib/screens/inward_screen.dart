import '../utils/search_picker.dart';
import 'package:flutter/material.dart';
import '../utils/operation_id.dart';
import '../models/product_model.dart';
import '../models/supplier_model.dart';
import '../services/api_service.dart';

class InwardScreen extends StatefulWidget {
  final ApiService? apiService;

  const InwardScreen({super.key, this.apiService});

  @override
  State<InwardScreen> createState() => _InwardScreenState();
}

class _InwardScreenState extends State<InwardScreen> {
  late final ApiService _api;
  List<Product> _products = [];
  List<SupplierModel> _suppliers = [];
  bool _isLoading = true;
  String? _errorMessage;

  // Form Fields
  ProductVariant? _selectedVariant;
  SupplierModel? _selectedSupplier;
  bool _isNewProduct = false;
  final _newProductNameController = TextEditingController();
  final _newLitresController = TextEditingController(text: '0.5');
  final _quantityController = TextEditingController(text: '100');
  final _costPriceController = TextEditingController();
  String _selectedPackage = 'dona';
  final String _operationId = OperationId.generate();

  @override
  void initState() {
    super.initState();
    _api = widget.apiService ?? ApiService();
    _loadData();
  }

  @override
  void dispose() {
    _newProductNameController.dispose();
    _newLitresController.dispose();
    _quantityController.dispose();
    _costPriceController.dispose();
    super.dispose();
  }

  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final pFuture = _api.getProducts();
      final sFuture = _api.getSuppliers();
      final res = await Future.wait([pFuture, sFuture]);

      if (!mounted) return;
      setState(() {
        _products = res[0] as List<Product>;
        _suppliers = res[1] as List<SupplierModel>;
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

  void _showAddSupplierDialog() {
    final nameCtrl = TextEditingController();
    final companyCtrl = TextEditingController();
    final phoneCtrl = TextEditingController();

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Yangi Ta\'minotchi'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: nameCtrl,
              decoration: const InputDecoration(
                labelText: 'Ismi / Mas\'ul shaxs *',
              ),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: companyCtrl,
              decoration: const InputDecoration(
                labelText: 'Kompaniya / Zavod nomi',
              ),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: phoneCtrl,
              decoration: const InputDecoration(labelText: 'Telefon raqami'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Bekor qilish'),
          ),
          ElevatedButton(
            onPressed: () async {
              if (nameCtrl.text.trim().isEmpty) return;
              try {
                final newSupp = await _api.createSupplier(
                  name: nameCtrl.text.trim(),
                  companyName: companyCtrl.text.trim().isEmpty
                      ? null
                      : companyCtrl.text.trim(),
                  phone: phoneCtrl.text.trim().isEmpty
                      ? null
                      : phoneCtrl.text.trim(),
                );
                if (!ctx.mounted) return;
                Navigator.pop(ctx);
                if (mounted) {
                  setState(() {
                    _suppliers.add(newSupp);
                    _selectedSupplier = newSupp;
                  });
                }
              } catch (e) {
                if (!ctx.mounted) return;
                ScaffoldMessenger.of(
                  ctx,
                ).showSnackBar(SnackBar(content: Text('Xatolik: $e')));
              }
            },
            child: const Text('Saqlash'),
          ),
        ],
      ),
    );
  }

  Future<void> _submitInward() async {
    if (_isLoading) return;
    final qty = double.tryParse(_quantityController.text.trim()) ?? 0.0;
    if (qty <= 0 || qty != qty.roundToDouble()) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Miqdor musbat bo\'lishi shart!')),
      );
      return;
    }

    final costPrice = int.tryParse(_costPriceController.text.trim()) ?? 0;

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      if (_isNewProduct) {
        final prodName = _newProductNameController.text.trim();
        final litres = double.tryParse(_newLitresController.text.trim()) ?? 0.5;
        if (prodName.isEmpty) {
          throw Exception('Mahsulot nomi kiritilishi shart!');
        }

        await _api.createInward(
          operationId: _operationId,
          productName: prodName,
          litres: litres,
          quantity: qty,
          packageName: _selectedPackage,
          costPrice: costPrice > 0 ? costPrice : null,
          supplierName: _selectedSupplier?.name,
        );
      } else {
        if (_selectedVariant == null) {
          throw Exception('Mavjud mahsulot variantini tanlang!');
        }

        await _api.createInward(
          operationId: _operationId,
          variantId: _selectedVariant!.id,
          quantity: qty,
          packageName: _selectedPackage,
          costPrice: costPrice > 0 ? costPrice : null,
          supplierName: _selectedSupplier?.name,
        );
      }

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Kirim muvaffaqiyatli saqlandi!'),
          backgroundColor: Colors.green,
        ),
      );
      Navigator.pop(context, true);
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
        title: const Text('Omborga Kirim Qilish'),
        backgroundColor: Theme.of(context).colorScheme.surface,
      ),
      body: _isLoading && _products.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
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
                        style: const TextStyle(
                          color: Colors.redAccent,
                          fontSize: 13,
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                  ],

                  // Product Selection Mode
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
                              ChoiceChip(
                                label: const Text('Mavjud Mahsulot'),
                                selected: !_isNewProduct,
                                onSelected: (_) =>
                                    setState(() => _isNewProduct = false),
                                selectedColor: Colors.blueAccent,
                              ),
                              const SizedBox(width: 8),
                              ChoiceChip(
                                label: const Text('Yangi Mahsulot'),
                                selected: _isNewProduct,
                                onSelected: (_) =>
                                    setState(() => _isNewProduct = true),
                                selectedColor: Colors.blueAccent,
                              ),
                            ],
                          ),
                          const SizedBox(height: 16),

                          if (!_isNewProduct) ...[
                            SearchPicker<ProductVariant>(
                              initialValue: _selectedVariant,
                              label: 'Mahsulot va hajmni qidiring',
                              items: _products
                                  .expand((p) => p.variants)
                                  .toList(),
                              labelFor: (v) =>
                                  '${_products.firstWhere((p) => p.id == v.productId).name} — ${v.displayVolume} · ${v.stockQty} dona',
                              onChanged: (val) => setState(() {
                                _selectedVariant = val;
                                if (val?.costPrice != null)
                                  _costPriceController.text =
                                      '${val!.costPrice}';
                              }),
                            ),
                          ] else ...[
                            TextField(
                              controller: _newProductNameController,
                              style: TextStyle(
                                color: Theme.of(context).colorScheme.onSurface,
                              ),
                              decoration: InputDecoration(
                                labelText: 'Yangi mahsulot nomi *',
                                filled: true,
                                fillColor: Theme.of(
                                  context,
                                ).scaffoldBackgroundColor,
                                border: OutlineInputBorder(),
                              ),
                            ),
                            const SizedBox(height: 12),
                            TextField(
                              controller: _newLitresController,
                              keyboardType:
                                  const TextInputType.numberWithOptions(
                                    decimal: true,
                                  ),
                              style: TextStyle(
                                color: Theme.of(context).colorScheme.onSurface,
                              ),
                              decoration: InputDecoration(
                                labelText:
                                    'Hajmi (litr, masalan: 0.5 yoki 1.5) *',
                                filled: true,
                                fillColor: Theme.of(
                                  context,
                                ).scaffoldBackgroundColor,
                                border: OutlineInputBorder(),
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Supplier Card
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
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                'Ta\'minotchi (Zavod / Diler)',
                                style: TextStyle(
                                  color: Theme.of(
                                    context,
                                  ).colorScheme.onSurface,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              IconButton(
                                icon: Icon(
                                  Icons.add_business,
                                  color: Theme.of(context).colorScheme.primary,
                                ),
                                tooltip: 'Yangi ta\'minotchi qo\'shish',
                                onPressed: _showAddSupplierDialog,
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          SearchPicker<SupplierModel>(
                            initialValue: _selectedSupplier,
                            label: 'Ta’minotchini qidiring',
                            items: _suppliers,
                            labelFor: (s) =>
                                '${s.name} · ${s.companyName ?? ""} · ${s.phone ?? ""}',
                            onChanged: (val) =>
                                setState(() => _selectedSupplier = val),
                          ),
                          const SizedBox(height: 12),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Quantity & Cost Card
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
                          // Package selector
                          Row(
                            children: [
                              Text(
                                'Qadoq turi: ',
                                style: TextStyle(
                                  color: Theme.of(
                                    context,
                                  ).colorScheme.onSurfaceVariant,
                                ),
                              ),
                              const SizedBox(width: 8),
                              ChoiceChip(
                                label: const Text('Dona'),
                                selected: _selectedPackage == 'dona',
                                onSelected: (_) =>
                                    setState(() => _selectedPackage = 'dona'),
                                selectedColor: Colors.blueAccent,
                              ),
                              const SizedBox(width: 6),
                              ChoiceChip(
                                label: const Text('Blok'),
                                selected: _selectedPackage == 'blok',
                                onSelected: (_) =>
                                    setState(() => _selectedPackage = 'blok'),
                                selectedColor: Colors.blueAccent,
                              ),
                              const SizedBox(width: 6),
                              ChoiceChip(
                                label: const Text('Yashik'),
                                selected: _selectedPackage == 'yashik',
                                onSelected: (_) =>
                                    setState(() => _selectedPackage = 'yashik'),
                                selectedColor: Colors.blueAccent,
                              ),
                            ],
                          ),
                          const SizedBox(height: 14),
                          TextField(
                            controller: _quantityController,
                            keyboardType: TextInputType.number,
                            style: TextStyle(
                              color: Theme.of(context).colorScheme.onSurface,
                            ),
                            decoration: InputDecoration(
                              labelText: 'Kirim miqdori *',
                              filled: true,
                              fillColor: Theme.of(
                                context,
                              ).scaffoldBackgroundColor,
                              border: OutlineInputBorder(),
                            ),
                          ),
                          const SizedBox(height: 12),
                          TextField(
                            controller: _costPriceController,
                            keyboardType: TextInputType.number,
                            style: TextStyle(
                              color: Theme.of(context).colorScheme.onSurface,
                            ),
                            decoration: InputDecoration(
                              labelText: 'Birlik tannarxi (so\'m) *',
                              filled: true,
                              fillColor: Theme.of(
                                context,
                              ).scaffoldBackgroundColor,
                              border: OutlineInputBorder(),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 24),

                  // Submit Button
                  ElevatedButton(
                    onPressed: _isLoading ? null : _submitInward,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.blueAccent,
                      foregroundColor: Theme.of(context).colorScheme.onSurface,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: _isLoading
                        ? SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Theme.of(context).colorScheme.onSurface,
                            ),
                          )
                        : const Text(
                            'Kirimni tasdiqlash va qabul qilish',
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                  ),
                ],
              ),
            ),
    );
  }
}
