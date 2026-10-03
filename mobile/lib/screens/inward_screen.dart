import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../services/api_service.dart';

class InwardScreen extends StatefulWidget {
  const InwardScreen({super.key});

  @override
  State<InwardScreen> createState() => _InwardScreenState();
}

class _InwardScreenState extends State<InwardScreen> {
  final _nameController = TextEditingController();
  final _qtyController = TextEditingController(text: '150');
  final _costController = TextEditingController(text: '5000');

  double _selectedLitre = 0.5;
  final List<double> _availableLitres = [0.25, 0.5, 1.0, 1.5, 5.0, 18.9];

  bool _isLoading = false;
  final _moneyFormat = NumberFormat('#,###', 'uz_UZ');

  double get _totalSum {
    final qty = int.tryParse(_qtyController.text) ?? 0;
    final cost = double.tryParse(_costController.text) ?? 0;
    return qty * cost;
  }

  Future<void> _submitInward() async {
    final name = _nameController.text.trim();
    final qty = int.tryParse(_qtyController.text) ?? 0;
    final cost = double.tryParse(_costController.text) ?? 0;

    if (name.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Iltimos, mahsulot nomini kiriting!')),
      );
      return;
    }

    if (qty <= 0 || cost <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Miqdor va narx noldan katta bo\'lishi kerak!')),
      );
      return;
    }

    setState(() => _isLoading = true);

    final success = await ApiService.quickInward(
      name: name,
      litres: _selectedLitre,
      quantity: qty,
      costPrice: cost,
    );

    setState(() => _isLoading = false);

    if (success && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          backgroundColor: Colors.teal,
          content: Text('✓ Kirim qabul qilindi: $name ${_selectedLitre}L ($qty dona)'),
        ),
      );
      _nameController.clear();
      _qtyController.text = '150';
      _costController.text = '5000';
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _qtyController.dispose();
    _costController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      appBar: AppBar(
        title: const Text('Tezkor Kirim (Yuk Kelishi)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        backgroundColor: const Color(0xFF1E293B),
        elevation: 0,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header card
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: const Color(0xFF1E293B),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFF334155)),
              ),
              child: const Row(
                children: [
                  Text('🚚', style: TextStyle(fontSize: 32)),
                  SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Yangi Mahsulot Kirimi', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
                        Text('Nomi yozilsa yangi ID bilan yaratiladi', style: TextStyle(color: Colors.grey, fontSize: 12)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Mahsulot nomi
            const Text('1. Mahsulot Nomi', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
            const SizedBox(height: 6),
            TextField(
              controller: _nameController,
              style: const TextStyle(color: Colors.white),
              decoration: InputDecoration(
                hintText: 'Masalan: Fanta, Coca-Cola, Chortoq...',
                hintStyle: TextStyle(color: Colors.grey[600]),
                filled: true,
                fillColor: const Color(0xFF1E293B),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
              ),
            ),
            const SizedBox(height: 16),

            // Litr / Hajm
            const Text('2. Idish Hajmi (Litri)', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
            const SizedBox(height: 6),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: _availableLitres.map((litre) {
                final isSelected = _selectedLitre == litre;
                return ChoiceChip(
                  label: Text('${litre}L', style: TextStyle(color: isSelected ? Colors.white : Colors.grey[400], fontWeight: FontWeight.bold)),
                  selected: isSelected,
                  selectedColor: Colors.blue[600],
                  backgroundColor: const Color(0xFF1E293B),
                  onSelected: (val) {
                    if (val) setState(() => _selectedLitre = litre);
                  },
                );
              }).toList(),
            ),
            const SizedBox(height: 16),

            // Soni va Kirim Narxi
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('3. Miqdor (dona)', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
                      const SizedBox(height: 6),
                      TextField(
                        controller: _qtyController,
                        keyboardType: TextInputType.number,
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                        onChanged: (_) => setState(() {}),
                        decoration: InputDecoration(
                          hintText: '150',
                          filled: true,
                          fillColor: const Color(0xFF1E293B),
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('4. Kirim Narxi (so\'m)', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
                      const SizedBox(height: 6),
                      TextField(
                        controller: _costController,
                        keyboardType: TextInputType.number,
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                        onChanged: (_) => setState(() {}),
                        decoration: InputDecoration(
                          hintText: '5000',
                          filled: true,
                          fillColor: const Color(0xFF1E293B),
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 20),

            // Jami Partiya Summasi
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.blue.withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: Colors.blue.withValues(alpha: 0.3)),
              ),

              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Jami kirim summasi:', style: TextStyle(color: Colors.blueAccent, fontSize: 13)),
                  Text(
                    '${_moneyFormat.format(_totalSum)} so\'m',
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 18),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            // Submit Button
            SizedBox(
              width: double.infinity,
              height: 50,
              child: ElevatedButton.icon(
                onPressed: _isLoading ? null : _submitInward,
                icon: _isLoading ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)) : const Icon(Icons.check),
                label: const Text('KIRIMNI SAQLASH', style: TextStyle(fontWeight: FontWeight.bold)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.blue[600],
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
