import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { motion, AnimatePresence } from "framer-motion";
import { Bell, Search, ChevronDown, Moon, Sun, LogOut, User, Settings, Clock } from "lucide-react";
import { cn } from "@/lib/utils";

interface TopBarProps {
  title: string;
  subtitle?: string;
}

interface Notification {
  id: number;
  type: string;
  title: string;
  message: string;
  time: string;
  read: boolean;
}

export function TopBar({ title, subtitle }: TopBarProps) {
  const navigate = useNavigate();
  const [showNotifications, setShowNotifications] = useState(false);
  const [showUserMenu, setShowUserMenu] = useState(false);
  const [isDark, setIsDark] = useState(false);
  const [searchFocused, setSearchFocused] = useState(false);
  const [user, setUser] = useState<{name: string; email: string; role: string; avatar: string | null} | null>(null);
  const [unreadCount, setUnreadCount] = useState(0);
  const [recentNotifications, setRecentNotifications] = useState<Notification[]>([]);

  useEffect(() => {
    fetchUserData();
    fetchUnreadCount();
    syncTheme();
    const interval = setInterval(fetchUnreadCount, 30000); // Refresh every 30 seconds
    return () => clearInterval(interval);
  }, []);

  const syncTheme = () => {
    // Sync theme state with current document class
    const hasDarkClass = document.documentElement.classList.contains('dark');
    setIsDark(hasDarkClass);
  };

  const fetchUnreadCount = async () => {
    try {
      const storedUser = JSON.parse(localStorage.getItem('user') || '{}');
      const response = await fetch(`http://localhost:8000/notifications.php?userId=${storedUser.id}&filter=unread`, {
        headers: {
          'Authorization': localStorage.getItem('token') || '',
        },
      });
      const data = await response.json();
      if (data.success) {
        setUnreadCount(data.unreadCount || 0);
      }
    } catch (error) {
      console.error('Failed to fetch unread count:', error);
    }
  };

  const fetchRecentNotifications = async () => {
    try {
      const storedUser = JSON.parse(localStorage.getItem('user') || '{}');
      const response = await fetch(`http://localhost:8000/notifications.php?userId=${storedUser.id}&filter=all`, {
        headers: {
          'Authorization': localStorage.getItem('token') || '',
        },
      });
      const data = await response.json();
      if (data.success && data.notifications) {
        setRecentNotifications(data.notifications.slice(0, 3));
      }
    } catch (error) {
      console.error('Failed to fetch recent notifications:', error);
    }
  };

  const fetchUserData = async () => {
    try {
      const storedUser = JSON.parse(localStorage.getItem('user') || '{}');
      const response = await fetch(`http://localhost:8000/profile.php?id=${storedUser.id}`, {
        headers: {
          'Authorization': localStorage.getItem('token') || '',
        },
      });
      const data = await response.json();
      if (data.success) {
        setUser({
          name: data.user.name,
          email: data.user.email,
          role: data.user.role || 'User',
          avatar: data.user.avatar || null
        });
      }
    } catch (error) {
      console.error('Failed to fetch user data:', error);
    }
  };

  const toggleTheme = async () => {
    const newTheme = isDark ? 'light' : 'dark';
    setIsDark(!isDark);
    document.documentElement.classList.toggle("dark");
    
    // Save theme to database
    try {
      const storedUser = JSON.parse(localStorage.getItem('user') || '{}');
      if (storedUser.id) {
        // Fetch current preferences first
        const getResponse = await fetch(`http://localhost:8000/settings.php?userId=${storedUser.id}`, {
          headers: {
            'Authorization': localStorage.getItem('token') || '',
          },
        });
        const getData = await getResponse.json();
        
        if (getData.success) {
          const updatedPreferences = { ...getData.preferences, theme: newTheme };
          
          // Save updated preferences
          await fetch('http://localhost:8000/settings.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': localStorage.getItem('token') || '',
            },
            body: JSON.stringify({
              action: 'updatePreferences',
              id: storedUser.id,
              preferences: updatedPreferences,
            }),
          });
        }
      }
    } catch (error) {
      console.error('Failed to save theme:', error);
    }
  };

  const handleLogout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    navigate('/login');
  };

  const getInitials = (name: string) => {
    return name
      .split(' ')
      .map(n => n[0])
      .join('')
      .toUpperCase()
      .slice(0, 2);
  };

  return (
    <header className="h-[72px] bg-card/80 backdrop-blur-xl border-b border-border/50 px-8 flex items-center justify-between sticky top-0 z-40">
      {/* Left Section - Title */}
      <div>
        <motion.h1
          initial={{ opacity: 0, y: -10 }}
          animate={{ opacity: 1, y: 0 }}
          className="text-xl font-semibold text-foreground tracking-tight"
        >
          {title}
        </motion.h1>
        {subtitle && (
          <motion.p
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ delay: 0.1 }}
            className="text-sm text-muted-foreground mt-0.5"
          >
            {subtitle}
          </motion.p>
        )}
      </div>

      {/* Right Section */}
      <div className="flex items-center gap-3">
        {/* Search */}
        <motion.div
          animate={{ width: searchFocused ? 280 : 200 }}
          className="relative hidden md:block"
        >
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
          <input
            type="text"
            placeholder="Search..."
            onFocus={() => setSearchFocused(true)}
            onBlur={() => setSearchFocused(false)}
            className="w-full pl-10 pr-4 py-2 bg-secondary/50 border border-border/50 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50 transition-all duration-200"
          />
        </motion.div>

        {/* Theme Toggle */}
        <button
          onClick={toggleTheme}
          className="w-10 h-10 rounded-xl bg-secondary/50 border border-border/50 flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-secondary transition-all duration-200"
        >
          <AnimatePresence mode="wait">
            {isDark ? (
              <motion.div
                key="sun"
                initial={{ rotate: -90, opacity: 0 }}
                animate={{ rotate: 0, opacity: 1 }}
                exit={{ rotate: 90, opacity: 0 }}
                transition={{ duration: 0.2 }}
              >
                <Sun className="w-4 h-4" />
              </motion.div>
            ) : (
              <motion.div
                key="moon"
                initial={{ rotate: 90, opacity: 0 }}
                animate={{ rotate: 0, opacity: 1 }}
                exit={{ rotate: -90, opacity: 0 }}
                transition={{ duration: 0.2 }}
              >
                <Moon className="w-4 h-4" />
              </motion.div>
            )}
          </AnimatePresence>
        </button>

        {/* Notifications */}
        <div className="relative">
          <button
            onClick={() => {
              setShowNotifications(!showNotifications);
              setShowUserMenu(false);
              if (!showNotifications) {
                fetchRecentNotifications();
              }
            }}
            className="relative w-10 h-10 rounded-xl bg-secondary/50 border border-border/50 flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-secondary transition-all duration-200"
          >
            <Bell className="w-4 h-4" />
            {unreadCount > 0 && (
              <motion.span
                initial={{ scale: 0 }}
                animate={{ scale: 1 }}
                className="absolute -top-1 -right-1 w-5 h-5 bg-destructive text-destructive-foreground text-[10px] font-bold rounded-full flex items-center justify-center"
              >
                {unreadCount}
              </motion.span>
            )}
          </button>

          <AnimatePresence>
            {showNotifications && (
              <motion.div
                initial={{ opacity: 0, y: 10, scale: 0.95 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                exit={{ opacity: 0, y: 10, scale: 0.95 }}
                transition={{ duration: 0.2 }}
                className="absolute right-0 top-full mt-2 w-80 bg-card rounded-2xl border border-border shadow-soft-xl overflow-hidden"
              >
                <div className="px-4 py-3 border-b border-border">
                  <h3 className="font-semibold text-foreground">Notifications</h3>
                </div>
                <div className="max-h-80 overflow-y-auto">
                  {recentNotifications.length > 0 ? (
                    <div className="divide-y divide-border">
                      {recentNotifications.map((notification) => (
                        <div key={notification.id} className="px-4 py-3 hover:bg-muted/50 transition-colors cursor-pointer">
                          <div className="flex gap-3">
                            <div className="flex-shrink-0 mt-1">
                              <div className={cn("w-2 h-2 rounded-full", !notification.read ? "bg-primary" : "bg-muted")}>
                              </div>
                            </div>
                            <div className="flex-1 min-w-0">
                              <p className="text-sm font-medium text-foreground">{notification.title}</p>
                              <p className="text-xs text-muted-foreground mt-0.5 line-clamp-2">{notification.message}</p>
                              <p className="text-xs text-muted-foreground mt-1.5 flex items-center gap-1">
                                <Clock className="w-3 h-3" />
                                {notification.time}
                              </p>
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="px-4 py-8 text-center text-sm text-muted-foreground">
                      No notifications
                    </div>
                  )}
                </div>
                <div className="px-4 py-3 border-t border-border bg-muted/30">
                  <button 
                    onClick={() => {
                      setShowNotifications(false);
                      navigate('/notifications');
                    }}
                    className="text-sm text-primary font-medium hover:underline"
                  >
                    View all notifications
                  </button>
                </div>
              </motion.div>
            )}
          </AnimatePresence>
        </div>

        {/* User Menu */}
        <div className="relative">
          <button
            onClick={() => {
              setShowUserMenu(!showUserMenu);
              setShowNotifications(false);
            }}
            className="flex items-center gap-3 pl-3 pr-2 py-1.5 rounded-xl hover:bg-secondary/50 transition-all duration-200"
          >
            <div className="text-right hidden sm:block">
              <p className="text-sm font-medium text-foreground">{user?.name || 'Loading...'}</p>
              <p className="text-xs text-muted-foreground">{user?.role || 'User'}</p>
            </div>
            {user?.avatar ? (
              <img
                src={`http://localhost:8000/${user.avatar}`}
                alt={user.name}
                className="w-9 h-9 rounded-xl object-cover"
              />
            ) : (
              <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center">
                <span className="text-primary-foreground font-semibold text-sm">
                  {user ? getInitials(user.name) : '??'}
                </span>
              </div>
            )}
            <span className="hidden sm:inline-flex items-center justify-center px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wide bg-accent/15 text-accent border border-accent/30">
              {user?.role || 'User'}
            </span>
            <ChevronDown className="w-4 h-4 text-muted-foreground" />
          </button>

          <AnimatePresence>
            {showUserMenu && (
              <motion.div
                initial={{ opacity: 0, y: 10, scale: 0.95 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                exit={{ opacity: 0, y: 10, scale: 0.95 }}
                transition={{ duration: 0.2 }}
                className="absolute right-0 top-full mt-2 w-56 bg-card rounded-2xl border border-border shadow-soft-xl overflow-hidden"
              >
                <div className="p-2">
                  <button 
                    onClick={() => {
                      navigate('/profile');
                      setShowUserMenu(false);
                    }}
                    className="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-foreground hover:bg-muted transition-colors"
                  >
                    <User className="w-4 h-4" />
                    Profile
                  </button>
                  <button 
                    onClick={() => {
                      navigate('/settings');
                      setShowUserMenu(false);
                    }}
                    className="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-foreground hover:bg-muted transition-colors"
                  >
                    <Settings className="w-4 h-4" />
                    Settings
                  </button>
                </div>
                <div className="border-t border-border p-2">
                  <button 
                    onClick={handleLogout}
                    className="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-destructive hover:bg-destructive/10 transition-colors"
                  >
                    <LogOut className="w-4 h-4" />
                    Sign out
                  </button>
                </div>
              </motion.div>
            )}
          </AnimatePresence>
        </div>
      </div>
    </header>
  );
}