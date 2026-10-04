class SalesReport {
  final int totalGrossSales;
  final int totalInitialPaid;
  final int totalInitialDebt;
  final int totalOrdersCount;
  final int cashAtPos;
  final int cardAtPos;
  final int bankAtPos;
  final int totalUnitsSold;
  final int totalReturnsAmount;

  const SalesReport({
    this.totalGrossSales = 0,
    this.totalInitialPaid = 0,
    this.totalInitialDebt = 0,
    this.totalOrdersCount = 0,
    this.cashAtPos = 0,
    this.cardAtPos = 0,
    this.bankAtPos = 0,
    this.totalUnitsSold = 0,
    this.totalReturnsAmount = 0,
  });

  factory SalesReport.fromJson(Map<String, dynamic> json) {
    return SalesReport(
      totalGrossSales: (json['total_gross_sales'] as num?)?.toInt() ?? 0,
      totalInitialPaid: (json['total_initial_paid'] as num?)?.toInt() ?? 0,
      totalInitialDebt: (json['total_initial_debt'] as num?)?.toInt() ?? 0,
      totalOrdersCount: (json['total_orders_count'] as num?)?.toInt() ?? 0,
      cashAtPos: (json['cash_at_pos'] as num?)?.toInt() ?? 0,
      cardAtPos: (json['card_at_pos'] as num?)?.toInt() ?? 0,
      bankAtPos: (json['bank_at_pos'] as num?)?.toInt() ?? 0,
      totalUnitsSold: (json['total_units_sold'] as num?)?.toInt() ?? 0,
      totalReturnsAmount: (json['total_returns_amount'] as num?)?.toInt() ?? 0,
    );
  }
}

class CashReport {
  final int initialBalance;
  final int totalInflow;
  final int totalOutflow;
  final int closingBalance;

  const CashReport({
    this.initialBalance = 0,
    this.totalInflow = 0,
    this.totalOutflow = 0,
    this.closingBalance = 0,
  });

  factory CashReport.fromJson(Map<String, dynamic> json) {
    return CashReport(
      initialBalance: (json['initial_balance'] as num?)?.toInt() ?? 0,
      totalInflow: (json['total_inflow'] as num?)?.toInt() ?? 0,
      totalOutflow: (json['total_outflow'] as num?)?.toInt() ?? 0,
      closingBalance: (json['closing_balance'] as num?)?.toInt() ?? 0,
    );
  }
}

class ReportsData {
  final SalesReport sales;
  final CashReport cash;

  const ReportsData({
    required this.sales,
    required this.cash,
  });

  factory ReportsData.fromJson(Map<String, dynamic> json) {
    return ReportsData(
      sales: SalesReport.fromJson(json['sales'] as Map<String, dynamic>? ?? {}),
      cash: CashReport.fromJson(json['cash'] as Map<String, dynamic>? ?? {}),
    );
  }
}
