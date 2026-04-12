import { useState, useEffect } from "react";
import { motion } from "framer-motion";
import { Download, Calendar, TrendingUp, Users, CheckCircle2, Box, RotateCcw, AlertCircle, Filter, Clock, Zap } from "lucide-react";
import { AppLayout } from "@/components/layout/AppLayout";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  Tooltip,
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell,
} from "recharts";

interface ReportData {
  kpis: {
    totalCompleted: { value: string; change: string };
    activeUsers: { value: string; change: string };
    resourceUtilization: { value: string; change: string };
    avgCompletionTime: { value: string; change: string };
  };
  period: {
    startDate: string;
    endDate: string;
    label: string;
  };
  quickMetrics: Array<{
    title: string;
    value: string;
    hint: string;
  }>;
  monthlyTasks: Array<{ name: string; completed: number; created: number }>;
  userPerformance: Array<{ name: string; tasks: number }>;
  resourceUtilization: Array<{ name: string; value: number; color: string }>;
  advancedReports?: {
    complexityVsTime: {
      avgDaysByPriority: { low: number; medium: number; high: number };
      outlierCount: number;
      complexityDelayTrend: number;
    };
  };
}

const getDefaultDateRange = () => {
  const endDate = new Date();
  const startDate = new Date(endDate.getFullYear(), endDate.getMonth() - 11, 1);

  const formatDate = (date: Date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  };

  return {
    startDate: formatDate(startDate),
    endDate: formatDate(endDate),
  };
};

