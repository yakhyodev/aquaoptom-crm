class DashboardFlow {
  final int totalSales;
  final int paidAtPos;
  final int newDebt;
  final int totalInflow;
  final int debtCollected;
  final int totalOutflow;
  final int supplierPaid;
  final int expenses;
  final int? grossProfit;
  final int? netOperating;
  final int ordersCount;

  const DashboardFlow({
    this.totalSales = 0,
    this.paidAtPos = 0,
    this.newDebt = 0,
    this.totalInflow = 0,
    this.debtCollected = 0,
    this.totalOutflow = 0,
    this.supplierPaid = 0,
    this.expenses = 0,
    this.grossProfit,
    this.netOperating,
    this.ordersCount = 0,
  });

  factory DashboardFlow.fromJson(Map<String, dynamic> json, bool canViewCost) {
    return DashboardFlow(
      totalSales: (json['total_sales'] as num?)?.toInt() ?? 0,
      paidAtPos: (json['paid_at_pos'] as num?)?.toInt() ?? 0,
      newDebt: (json['new_debt'] as num?)?.toInt() ?? 0,
      totalInflow: (json['total_inflow'] as num?)?.toInt() ?? 0,
      debtCollected: (json['debt_collected'] as num?)?.toInt() ?? 0,
      totalOutflow: (json['total_outflow'] as num?)?.toInt() ?? 0,
      supplierPaid: (json['supplier_paid'] as num?)?.toInt() ?? 0,
      expenses: (json['expenses'] as num?)?.toInt() ?? 0,
      grossProfit: canViewCost && json['gross_profit'] != null
          ? (json['gross_profit'] as num).toInt()
          : null,
      netOperating: canViewCost && json['net_operating'] != null
          ? (json['net_operating'] as num).toInt()
          : null,
      ordersCount: (json['orders_count'] as num?)?.toInt() ?? 0,
    );
  }
}

class DashboardBalances {
  final int cashTotal;
  final int cashInHand;
  final int cardTotal;
  final int bankTotal;
  final int customerDebts;
  final int supplierPayables;
  final int stockTotalUnits;
  final int? stockCostValue;
  final int stockPotentialRevenue;

  const DashboardBalances({
    this.cashTotal = 0,
    this.cashInHand = 0,
    this.cardTotal = 0,
    this.bankTotal = 0,
    this.customerDebts = 0,
    this.supplierPayables = 0,
    this.stockTotalUnits = 0,
    this.stockCostValue,
    this.stockPotentialRevenue = 0,
  });

  factory DashboardBalances.fromJson(Map<String, dynamic> json, bool canViewCost) {
    final cashAccounts = json['cash_accounts'] as Map<String, dynamic>? ?? {};
    final byType = cashAccounts['by_type'] as Map<String, dynamic>? ?? {};
    final cust = json['customer_receivables'] as Map<String, dynamic>? ?? {};
    final supp = json['supplier_payables'] as Map<String, dynamic>? ?? {};
    final stock = json['stock_valuation'] as Map<String, dynamic>? ?? {};

    return DashboardBalances(
      cashTotal: (cashAccounts['total'] as num?)?.toInt() ?? 0,
      cashInHand: (byType['CASH'] as num?)?.toInt() ?? 0,
      cardTotal: (byType['CARD'] as num?)?.toInt() ?? 0,
      bankTotal: (byType['BANK'] as num?)?.toInt() ?? 0,
      customerDebts: (cust['total_debt'] as num?)?.toInt() ?? 0,
      supplierPayables: (supp['total_payable'] as num?)?.toInt() ?? 0,
      stockTotalUnits: (stock['total_units'] as num?)?.toInt() ?? 0,
      stockCostValue: canViewCost && stock['total_cost_value'] != null
          ? (stock['total_cost_value'] as num).toInt()
          : null,
      stockPotentialRevenue:
          (stock['total_potential_revenue'] as num?)?.toInt() ?? 0,
    );
  }
}

class DashboardWarnings {
  final int lowStockCount;
  final int offlineDevicesCount;
  final int pendingConflictsCount;
  final bool isFullySynced;

  const DashboardWarnings({
    this.lowStockCount = 0,
    this.offlineDevicesCount = 0,
    this.pendingConflictsCount = 0,
    this.isFullySynced = true,
  });

  factory DashboardWarnings.fromJson(Map<String, dynamic> json) {
    return DashboardWarnings(
      lowStockCount: (json['low_stock_count'] as num?)?.toInt() ?? 0,
      offlineDevicesCount:
          (json['offline_devices_count'] as num?)?.toInt() ?? 0,
      pendingConflictsCount:
          (json['pending_conflicts_count'] as num?)?.toInt() ?? 0,
      isFullySynced: json['is_fully_synced'] as bool? ?? true,
    );
  }
}

class DashboardData {
  final String periodKey;
  final String periodLabel;
  final bool canViewCost;
  final DashboardFlow flow;
  final DashboardBalances balances;
  final DashboardWarnings warnings;
  final String asOfTime;

  const DashboardData({
    required this.periodKey,
    required this.periodLabel,
    required this.canViewCost,
    required this.flow,
    required this.balances,
    required this.warnings,
    required this.asOfTime,
  });

  factory DashboardData.fromJson(Map<String, dynamic> json) {
    final period = json['period'] as Map<String, dynamic>? ?? {};
    final canViewCost = json['can_view_cost'] as bool? ?? false;

    return DashboardData(
      periodKey: period['key'] as String? ?? 'today',
      periodLabel: period['label'] as String? ?? 'Bugun',
      canViewCost: canViewCost,
      flow: DashboardFlow.fromJson(
        json['flow'] as Map<String, dynamic>? ?? {},
        canViewCost,
      ),
      balances: DashboardBalances.fromJson(
        json['balances'] as Map<String, dynamic>? ?? {},
        canViewCost,
      ),
      warnings: DashboardWarnings.fromJson(
        json['warnings'] as Map<String, dynamic>? ?? {},
      ),
      asOfTime: json['as_of_time'] as String? ?? '',
    );
  }
}
