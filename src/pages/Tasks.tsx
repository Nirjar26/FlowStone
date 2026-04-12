import { useState, useEffect } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Plus, Search, Grid3X3, List, X, SlidersHorizontal } from "lucide-react";
import { AppLayout } from "@/components/layout/AppLayout";
import { TaskCard, TaskStatus, TaskPriority } from "@/components/tasks/TaskCard";
import { TaskDetailModal } from "@/components/tasks/TaskDetailModal";
import { CreateTaskModal } from "@/components/tasks/CreateTaskModal";
import { cn } from "@/lib/utils";
import { useLocation } from "react-router-dom";
import Swal from 'sweetalert2';

interface Task {
  id: number;
  title: string;
  description: string;
  status: TaskStatus;
  priority: TaskPriority;
  assignee: { name: string; email?: string };
  createdBy?: { id: number; name: string };
  deadline: string;
  createdAt: string;
  updatedAt?: string;
  comments?: number;
  attachments?: number;
}

const statusFilters = [
  { key: "all", label: "All Tasks" },
  { key: "pending", label: "Pending" },
  { key: "in-progress", label: "In Progress" },
  { key: "review", label: "Review" },
  { key: "completed", label: "Completed" },
];

export default function Tasks() {
  const location = useLocation();
  const [tasks, setTasks] = useState<Task[]>([]);
  const [users, setUsers] = useState<Array<{ id: number; name: string; email: string }>>([]);
  const [currentUser, setCurrentUser] = useState<{ id: number; role?: string; name?: string }>({ id: 0 });
  const [isLoading, setIsLoading] = useState(true);
  const [selectedTask, setSelectedTask] = useState<Task | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);
  const [editingTask, setEditingTask] = useState<Task | null>(null);
  const [activeFilter, setActiveFilter] = useState("all");
  const [sortMode, setSortMode] = useState<"newest" | "oldest">("newest");
  const [createdDateFilter, setCreatedDateFilter] = useState("");
  const [selectedAssigneeId, setSelectedAssigneeId] = useState<string>("");
  const [isFilterPanelOpen, setIsFilterPanelOpen] = useState(false);
  const [draftSortMode, setDraftSortMode] = useState<"newest" | "oldest">("newest");
  const [draftCreatedDateFilter, setDraftCreatedDateFilter] = useState("");
  const [draftAssigneeId, setDraftAssigneeId] = useState<string>("");
  const [viewMode, setViewMode] = useState<"grid" | "list">("grid");
  const [searchQuery, setSearchQuery] = useState("");
  const [statusCounts, setStatusCounts] = useState({
    all: 0,
    pending: 0,
    "in-progress": 0,
    review: 0,
    completed: 0,
  });

  useEffect(() => {
    fetchTasks();
  }, [activeFilter, sortMode, selectedAssigneeId, createdDateFilter]);

  useEffect(() => {
    try {
      setCurrentUser(JSON.parse(localStorage.getItem('user') || '{}'));
    } catch {
      setCurrentUser({ id: 0 });
    }
  }, []);

  useEffect(() => {
    fetchUsers();
  }, []);

  useEffect(() => {
    const searchParams = new URLSearchParams(location.search);
    const shouldOpenCreateModal =
      location.pathname === "/tasks/create" || searchParams.get("create") === "1";

    if (shouldOpenCreateModal) {
      setIsCreateModalOpen(true);
    }
  }, [location.pathname, location.search]);

  const fetchUsers = async () => {
    try {
      const response = await fetch('http://localhost:8000/backend/users.php', {
        headers: {
          'Authorization': localStorage.getItem('token') || '',
        },
      });
      const data = await response.json();
      if (data.success && data.users) {
        setUsers(data.users);
      }
    } catch (error) {
      console.error('Failed to fetch users:', error);
    }
  };

  const isAdmin = (currentUser.role || '').toLowerCase().includes('admin');

  const canManageTask = (task: Task) => {
    return isAdmin || task.createdBy?.id === currentUser.id;
  };

  const fetchTasks = async () => {
    setIsLoading(true);
    try {
      const user = JSON.parse(localStorage.getItem('user') || '{}');
      const params = new URLSearchParams();
      if (user?.id) {
        params.append('user_id', String(user.id));
      }
      if (activeFilter !== 'all') {
        params.append('status', activeFilter);
      }
      params.append('sort', sortMode);
      if (selectedAssigneeId) {
        params.append('assignee_id', selectedAssigneeId);
      }
      if (createdDateFilter) {
        params.append('created_date', createdDateFilter);
      }
      if (searchQuery) {
        params.append('search', searchQuery);
      }

      const response = await fetch(`http://localhost:8000/backend/tasks.php?${params.toString()}`, {
        headers: {
          'Authorization': localStorage.getItem('token') || '',
        },
      });
      const data = await response.json();

      if (data.success) {
        setTasks(data.tasks);
        if (data.statusCounts) {
          setStatusCounts(data.statusCounts);
        }
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: data.message || 'Failed to load tasks',
          confirmButtonColor: '#3b82f6',
        });
      }
    } catch (error) {
      console.error('Failed to fetch tasks:', error);
      Swal.fire({
        icon: 'error',
        title: 'Network Error',
        text: 'Failed to load tasks. Please try again.',
        confirmButtonColor: '#3b82f6',
      });
    } finally {
      setIsLoading(false);
    }
  };

  const handleSearch = () => {
    fetchTasks();
  };

  const handleSortChange = (mode: "newest" | "oldest") => {
    setSortMode(mode);
  };

  const handleAssigneeChange = (value: string) => {
    setSelectedAssigneeId(value);
  };

  const handleOpenFilterPanel = () => {
    setDraftSortMode(sortMode);
    setDraftCreatedDateFilter(createdDateFilter);
    setDraftAssigneeId(selectedAssigneeId);
    setIsFilterPanelOpen(true);
  };

  const handleApplyFilters = () => {
    setSortMode(draftSortMode);
    setCreatedDateFilter(draftCreatedDateFilter);
    setSelectedAssigneeId(draftAssigneeId);
    setIsFilterPanelOpen(false);
  };

  const handleResetFilters = () => {
    setDraftSortMode("newest");
    setDraftCreatedDateFilter("");
    setDraftAssigneeId("");

    setSortMode("newest");
    setCreatedDateFilter("");
    setSelectedAssigneeId("");
    setIsFilterPanelOpen(false);
  };

  const handleEditTask = (task: Task) => {
    setEditingTask(task);
    setIsEditModalOpen(true);
  };

  const filteredTasks = tasks.filter((task) => {
    const matchesSearch = task.title.toLowerCase().includes(searchQuery.toLowerCase());
    return matchesSearch;
  });

  const handleTaskClick = async (task: Task) => {
    try {
      const response = await fetch(`http://localhost:8000/backend/task_detail.php?id=${task.id}`);
      const data = await response.json();
      
      if (data.success) {
        setSelectedTask(data.task);
        setIsModalOpen(true);
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Failed to load task details',
          confirmButtonColor: '#3b82f6',
        });
      }
    } catch (error) {
      console.error('Failed to fetch task details:', error);
      Swal.fire({
        icon: 'error',
        title: 'Network Error',
        text: 'Failed to load task details. Please try again.',
        confirmButtonColor: '#3b82f6',
      });
    }
  };

  const handleDataUpdate = (taskId: number, commentsCount: number, attachmentsCount: number) => {
    // Update the tasks list with new counts
    setTasks(prevTasks =>
      prevTasks.map(task =>
        task.id === taskId
          ? { ...task, comments: commentsCount, attachments: attachmentsCount }
          : task
      )
    );
  };

  const handleKeyPress = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter') {
      handleSearch();
    }
  };

  const handleTaskUpdate = async (taskId: number, updates: any) => {
    try {
      const response = await fetch('http://localhost:8000/backend/update_task.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': localStorage.getItem('token') || '',
        },
        body: JSON.stringify({ id: taskId, user_id: currentUser.id, ...updates }),
      });

      const data = await response.json();

      if (data.success) {
        await Swal.fire({
          icon: 'success',
          title: 'Task Updated!',
          text: 'Task has been updated successfully.',
          confirmButtonColor: '#3b82f6',
          timer: 1500,
        });
        fetchTasks();
        setIsModalOpen(false);
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Update Failed',
          text: data.message || 'Failed to update task',
          confirmButtonColor: '#3b82f6',
        });
      }
    } catch (error) {
      console.error('Failed to update task:', error);
      Swal.fire({
        icon: 'error',
        title: 'Network Error',
        text: 'Failed to update task. Please try again.',
        confirmButtonColor: '#3b82f6',
      });
    }
  };

  const handleTaskDelete = async (taskId: number) => {
    const result = await Swal.fire({
      title: 'Are you sure?',
      text: "You won't be able to revert this!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'Yes, delete it!',
      cancelButtonText: 'Cancel'
    });

    if (!result.isConfirmed) {
      return;
    }

    try {
      const response = await fetch('http://localhost:8000/backend/delete_task.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': localStorage.getItem('token') || '',
        },
        body: JSON.stringify({ id: taskId, user_id: currentUser.id }),
      });

      const data = await response.json();

      if (data.success) {
        await Swal.fire({
          icon: 'success',
          title: 'Deleted!',
          text: 'Task has been deleted successfully.',
          confirmButtonColor: '#3b82f6',
          timer: 1500,
        });
        fetchTasks();
        setIsModalOpen(false);
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Delete Failed',
          text: data.message || 'Failed to delete task',
          confirmButtonColor: '#3b82f6',
        });
      }
    } catch (error) {
      console.error('Failed to delete task:', error);
      Swal.fire({
        icon: 'error',
        title: 'Network Error',
        text: 'Failed to delete task. Please try again.',
        confirmButtonColor: '#3b82f6',
      });
    }
  };

  return (
    <AppLayout title="Task Management" subtitle="Manage and track all your tasks in one place.">
      {isLoading ? (
        <div className="flex items-center justify-center h-64">
          <div className="text-muted-foreground">Loading tasks...</div>
        </div>
      ) : (
        <>
          {/* Header Actions */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
        {/* Search and Filters */}
        <div className="relative min-w-0 flex items-center gap-2">
          <div className="relative max-w-sm">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            <input
              type="text"
              placeholder="Search tasks..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              onKeyPress={handleKeyPress}
              className="w-full pl-10 pr-10 py-2.5 bg-card border border-border/80 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/15 focus:border-primary/40 transition-all"
            />
            {searchQuery && (
              <button
                onClick={() => setSearchQuery("")}
                className="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-muted/70 flex items-center justify-center text-muted-foreground hover:text-foreground transition-colors"
              >
                <X className="w-3 h-3" />
              </button>
            )}
          </div>

          <button
            type="button"
            onClick={handleOpenFilterPanel}
            className={cn(
              "h-10 w-10 shrink-0 rounded-xl border border-border/70 bg-card text-muted-foreground flex items-center justify-center transition-all duration-200",
              "hover:text-foreground hover:border-border hover:bg-muted/40",
              isFilterPanelOpen && "text-primary border-primary/40 bg-primary/10"
            )}
            aria-label="Open filters"
          >
            <SlidersHorizontal className="w-4 h-4" />
          </button>

          <div className="flex items-center gap-2 overflow-x-auto pb-1">
            {statusFilters.map((filter) => (
              <button
                key={filter.key}
                onClick={() => setActiveFilter(filter.key)}
                className={cn(
                  "px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition-all flex items-center gap-2",
                  activeFilter === filter.key
                    ? "bg-primary text-primary-foreground"
                    : "bg-card border border-border/70 text-muted-foreground hover:text-foreground hover:bg-muted/40"
                )}
              >
                <span>{filter.label}</span>
                <span
                  className={cn(
                    "px-2 py-0.5 rounded-full text-xs font-semibold",
                    activeFilter === filter.key
                      ? "bg-primary-foreground/20 text-primary-foreground"
                      : "bg-muted text-muted-foreground"
                  )}
                >
                  {statusCounts[filter.key as keyof typeof statusCounts]}
                </span>
              </button>
            ))}
          </div>

          <AnimatePresence>
            {isFilterPanelOpen && (
              <>
                <motion.button
                  type="button"
                  initial={{ opacity: 0 }}
                  animate={{ opacity: 1 }}
                  exit={{ opacity: 0 }}
                  onClick={() => setIsFilterPanelOpen(false)}
                  className="fixed inset-0 z-30 bg-black/10"
                  aria-label="Close filters"
                />
                <motion.div
                  initial={{ opacity: 0, y: 8, scale: 0.98 }}
                  animate={{ opacity: 1, y: 0, scale: 1 }}
                  exit={{ opacity: 0, y: 8, scale: 0.98 }}
                  transition={{ duration: 0.18 }}
                  className="absolute right-0 top-full mt-2 z-40 w-[min(100%,26rem)] rounded-2xl border border-border/70 bg-card shadow-xl"
                >
                  <div className="p-4 sm:p-5 space-y-5">
                    <div>
                      <h3 className="text-sm font-semibold text-foreground">Sort</h3>
                      <div className="mt-3 inline-flex items-center gap-1 rounded-xl border border-border/70 bg-muted/20 p-1">
                        <button
                          type="button"
                          onClick={() => setDraftSortMode("newest")}
                          className={cn(
                            "px-3 py-2 rounded-lg text-xs font-medium transition-colors",
                            draftSortMode === "newest"
                              ? "bg-primary text-primary-foreground"
                              : "text-muted-foreground hover:text-foreground hover:bg-muted"
                          )}
                        >
                          Newest
                        </button>
                        <button
                          type="button"
                          onClick={() => setDraftSortMode("oldest")}
                          className={cn(
                            "px-3 py-2 rounded-lg text-xs font-medium transition-colors",
                            draftSortMode === "oldest"
                              ? "bg-primary text-primary-foreground"
                              : "text-muted-foreground hover:text-foreground hover:bg-muted"
                          )}
                        >
                          Oldest
                        </button>
                      </div>
                    </div>

                    <div className="h-px bg-border/60" />

                    <div className="space-y-3">
                      <div>
                        <h3 className="text-sm font-semibold text-foreground">Date Filter</h3>
                        <input
                          type="date"
                          value={draftCreatedDateFilter}
                          onChange={(e) => setDraftCreatedDateFilter(e.target.value)}
                          className="mt-3 w-full px-3 py-2.5 rounded-xl border border-border/70 bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/15 focus:border-primary/40 transition-all"
                        />
                      </div>

                      <div>
                        <h3 className="text-sm font-semibold text-foreground">Assigned To</h3>
                        <select
                          value={draftAssigneeId}
                          onChange={(e) => setDraftAssigneeId(e.target.value)}
                          className="mt-3 w-full px-3 py-2.5 rounded-xl border border-border/70 bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/15 focus:border-primary/40 transition-all"
                        >
                          <option value="">Any assignee</option>
                          {users.map((user) => (
                            <option key={user.id} value={user.id}>
                              {user.name}
                            </option>
                          ))}
                        </select>
                      </div>
                    </div>
                  </div>

                  <div className="border-t border-border/60 px-4 sm:px-5 py-3 flex items-center justify-between">
                    <button
                      type="button"
                      onClick={handleResetFilters}
                      className="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
                    >
                      Reset
                    </button>
                    <button
                      type="button"
                      onClick={handleApplyFilters}
                      className="px-4 py-2 rounded-lg bg-primary text-primary-foreground text-sm font-medium hover:bg-primary/90 active:scale-[0.98] transition-all"
                    >
                      Apply
                    </button>
                  </div>
                </motion.div>
              </>
            )}
          </AnimatePresence>
        </div>

        {/* View Toggle and Create */}
        <div className="flex items-center gap-3">
          <div className="flex items-center bg-card border border-border rounded-xl p-1">
            <button
              onClick={() => setViewMode("grid")}
              className={cn(
                "w-8 h-8 rounded-lg flex items-center justify-center transition-colors",
                viewMode === "grid"
                  ? "bg-primary text-primary-foreground"
                  : "text-muted-foreground hover:text-foreground"
              )}
            >
              <Grid3X3 className="w-4 h-4" />
            </button>
            <button
              onClick={() => setViewMode("list")}
              className={cn(
                "w-8 h-8 rounded-lg flex items-center justify-center transition-colors",
                viewMode === "list"
                  ? "bg-primary text-primary-foreground"
                  : "text-muted-foreground hover:text-foreground"
              )}
            >
              <List className="w-4 h-4" />
            </button>
          </div>
          <motion.button
            whileHover={{ scale: 1.02 }}
            whileTap={{ scale: 0.98 }}
            onClick={() => setIsCreateModalOpen(true)}
            className="btn-primary-gradient flex items-center gap-2"
          >
            <Plus className="w-4 h-4" />
            <span>Create Task</span>
          </motion.button>
        </div>
      </div>

      {/* Task Grid */}
      <div
        className={cn(
          "grid gap-4",
          viewMode === "grid"
            ? "grid-cols-1 md:grid-cols-2 xl:grid-cols-3"
            : "grid-cols-1"
        )}
      >
        {filteredTasks.map((task, index) => (
          <TaskCard
            key={task.id}
            {...task}
            comments={task.comments}
            attachments={task.attachments}
            delay={index * 0.05}
            onClick={() => handleTaskClick(task)}
            canManage={canManageTask(task)}
            onEdit={canManageTask(task) ? () => handleEditTask(task) : undefined}
            onDelete={canManageTask(task) ? () => handleTaskDelete(task.id) : undefined}
          />
        ))}
      </div>

      {/* Empty State */}
      {filteredTasks.length === 0 && (
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="flex flex-col items-center justify-center py-16 text-center"
        >
          <div className="w-16 h-16 rounded-2xl bg-muted flex items-center justify-center mb-4">
            <Search className="w-8 h-8 text-muted-foreground" />
          </div>
          <h3 className="text-lg font-semibold text-foreground mb-2">
            No tasks found
          </h3>
          <p className="text-muted-foreground max-w-sm">
            {searchQuery
              ? `No tasks match "${searchQuery}". Try a different search term.`
              : "There are no tasks in this category yet."}
          </p>
        </motion.div>
      )}

      {/* Task Detail Modal */}
      <TaskDetailModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        task={selectedTask}
        onUpdate={handleTaskUpdate}
        onDelete={handleTaskDelete}
        onDataUpdate={handleDataUpdate}
      />

      {/* Edit Task Modal */}
      <CreateTaskModal
        isOpen={isEditModalOpen}
        onClose={() => {
          setIsEditModalOpen(false);
          setEditingTask(null);
        }}
        onTaskCreated={() => {
          fetchTasks();
        }}
        task={editingTask}
      />

      {/* Create Task Modal */}
      <CreateTaskModal
        isOpen={isCreateModalOpen}
        onClose={() => {
          setIsCreateModalOpen(false);
        }}
        onTaskCreated={() => {
          fetchTasks();
        }}
      />
        </>
      )}
    </AppLayout>
  );
}