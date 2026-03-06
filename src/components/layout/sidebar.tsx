"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/utils";

const navItems = [
  { href: "/dashboard", label: "Overview" },
  { href: "/dashboard/clarity", label: "Microsoft Clarity" },
  { href: "/dashboard/contentsquare", label: "ContentSquare" },
  { href: "/dashboard/clarity-demo-1", label: "Clarity Demo 1" },
  { href: "/dashboard/clarity-demo-2", label: "Clarity Demo 2" },
];

export function Sidebar() {
  const pathname = usePathname();

  return (
    <aside className="flex h-full w-64 flex-col border-r bg-card">
      <div className="border-b p-6">
        <h1 className="text-xl font-bold tracking-tight">Vyzor</h1>
        <p className="text-xs text-muted-foreground">Analytics Automation</p>
      </div>
      <nav className="flex-1 space-y-1 p-4">
        {navItems.map((item) => (
          <Link
            key={item.href}
            href={item.href}
            className={cn(
              "block rounded-md px-3 py-2 text-sm font-medium transition-colors",
              pathname === item.href
                ? "bg-primary text-primary-foreground"
                : "text-muted-foreground hover:bg-accent hover:text-accent-foreground",
            )}
          >
            {item.label}
          </Link>
        ))}
      </nav>
      <div className="border-t p-4">
        <p className="text-xs text-muted-foreground">
          Providers: 2 registered
        </p>
      </div>
    </aside>
  );
}
