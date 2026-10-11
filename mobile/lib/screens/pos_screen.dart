import 'package:flutter/material.dart';
import '../models/customer_model.dart';
import '../models/product_model.dart';
import '../models/sale_model.dart';
import '../services/api_exceptions.dart';
import '../services/api_service.dart';
import '../services/offline_sales_service.dart';
import '../utils/formatters.dart';
import '../utils/operation_id.dart';
import '../utils/search_picker.dart';

/// Savat qatori. Miqdor faqat butun dona (backend kasr donani rad etadi),
/// narx butun so'm. UI dagi jami faqat ko'rinish uchun (preview) — yakuniy
/// summa, qarz va foyda server javobidan (chek) olinadi.
class CartItem {
  final ProductVariant variant;
  final String productName;
  int quantity;
  int salePrice;
  bool isSystemPrice;

  CartItem({
    required this.variant,
    required this.productName,
    this.quantity = 1,
    required this.salePrice,
    this.isSystemPrice = true,
  });

  int get lineTotal => quantity * salePrice;

  Map<String, dynamic> toPayload() => {
    'variant_id': variant.id,
    'quantity': quantity,
    'package_name': 'dona',
    'is_system_price': isSystemPrice,
    if (!isSystemPrice) 'sale_price': salePrice,
  };
}

class PosScreen extends StatefulWidget {
  final ApiService? apiService;

  const PosScreen({super.key, this.apiService});

  @override
  State<PosScreen> createState() => _PosScreenState();
}

class _PosScreenState extends State<PosScreen> {
  late final ApiService _api;
  List<Product> _catalog = [];
  List<CustomerModel> _customers = [];
  bool _isLoading = true;
  String? _errorMessage;

  // Cart & Draft State
  final List<CartItem> _cart = [];
  CustomerModel? _selectedCustomer;
  bool _isQuickSale = true;
  String _paymentMode = 'FULL'; // FULL, PARTIAL, DEBT
  String _paymentMethod = 'CASH'; // CASH, CARD, BANK
  final _partialAmountController = TextEditingController();
  late String _currentOperationId;

  @visibleForTesting
  String get currentOperationId => _currentOperationId;

  @override
  void initState() {
    super.initState();
    _api = widget.apiService ?? ApiService();
    _resetDraft();
    _loadInitialData();
  }

  @override
  void dispose() {
    _partialAmountController.dispose();
    super.dispose();
  }

  void _resetDraft() {
    _currentOperationId = OperationId.generate();
    _cart.clear();
    _selectedCustomer = null;
    _isQuickSale = true;
    _paymentMode = 'FULL';
    _paymentMethod = 'CASH';
    _partialAmountController.clear();
    _errorMessage = null;
  }

  /// Qoralama mazmuni o'zgarganda yangi operation_id. O'zgarishsiz qayta
  /// yuborish (retry/timeout) esa aynan eski operation_id ni ishlatadi.
  void _touchDraft() {
    _currentOperationId = OperationId.generate();
    _errorMessage = null;
  }

  Future<void> _loadInitialData() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final productsFuture = _api.getProducts();
      final customersFuture = _api.getCustomers();

      final results = await Future.wait([productsFuture, customersFuture]);
      if (!mounted) return;