export default function Reports() {
  const defaults = getDefaultDateRange();
  const [data, setData] = useState<ReportData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isExporting, setIsExporting] = useState(false);
  const [isMembersDialogOpen, setIsMembersDialogOpen] = useState(false);
  const [errorMessage, setErrorMessage] = useState("");
  const [startDate, setStartDate] = useState(defaults.startDate);
  const [endDate, setEndDate] = useState(defaults.endDate);
  const [isFilterPanelOpen, setIsFilterPanelOpen] = useState(false);
  const [draftStartDate, setDraftStartDate] = useState(defaults.startDate);
  const [draftEndDate, setDraftEndDate] = useState(defaults.endDate);
  const [statusFilter, setStatusFilter] = useState("all");
  const [priorityFilter, setPriorityFilter] = useState("all");

  const [draftStatusFilter, setDraftStatusFilter] = useState("all");
  const [draftPriorityFilter, setDraftPriorityFilter] = useState("all");

  const isDateRangeInvalid = startDate > endDate;
  const isDraftDateRangeInvalid = draftStartDate > draftEndDate;

  useEffect(() => {
    if (isDateRangeInvalid) {
      return;
    }
    fetchReportsData();
  }, [startDate, endDate, statusFilter, priorityFilter]);

  const fetchReportsData = async () => {
    setIsLoading(true);
    setErrorMessage("");

    try {
      const user = JSON.parse(localStorage.getItem("user") || "{}");
      const params = new URLSearchParams({
        start_date: startDate,
        end_date: endDate,
        status: statusFilter,
        priority: priorityFilter,
      });

      if (user?.id) {
        params.append("user_id", String(user.id));
      }

      const response = await fetch(`http://localhost:8000/backend/reports.php?${params.toString()}`, {
        headers: {
          Authorization: localStorage.getItem("token") || "",
        },
      });

      if (!response.ok) {
        throw new Error(`Failed request with status ${response.status}`);
      }

      const result = await response.json();

      if (result.success) {
        setData(result);
      } else {
        throw new Error(result.message || "Failed to load reports data");
      }
    } catch (error) {
      console.error("Failed to fetch reports data:", error);
      setErrorMessage(error instanceof Error ? error.message : "Failed to fetch reports data");
    } finally {
      setIsLoading(false);
    }
  };

  const handleOpenFilterPanel = () => {
    setDraftStartDate(startDate);
    setDraftEndDate(endDate);
    setDraftStatusFilter(statusFilter);
    setDraftPriorityFilter(priorityFilter);
    setIsFilterPanelOpen(true);
  };

  const handleApplyDateFilters = () => {
    if (isDraftDateRangeInvalid) return;
    setStartDate(draftStartDate);
    setEndDate(draftEndDate);
    setStatusFilter(draftStatusFilter);
    setPriorityFilter(draftPriorityFilter);
    setIsFilterPanelOpen(false);
  };

  const handleResetDraftDateRange = () => {
    const range = getDefaultDateRange();
    setDraftStartDate(range.startDate);
    setDraftEndDate(range.endDate);
    setDraftStatusFilter("all");
    setDraftPriorityFilter("all");
    setStartDate(range.startDate);
    setEndDate(range.endDate);
    setStatusFilter("all");
    setPriorityFilter("all");
    setIsFilterPanelOpen(false);
  };

  const csvEscape = (value: string | number) => {
    const stringValue = String(value ?? "");
    return `"${stringValue.replace(/"/g, '""')}"`;
  };

  const handleExportReport = () => {
    if (!data || isExporting) return;

    setIsExporting(true);

    try {
      const lines: string[] = [];
      const now = new Date();
      const timestamp = now.toISOString();

      lines.push("FlowStone Reports Export");
      lines.push(`Generated At,${csvEscape(timestamp)}`);
      lines.push("");

      lines.push("KPI Summary");
      lines.push("Metric,Value,Change");
      lines.push(`Total Tasks Completed,${csvEscape(data.kpis.totalCompleted.value)},${csvEscape(data.kpis.totalCompleted.change)}`);
      lines.push(`Active Users,${csvEscape(data.kpis.activeUsers.value)},${csvEscape(data.kpis.activeUsers.change)}`);
      lines.push(`Resources Utilized,${csvEscape(data.kpis.resourceUtilization.value)},${csvEscape(data.kpis.resourceUtilization.change)}`);
      lines.push(`Avg Completion Time,${csvEscape(data.kpis.avgCompletionTime.value)},${csvEscape(data.kpis.avgCompletionTime.change)}`);
      lines.push("");

      lines.push("Quick Insights");
      lines.push("Metric,Value,Hint");
      data.quickMetrics.forEach((item) => {
        lines.push(`${csvEscape(item.title)},${csvEscape(item.value)},${csvEscape(item.hint)}`);
      });
      lines.push("");

      lines.push("Monthly Tasks");
      lines.push("Month,Completed,Created");
      data.monthlyTasks.forEach((item) => {
        lines.push(`${csvEscape(item.name)},${item.completed},${item.created}`);
      });
      lines.push("");

      lines.push("User Performance");
      lines.push("User,Tasks");
      data.userPerformance.forEach((item) => {
        lines.push(`${csvEscape(item.name)},${item.tasks}`);
      });
      lines.push("");

      lines.push("Resource Utilization");
      lines.push("Category,UtilizationPercent,Color");
      data.resourceUtilization.forEach((item) => {
        lines.push(`${csvEscape(item.name)},${item.value},${csvEscape(item.color)}`);
      });

      const csvContent = lines.join("\r\n");
      const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
      const url = URL.createObjectURL(blob);

      const yyyy = now.getFullYear();
      const mm = String(now.getMonth() + 1).padStart(2, "0");
      const dd = String(now.getDate()).padStart(2, "0");
      const hh = String(now.getHours()).padStart(2, "0");
      const min = String(now.getMinutes()).padStart(2, "0");
      const fileName = `flowstone-report-${yyyy}${mm}${dd}-${hh}${min}.csv`;

      const link = document.createElement("a");
      link.href = url;
      link.setAttribute("download", fileName);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      URL.revokeObjectURL(url);
    } catch (error) {
      console.error("Failed to export report:", error);
    } finally {
      setIsExporting(false);
    }
  };

  if (isLoading && !data) {
    return (
      <AppLayout title="Reports & Analytics" subtitle="Comprehensive insights into your workflow and resources.">
        <div className="flex items-center justify-center h-64">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
        </div>
      </AppLayout>
    );
  }

  if (!isLoading && !data) {
    return (
      <AppLayout title="Reports & Analytics" subtitle="Comprehensive insights into your workflow and resources.">
        <div className="card-elevated p-6 max-w-2xl mx-auto mt-10">
          <div className="flex items-start gap-3">
            <AlertCircle className="w-5 h-5 text-destructive mt-0.5" />
            <div>
              <h3 className="text-lg font-semibold text-foreground mb-1">Unable to load reports</h3>
              <p className="text-sm text-muted-foreground mb-4">
                {errorMessage || "An unexpected error occurred while fetching analytics."}
              </p>
              <button
                type="button"
                onClick={fetchReportsData}
                className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90"
              >
                Retry
              </button>
            </div>
          </div>
        </div>
      </AppLayout>
    );
  }

  if (!data) {
    return null;
  }

  const kpiData = [
    { title: "Total Tasks Completed", value: data.kpis.totalCompleted.value, change: data.kpis.totalCompleted.change, icon: CheckCircle2 },
    { title: "Active Users", value: data.kpis.activeUsers.value, change: data.kpis.activeUsers.change, icon: Users },
    { title: "Resources Utilized", value: data.kpis.resourceUtilization.value, change: data.kpis.resourceUtilization.change, icon: Box },
    { title: "Avg. Completion Time", value: data.kpis.avgCompletionTime.value, change: data.kpis.avgCompletionTime.change, icon: TrendingUp },
  ];

  const shouldShowMembersPopup = data.userPerformance.length > 12;
  const visiblePerformers = shouldShowMembersPopup ? data.userPerformance.slice(0, 12) : data.userPerformance;

  return (
    <AppLayout title="Reports & Analytics" subtitle="Comprehensive insights into your workflow and resources.">
      {/* Header Actions */}
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
        <div className="flex flex-col sm:flex-row sm:items-center gap-3">
          <div className="flex items-center gap-2 px-4 py-2 bg-card border border-border rounded-xl text-sm w-fit">
            <Calendar className="w-4 h-4 text-muted-foreground" />
            <span className="text-muted-foreground">{data.period?.label || `${startDate} - ${endDate}`}</span>
          </div>
          <div className="relative">
            <motion.button
              whileHover={{ scale: 1.05 }}
              whileTap={{ scale: 0.95 }}
              onClick={handleOpenFilterPanel}
              className="p-2.5 rounded-xl border border-border bg-card text-muted-foreground hover:text-foreground hover:bg-muted transition-all"
              aria-label="Open date filters"
            >
              <Filter className="w-4 h-4" />
            </motion.button>

            {isFilterPanelOpen && (
              <motion.div
                initial={{ opacity: 0, y: -8 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -8 }}
                transition={{ duration: 0.15 }}
                className="absolute top-full mt-2 left-0 z-50 w-[22rem] rounded-xl border border-border bg-card shadow-lg p-4 space-y-4"
              >
                <div className="space-y-2">
                  <label className="text-xs font-semibold text-muted-foreground">Start Date</label>
                  <input
                    type="date"
                    value={draftStartDate}
                    max={draftEndDate}
                    onChange={(e) => setDraftStartDate(e.target.value)}
                    className="w-full px-3 py-2 bg-card border border-border rounded-xl text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50"
                  />
                </div>

                <div className="space-y-2">
                  <label className="text-xs font-semibold text-muted-foreground">End Date</label>
                  <input
                    type="date"
                    value={draftEndDate}
                    min={draftStartDate}
                    onChange={(e) => setDraftEndDate(e.target.value)}
                    className="w-full px-3 py-2 bg-card border border-border rounded-xl text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50"
                  />
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div className="space-y-2">
                    <label className="text-xs font-semibold text-muted-foreground">Status</label>
                    <select
                      value={draftStatusFilter}
                      onChange={(e) => setDraftStatusFilter(e.target.value)}
                      className="w-full px-3 py-2 bg-card border border-border rounded-xl text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50"
                    >
                      <option value="all">All Statuses</option>
                      <option value="pending">Pending</option>
                      <option value="in-progress">In Progress</option>
                      <option value="review">Review</option>
                      <option value="completed">Completed</option>
                    </select>
                  </div>

                  <div className="space-y-2">
                    <label className="text-xs font-semibold text-muted-foreground">Priority</label>
                    <select
                      value={draftPriorityFilter}
                      onChange={(e) => setDraftPriorityFilter(e.target.value)}
                      className="w-full px-3 py-2 bg-card border border-border rounded-xl text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50"
                    >
                      <option value="all">All Priorities</option>
                      <option value="low">Low</option>
                      <option value="medium">Medium</option>
                      <option value="high">High</option>
                    </select>
                  </div>
                </div>

                {isDraftDateRangeInvalid ? (
                  <p className="text-xs text-destructive">Start date must be before end date.</p>
                ) : null}

                <div className="flex items-center justify-end gap-2 border-t border-border pt-3">
                  <button
                    type="button"
                    onClick={handleResetDraftDateRange}
                    className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-muted-foreground hover:bg-muted transition-all"
                  >
                    <RotateCcw className="w-3.5 h-3.5" />
                    Reset
                  </button>
                  <button
                    type="button"
                    onClick={handleApplyDateFilters}
                    disabled={isDraftDateRangeInvalid}
                    className="px-3 py-1.5 rounded-lg text-xs font-medium bg-primary text-primary-foreground hover:bg-primary/90 transition-all disabled:opacity-50"
                  >
                    Apply
                  </button>
                </div>
              </motion.div>
            )}
          </div>
        </div>
        <div className="flex items-center gap-3">
          {isDateRangeInvalid ? (
            <p className="text-xs text-destructive">Start date must be before end date.</p>
          ) : null}
          <motion.button
            whileHover={{ scale: 1.02 }}
            whileTap={{ scale: 0.98 }}
            onClick={handleExportReport}
            disabled={isExporting || !data}
            className="btn-accent-gradient flex items-center gap-2"
          >
            <Download className="w-4 h-4" />
            <span>{isExporting ? "Exporting..." : "Export Report"}</span>
          </motion.button>
        </div>
      </div>

      {isFilterPanelOpen && (
        <div
          className="fixed inset-0 z-30"
          onClick={() => setIsFilterPanelOpen(false)}
        />
      )}

      {errorMessage ? (
        <div className="mb-5 rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning">
          Showing last successful report data. Latest refresh failed: {errorMessage}
        </div>
      ) : null}

      {/* KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        {kpiData.map((kpi, index) => (
          <motion.div
            key={kpi.title}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.1 }}
            className="card-elevated p-5"
          >
            <div className="flex items-center justify-between mb-3">
              <div className="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                <kpi.icon className="w-5 h-5 text-primary" />
              </div>
              <span className={`text-xs font-semibold ${kpi.change.startsWith("-") ? "text-destructive" : "text-success"}`}>
                {kpi.change}
              </span>
            </div>
            <h3 className="text-2xl font-bold text-foreground mb-1">{kpi.value}</h3>
            <p className="text-sm text-muted-foreground">{kpi.title}</p>
          </motion.div>
        ))}
      </div>

      {/* Insights Row - with Icons */}
      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">
        {/* On-Time Completion */}
        <motion.div
          key="on-time"
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.15 }}
          className="card-elevated p-5"
        >
          <div className="flex items-center justify-between mb-3">
            <div className="w-10 h-10 rounded-xl bg-success/10 flex items-center justify-center">
              <CheckCircle2 className="w-5 h-5 text-success" />
            </div>
          </div>
          <p className="text-2xl font-bold text-foreground mb-3">{data.quickMetrics[0]?.value || "0%"}</p>
          <p className="text-xs font-medium text-muted-foreground">{data.quickMetrics[0]?.title || "On-Time Completion"}</p>
        </motion.div>

        {/* Overdue Open Tasks */}
        <motion.div
          key="overdue"
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.21 }}
          className="card-elevated p-5"
        >
          <div className="flex items-center justify-between mb-3">
            <div className="w-10 h-10 rounded-xl bg-destructive/10 flex items-center justify-center">
              <AlertCircle className="w-5 h-5 text-destructive" />
            </div>
          </div>
          <p className="text-2xl font-bold text-foreground mb-3">{data.quickMetrics[1]?.value || "0"}</p>
          <p className="text-xs font-medium text-muted-foreground">{data.quickMetrics[1]?.title || "Overdue Open Tasks"}</p>
        </motion.div>

        {/* Work In Progress */}
        <motion.div
          key="wip"
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.27 }}
          className="card-elevated p-5"
        >
          <div className="flex items-center justify-between mb-3">
            <div className="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
              <Zap className="w-5 h-5 text-primary" />
            </div>
          </div>
          <p className="text-2xl font-bold text-foreground mb-3">{data.quickMetrics[2]?.value || "0"}</p>
          <p className="text-xs font-medium text-muted-foreground">{data.quickMetrics[2]?.title || "Work In Progress"}</p>
        </motion.div>

        {/* Task Complexity */}
        {data.advancedReports ? (
          <motion.div
            key="complexity"
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.34 }}
            className="card-elevated p-5"
          >
            <div className="flex items-center justify-between mb-3">
              <div className="w-10 h-10 rounded-xl bg-warning/10 flex items-center justify-center">
                <Clock className="w-5 h-5 text-warning" />
              </div>
            </div>
            <div className="grid grid-cols-3 gap-2 mb-4">
              <div className="text-center">
                <span className="text-xs font-medium text-muted-foreground block">Low</span>
                <span className="text-lg font-bold text-foreground">{data.advancedReports.complexityVsTime.avgDaysByPriority.low}d</span>
              </div>
              <div className="text-center">
                <span className="text-xs font-medium text-muted-foreground block">Medium</span>
                <span className="text-lg font-bold text-foreground">{data.advancedReports.complexityVsTime.avgDaysByPriority.medium}d</span>
              </div>
              <div className="text-center">
                <span className="text-xs font-medium text-muted-foreground block">High</span>
                <span className="text-lg font-bold text-foreground">{data.advancedReports.complexityVsTime.avgDaysByPriority.high}d</span>
              </div>
            </div>
            <p className="text-sm text-muted-foreground">Task Complexity</p>
          </motion.div>
        ) : null}
      </div>

      {/* Charts Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {/* Tasks Over Time */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3 }}
          className="card-elevated p-6"
        >
          <h3 className="text-lg font-semibold text-foreground mb-6">Tasks Completed per Month</h3>
          <div className="h-72">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={data.monthlyTasks}>
                <defs>
                  <linearGradient id="completedGradient" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="hsl(230, 45%, 28%)" stopOpacity={0.3} />
                    <stop offset="100%" stopColor="hsl(230, 45%, 28%)" stopOpacity={0} />
                  </linearGradient>
                  <linearGradient id="createdGradient" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="hsl(174, 42%, 42%)" stopOpacity={0.3} />
                    <stop offset="100%" stopColor="hsl(174, 42%, 42%)" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <XAxis
                  dataKey="name"
                  axisLine={false}
                  tickLine={false}
                  tick={{ fill: "hsl(var(--muted-foreground))", fontSize: 11 }}
                />
                <YAxis
                  axisLine={false}
                  tickLine={false}
                  tick={{ fill: "hsl(var(--muted-foreground))", fontSize: 11 }}
                />
                <Tooltip
                  contentStyle={{
                    backgroundColor: "hsl(var(--card))",
                    border: "1px solid hsl(var(--border))",
                    borderRadius: "12px",
                    boxShadow: "var(--shadow-lg)",
                  }}
                />
                <Area
                  type="monotone"
                  dataKey="completed"
                  stroke="hsl(230, 45%, 28%)"
                  strokeWidth={2}
                  fill="url(#completedGradient)"
                  name="Completed"
                />
                <Area
                  type="monotone"
                  dataKey="created"
                  stroke="hsl(174, 42%, 42%)"
                  strokeWidth={2}
                  fill="url(#createdGradient)"
                  name="Created"
                />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </motion.div>

        {/* User Performance */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.4 }}
          className="card-elevated p-6"
        >
          <div className="flex items-start justify-between gap-3 mb-6">
            <h3 className="text-lg font-semibold text-foreground">Top Performers</h3>
            {shouldShowMembersPopup ? (
              <button
                type="button"
                onClick={() => setIsMembersDialogOpen(true)}
                className="text-xs sm:text-sm font-semibold text-primary hover:underline whitespace-nowrap"
              >
                View all members ({data.userPerformance.length})
              </button>
            ) : null}
          </div>
          <div
            className={`grid gap-3 ${visiblePerformers.length > 6 ? "md:grid-cols-2" : "grid-cols-1"}`}
          >
            {visiblePerformers.length > 0 ? visiblePerformers.map((user, index) => (
              <motion.div
                key={`${user.name}-${index}`}
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.45 + index * 0.04 }}
                className="rounded-lg border border-border/80 bg-background/70 px-4 py-3"
              >
                <div className="flex items-center justify-between gap-3">
                  <p className="text-sm font-medium text-foreground whitespace-normal break-words leading-snug">
                    {user.name}
                  </p>
                  <p className="text-sm font-semibold text-primary whitespace-nowrap">
                    {user.tasks} completed
                  </p>
                </div>
              </motion.div>
            )) : (
              <p className="text-sm text-muted-foreground">No completed task data found in this period.</p>
            )}
          </div>
          {shouldShowMembersPopup ? (
            <p className="text-xs text-muted-foreground mt-4">
              Showing first 12 performers. Press "View all members" to see everyone.
            </p>
          ) : null}
        </motion.div>
      </div>

      <Dialog open={isMembersDialogOpen} onOpenChange={setIsMembersDialogOpen}>
        <DialogContent className="max-w-3xl max-h-[85vh] overflow-hidden">
          <DialogHeader>
            <DialogTitle>All Top Performers</DialogTitle>
            <DialogDescription>
              Complete member list with their completed task counts.
            </DialogDescription>
          </DialogHeader>
          <div className="overflow-y-auto pr-1">
            <div className={`grid gap-3 ${data.userPerformance.length > 10 ? "md:grid-cols-2" : "grid-cols-1"}`}>
              {data.userPerformance.map((user, index) => (
                <div
                  key={`all-members-${user.name}-${index}`}
                  className="rounded-lg border border-border/80 bg-background/70 px-4 py-3"
                >
                  <div className="flex items-center justify-between gap-3">
                    <p className="text-sm font-medium text-foreground whitespace-normal break-words leading-snug">
                      {user.name}
                    </p>
                    <p className="text-sm font-semibold text-primary whitespace-nowrap">
                      {user.tasks} completed
                    </p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </DialogContent>
      </Dialog>

      {/* Resource Utilization */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.5 }}
        className="card-elevated p-6"
      >
        <h3 className="text-lg font-semibold text-foreground mb-6">Resource Utilization by Category</h3>
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <PieChart>
                <Pie
                  data={data.resourceUtilization}
                  cx="50%"
                  cy="50%"
                  innerRadius={60}
                  outerRadius={90}
                  paddingAngle={4}
                  dataKey="value"
                >
                  {data.resourceUtilization.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={entry.color} />
                  ))}
                </Pie>
                <Tooltip
                  contentStyle={{
                    backgroundColor: "hsl(var(--card))",
                    border: "1px solid hsl(var(--border))",
                    borderRadius: "12px",
                    boxShadow: "var(--shadow-lg)",
                  }}
                  formatter={(value: number) => [`${value}%`, "Utilization"]}
                />
              </PieChart>
            </ResponsiveContainer>
          </div>
          <div className="space-y-4">
            {data.resourceUtilization.map((resource) => (
              <div key={resource.name} className="flex items-center gap-4">
                <div
                  className="w-3 h-3 rounded-full flex-shrink-0"
                  style={{ backgroundColor: resource.color }}
                />
                <div className="flex-1">
                  <div className="flex items-center justify-between mb-1">
                    <span className="text-sm font-medium text-foreground">{resource.name}</span>
                    <span className="text-sm font-semibold text-foreground">{resource.value}%</span>
                  </div>
                  <div className="h-2 bg-muted rounded-full overflow-hidden">
                    <motion.div
                      initial={{ width: 0 }}
                      animate={{ width: `${resource.value}%` }}
                      transition={{ delay: 0.6, duration: 0.8, ease: "easeOut" }}
                      className="h-full rounded-full"
                      style={{ backgroundColor: resource.color }}
                    />
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </motion.div>
    </AppLayout>
  );
}