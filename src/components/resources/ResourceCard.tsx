import { motion } from "framer-motion";
import { Monitor, Cpu, DoorOpen, Package, Pencil, Check, X, Send, Undo2, UserPlus, Wrench, CheckCircle2, UserCheck, Clock3, AlertCircle } from "lucide-react";
import { cn } from "@/lib/utils";

export type ResourceType = "device" | "software" | "room" | "equipment";
export type ResourceStatus = "available" | "assigned" | "maintenance" | "requested" | "rejected";

interface ResourceCardProps {
  id: number;
  name: string;
  type: ResourceType;
  status: ResourceStatus;
  assignedTo?: {
    id?: number;
    name: string;
  };
  requestedBy?: {
    id?: number;
    name: string;
    department?: string | null;
  };
  requestedAt?: string;
  description?: string;
  location?: string;
  isAdmin?: boolean;
  onModify?: () => void;
  onAssign?: () => void;
  onRequest?: () => void;
  onMarkAvailable?: () => void;
  onMaintenance?: () => void;
  onApprove?: () => void;
  onReject?: () => void;
  delay?: number;
}

const typeConfig = {
  device: { icon: Monitor, label: "Device" },
  software: { icon: Cpu, label: "Software" },
  room: { icon: DoorOpen, label: "Room" },
  equipment: { icon: Package, label: "Equipment" },
};

const statusConfig = {
  available: { label: "Available", class: "badge-completed", icon: CheckCircle2 },
  assigned: { label: "Assigned", class: "badge-progress", icon: UserCheck },
  maintenance: { label: "Maintenance", class: "badge-pending", icon: Wrench },
  requested: { label: "Requested", class: "bg-warning/15 text-warning", icon: Clock3 },
  rejected: { label: "Rejected", class: "bg-destructive/10 text-destructive", icon: AlertCircle },
};

