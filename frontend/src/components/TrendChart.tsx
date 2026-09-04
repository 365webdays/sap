import type { TrendPoint } from "@/api/admin";

/**
 * Minimal CSS bar chart — no chart library, so the bundle stays small and the
 * chart stays readable on a phone. Bars are scaled to the largest value in
 * the series.
 */
export default function TrendChart({ series }: { series: TrendPoint[] }) {
  const max = Math.max(1, ...series.map((p) => p.count));

  return (
    <div className="flex items-end gap-1 overflow-x-auto pb-2" role="img" aria-label="Attendance trend">
      {series.map((point) => {
        const height = Math.round((point.count / max) * 100);
        return (
          <div key={point.bucket} className="flex min-w-[28px] flex-1 flex-col items-center gap-1">
            <div className="flex h-24 w-full items-end justify-center">
              <div
                className="w-full max-w-[24px] rounded-t bg-accent/70 transition-all"
                style={{ height: `${Math.max(2, height)}%` }}
                title={`${point.label}: ${point.count}`}
              />
            </div>
            <span className="text-[10px] leading-tight text-muted">{point.label}</span>
            <span className="text-[10px] font-medium text-text">{point.count}</span>
          </div>
        );
      })}
    </div>
  );
}
