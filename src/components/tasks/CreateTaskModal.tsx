import { useState, useEffect } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { X, Calendar, AlertCircle } from "lucide-react";
import { TaskStatus, TaskPriority } from "./TaskCard";
import { useToast } from "@/hooks/use-toast";
import { cn } from "@/lib/utils";

interface CreateTaskModalProps {
  isOpen: boolean;
  onClose: () => void;
  onTaskCreated: () => void;
  task?: {
    id: number;
    title: string;
    description: string;
    status: TaskStatus;
    priority: TaskPriority;
    deadline: string;
    assignee?: { id?: number; name: string };
  } | null;
}

export function CreateTaskModal({ isOpen, onClose, onTaskCreated, task }: CreateTaskModalProps) {
  const { toast } = useToast();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [users, setUsers] = useState<Array<{ id: number; name: string; email: string }>>([]);
  const isEditMode = !!task;
  const [formData, setFormData] = useState({
    title: task?.title || "",
    description: task?.description || "",
    status: task?.status || "pending" as TaskStatus,
    priority: task?.priority || "medium" as TaskPriority,
    deadline: task?.deadline || "",
    assignee_id: task?.assignee?.id || 0,
  });

  // Fetch users list
  useEffect(() => {
    if (isOpen) {
      fetchUsers();
    }
  }, [isOpen]);

  const fetchUsers = async () => {
    try {
      const response = await fetch('http://localhost:8000/backend/users.php');
      const data = await response.json();
      if (data.success && data.users) {
        setUsers(data.users);
      }
    } catch (error) {
      console.error('Failed to fetch users:', error);
    }
  };

  // Update form data when task prop changes
  useEffect(() => {
    if (task) {
      setFormData({
        title: task.title,
        description: task.description,
        status: task.status,
        priority: task.priority,
        deadline: task.deadline,
        assignee_id: task.assignee?.id || 0,
      });
    } else {
      setFormData({
        title: "",
        description: "",
        status: "pending",
        priority: "medium",
        deadline: "",
        assignee_id: 0,
      });
    }
  }, [task, isOpen]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!formData.title.trim()) {
      toast({
        variant: "destructive",
        title: "Validation Error",
        description: "Task title is required",
      });
      return;
    }

    setIsSubmitting(true);

    try {
      const user = JSON.parse(localStorage.getItem('user') || '{}');
      
      const url = isEditMode 
        ? 'http://localhost:8000/backend/update_task.php' 
        : 'http://localhost:8000/backend/create_task.php';
      
      const payload = isEditMode
        ? { ...formData, task_id: task?.id, user_id: user.id }
        : { ...formData, user_id: user.id, assignee_id: formData.assignee_id || user.id };
      
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': localStorage.getItem('token') || '',
        },
        body: JSON.stringify(payload),
      });

      const data = await response.json();

      if (data.success) {
        toast({
          title: isEditMode ? "Task updated" : "Task created",
          description: isEditMode 
            ? "Your task has been updated successfully." 
            : "Your task has been created successfully.",
        });
        
        // Reset form only if creating
        if (!isEditMode) {
          setFormData({
            title: "",
            description: "",
            status: "pending",
            priority: "medium",
            deadline: "",
            assignee_id: 0,
          });
        }
        
        onTaskCreated();
        onClose();
      } else {
        toast({
          variant: "destructive",
          title: isEditMode ? "Update failed" : "Creation failed",
          description: data.message || `Failed to ${isEditMode ? 'update' : 'create'} task`,
        });
      }
    } catch (error) {
      console.error(`Failed to ${isEditMode ? 'update' : 'create'} task:`, error);
      toast({
        variant: "destructive",
        title: "Network error",
        description: `Failed to ${isEditMode ? 'update' : 'create'} task. Please try again.`,
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
            className="fixed inset-0 bg-foreground/20 backdrop-blur-sm z-50"
          />

          {/* Modal */}
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <motion.div
              initial={{ opacity: 0, scale: 0.95, y: 20 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.95, y: 20 }}
              transition={{ type: "spring", damping: 30, stiffness: 300 }}
              className="w-full max-w-2xl h-auto max-h-[90vh] rounded-2xl bg-card border border-border shadow-2xl flex flex-col"
            >
            {/* Header */}
            <div className="sticky top-0 bg-card/95 backdrop-blur-sm border-b border-border px-6 py-4 flex items-center justify-between z-10 rounded-t-2xl">
              <h2 className="text-xl font-semibold text-foreground">{isEditMode ? "Edit Task" : "Create New Task"}</h2>
              <button
                onClick={onClose}
                className="w-8 h-8 rounded-lg flex items-center justify-center text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Form */}
            <form onSubmit={handleSubmit} className="p-6 space-y-6 flex-1 overflow-y-auto">
              {/* Title */}
              <div className="space-y-2">
                <label className="text-sm font-medium text-foreground">
                  Task Title <span className="text-destructive">*</span>
                </label>
                <input
                  type="text"
                  value={formData.title}
                  onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                  placeholder="Enter task title"
                  className="w-full px-4 py-3 bg-muted/50 border border-border rounded-xl text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50 transition-all"
                  required
                />
              </div>

              {/* Description */}
              <div className="space-y-2">
                <label className="text-sm font-medium text-foreground">Description</label>
                <textarea
                  value={formData.description}
                  onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                  placeholder="Enter task description"
                  rows={4}
                  className="w-full px-4 py-3 bg-muted/50 border border-border rounded-xl text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50 transition-all resize-none"
                />
              </div>

              {/* Assigned To */}
              <div className="space-y-2">
                <label className="text-sm font-medium text-foreground">Assigned To</label>
                <select
                  value={formData.assignee_id}
                  onChange={(e) => setFormData({ ...formData, assignee_id: parseInt(e.target.value) })}
                  className="w-full px-4 py-3 bg-muted/50 border border-border rounded-xl text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50 transition-all"
                >
                  <option value="0">Select assignee</option>
                  {users.map((user) => (
                    <option key={user.id} value={user.id}>
                      {user.name} ({user.email})
                    </option>
                  ))}
                </select>
              </div>

              {/* Status and Priority */}
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <label className="text-sm font-medium text-foreground">Status</label>
                  <select
                    value={formData.status}
                    onChange={(e) => setFormData({ ...formData, status: e.target.value as TaskStatus })}
                    className="w-full px-4 py-3 bg-muted/50 border border-border rounded-xl text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50 transition-all"
                  >
                    <option value="pending">Pending</option>
                    <option value="in-progress">In Progress</option>
                    <option value="review">Review</option>
                    <option value="completed">Completed</option>
                  </select>
                </div>

                <div className="space-y-2">
                  <label className="text-sm font-medium text-foreground">Priority</label>
                  <select
                    value={formData.priority}
                    onChange={(e) => setFormData({ ...formData, priority: e.target.value as TaskPriority })}
                    className="w-full px-4 py-3 bg-muted/50 border border-border rounded-xl text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50 transition-all"
                  >
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                  </select>
                </div>
              </div>

              {/* Deadline */}
              <div className="space-y-2">
                <label className="text-sm font-medium text-foreground flex items-center gap-2">
                  <Calendar className="w-4 h-4" />
                  Deadline
                </label>
                <input
                  type="date"
                  value={formData.deadline}
                  onChange={(e) => setFormData({ ...formData, deadline: e.target.value })}
                  className="w-full px-4 py-3 bg-muted/50 border border-border rounded-xl text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50 transition-all"
                />
              </div>

              {/* Info */}
              {!isEditMode && (
                <div className="flex items-start gap-3 p-4 bg-muted/30 rounded-xl border border-border/50">
                  <AlertCircle className="w-5 h-5 text-primary flex-shrink-0 mt-0.5" />
                  <div className="text-sm text-muted-foreground">
                    <p className="font-medium text-foreground mb-1">Note</p>
                    The task will be assigned to you by default. You can change the assignee later.
                  </div>
                </div>
              )}

            </form>

            {/* Actions */}
            <div className="border-t border-border p-6 bg-card rounded-b-2xl">
              <div className="flex items-center gap-3">
                <button
                  type="submit"
                  onClick={handleSubmit}
                  disabled={isSubmitting}
                  className="flex-1 px-6 py-3 bg-primary text-primary-foreground rounded-xl font-medium hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isSubmitting ? (isEditMode ? "Updating..." : "Creating...") : (isEditMode ? "Update Task" : "Create Task")}
                </button>
                <button
                  type="button"
                  onClick={onClose}
                  disabled={isSubmitting}
                  className="px-6 py-3 bg-muted text-foreground rounded-xl font-medium hover:bg-muted/80 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Cancel
                </button>
              </div>
            </div>
            </motion.div>
          </div>
        </>
      )}
    </AnimatePresence>
  );
}
