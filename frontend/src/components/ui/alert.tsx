import * as React from "react";
import { AlertCircle } from "lucide-react";
import { cn } from "@/lib/utils";

interface AlertProps extends React.HTMLAttributes<HTMLDivElement> {
  variant?: "error" | "info";
}

const variantClasses = {
  error: "border-red-300 bg-red-50 text-red-800",
  info: "border-accent/30 bg-accent/5 text-text",
} as const;

/**
 * Inline banner for form-level errors and notices. Field-level errors are
 * rendered next to their inputs instead.
 */
const Alert = React.forwardRef<HTMLDivElement, AlertProps>(
  ({ className, variant = "error", children, ...props }, ref) => {
    return (
      <div
        ref={ref}
        role="alert"
        className={cn(
          "flex items-start gap-2 rounded-md border px-3 py-2 text-sm",
          variantClasses[variant],
          className
        )}
        {...props}
      >
        {variant === "error" && (
          <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
        )}
        <div className="flex-1">{children}</div>
      </div>
    );
  }
);
Alert.displayName = "Alert";

export { Alert };
