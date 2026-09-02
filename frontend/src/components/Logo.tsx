import { cn } from "@/lib/utils";

interface LogoProps {
  size?: number;
  className?: string;
  alt?: string;
}

/**
 * Parish logo. The source file is public/logo_web.png (1000x1000).
 * Rendered with an explicit width/height to avoid layout shift.
 */
export default function Logo({ size = 64, className, alt = "St. Anthony of Padua parish logo" }: LogoProps) {
  return (
    <img
      src="/logo_web.png"
      alt={alt}
      width={size}
      height={size}
      className={cn("rounded-full object-contain", className)}
    />
  );
}
