import * as React from "react";
import { cn } from "@/lib/utils";

interface SwitchProps
  extends Omit<React.InputHTMLAttributes<HTMLInputElement>, "type" | "role"> {
  label: React.ReactNode;
  description?: React.ReactNode;
}

/**
 * Labelled on/off toggle. A native checkbox drives it, so keyboard focus,
 * space-to-toggle, and screen-reader state all work without extra wiring; the
 * visible track and knob are styled siblings driven by :checked.
 */
const Switch = React.forwardRef<HTMLInputElement, SwitchProps>(
  ({ className, label, description, id, ...props }, ref) => {
    const generatedId = React.useId();
    const inputId = id ?? generatedId;

    return (
      <label
        htmlFor={inputId}
        className="flex cursor-pointer items-start justify-between gap-4 py-3"
      >
        <span className="flex-1">
          <span className="block text-sm font-medium text-text">{label}</span>
          {description && (
            <span className="mt-0.5 block text-sm text-muted">{description}</span>
          )}
        </span>

        <span className="relative mt-0.5 inline-flex shrink-0">
          <input
            id={inputId}
            ref={ref}
            type="checkbox"
            className={cn(
              "peer h-7 w-12 cursor-pointer appearance-none rounded-full bg-border transition-colors checked:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50",
              className
            )}
            {...props}
          />
          <span
            aria-hidden="true"
            className="pointer-events-none absolute left-0.5 top-0.5 h-6 w-6 rounded-full bg-surface shadow-sm transition-transform peer-checked:translate-x-5"
          />
        </span>
      </label>
    );
  }
);
Switch.displayName = "Switch";

export { Switch };
