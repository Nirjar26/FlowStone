import { useEffect, useMemo, useState } from "react";
import { NavLink, useLocation } from "react-router-dom";
import { motion, AnimatePresence } from "framer-motion";
import {
  LayoutDashboard,
  CheckSquare,
  ClipboardCheck,
  Box,
  BarChart3,
  Bell,
  Settings,
  Shield,
  ChevronLeft,
} from "lucide-react";
import { cn } from "@/lib/utils";

const navItems = [
  { icon: LayoutDashboard, label: "Dashboard", path: "/dashboard" },
  { icon: CheckSquare, label: "Tasks", path: "/tasks" },
  { icon: ClipboardCheck, label: "Approvals", path: "/approvals" },
  { icon: Box, label: "Resources", path: "/resources" },
  { icon: BarChart3, label: "Reports", path: "/reports" },
  { icon: Bell, label: "Notifications", path: "/notifications" },
  { icon: Settings, label: "Settings", path: "/settings" },
];

interface SidebarProps {
  collapsed?: boolean;
  onCollapsedChange?: (value: boolean) => void;
}

export function Sidebar({ collapsed, onCollapsedChange }: SidebarProps) {
  const [internalCollapsed, setInternalCollapsed] = useState(false);
  const [isAdmin, setIsAdmin] = useState(false);
  const location = useLocation();

  const collapsedState = collapsed ?? internalCollapsed;
  const setCollapsedState = (value: boolean) => {
    if (collapsed !== undefined) {
      onCollapsedChange?.(value);
    } else {
      setInternalCollapsed(value);
    }
  };

  const toggleCollapsed = () => setCollapsedState(!collapsedState);

  useEffect(() => {
    const loadRole = async () => {
      try {
        const storedUser = JSON.parse(localStorage.getItem("user") || "{}");
        const localRole = (storedUser?.role || "").toLowerCase();

        if (localRole.includes("admin")) {
          setIsAdmin(true);
          return;
        }

        if (!storedUser?.id) {
          setIsAdmin(false);
          return;
        }

        const response = await fetch(`http://localhost:8000/profile.php?id=${storedUser.id}`, {
          headers: {
            Authorization: localStorage.getItem("token") || "",
          },
        });
        const data = await response.json();
        const role = (data?.user?.role || "").toLowerCase();
        setIsAdmin(Boolean(data?.success && role.includes("admin")));
      } catch (error) {
        console.error("Failed to resolve admin role:", error);
        setIsAdmin(false);
      }
    };

    loadRole();
  }, []);

  const visibleNavItems = useMemo(() => {
    if (!isAdmin) {
      return navItems;
    }

    return [...navItems, { icon: Shield, label: "Admin", path: "/admin" }];
  }, [isAdmin]);

  return (
    <motion.aside
      initial={false}
      animate={{ width: collapsedState ? 80 : 260 }}
      transition={{ duration: 0.3, ease: [0.4, 0, 0.2, 1] }}
      className="fixed left-0 top-0 h-screen bg-sidebar z-50 flex flex-col border-r border-sidebar-border"
    >
      {/* Logo Section */}
      <div className="flex items-center justify-between px-5 py-6 border-b border-sidebar-border">
        <AnimatePresence mode="wait">
          {!collapsed && (
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              className="flex items-center gap-3"
            >
              <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-sidebar-primary to-sidebar-primary/70 flex items-center justify-center shadow-lg">
                <span className="text-sidebar-primary-foreground font-bold text-sm">SR</span>
              </div>
              <div>
                <h1 className="text-sidebar-accent-foreground font-semibold text-base tracking-tight">
                  SmartFlow
                </h1>
                <p className="text-sidebar-muted text-[10px] uppercase tracking-widest">
                  Resource Hub
                </p>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
        
        {collapsed && (
          <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-sidebar-primary to-sidebar-primary/70 flex items-center justify-center shadow-lg mx-auto">
            <span className="text-sidebar-primary-foreground font-bold text-sm">SR</span>
          </div>
        )}
      </div>

      {/* Navigation */}
      <nav className="flex-1 py-6 px-3 space-y-1.5 overflow-y-auto scrollbar-thin">
        {visibleNavItems.map((item, index) => {
          const isActive = location.pathname === item.path;
          return (
            <NavLink
              key={item.path}
              to={item.path}
              className="block"
            >
              <motion.div
                initial={{ opacity: 0, x: -20 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: index * 0.05 }}
                className={cn(
                  "flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200 group relative",
                  isActive
                    ? "bg-sidebar-accent text-sidebar-primary"
                    : "text-sidebar-foreground hover:bg-sidebar-accent/50 hover:text-sidebar-accent-foreground"
                )}
              >
                {isActive && (
                  <motion.div
                    layoutId="activeIndicator"
                    className="absolute left-0 inset-y-0 my-auto w-1 h-8 bg-sidebar-primary rounded-r-full -ml-3"
                    transition={{ type: "spring", stiffness: 380, damping: 30 }}
                  />
                )}
                <item.icon
                  className={cn(
                    "w-5 h-5 flex-shrink-0 transition-transform duration-200",
                    isActive ? "text-sidebar-primary" : "group-hover:scale-110"
                  )}
                />
                <AnimatePresence mode="wait">
                  {!collapsed && (
                    <motion.span
                      initial={{ opacity: 0, width: 0 }}
                      animate={{ opacity: 1, width: "auto" }}
                      exit={{ opacity: 0, width: 0 }}
                      className="font-medium text-sm whitespace-nowrap"
                    >
                      {item.label}
                    </motion.span>
                  )}
                </AnimatePresence>
              </motion.div>
            </NavLink>
          );
        })}
      </nav>

      {/* Bottom Section */}
      <div className="p-3 border-t border-sidebar-border">
        <button
          onClick={toggleCollapsed}
          className="w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl text-sidebar-muted hover:bg-sidebar-accent/50 hover:text-sidebar-accent-foreground transition-all duration-200"
        >
          <motion.div
            animate={{ rotate: collapsedState ? 180 : 0 }}
            transition={{ duration: 0.3 }}
          >
            <ChevronLeft className="w-5 h-5" />
          </motion.div>
          <AnimatePresence mode="wait">
            {!collapsedState && (
              <motion.span
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                exit={{ opacity: 0 }}
                className="text-sm font-medium"
              >
                Collapse
              </motion.span>
            )}
          </AnimatePresence>
        </button>
      </div>
    </motion.aside>
  );
}