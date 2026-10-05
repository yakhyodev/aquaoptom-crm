import 'package:flutter/material.dart';
import '../models/cash_account_model.dart';
import '../models/customer_model.dart';
import '../models/supplier_model.dart';
import '../services/api_service.dart';
import '../utils/formatters.dart';
import '../utils/operation_id.dart';

class DebtScreen extends StatefulWidget {
  final ApiService? apiService;

  const DebtScreen({super.key, this.apiService});

  @override
  State<DebtScreen> createState() => _DebtScreenState();
}

class _DebtScreenState extends State<DebtScreen>
    with SingleTickerProviderStateMixin {
  late final ApiService _api;
  late final TabController _tabController;

  List<CustomerModel> _customers = [];
  List<SupplierModel> _suppliers = [];
  List<CashAccountModel> _cashAccounts = [];

  bool _isLoading = true;
  String? _errorMessage;
  String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    _api = widget.apiService ?? ApiService();
    _tabController = TabController(length: 2, vsync: this);
    _loadData();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final custFuture = _api.getCustomers();
      final suppFuture = _api.getSuppliers();
      final cashFuture = _api.getCashAccounts();

      final res = await Future.wait([custFuture, suppFuture, cashFuture]);
      if (!mounted) return;

      setState(() {
        _customers = res[0] as List<CustomerModel>;
        _suppliers = res[1] as List<SupplierModel>;
        _cashAccounts = res[2] as List<CashAccountModel>;
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

  void _showPaymentDialog({
    required bool isCustomer,
    required int partyId,
    required String partyName,
    required int currentBalance,
  }) {
    final amountCtrl = TextEditingController(
      text: currentBalance > 0 ? currentBalance.toString() : '',
    );
    final notesCtrl = TextEditingController();
    final cashAccount = _cashAccounts.isEmpty ? null : _cashAccounts.firstWhere(
      (account) => account.isDefault,
      orElse: () => _cashAccounts.first,
    );
    final selectedAccountId = cashAccount?.id;
    String? paymentError;
    final opId = OperationId.generate();
    bool submitting = false;
    bool confirmAdvance = false;

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setDlgState) {
          return AlertDialog(
            backgroundColor: const Color(0xFF1E293B),
            title: Text(
              isCustomer
                  ? 'Mijozdan To\'lov Qabul Qilish'
                  : 'Ta\'minotchiga Qarz To\'lash',
              style: const TextStyle(color: Colors.white, fontSize: 16),
            ),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Taraf: $partyName',
                    style: const TextStyle(
                      color: Colors.cyanAccent,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Joriy qarz: ${Formatters.formatMoney(currentBalance)}',
                    style: TextStyle(
                      color: currentBalance > 0
                          ? Colors.redAccent
                          : Colors.greenAccent,
                      fontSize: 13,
                    ),
                  ),
                  const Divider(color: Color(0xFF334155), height: 20),

                  // Amount
                  TextField(
                    controller: amountCtrl,
                    onChanged: (_) => setDlgState(() {}),
                    keyboardType: TextInputType.number,
                    style: const TextStyle(color: Colors.white),
                    decoration: const InputDecoration(
                      labelText: 'To\'lov summasi (so\'m) *',
                      labelStyle: TextStyle(color: Colors.blueGrey),
                      filled: true,
                      fillColor: Color(0xFF0F172A),
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 12),

                  Text('Kassada hozir: ${Formatters.formatMoney(cashAccount?.balance ?? 0)}', style: const TextStyle(color: Colors.white)),
                  const SizedBox(height: 12),
                  if (paymentError != null) ...[
                    Text(paymentError!, style: const TextStyle(color: Colors.redAccent)),
                    const SizedBox(height: 12),
                  ],
                  if ((int.tryParse(amountCtrl.text) ?? 0) >
                      (currentBalance > 0 ? currentBalance : 0))
                    CheckboxListTile(
                      title: const Text(
                        'Ortiqcha summani avans sifatida tasdiqlayman',
                      ),
                      value: confirmAdvance,
                      onChanged: submitting
                          ? null
                          : (value) => setDlgState(
                              () => confirmAdvance = value ?? false,
                            ),
                    ),

                  // Notes
                  TextField(
                    controller: notesCtrl,
                    style: const TextStyle(color: Colors.white),
                    decoration: const InputDecoration(
                      labelText: 'Izoh (ixtiyoriy)',
                      labelStyle: TextStyle(color: Colors.blueGrey),
                      filled: true,
                      fillColor: Color(0xFF0F172A),
                      border: OutlineInputBorder(),
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
                onPressed: submitting
                    ? null
                    : () async {
                        if (submitting) return;
                        final messenger = ScaffoldMessenger.of(context);
                        final amount =
                            int.tryParse(amountCtrl.text.trim()) ?? 0;
                        if (amount <= 0) {
                          setDlgState(() => paymentError = 'Summani butun so‘mda, 0 dan katta qilib yozing.');
                          return;
                        }
                        if (!isCustomer && amount > (cashAccount?.balance ?? 0)) {
                          setDlgState(() => paymentError = 'Kassada pul yetmaydi. Summani kamaytiring yoki kassaga pul qo‘shing.');
                          return;
                        }
                        setDlgState(() => paymentError = null);

                        setDlgState(() => submitting = true);

                        try {
                          await _api.createPayment(
                            type: isCustomer ? 'customer' : 'supplier',
                            partyId: partyId,
                            amount: amount,
                            cashAccountId: selectedAccountId,
                            paymentMethod: 'CASH',
                            operationId: opId,
                            confirmExcessAsAdvance: confirmAdvance,
                            notes: notesCtrl.text.trim().isEmpty
                                ? null
                                : notesCtrl.text.trim(),
                          );

                          if (ctx.mounted) {
                            Navigator.pop(ctx);
                          }
                          messenger.showSnackBar(
                            const SnackBar(
                              content: Text(
                                'To\'lov muvaffaqiyatli qabul qilindi!',
                              ),
                              backgroundColor: Colors.green,
                            ),
                          );
                          if (mounted) {
                            _loadData();
                          }
                        } catch (e) {
                          if (ctx.mounted) {
                            setDlgState(() { submitting = false; paymentError = 'To‘lov saqlanmadi: $e'; });
                          }
                        }
                      },
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.blueAccent,
                ),
                child: const Text('Tasdiqlash'),
              ),
            ],
          );
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final filteredCustomers = _customers.where((c) {
      if (_searchQuery.trim().isEmpty) return true;
      final q = _searchQuery.toLowerCase();
      return c.name.toLowerCase().contains(q) ||
          (c.storeName?.toLowerCase().contains(q) ?? false) ||
          (c.phone?.contains(q) ?? false);
    }).toList();

    final filteredSuppliers = _suppliers.where((s) {
      if (_searchQuery.trim().isEmpty) return true;
      final q = _searchQuery.toLowerCase();
      return s.name.toLowerCase().contains(q) ||
          (s.companyName?.toLowerCase().contains(q) ?? false) ||
          (s.phone?.contains(q) ?? false);
    }).toList();

    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      appBar: AppBar(
        title: const Text('Qarzlar va To\'lovlar'),
        backgroundColor: const Color(0xFF1E293B),
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: Colors.cyanAccent,
          tabs: const [
            Tab(text: 'Mijozlar Qarzi'),
            Tab(text: 'Ta\'minotchilarga Qarz'),
          ],
        ),
      ),
      body: RefreshIndicator(
        onRefresh: _loadData,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(12),
              child: TextField(
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  hintText: 'Qidirish (ism, do\'kon, telefon)...',
                  hintStyle: const TextStyle(color: Colors.blueGrey),
                  prefixIcon: const Icon(
                    Icons.search,
                    color: Colors.blueAccent,
                  ),
                  filled: true,
                  fillColor: const Color(0xFF1E293B),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(10),
                    borderSide: const BorderSide(color: Color(0xFF334155)),
                  ),
                ),
                onChanged: (val) => setState(() => _searchQuery = val),
              ),
            ),
            if (_errorMessage != null)
              Padding(
                padding: const EdgeInsets.all(12),
                child: Text(
                  _errorMessage!,
                  style: const TextStyle(color: Colors.redAccent),
                ),
              ),
            Expanded(
              child: _isLoading
                  ? const Center(child: CircularProgressIndicator())
                  : TabBarView(
                      controller: _tabController,
                      children: [
                        // Customer Debts Tab
                        filteredCustomers.isEmpty
                            ? const Center(
                                child: Text(
                                  'Mijozlar topilmadi',
                                  style: TextStyle(color: Colors.blueGrey),
                                ),
                              )
                            : ListView.builder(
                                padding: const EdgeInsets.all(12),
                                itemCount: filteredCustomers.length,
                                itemBuilder: (ctx, idx) {
                                  final c = filteredCustomers[idx];
                                  final hasDebt = c.currentDebt > 0;
                                  return Card(
                                    color: const Color(0xFF1E293B),
                                    margin: const EdgeInsets.only(bottom: 8),
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(10),
                                      side: const BorderSide(
                                        color: Color(0xFF334155),
                                      ),
                                    ),
                                    child: ListTile(
                                      title: Text(
                                        c.name,
                                        style: const TextStyle(
                                          color: Colors.white,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                                      subtitle: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          if (c.storeName != null &&
                                              c.storeName!.isNotEmpty)
                                            Text(
                                              c.storeName!,
                                              style: const TextStyle(
                                                color: Colors.blueGrey,
                                                fontSize: 12,
                                              ),
                                            ),
                                          if (c.phone != null)
                                            Text(
                                              c.phone!,
                                              style: const TextStyle(
                                                color: Colors.blueGrey,
                                                fontSize: 12,
                                              ),
                                            ),
                                          if (c.debtLimit > 0)
                                            Text(
                                              'Limit: ${Formatters.formatMoney(c.debtLimit)}',
                                              style: const TextStyle(
                                                color: Colors.grey,
                                                fontSize: 11,
                                              ),
                                            ),
                                        ],
                                      ),
                                      trailing: Column(
                                        mainAxisAlignment:
                                            MainAxisAlignment.center,
                                        crossAxisAlignment:
                                            CrossAxisAlignment.end,
                                        children: [
                                          Text(
                                            Formatters.formatMoney(
                                              c.currentDebt,
                                            ),
                                            style: TextStyle(
                                              color: hasDebt
                                                  ? Colors.redAccent
                                                  : (c.currentDebt < 0
                                                        ? Colors.greenAccent
                                                        : Colors.grey),
                                              fontWeight: FontWeight.bold,
                                              fontSize: 14,
                                            ),
                                          ),
                                          const SizedBox(height: 4),
                                          ElevatedButton(
                                            onPressed: () => _showPaymentDialog(
                                              isCustomer: true,
                                              partyId: c.id,
                                              partyName: c.name,
                                              currentBalance: c.currentDebt,
                                            ),
                                            style: ElevatedButton.styleFrom(
                                              backgroundColor:
                                                  Colors.blueAccent,
                                              padding:
                                                  const EdgeInsets.symmetric(
                                                    horizontal: 10,
                                                    vertical: 4,
                                                  ),
                                              minimumSize: Size.zero,
                                            ),
                                            child: const Text(
                                              'To\'lov',
                                              style: TextStyle(fontSize: 11),
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  );
                                },
                              ),

                        // Supplier Payables Tab
                        filteredSuppliers.isEmpty
                            ? const Center(
                                child: Text(
                                  'Ta\'minotchilar topilmadi',
                                  style: TextStyle(color: Colors.blueGrey),
                                ),
                              )
                            : ListView.builder(
                                padding: const EdgeInsets.all(12),
                                itemCount: filteredSuppliers.length,
                                itemBuilder: (ctx, idx) {
                                  final s = filteredSuppliers[idx];
                                  final hasDebt = s.balance > 0;
                                  return Card(
                                    color: const Color(0xFF1E293B),
                                    margin: const EdgeInsets.only(bottom: 8),
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(10),
                                      side: const BorderSide(
                                        color: Color(0xFF334155),
                                      ),
                                    ),
                                    child: ListTile(
                                      title: Text(
                                        s.name,
                                        style: const TextStyle(
                                          color: Colors.white,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                                      subtitle: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          if (s.companyName != null &&
                                              s.companyName!.isNotEmpty)
                                            Text(
                                              s.companyName!,
                                              style: const TextStyle(
                                                color: Colors.blueGrey,
                                                fontSize: 12,
                                              ),
                                            ),
                                          if (s.phone != null)
                                            Text(
                                              s.phone!,
                                              style: const TextStyle(
                                                color: Colors.blueGrey,
                                                fontSize: 12,
                                              ),
                                            ),
                                        ],
                                      ),
                                      trailing: Column(
                                        mainAxisAlignment:
                                            MainAxisAlignment.center,
                                        crossAxisAlignment:
                                            CrossAxisAlignment.end,
                                        children: [
                                          Text(
                                            Formatters.formatMoney(s.balance),
                                            style: TextStyle(
                                              color: hasDebt
                                                  ? Colors.redAccent
                                                  : (s.balance < 0
                                                        ? Colors.greenAccent
                                                        : Colors.grey),
                                              fontWeight: FontWeight.bold,
                                              fontSize: 14,
                                            ),
                                          ),
                                          const SizedBox(height: 4),
                                          ElevatedButton(
                                            onPressed: () => _showPaymentDialog(
                                              isCustomer: false,
                                              partyId: s.id,
                                              partyName: s.name,
                                              currentBalance: s.balance,
                                            ),
                                            style: ElevatedButton.styleFrom(
                                              backgroundColor: Colors.teal,
                                              padding:
                                                  const EdgeInsets.symmetric(
                                                    horizontal: 10,
                                                    vertical: 4,
                                                  ),
                                              minimumSize: Size.zero,
                                            ),
                                            child: const Text(
                                              'To\'lash',
                                              style: TextStyle(fontSize: 11),
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  );
                                },
                              ),
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
