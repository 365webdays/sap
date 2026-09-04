import type { PeakCell } from "@/api/admin";
import { DAYS as SCHEDULE_DAYS } from "@/lib/schedule";

const HOURS = Array.from({ length: 24 }, (_, h) => h);

/**
 * Day x hour heatmap of check-in frequency. Cells with no check-ins are empty
 * (transparent); cells with data shade from light to dark accent.
 */
export default function PeakHeatmap({ cells, max }: { cells: PeakCell[]; max: number }) {
  const lookup = new Map<string, number>();
  for (const cell of cells) {
    lookup.set(`${cell.day}|${cell.hour}`, cell.count);
  }

  const intensity = (count: number) => {
    if (count === 0 || max === 0) return 0;
    return Math.max(0.1, count / max);
  };

  return (
    <div className="overflow-x-auto">
      <table className="w-full border-collapse text-xs">
        <thead>
          <tr>
            <th className="sticky left-0 z-10 bg-surface px-2 py-1 text-left text-muted">Hour</th>
            {SCHEDULE_DAYS.map((day) => (
              <th key={day} className="px-1 py-1 text-center font-medium text-muted">
                {day.slice(0, 3)}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {HOURS.map((hour) => (
            <tr key={hour}>
              <td className="sticky left-0 z-10 bg-surface px-2 py-0.5 text-muted">
                {hour === 0 ? "12a" : hour < 12 ? `${hour}a` : hour === 12 ? "12p" : `${hour - 12}p`}
              </td>
              {SCHEDULE_DAYS.map((day) => {
                const count = lookup.get(`${day}|${hour}`) ?? 0;
                const alpha = intensity(count);
                return (
                  <td key={day} className="p-0.5">
                    <div
                      className="flex h-6 items-center justify-center rounded text-[10px]"
                      style={{
                        backgroundColor: count > 0 ? `rgba(103, 155, 8, ${alpha})` : "transparent",
                        color: alpha > 0.5 ? "#fff" : "inherit",
                      }}
                      title={`${day} ${hour}:00 — ${count} check-in${count === 1 ? "" : "s"}`}
                    >
                      {count > 0 ? count : ""}
                    </div>
                  </td>
                );
              })}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
