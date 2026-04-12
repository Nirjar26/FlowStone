import { useEffect, useMemo, useState } from "react";
import { motion } from "framer-motion";
import {
  AlertTriangle,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  ListTodo,
  RefreshCw,
  Shield,
  UserCheck,
  UserCog,
  Users,
  Wrench,
  X,
} from "lucide-react";
import { AdminStatCard } from "./AdminStatCard";

interface User {
  id: number;
  name: string;
  email: string;
  role: string | null;
  department: string | null;
}

interface Task {
  id: number;
  title: string;
  status: "pending" | "in-progress" | "review" | "completed";
  priority: "low" | "medium" | "high";
  assignee: {
    id: number | null;
    name: string;
    email: string | null;
  };
  deadline: string | null;
}

interface Approval {
  id: number;
  type: string;
  status: "pending" | "approved" | "rejected";
  requestedBy: {
    name: string;
    department: string | null;
  };
  date: string;
}

interface Resource {
  id: number;
  name: string;
  type: string;
  status: "available" | "assigned" | "maintenance";
}

interface NotificationsResponse {
  unreadCount: number;
}

type TaskStatus = Task["status"];
type ApprovalStatus = Approval["status"];
type ResourceStatus = Resource["status"];
type PopupKind = "users" | "tasks" | "approvals" | "resources";

const PREVIEW_LIMIT = 20;
const PAGE_SIZE = 10;