      setState(() {
        _catalog = results[0] as List<Product>;
        _customers = results[1] as List<CustomerModel>;
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

  int get _cartTotal {
    int total = 0;
    for (final item in _cart) {
      total += item.lineTotal;
    }
    return total;
  }

  int get _calculatedPaidAmount {
    if (_paymentMode == 'DEBT') return 0;
    if (_paymentMode == 'FULL') return _cartTotal;
    // PARTIAL
    final entered = int.tryParse(_partialAmountController.text.trim()) ?? 0;
    return entered.clamp(0, _cartTotal);
  }

  int get _calculatedDebtAmount {
    return _cartTotal - _calculatedPaidAmount;
  }

  void _addToCart(Product product, ProductVariant variant) {
    final existingIdx = _cart.indexWhere((it) => it.variant.id == variant.id);
    setState(() {
      if (existingIdx != -1) {
        _cart[existingIdx].quantity += 1;
      } else {
        final hasSystemPrice = variant.defaultSalePrice > 0;
        _cart.add(
          CartItem(
            variant: variant,
            productName: product.name,
            quantity: 1,
            salePrice: variant.defaultSalePrice,
            // Tizim narxi yo'q bo'lsa nolga tenglashtirilmaydi — manual narx talab qilinadi
            isSystemPrice: hasSystemPrice,
          ),
        );
      }
      _touchDraft();
    });
  }

  void _showPriceDialog(CartItem item) {
    final priceCtrl = TextEditingController(
      text: item.salePrice > 0 ? item.salePrice.toString() : '',
    );
    bool useSystem = item.isSystemPrice;
    final hasSystemPrice = item.variant.defaultSalePrice > 0;

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDlg) => AlertDialog(
          title: Text(
            '${item.productName} (${item.variant.displayVolume}) narxi',
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SwitchListTile(
                key: const Key('pos_system_price_switch'),
                title: const Text('Tizim narxi'),
                subtitle: Text(
                  hasSystemPrice
                      ? Formatters.formatMoney(item.variant.defaultSalePrice)
                      : 'Belgilanmagan',
                ),
                value: useSystem,
                onChanged: hasSystemPrice
                    ? (v) => setDlg(() => useSystem = v)
                    : null,
              ),
              if (!useSystem)
                TextField(
                  key: const Key('pos_manual_price_field'),
                  controller: priceCtrl,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Manual narx (butun so\'m)',
                  ),
                ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Bekor qilish'),
            ),
            ElevatedButton(
              onPressed: () {
                if (!useSystem) {
                  final p = int.tryParse(priceCtrl.text.trim()) ?? 0;
                  if (p <= 0) return;
                  setState(() {
                    item.isSystemPrice = false;
                    item.salePrice = p;
                    _touchDraft();
                  });
                } else {
                  setState(() {
                    item.isSystemPrice = true;
                    item.salePrice = item.variant.defaultSalePrice;
                    _touchDraft();
                  });
                }
                Navigator.pop(ctx);
              },
              child: const Text('Saqlash'),
            ),
          ],
        ),
      ),
    );
  }

  void _showAddCustomerDialog() {
    final nameCtrl = TextEditingController();
    final phoneCtrl = TextEditingController();
    final storeCtrl = TextEditingController();
    final limitCtrl = TextEditingController(text: '0');

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Yangi Mijoz Qo\'shish'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: nameCtrl,
                decoration: const InputDecoration(labelText: 'Mijoz ismi *'),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: phoneCtrl,
                decoration: const InputDecoration(labelText: 'Telefon raqami'),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: storeCtrl,
                decoration: const InputDecoration(
                  labelText: 'Do\'kon / Savdo nuqtasi',
                ),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: limitCtrl,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'Qarz limiti (so\'m)',
                ),
              ),
            ],
          ),
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
                final newCust = await _api.createCustomer(
                  name: nameCtrl.text.trim(),
                  phone: phoneCtrl.text.trim().isEmpty
                      ? null
                      : phoneCtrl.text.trim(),
                  storeName: storeCtrl.text.trim().isEmpty
                      ? null
                      : storeCtrl.text.trim(),
                  debtLimit: int.tryParse(limitCtrl.text) ?? 0,
                );
                if (!ctx.mounted) return;
                Navigator.pop(ctx);
                if (mounted) {
                  setState(() {
                    _customers.add(newCust);
                    _selectedCustomer = newCust;
                    _isQuickSale = false;
                  });
                }
              } catch (e) {
                if (!ctx.mounted) return;
                ScaffoldMessenger.of(ctx).showSnackBar(
                  SnackBar(content: Text('Mijoz qo\'shishda xatolik: $e')),
                );
              }
            },
            child: const Text('Saqlash'),
          ),
        ],
      ),
    );
  }

  Future<void> _showProductSelectorDialog() async {
    final choices = _catalog
        .expand(
          (product) => product.variants.map(
            (variant) => (product: product, variant: variant),
          ),
        )
        .toList();
    final selected = await chooseFromList(
      context,
      title: 'Mahsulot nomi yoki hajmini qidiring',
      items: choices,
      labelFor: (item) =>
          '${item.product.name} — ${item.variant.displayVolume} · ${item.variant.stockQty} dona',
    );
    if (selected != null && mounted) {
      _addToCart(selected.product, selected.variant);
    }
  }

  void _showReceiptDialog(SaleRecord sale) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        backgroundColor: Theme.of(context).colorScheme.surface,
        title: Row(
          children: [
            const Icon(Icons.check_circle, color: Colors.greenAccent),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                'Chek #${sale.invoiceNumber}',
                style: TextStyle(
                  color: Theme.of(context).colorScheme.onSurface,
                  fontWeight: FontWeight.bold,
                  fontSize: 16,
                ),
              ),
            ),
          ],
        ),
        content: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'Mijoz: ${sale.customerName}',
                style: TextStyle(
                  color: Theme.of(context).colorScheme.primary,
                  fontSize: 13,
                ),
              ),
              Text(
                'Vaqt: ${Formatters.formatDateTime(sale.createdAt)}',
                style: TextStyle(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                  fontSize: 12,
                ),
              ),
              Divider(
                color: Theme.of(context).colorScheme.outlineVariant,
                height: 20,
              ),
              ...sale.items.map(
                (it) => Padding(
                  padding: const EdgeInsets.symmetric(vertical: 3.0),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Text(
                          '${it.productName} (${it.volumeName}) x ${it.quantity}',
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.onSurface,
                            fontSize: 12,
                          ),
                        ),
                      ),
                      Text(
                        Formatters.formatMoney(it.totalPrice),
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.onSurface,
                          fontWeight: FontWeight.w600,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              Divider(
                color: Theme.of(context).colorScheme.outlineVariant,
                height: 20,
              ),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Jami Summa:',
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.onSurface,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  Text(
                    Formatters.formatMoney(sale.totalAmount),
                    style: const TextStyle(
                      color: Colors.greenAccent,
                      fontWeight: FontWeight.bold,
                      fontSize: 15,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'To\'langan:',
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                      fontSize: 13,
                    ),
                  ),
                  Text(
                    Formatters.formatMoney(sale.paidAmount),
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.onSurface,
                      fontSize: 13,
                    ),
                  ),
                ],
              ),
              if (sale.debtAmount > 0) ...[
                const SizedBox(height: 4),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Nasiya Qarz:',
                      style: TextStyle(color: Colors.redAccent, fontSize: 13),
                    ),
                    Text(
                      Formatters.formatMoney(sale.debtAmount),
                      style: const TextStyle(
                        color: Colors.redAccent,
                        fontWeight: FontWeight.bold,
                        fontSize: 13,
                      ),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
        actions: [
          ElevatedButton(
            onPressed: () {
              Navigator.pop(ctx);
              setState(() {
                _resetDraft();
              });
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.blueAccent),
            child: const Text('Yangi savdo ochish'),
          ),
        ],
      ),
    );
  }

  void _showOfflineReceiptDialog(LocalSaleConfirmResult result) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        backgroundColor: Theme.of(context).colorScheme.surface,
        title: Row(
          children: [
            const Icon(Icons.cloud_off, color: Colors.amberAccent),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                'Chek #${result.tempInvoiceNumber}',
                style: TextStyle(
                  color: Theme.of(context).colorScheme.onSurface,
                  fontWeight: FontWeight.bold,
                  fontSize: 16,
                ),
              ),
            ),
          ],
        ),
        content: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 6,
                ),
                decoration: BoxDecoration(
                  color: Colors.amberAccent.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(color: Colors.amberAccent),
                ),
                child: const Text(
                  'Offline saqlandi. Internet qaytganda serverga yuboriladi va server chek raqamiga yangilanadi.',
                  style: TextStyle(color: Colors.amberAccent, fontSize: 12),
                ),
              ),
              const SizedBox(height: 12),
              Text(
                'Mijoz: ${_isQuickSale ? "Tezkor Xaridor" : (_selectedCustomer?.name ?? "Tezkor")}',
                style: TextStyle(
                  color: Theme.of(context).colorScheme.primary,
                  fontSize: 13,
                ),
              ),
              Text(
                'Vaqt: ${Formatters.formatDateTime(DateTime.now())}',
                style: TextStyle(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                  fontSize: 12,
                ),
              ),
              Divider(
                color: Theme.of(context).colorScheme.outlineVariant,
                height: 20,
              ),
              ...((result.receipt['items'] as List<dynamic>?) ?? []).map((it) {
                final item = it as Map<String, dynamic>;
                return Padding(
                  padding: const EdgeInsets.symmetric(vertical: 4),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Text(
                          '${item['product_name']} x${item['quantity']}',
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.onSurface,
                            fontSize: 13,
                          ),
                        ),
                      ),
                      Text(
                        Formatters.formatMoney(
                          (item['total_price'] as num).toInt(),
                        ),
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.onSurface,
                          fontSize: 13,
                        ),
                      ),
                    ],
                  ),
                );
              }),
              Divider(
                color: Theme.of(context).colorScheme.outlineVariant,
                height: 20,
              ),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Jami:',
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                      fontSize: 13,
                    ),
                  ),
                  Text(
                    Formatters.formatMoney(result.totalAmount),
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.onSurface,
                      fontWeight: FontWeight.bold,
                      fontSize: 15,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'To\'landi:',
                    style: TextStyle(color: Colors.greenAccent, fontSize: 13),
                  ),
                  Text(
                    Formatters.formatMoney(result.paidAmount),
                    style: const TextStyle(
                      color: Colors.greenAccent,
                      fontWeight: FontWeight.bold,
                      fontSize: 14,
                    ),
                  ),
                ],
              ),
              if (result.debtAmount > 0) ...[
                const SizedBox(height: 4),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Nasiya Qarz:',
                      style: TextStyle(color: Colors.redAccent, fontSize: 13),
                    ),
                    Text(
                      Formatters.formatMoney(result.debtAmount),
                      style: const TextStyle(
                        color: Colors.redAccent,
                        fontWeight: FontWeight.bold,
                        fontSize: 13,
                      ),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
        actions: [
          ElevatedButton(
            onPressed: () {
              Navigator.pop(ctx);
              setState(() {
                _resetDraft();
              });
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.blueAccent),
            child: const Text('Yangi savdo ochish'),
          ),
        ],
      ),
    );
  }

  Future<void> _submitSale() async {
    if (_cart.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Savat bo\'sh! Mahsulot qo\'shing.')),
      );
      return;
    }

    if (!_isQuickSale && _selectedCustomer == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Xaridorni tanlang yoki Tezkor savdoni belgilang.'),
        ),
      );
      return;
    }

    if (_paymentMode != 'FULL' && _isQuickSale) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Tezkor (nomsiz) xaridor faqat to\'liq to\'laydi. Qisman/nasiya uchun mijoz tanlang!',
          ),
        ),
      );
      return;
    }

    if (_cart.any((it) => !it.isSystemPrice && it.salePrice <= 0)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Narxi belgilanmagan qatorlar bor — manual narx kiriting.',
          ),
        ),
      );
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final itemsPayload = _cart.map((it) => it.toPayload()).toList();

    try {
      // Bir xil qoralama qayta yuborilsa (retry/timeout) aynan shu operation_id ketadi.
      final sale = await _api.createSale(
        customerId: _isQuickSale ? null : _selectedCustomer?.id,
        items: itemsPayload,
        paymentType: _paymentMode == 'FULL'
            ? _paymentMethod
            : (_paymentMode == 'DEBT' ? 'DEBT' : 'PARTIAL'),
        paymentMethod: _paymentMethod,
        paidAmount: _calculatedPaidAmount,
        operationId: _currentOperationId,
      );

      if (!mounted) return;
      setState(() {
        _isLoading = false;
      });
      _showReceiptDialog(sale);
    } catch (e) {
      // Agar tarmoq xatosi bo'lsa, avtomatik offline saqlash
      if (e is NetworkException) {
        try {
          final offlineService = OfflineSalesService();
          final offlineResult = await offlineService.confirmSaleOffline(
            items: itemsPayload,
            customerId: _isQuickSale ? null : _selectedCustomer?.id,
            customerName: _isQuickSale
                ? 'Tezkor Mijoz'
                : _selectedCustomer?.name,
            paymentType: _paymentMode == 'FULL'
                ? _paymentMethod
                : (_paymentMode == 'DEBT' ? 'DEBT' : 'PARTIAL'),
            paymentMethod: _paymentMethod,
            paidAmount: _calculatedPaidAmount,
            operationId: _currentOperationId,
          );

          if (!mounted) return;
          setState(() {
            _isLoading = false;
          });
          _showOfflineReceiptDialog(offlineResult);
          return;
        } catch (offlineErr) {
          if (!mounted) return;
          setState(() {
            _errorMessage = offlineErr.toString();
            _isLoading = false;
          });
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Offline savdo xatosi: $offlineErr'),
              backgroundColor: Colors.redAccent,
            ),
          );
          return;
        }
      }

      if (!mounted) return;
      setState(() {
        _errorMessage = e.toString();
        _isLoading = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Savdo xatosi: $e'),
          backgroundColor: Colors.redAccent,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: _isLoading && _catalog.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                // Customer & Quick Sale Header
                Container(
                  padding: const EdgeInsets.all(12),
                  color: Theme.of(context).colorScheme.surface,
                  child: Column(
                    children: [
                      Row(
                        children: [
                          ChoiceChip(
                            label: const Text('Tezkor Xaridor'),
                            selected: _isQuickSale,
                            onSelected: (val) {
                              setState(() {
                                _isQuickSale = true;
                                _selectedCustomer = null;
                              });
                            },
                            selectedColor: Colors.blueAccent,
                          ),
                          const SizedBox(width: 8),
                          ChoiceChip(
                            label: const Text('Mijoz tanlash'),
                            selected: !_isQuickSale,
                            onSelected: (val) {
                              setState(() {
                                _isQuickSale = false;
                              });
                            },
                            selectedColor: Colors.blueAccent,
                          ),
                          const Spacer(),
                          IconButton(
                            icon: Icon(
                              Icons.person_add,
                              color: Theme.of(context).colorScheme.primary,
                            ),
                            tooltip: 'Yangi mijoz yaratish',
                            onPressed: _showAddCustomerDialog,
                          ),
                        ],
                      ),
                      if (!_isQuickSale) ...[
                        const SizedBox(height: 8),
                        SearchPicker<CustomerModel>(
                          items: _customers,
                          initialValue: _selectedCustomer,
                          label: 'Mijozni qidiring',
                          labelFor: (c) =>
                              '${c.name} · ${c.storeName ?? ""} · ${c.phone ?? ""}',
                          onChanged: (val) => setState(() {
                            _selectedCustomer = val;
                            _touchDraft();
                          }),
                        ),
                      ],
                    ],
                  ),
                ),

                // Cart Lines List
                Expanded(
                  child: _cart.isEmpty
                      ? Center(
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(
                                Icons.shopping_cart_outlined,
                                size: 48,
                                color: Theme.of(
                                  context,
                                ).colorScheme.onSurfaceVariant,
                              ),
                              const SizedBox(height: 12),
                              Text(
                                'Savat hozircha bo\'sh',
                                style: TextStyle(
                                  color: Theme.of(
                                    context,
                                  ).colorScheme.onSurfaceVariant,
                                  fontSize: 15,
                                ),
                              ),
                              const SizedBox(height: 8),
                              ElevatedButton.icon(
                                onPressed: _showProductSelectorDialog,
                                icon: const Icon(Icons.add),
                                label: const Text('Mahsulot qo\'shish'),
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: Colors.blueAccent,
                                ),
                              ),
                            ],
                          ),
                        )
                      : ListView.builder(
                          padding: const EdgeInsets.all(12),
                          itemCount: _cart.length,
                          itemBuilder: (ctx, idx) {
                            final item = _cart[idx];
                            return Card(
                              color: Theme.of(context).colorScheme.surface,
                              margin: const EdgeInsets.only(bottom: 8),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(10),
                                side: BorderSide(
                                  color: Theme.of(
                                    context,
                                  ).colorScheme.outlineVariant,
                                ),
                              ),
                              child: Padding(
                                padding: const EdgeInsets.all(10.0),
                                child: Column(
                                  children: [
                                    Row(
                                      children: [
                                        Expanded(
                                          child: Text(
                                            '${item.productName} (${item.variant.displayVolume})',
                                            style: TextStyle(
                                              color: Theme.of(
                                                context,
                                              ).colorScheme.onSurface,
                                              fontWeight: FontWeight.bold,
                                              fontSize: 14,
                                            ),
                                          ),
                                        ),
                                        IconButton(
                                          icon: const Icon(
                                            Icons.delete_outline,
                                            color: Colors.redAccent,
                                            size: 20,
                                          ),
                                          onPressed: () {
                                            setState(() {
                                              _cart.removeAt(idx);
                                              _touchDraft();
                                            });
                                          },
                                        ),
                                      ],
                                    ),
                                    Row(
                                      children: [
                                        // Quantity Controls
                                        IconButton(
                                          icon: Icon(
                                            Icons.remove_circle_outline,
                                            color: Theme.of(
                                              context,
                                            ).colorScheme.primary,
                                          ),
                                          onPressed: () {
                                            setState(() {
                                              if (item.quantity > 1) {
                                                item.quantity -= 1;
                                                _touchDraft();
                                              }
                                            });
                                          },
                                        ),
                                        Text(
                                          '${item.quantity} dona',
                                          style: TextStyle(
                                            color: Theme.of(
                                              context,
                                            ).colorScheme.onSurface,
                                            fontWeight: FontWeight.bold,
                                          ),
                                        ),
                                        IconButton(
                                          icon: Icon(
                                            Icons.add_circle_outline,
                                            color: Theme.of(
                                              context,
                                            ).colorScheme.primary,
                                          ),
                                          onPressed: () {
                                            setState(() {
                                              item.quantity += 1;
                                              _touchDraft();
                                            });
                                          },
                                        ),
                                        const Spacer(),
                                        // Price (system/manual)
                                        InkWell(
                                          key: Key(
                                            'pos_price_${item.variant.id}',
                                          ),
                                          onTap: () => _showPriceDialog(item),
                                          child: Column(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.end,
                                            children: [
                                              Text(
                                                item.salePrice > 0
                                                    ? Formatters.formatMoney(
                                                        item.lineTotal,
                                                      )
                                                    : 'Narx kiriting',
                                                style: TextStyle(
                                                  color: item.salePrice > 0
                                                      ? Colors.greenAccent
                                                      : Colors.amberAccent,
                                                  fontWeight: FontWeight.bold,
                                                  fontSize: 14,
                                                ),
                                              ),
                                              Text(
                                                item.isSystemPrice
                                                    ? 'tizim narxi ✎'
                                                    : 'manual narx ✎',
                                                style: TextStyle(
                                                  color: item.isSystemPrice
                                                      ? Theme.of(context)
                                                            .colorScheme
                                                            .onSurfaceVariant
                                                      : Colors.amberAccent,
                                                  fontSize: 10,
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                              ),
                            );
                          },
                        ),
                ),

                // Checkout & Payment Panel
                if (_cart.isNotEmpty)
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.surface,
                      borderRadius: BorderRadius.vertical(
                        top: Radius.circular(16),
                      ),
                      border: Border(
                        top: BorderSide(
                          color: Theme.of(context).colorScheme.outlineVariant,
                        ),
                      ),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        // Total
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'Jami Summa:',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                                color: Theme.of(context).colorScheme.onSurface,
                              ),
                            ),
                            Text(
                              Formatters.formatMoney(_cartTotal),
                              style: const TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                                color: Colors.greenAccent,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),

                        // Payment Degree (Full, Partial, Debt)
                        Row(
                          children: [
                            Expanded(
                              child: ChoiceChip(
                                label: const Center(
                                  child: Text(
                                    'To\'liq',
                                    style: TextStyle(fontSize: 12),
                                  ),
                                ),
                                selected: _paymentMode == 'FULL',
                                onSelected: (_) =>
                                    setState(() => _paymentMode = 'FULL'),
                                selectedColor: Colors.blueAccent,
                              ),
                            ),
                            const SizedBox(width: 6),
                            Expanded(
                              child: ChoiceChip(
                                label: const Center(
                                  child: Text(
                                    'Qisman',
                                    style: TextStyle(fontSize: 12),
                                  ),
                                ),
                                selected: _paymentMode == 'PARTIAL',
                                onSelected: (_) =>
                                    setState(() => _paymentMode = 'PARTIAL'),
                                selectedColor: Colors.blueAccent,
                              ),
                            ),
                            const SizedBox(width: 6),
                            Expanded(
                              child: ChoiceChip(
                                label: const Center(
                                  child: Text(
                                    'Nasiya',
                                    style: TextStyle(fontSize: 12),
                                  ),
                                ),
                                selected: _paymentMode == 'DEBT',
                                onSelected: (_) =>
                                    setState(() => _paymentMode = 'DEBT'),
                                selectedColor: Colors.blueAccent,
                              ),
                            ),
                          ],
                        ),

                        // Partial amount input if PARTIAL
                        if (_paymentMode == 'PARTIAL') ...[
                          const SizedBox(height: 8),
                          TextField(
                            controller: _partialAmountController,
                            keyboardType: TextInputType.number,
                            style: TextStyle(
                              color: Theme.of(context).colorScheme.onSurface,
                            ),
                            decoration: InputDecoration(
                              labelText: 'Hozir to\'lanadigan summa (so\'m)',
                              labelStyle: TextStyle(
                                color: Theme.of(
                                  context,
                                ).colorScheme.onSurfaceVariant,
                              ),
                              filled: true,
                              fillColor: Theme.of(
                                context,
                              ).scaffoldBackgroundColor,
                              border: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(8),
                              ),
                            ),
                            onChanged: (_) => setState(() {}),
                          ),
                        ],
                        const SizedBox(height: 8),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'To\'lanadi: ${Formatters.formatMoney(_calculatedPaidAmount)}',
                              style: TextStyle(
                                color: Theme.of(context).colorScheme.onSurface,
                                fontSize: 12,
                              ),
                            ),
                            Text(
                              'Nasiya: ${Formatters.formatMoney(_calculatedDebtAmount)}',
                              key: const Key('pos_debt_preview'),
                              style: TextStyle(
                                color: _calculatedDebtAmount > 0
                                    ? Colors.amberAccent
                                    : Theme.of(
                                        context,
                                      ).colorScheme.onSurfaceVariant,
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                        if (_errorMessage != null) ...[
                          const SizedBox(height: 8),
                          Text(
                            _errorMessage!,
                            style: const TextStyle(
                              color: Colors.redAccent,
                              fontSize: 12,
                            ),
                          ),
                        ],
                        const SizedBox(height: 12),

                        // Action Buttons: Add Item & Checkout
                        Row(
                          children: [
                            OutlinedButton.icon(
                              onPressed: _showProductSelectorDialog,
                              icon: const Icon(Icons.add),
                              label: const Text('Qo\'shish'),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: ElevatedButton(
                                onPressed: _isLoading ? null : _submitSale,
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: Colors.green,
                                  foregroundColor: Theme.of(
                                    context,
                                  ).colorScheme.onSurface,
                                  padding: const EdgeInsets.symmetric(
                                    vertical: 14,
                                  ),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                ),
                                child: _isLoading
                                    ? SizedBox(
                                        height: 20,
                                        width: 20,
                                        child: CircularProgressIndicator(
                                          strokeWidth: 2,
                                          color: Theme.of(
                                            context,
                                          ).colorScheme.onSurface,
                                        ),
                                      )
                                    : Text(
                                        'Yakunlash: ${Formatters.formatMoney(_calculatedPaidAmount)}',
                                        style: const TextStyle(
                                          fontSize: 15,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                              ),
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
