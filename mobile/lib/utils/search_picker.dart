import 'package:flutter/material.dart';

Future<T?> chooseFromList<T>(
  BuildContext context, {
  required String title,
  required List<T> items,
  required String Function(T) labelFor,
}) async {
  final search = TextEditingController();
  final sorted = [...items]
    ..sort(
      (a, b) => labelFor(a).toLowerCase().compareTo(labelFor(b).toLowerCase()),
    );
  try {
    return await showModalBottomSheet<T>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: SizedBox(
          height: MediaQuery.sizeOf(context).height * .72,
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.all(16),
                child: TextField(
                  controller: search,
                  autofocus: true,
                  decoration: InputDecoration(
                    labelText: title,
                    hintText: 'Nom, telefon yoki do‘konni yozing',
                    prefixIcon: const Icon(Icons.search),
                    border: const OutlineInputBorder(),
                  ),
                ),
              ),
              Expanded(
                child: ValueListenableBuilder<TextEditingValue>(
                  valueListenable: search,
                  builder: (context, value, _) {
                    final words = value.text.toLowerCase().trim().split(
                      RegExp(r'\s+'),
                    );
                    final matches = sorted
                        .where(
                          (item) => words.every(
                            (word) =>
                                labelFor(item).toLowerCase().contains(word),
                          ),
                        )
                        .toList();
                    if (matches.isEmpty) {
                      return const Center(child: Text('Mos yozuv topilmadi'));
                    }
                    return ListView.builder(
                      itemCount: matches.length,
                      itemBuilder: (context, index) => ListTile(
                        title: Text(labelFor(matches[index])),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () => Navigator.pop(context, matches[index]),
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  } finally {
    search.dispose();
  }
}

class SearchPicker<T> extends StatelessWidget {
  final List<T> items;
  final T? initialValue;
  final String label;
  final String Function(T) labelFor;
  final ValueChanged<T?> onChanged;
  const SearchPicker({
    super.key,
    required this.items,
    this.initialValue,
    required this.label,
    required this.labelFor,
    required this.onChanged,
  });
  @override
  Widget build(BuildContext context) => InkWell(
    onTap: () async {
      final selected = await chooseFromList(
        context,
        title: label,
        items: items,
        labelFor: labelFor,
      );
      if (selected != null) onChanged(selected);
    },
    child: InputDecorator(
      decoration: InputDecoration(
        labelText: label,
        border: const OutlineInputBorder(),
        suffixIcon: const Icon(Icons.search),
      ),
      child: Text(
        initialValue == null
            ? 'Qidirish va tanlash'
            : labelFor(initialValue as T),
      ),
    ),
  );
}
