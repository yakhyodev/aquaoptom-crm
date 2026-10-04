import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../config/app_config.dart';
import '../services/session_service.dart';

class AdminWebScreen extends StatelessWidget {
  const AdminWebScreen({super.key});

  void _copyLink(BuildContext context, String path) {
    final fullUrl = '${AppConfig.webAdminUrl}$path';
    Clipboard.setData(ClipboardData(text: fullUrl));
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text("Havola nusxalandi: $fullUrl"),
        backgroundColor: Colors.teal,
        duration: const Duration(seconds: 2),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final token = SessionService().token ?? '';
    final maskedToken = token.length > 10
        ? '${token.substring(0, 6)}...${token.substring(token.length - 4)}'
        : 'Mavjud emas';

    final adminLinks = [
      {
        'title': 'Foydalanuvchilar va ruxsatlar',
        'desc': 'Yangi xodimlar ochish, rollar, parollarni yangilash',
        'icon': Icons.people_outline,
        'path': '/users',
      },
      {
        'title': 'Qurilmalar va offline ajratmalar',
        'desc': 'POS terminallarini tasdiqlash, kassa limitlari va lizing',
        'icon': Icons.devices_outlined,
        'path': '/devices',
      },
      {
        'title': 'Audit jurnali va xavfsizlik',
        'desc': 'Barcha amallar, loglar va IP manzillar tarixi',
        'icon': Icons.security_outlined,
        'path': '/audit',
      },
      {
        'title': 'NEEDS_REVIEW va ziddiyatlar',
        'desc': 'Offline sinxronizatsiya ziddiyatlarini hal qilish',
        'icon': Icons.rule_outlined,
        'path': '/conflicts',
      },
      {
        'title': 'Tizim sozlamalari',
        'desc': 'Minimal qoldiq chegarasi, vaqt mintaqasi, kassa sozlamalari',
        'icon': Icons.settings_outlined,
        'path': '/settings',
      },
    ];

    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      appBar: AppBar(
        title: const Text('Admin Boshqaruvi (Web Linklar)'),
        backgroundColor: const Color(0xFF1E293B),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16.0),
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: const Color(0xFF1E293B),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFF334155)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Row(
                  children: [
                    Icon(Icons.shield_outlined, color: Colors.blueAccent),
                    SizedBox(width: 8),
                    Text(
                      'Xavfsiz Web Havolalar',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                const Text(
                  'Murakkab ma\'muriy amallar, foydalanuvchilar audit va ziddiyatlar yechimi xavfsizlik nuqtai nazaridan himoyalangan Web boshqaruv panelida amalga oshiriladi.',
                  style: TextStyle(fontSize: 13, color: Colors.blueGrey),
                ),
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  decoration: BoxDecoration(
                    color: const Color(0xFF0F172A),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Joriy sessiya:',
                        style: TextStyle(fontSize: 12, color: Colors.grey),
                      ),
                      Text(
                        maskedToken,
                        style: const TextStyle(
                          fontSize: 12,
                          color: Colors.cyanAccent,
                          fontFamily: 'monospace',
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          ...adminLinks.map(
            (link) => Card(
              color: const Color(0xFF1E293B),
              margin: const EdgeInsets.only(bottom: 12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
                side: const BorderSide(color: Color(0xFF334155)),
              ),
              child: ListTile(
                leading: Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: Colors.blueAccent.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(link['icon'] as IconData, color: Colors.blueAccent),
                ),
                title: Text(
                  link['title'] as String,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                subtitle: Text(
                  link['desc'] as String,
                  style: const TextStyle(color: Colors.blueGrey, fontSize: 12),
                ),
                trailing: IconButton(
                  icon: const Icon(Icons.copy_rounded, color: Colors.cyanAccent),
                  tooltip: 'Havolani nusxalash',
                  onPressed: () => _copyLink(context, link['path'] as String),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