export function AdminPanel() {
  const [users, setUsers] = useState<User[]>([]);
  const [tasks, setTasks] = useState<Task[]>([]);
  const [approvals, setApprovals] = useState<Approval[]>([]);
  const [resources, setResources] = useState<Resource[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [popupKind, setPopupKind] = useState<PopupKind | null>(null);
  const [popupPage, setPopupPage] = useState(1);

  const getAuthHeaders = () => ({
    Authorization: localStorage.getItem("token") || "",
  });

  const fetchAll = async () => {
    setIsLoading(true);
    setError(null);

    try {
      const storedUser = JSON.parse(localStorage.getItem("user") || "{}");
      const userId = storedUser?.id;

      const [usersRes, tasksRes, approvalsRes, resourcesRes, notificationsRes] =
        await Promise.all([
          fetch("http://localhost:8000/backend/users.php", { headers: getAuthHeaders() }),
          fetch("http://localhost:8000/backend/tasks.php", { headers: getAuthHeaders() }),
          fetch(`http://localhost:8000/backend/approvals.php?user_id=${userId || 1}`, { headers: getAuthHeaders() }),
          fetch(`http://localhost:8000/backend/resources.php?user_id=${userId || 1}`, { headers: getAuthHeaders() }),
          fetch(
            `http://localhost:8000/backend/notifications.php?userId=${userId || 1}&filter=unread`,
            { headers: getAuthHeaders() }
          ),
        ]);

      const [
        usersData,
        tasksData,
        approvalsData,
        resourcesData,
        notificationsData,
      ] = await Promise.all([
        usersRes.json(),
        tasksRes.json(),
        approvalsRes.json(),
        resourcesRes.json(),
        notificationsRes.json(),
      ]);

      if (!usersData.success || !tasksData.success || !approvalsData.success || !resourcesData.success) {
        throw new Error("Some admin data failed to load");
      }

      // Keep IDs in consistent ascending order across all admin tables.
      setUsers([...(usersData.users || [])].sort((a: User, b: User) => a.id - b.id));
      setTasks([...(tasksData.tasks || [])].sort((a: Task, b: Task) => a.id - b.id));
      setApprovals([...(approvalsData.approvals || [])].sort((a: Approval, b: Approval) => a.id - b.id));
      setResources([...(resourcesData.resources || [])].sort((a: Resource, b: Resource) => a.id - b.id));
      setUnreadCount((notificationsData as NotificationsResponse).unreadCount || 0);
    } catch (loadError) {
      console.error("Failed to load admin data:", loadError);
      setError("Unable to load admin panel data. Please check API/server status.");
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchAll();
  }, []);

  const stats = useMemo(() => {
    const admins = users.filter((user) =>
      (user.role || "").toLowerCase().includes("admin")
    ).length;

    const activeTasks = tasks.filter((task) => task.status !== "completed").length;
    const pendingApprovals = approvals.filter((approval) => approval.status === "pending").length;
    const availableResources = resources.filter((resource) => resource.status === "available").length;

    const taskStatusCounts = tasks.reduce<Record<TaskStatus, number>>(
      (acc, task) => {
        acc[task.status] += 1;
        return acc;
      },
      {
        pending: 0,
        "in-progress": 0,
        review: 0,
        completed: 0,
      }
    );

    const approvalStatusCounts = approvals.reduce<Record<ApprovalStatus, number>>(
      (acc, approval) => {
        acc[approval.status] += 1;
        return acc;
      },
      {
        pending: 0,
        approved: 0,
        rejected: 0,
      }
    );

    const resourceStatusCounts = resources.reduce<Record<ResourceStatus, number>>(
      (acc, resource) => {
        acc[resource.status] += 1;
        return acc;
      },
      {
        available: 0,
        assigned: 0,
        maintenance: 0,
      }
    );

    return {
      admins,
      activeTasks,
      pendingApprovals,
      availableResources,
      taskStatusCounts,
      approvalStatusCounts,
      resourceStatusCounts,
    };
  }, [users, tasks, approvals, resources]);

  const openPopup = (kind: PopupKind) => {
    const sourceItems =
      kind === "users"
        ? users
        : kind === "tasks"
        ? tasks
        : kind === "approvals"
        ? approvals
        : resources;

    const startPage = Math.min(
      Math.max(1, Math.ceil(sourceItems.length / PAGE_SIZE)),
      Math.floor(PREVIEW_LIMIT / PAGE_SIZE) + 1
    );

    setPopupKind(kind);
    setPopupPage(startPage);
  };

  const closePopup = () => {
    setPopupKind(null);
    setPopupPage(1);
  };

  const popupItems = useMemo(() => {
    if (popupKind === "users") return users;
    if (popupKind === "tasks") return tasks;
    if (popupKind === "approvals") return approvals;
    if (popupKind === "resources") return resources;
    return [];
  }, [popupKind, users, tasks, approvals, resources]);

  const popupTitle =
    popupKind === "users"
      ? "All Users"
      : popupKind === "tasks"
      ? "All Tasks"
      : popupKind === "approvals"
      ? "All Approvals"
      : popupKind === "resources"
      ? "All Resources"
      : "";

  const totalPages = Math.max(1, Math.ceil(popupItems.length / PAGE_SIZE));
  const paginatedPopupItems = popupItems.slice((popupPage - 1) * PAGE_SIZE, popupPage * PAGE_SIZE);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-72">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <button
          onClick={fetchAll}
          className="btn-accent-gradient flex items-center gap-2"
          type="button"
        >
          <RefreshCw className="w-4 h-4" />
          Refresh Data
        </button>
      </div>

      {error && (
        <div className="card-elevated p-4 border-destructive/25 bg-destructive/5 flex items-start gap-3">
          <AlertTriangle className="w-5 h-5 text-destructive mt-0.5" />
          <div>
            <p className="font-semibold text-destructive">Load Error</p>
            <p className="text-sm text-muted-foreground">{error}</p>
          </div>
        </div>
      )}

      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-5">
        <AdminStatCard title="Total Users" value={users.length} hint="From users API" icon={Users} delay={0} tone="primary" />
        <AdminStatCard title="Administrators" value={stats.admins} hint="Role includes admin" icon={Shield} delay={0.05} tone="accent" />
        <AdminStatCard title="Active Tasks" value={stats.activeTasks} hint="Non-completed tasks" icon={ListTodo} delay={0.1} tone="warning" />
        <AdminStatCard title="Pending Approvals" value={stats.pendingApprovals} hint="Awaiting action" icon={UserCog} delay={0.15} tone="primary" />
        <AdminStatCard title="Available Resources" value={stats.availableResources} hint="Ready to assign" icon={Wrench} delay={0.2} tone="success" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
          className="card-elevated p-6"
        >
          <h3 className="text-base font-semibold text-foreground mb-4">Task Status</h3>
          <div className="grid grid-cols-2 gap-3 text-sm">
            <div className="p-3 rounded-xl bg-muted/50 border border-border/50">
              <p className="text-muted-foreground">Pending</p>
              <p className="text-lg font-bold text-warning">{stats.taskStatusCounts.pending}</p>
            </div>
            <div className="p-3 rounded-xl bg-muted/50 border border-border/50">
              <p className="text-muted-foreground">In Progress</p>
              <p className="text-lg font-bold text-info">{stats.taskStatusCounts["in-progress"]}</p>
            </div>
            <div className="p-3 rounded-xl bg-muted/50 border border-border/50">
              <p className="text-muted-foreground">Review</p>
              <p className="text-lg font-bold text-accent">{stats.taskStatusCounts.review}</p>
            </div>
            <div className="p-3 rounded-xl bg-muted/50 border border-border/50">
              <p className="text-muted-foreground">Completed</p>
              <p className="text-lg font-bold text-success">{stats.taskStatusCounts.completed}</p>
            </div>
          </div>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.25 }}
          className="card-elevated p-6"
        >
          <h3 className="text-base font-semibold text-foreground mb-4">Approvals Status</h3>
          <div className="grid grid-cols-3 gap-3 text-sm">
            <div className="p-3 rounded-xl bg-muted/50 border border-border/50">
              <p className="text-muted-foreground">Pending</p>
              <p className="text-lg font-bold text-warning">{stats.approvalStatusCounts.pending}</p>
            </div>
            <div className="p-3 rounded-xl bg-muted/50 border border-border/50">
              <p className="text-muted-foreground">Approved</p>
              <p className="text-lg font-bold text-success">{stats.approvalStatusCounts.approved}</p>
            </div>
            <div className="p-3 rounded-xl bg-muted/50 border border-border/50">
              <p className="text-muted-foreground">Rejected</p>
              <p className="text-lg font-bold text-destructive">{stats.approvalStatusCounts.rejected}</p>
            </div>
          </div>
          <div className="mt-4 p-3 rounded-xl bg-muted/30 border border-border/50 flex items-center justify-between">
            <p className="text-sm text-muted-foreground">Unread Notifications</p>
            <p className="font-bold text-primary">{unreadCount}</p>
          </div>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3 }}
          className="card-elevated p-6"
        >
          <h3 className="text-base font-semibold text-foreground mb-4">Resources Status</h3>
          <div className="grid grid-cols-3 gap-3 text-sm">
            <div className="p-3 rounded-xl bg-muted/50 border border-border/50">
              <p className="text-muted-foreground">Available</p>
              <p className="text-lg font-bold text-success">{stats.resourceStatusCounts.available}</p>
            </div>
            <div className="p-3 rounded-xl bg-muted/50 border border-border/50">
              <p className="text-muted-foreground">Assigned</p>
              <p className="text-lg font-bold text-primary">{stats.resourceStatusCounts.assigned}</p>
            </div>
            <div className="p-3 rounded-xl bg-muted/50 border border-border/50">
              <p className="text-muted-foreground">Maintenance</p>
              <p className="text-lg font-bold text-destructive">{stats.resourceStatusCounts.maintenance}</p>
            </div>
          </div>
          <div className="mt-4 p-3 rounded-xl bg-success/10 border border-success/30 flex items-center gap-3">
            <CheckCircle2 className="w-5 h-5 text-success" />
            <p className="text-sm text-foreground">All core modules synced</p>
          </div>
        </motion.div>
      </div>

      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.35 }}
        className="card-elevated p-6"
      >
        <div className="flex items-center justify-between mb-5">
          <h3 className="text-base font-semibold text-foreground">All Users</h3>
          <div className="flex items-center gap-2">
            <span className="text-xs text-muted-foreground">{users.length} users</span>
            {users.length > PREVIEW_LIMIT && (
              <button
                type="button"
                onClick={() => openPopup("users")}
                className="rounded-lg border border-border px-2.5 py-1 text-xs font-medium text-foreground hover:bg-muted"
              >
                View All
              </button>
            )}
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-muted-foreground">
                <th className="text-left py-2 pr-3 font-medium">ID</th>
                <th className="text-left py-2 pr-3 font-medium">Name</th>
                <th className="text-left py-2 pr-3 font-medium">Email</th>
                <th className="text-left py-2 pr-3 font-medium">Role</th>
                <th className="text-left py-2 pr-3 font-medium">Department</th>
              </tr>
            </thead>
            <tbody>
              {users.slice(0, PREVIEW_LIMIT).map((user) => (
                <tr key={user.id} className="border-b border-border/50 last:border-0">
                  <td className="py-3 pr-3 text-muted-foreground">#{user.id}</td>
                  <td className="py-3 pr-3 text-foreground font-medium">{user.name}</td>
                  <td className="py-3 pr-3 text-muted-foreground">{user.email}</td>
                  <td className="py-3 pr-3">
                    <span
                      className={`px-2.5 py-1 rounded-full text-xs font-medium ${
                        (user.role || "").toLowerCase().includes("admin")
                          ? "badge-review"
                          : "bg-muted text-foreground"
                      }`}
                    >
                      {user.role || "N/A"}
                    </span>
                  </td>
                  <td className="py-3 pr-3 text-muted-foreground">{user.department || "-"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </motion.div>

      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.4 }}
        className="card-elevated p-6"
      >
        <div className="flex items-center justify-between mb-5">
          <h3 className="text-base font-semibold text-foreground">All Tasks</h3>
          <div className="flex items-center gap-2">
            <span className="text-xs text-muted-foreground">{tasks.length} tasks</span>
            {tasks.length > PREVIEW_LIMIT && (
              <button
                type="button"
                onClick={() => openPopup("tasks")}
                className="rounded-lg border border-border px-2.5 py-1 text-xs font-medium text-foreground hover:bg-muted"
              >
                View All
              </button>
            )}
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-muted-foreground">
                <th className="text-left py-2 pr-3 font-medium">ID</th>
                <th className="text-left py-2 pr-3 font-medium">Title</th>
                <th className="text-left py-2 pr-3 font-medium">Assignee</th>
                <th className="text-left py-2 pr-3 font-medium">Priority</th>
                <th className="text-left py-2 pr-3 font-medium">Status</th>
                <th className="text-left py-2 pr-3 font-medium">Deadline</th>
              </tr>
            </thead>
            <tbody>
              {tasks.slice(0, PREVIEW_LIMIT).map((task) => (
                <tr key={task.id} className="border-b border-border/50 last:border-0">
                  <td className="py-3 pr-3 text-muted-foreground">#{task.id}</td>
                  <td className="py-3 pr-3 text-foreground max-w-[300px] truncate">{task.title}</td>
                  <td className="py-3 pr-3 text-muted-foreground">{task.assignee?.name || "Unassigned"}</td>
                  <td className="py-3 pr-3">
                    <span
                      className={`px-2.5 py-1 rounded-full text-xs font-medium ${
                        task.priority === "high"
                          ? "priority-high"
                          : task.priority === "medium"
                          ? "priority-medium"
                          : "priority-low"
                      }`}
                    >
                      {task.priority}
                    </span>
                  </td>
                  <td className="py-3 pr-3">
                    <span
                      className={`px-2.5 py-1 rounded-full text-xs font-medium ${
                        task.status === "pending"
                          ? "badge-pending"
                          : task.status === "in-progress"
                          ? "badge-progress"
                          : task.status === "review"
                          ? "badge-review"
                          : "badge-completed"
                      }`}
                    >
                      {task.status}
                    </span>
                  </td>
                  <td className="py-3 pr-3 text-muted-foreground">{task.deadline || "-"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </motion.div>

      <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.45 }}
          className="card-elevated p-6"
        >
          <div className="flex items-center justify-between mb-5">
            <h3 className="text-base font-semibold text-foreground">All Approvals</h3>
            <div className="flex items-center gap-2">
              <span className="text-xs text-muted-foreground">{approvals.length} approvals</span>
              {approvals.length > PREVIEW_LIMIT && (
                <button
                  type="button"
                  onClick={() => openPopup("approvals")}
                  className="rounded-lg border border-border px-2.5 py-1 text-xs font-medium text-foreground hover:bg-muted"
                >
                  View All
                </button>
              )}
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-muted-foreground">
                  <th className="text-left py-2 pr-3 font-medium">ID</th>
                  <th className="text-left py-2 pr-3 font-medium">Type</th>
                  <th className="text-left py-2 pr-3 font-medium">Requested By</th>
                  <th className="text-left py-2 pr-3 font-medium">Department</th>
                  <th className="text-left py-2 pr-3 font-medium">Status</th>
                  <th className="text-left py-2 pr-3 font-medium">Date</th>
                </tr>
              </thead>
              <tbody>
                {approvals.slice(0, PREVIEW_LIMIT).map((approval) => (
                  <tr key={approval.id} className="border-b border-border/50 last:border-0">
                    <td className="py-3 pr-3 text-muted-foreground">#{approval.id}</td>
                    <td className="py-3 pr-3 text-foreground">{approval.type}</td>
                    <td className="py-3 pr-3 text-muted-foreground">{approval.requestedBy?.name || "-"}</td>
                    <td className="py-3 pr-3 text-muted-foreground">{approval.requestedBy?.department || "-"}</td>
                    <td className="py-3 pr-3">
                      <span
                        className={`px-2.5 py-1 rounded-full text-xs font-medium ${
                          approval.status === "pending"
                            ? "badge-pending"
                            : approval.status === "approved"
                            ? "badge-completed"
                            : "badge-error"
                        }`}
                      >
                        {approval.status}
                      </span>
                    </td>
                    <td className="py-3 pr-3 text-muted-foreground">{approval.date}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.5 }}
          className="card-elevated p-6"
        >
          <div className="flex items-center justify-between mb-5">
            <h3 className="text-base font-semibold text-foreground">All Resources</h3>
            <div className="flex items-center gap-2">
              <span className="text-xs text-muted-foreground">{resources.length} resources</span>
              {resources.length > PREVIEW_LIMIT && (
                <button
                  type="button"
                  onClick={() => openPopup("resources")}
                  className="rounded-lg border border-border px-2.5 py-1 text-xs font-medium text-foreground hover:bg-muted"
                >
                  View All
                </button>
              )}
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-muted-foreground">
                  <th className="text-left py-2 pr-3 font-medium">ID</th>
                  <th className="text-left py-2 pr-3 font-medium">Name</th>
                  <th className="text-left py-2 pr-3 font-medium">Type</th>
                  <th className="text-left py-2 pr-3 font-medium">Status</th>
                </tr>
              </thead>
              <tbody>
                {resources.slice(0, PREVIEW_LIMIT).map((resource) => (
                  <tr key={resource.id} className="border-b border-border/50 last:border-0">
                    <td className="py-3 pr-3 text-muted-foreground">#{resource.id}</td>
                    <td className="py-3 pr-3 text-foreground">{resource.name}</td>
                    <td className="py-3 pr-3 text-muted-foreground capitalize">{resource.type}</td>
                    <td className="py-3 pr-3">
                      <span
                        className={`px-2.5 py-1 rounded-full text-xs font-medium ${
                          resource.status === "available"
                            ? "badge-completed"
                            : resource.status === "assigned"
                            ? "badge-progress"
                            : "badge-error"
                        }`}
                      >
                        {resource.status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* <div className="mt-4 p-3 rounded-xl bg-muted/30 border border-border/50 flex items-center gap-2 text-sm">
            <UserCheck className="w-4 h-4 text-primary" />
            <span className="text-muted-foreground">Showing full resource inventory from API</span>
          </div> */}
        </motion.div>
      </div>

      {popupKind && popupItems.length > PREVIEW_LIMIT && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-5xl rounded-2xl border border-border bg-card shadow-2xl">
            <div className="flex items-center justify-between border-b border-border px-6 py-4">
              <div>
                <h3 className="text-lg font-semibold text-foreground">{popupTitle}</h3>
                <p className="text-xs text-muted-foreground">Showing {(popupPage - 1) * PAGE_SIZE + 1}-{Math.min(popupPage * PAGE_SIZE, popupItems.length)} of {popupItems.length}</p>
              </div>
              <button
                type="button"
                onClick={closePopup}
                className="rounded-lg p-2 text-muted-foreground hover:bg-muted hover:text-foreground"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="max-h-[65vh] overflow-auto px-6 py-4">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border text-muted-foreground">
                    {popupKind === "users" && (
                      <>
                        <th className="text-left py-2 pr-3 font-medium">ID</th>
                        <th className="text-left py-2 pr-3 font-medium">Name</th>
                        <th className="text-left py-2 pr-3 font-medium">Email</th>
                        <th className="text-left py-2 pr-3 font-medium">Role</th>
                        <th className="text-left py-2 pr-3 font-medium">Department</th>
                      </>
                    )}
                    {popupKind === "tasks" && (
                      <>
                        <th className="text-left py-2 pr-3 font-medium">ID</th>
                        <th className="text-left py-2 pr-3 font-medium">Title</th>
                        <th className="text-left py-2 pr-3 font-medium">Assignee</th>
                        <th className="text-left py-2 pr-3 font-medium">Priority</th>
                        <th className="text-left py-2 pr-3 font-medium">Status</th>
                        <th className="text-left py-2 pr-3 font-medium">Deadline</th>
                      </>
                    )}
                    {popupKind === "approvals" && (
                      <>
                        <th className="text-left py-2 pr-3 font-medium">ID</th>
                        <th className="text-left py-2 pr-3 font-medium">Type</th>
                        <th className="text-left py-2 pr-3 font-medium">Requested By</th>
                        <th className="text-left py-2 pr-3 font-medium">Department</th>
                        <th className="text-left py-2 pr-3 font-medium">Status</th>
                        <th className="text-left py-2 pr-3 font-medium">Date</th>
                      </>
                    )}
                    {popupKind === "resources" && (
                      <>
                        <th className="text-left py-2 pr-3 font-medium">ID</th>
                        <th className="text-left py-2 pr-3 font-medium">Name</th>
                        <th className="text-left py-2 pr-3 font-medium">Type</th>
                        <th className="text-left py-2 pr-3 font-medium">Status</th>
                      </>
                    )}
                  </tr>
                </thead>
                <tbody>
                  {popupKind === "users" && (paginatedPopupItems as User[]).map((user) => (
                    <tr key={user.id} className="border-b border-border/50 last:border-0">
                      <td className="py-3 pr-3 text-muted-foreground">#{user.id}</td>
                      <td className="py-3 pr-3 text-foreground font-medium">{user.name}</td>
                      <td className="py-3 pr-3 text-muted-foreground">{user.email}</td>
                      <td className="py-3 pr-3">{user.role || "N/A"}</td>
                      <td className="py-3 pr-3 text-muted-foreground">{user.department || "-"}</td>
                    </tr>
                  ))}

                  {popupKind === "tasks" && (paginatedPopupItems as Task[]).map((task) => (
                    <tr key={task.id} className="border-b border-border/50 last:border-0">
                      <td className="py-3 pr-3 text-muted-foreground">#{task.id}</td>
                      <td className="py-3 pr-3 text-foreground max-w-[320px] truncate">{task.title}</td>
                      <td className="py-3 pr-3 text-muted-foreground">{task.assignee?.name || "Unassigned"}</td>
                      <td className="py-3 pr-3 text-muted-foreground capitalize">{task.priority}</td>
                      <td className="py-3 pr-3 text-muted-foreground">{task.status}</td>
                      <td className="py-3 pr-3 text-muted-foreground">{task.deadline || "-"}</td>
                    </tr>
                  ))}

                  {popupKind === "approvals" && (paginatedPopupItems as Approval[]).map((approval) => (
                    <tr key={approval.id} className="border-b border-border/50 last:border-0">
                      <td className="py-3 pr-3 text-muted-foreground">#{approval.id}</td>
                      <td className="py-3 pr-3 text-foreground">{approval.type}</td>
                      <td className="py-3 pr-3 text-muted-foreground">{approval.requestedBy?.name || "-"}</td>
                      <td className="py-3 pr-3 text-muted-foreground">{approval.requestedBy?.department || "-"}</td>
                      <td className="py-3 pr-3 text-muted-foreground">{approval.status}</td>
                      <td className="py-3 pr-3 text-muted-foreground">{approval.date}</td>
                    </tr>
                  ))}

                  {popupKind === "resources" && (paginatedPopupItems as Resource[]).map((resource) => (
                    <tr key={resource.id} className="border-b border-border/50 last:border-0">
                      <td className="py-3 pr-3 text-muted-foreground">#{resource.id}</td>
                      <td className="py-3 pr-3 text-foreground">{resource.name}</td>
                      <td className="py-3 pr-3 text-muted-foreground capitalize">{resource.type}</td>
                      <td className="py-3 pr-3 text-muted-foreground">{resource.status}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="flex items-center justify-between border-t border-border px-6 py-3">
              <button
                type="button"
                onClick={() => setPopupPage((prev) => Math.max(1, prev - 1))}
                disabled={popupPage === 1}
                className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-foreground disabled:opacity-50"
              >
                <ChevronLeft className="w-3.5 h-3.5" />
                Previous
              </button>
              <span className="text-xs text-muted-foreground">Page {popupPage} of {totalPages}</span>
              <button
                type="button"
                onClick={() => setPopupPage((prev) => Math.min(totalPages, prev + 1))}
                disabled={popupPage === totalPages}
                className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-foreground disabled:opacity-50"
              >
                Next
                <ChevronRight className="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