export function ResourceCard({
  name,
  type,
  status,
  assignedTo,
  requestedBy,
  requestedAt,
  description,
  location,
  isAdmin = false,
  onModify,
  onAssign,
  onRequest,
  onMarkAvailable,
  onMaintenance,
  onApprove,
  onReject,
  delay = 0,
}: ResourceCardProps) {
  const typeStyle = typeConfig[type];
  const statusStyle = statusConfig[status];
  const TypeIcon = typeStyle.icon;
  const StatusIcon = statusStyle.icon;

  const requestLabel = requestedBy?.name ? `${requestedBy.name}${requestedBy.department ? ` • ${requestedBy.department}` : ""}` : "Requested by unknown";
  const requestTimeLabel = requestedAt ? new Date(requestedAt).toLocaleString() : "";

  return (
    <motion.div
      initial={{ opacity: 0, scale: 0.95 }}
      animate={{ opacity: 1, scale: 1 }}
      transition={{ duration: 0.3, delay }}
      className="card-interactive p-5 group h-full min-h-[220px] flex flex-col"
    >
      {/* Header */}
      <div className="flex items-start justify-between mb-4">
        <div className="flex items-center gap-3 min-w-0">
          <div className="w-12 h-12 rounded-2xl bg-gradient-to-br from-primary/10 to-accent/10 flex items-center justify-center group-hover:scale-110 transition-transform duration-300 flex-shrink-0">
            <TypeIcon className="w-6 h-6 text-primary" />
          </div>
          {assignedTo && (
            <div className="min-w-0">
              <p className="text-[11px] text-muted-foreground leading-none mb-1">Assigned to</p>
              <p className="text-xs font-medium text-foreground truncate">{assignedTo.name}</p>
            </div>
          )}
        </div>
        <span
          className={cn(
            "inline-flex items-center justify-center h-8 w-8 rounded-lg text-xs font-medium",
            statusStyle.class
          )}
          title={statusStyle.label}
          aria-label={statusStyle.label}
        >
          <StatusIcon className="w-4 h-4" />
        </span>
      </div>

      {/* Content */}
      <div className="space-y-2 flex-1">
        <div className="flex items-center gap-2">
          <h3 className="font-semibold text-foreground text-lg group-hover:text-primary transition-colors truncate">
            {name}
          </h3>
          <span className="text-[11px] font-medium px-2 py-0.5 rounded-md bg-muted text-muted-foreground flex-shrink-0">
            {typeStyle.label}
          </span>
        </div>
        {description && (
          <p className="text-sm text-muted-foreground line-clamp-2">
            {description}
          </p>
        )}
        {location && (
          <p className="text-xs text-muted-foreground">{location}</p>
        )}
        {(status === "requested" || status === "rejected") && (
          <div className="rounded-xl border border-border bg-muted/30 px-3 py-2 text-xs text-muted-foreground space-y-1">
            <p className="font-medium text-foreground">{requestLabel}</p>
            {requestTimeLabel && <p>{requestTimeLabel}</p>}
          </div>
        )}
      </div>

      {isAdmin ? (
        <div className="mt-4 pt-4 border-t border-border flex items-center gap-2 flex-wrap">
          <button
            type="button"
            onClick={onModify}
            title="Modify"
            aria-label="Modify"
            className="h-8 w-8 rounded-lg border border-border flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted"
          >
            <Pencil className="w-4 h-4" />
          </button>
          {status === "available" && onAssign && (
            <button
              type="button"
              onClick={onAssign}
              title="Assign"
              aria-label="Assign"
              className="h-8 w-8 rounded-lg bg-primary flex items-center justify-center text-primary-foreground"
            >
              <UserPlus className="w-4 h-4" />
            </button>
          )}
          {(status === "available" || status === "assigned" || status === "requested" || status === "rejected") && onMaintenance && (
            <button
              type="button"
              onClick={onMaintenance}
              title="Maintenance"
              aria-label="Maintenance"
              className="h-8 w-8 rounded-lg border border-border flex items-center justify-center text-foreground hover:bg-muted"
            >
              <Wrench className="w-4 h-4" />
            </button>
          )}
          {status === "assigned" && onReject && (
            <button
              type="button"
              onClick={onReject}
              title="Reject"
              aria-label="Reject"
              className="h-8 w-8 rounded-lg border border-destructive/30 flex items-center justify-center text-destructive hover:bg-destructive/10"
            >
              <X className="w-4 h-4" />
            </button>
          )}
          {status === "maintenance" && onMarkAvailable && (
            <button
              type="button"
              onClick={onMarkAvailable}
              title="Available"
              aria-label="Available"
              className="h-8 w-8 rounded-lg border border-border flex items-center justify-center text-foreground hover:bg-muted"
            >
              <Undo2 className="w-4 h-4" />
            </button>
          )}
          {status === "requested" && onApprove && (
            <button
              type="button"
              onClick={onApprove}
              title={`Approve ${requestedBy?.name || 'request'}`}
              aria-label={`Approve ${requestedBy?.name || 'request'}`}
              className="h-8 w-8 rounded-lg bg-primary flex items-center justify-center text-primary-foreground"
            >
              <Check className="w-4 h-4" />
            </button>
          )}
          {status === "requested" && onReject && (
            <button
              type="button"
              onClick={onReject}
              title="Reject"
              aria-label="Reject"
              className="h-8 w-8 rounded-lg border border-destructive/30 flex items-center justify-center text-destructive hover:bg-destructive/10"
            >
              <X className="w-4 h-4" />
            </button>
          )}
          {status === "rejected" && onApprove && (
            <button
              type="button"
              onClick={onApprove}
              title={`Approve ${requestedBy?.name || 'request'}`}
              aria-label={`Approve ${requestedBy?.name || 'request'}`}
              className="h-8 w-8 rounded-lg bg-primary flex items-center justify-center text-primary-foreground"
            >
              <Check className="w-4 h-4" />
            </button>
          )}
          {status === "rejected" && onMarkAvailable && (
            <button
              type="button"
              onClick={onMarkAvailable}
              title="Available"
              aria-label="Available"
              className="h-8 w-8 rounded-lg border border-border flex items-center justify-center text-foreground hover:bg-muted"
            >
              <Undo2 className="w-4 h-4" />
            </button>
          )}
        </div>
      ) : status === "available" && onRequest ? (
        <div className="mt-4 pt-4 border-t border-border">
          <button
            type="button"
            onClick={onRequest}
            title="Request"
            aria-label="Request"
            className="h-8 w-8 rounded-lg bg-primary flex items-center justify-center text-primary-foreground"
          >
            <Send className="w-4 h-4" />
          </button>
        </div>
      ) : null}
    </motion.div>
  );
}