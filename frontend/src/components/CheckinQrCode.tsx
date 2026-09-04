import { useEffect, useRef, useState } from "react";
import QRCode from "qrcode";
import { Download, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/button";

/**
 * The chapel check-in QR code, rendered client-side and downloadable as a
 * print-ready PNG for posting at the chapel entrance.
 *
 * The encoded URL is derived from the current origin, so a staging build
 * naturally produces a staging QR and production produces a production one —
 * there is no separate value to remember to update at go-live.
 */
export default function CheckinQrCode({ size = 260 }: { size?: number }) {
  const canvasRef = useRef<HTMLCanvasElement | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [ready, setReady] = useState(false);

  const checkinUrl = `${window.location.origin}/checkin`;

  useEffect(() => {
    const canvas = canvasRef.current;
    if (canvas === null) return;

    let cancelled = false;
    QRCode.toCanvas(canvas, checkinUrl, {
      width: size,
      margin: 2,
      errorCorrectionLevel: "M",
      color: { dark: "#2d2d2d", light: "#ffffff" },
    })
      .then(() => {
        if (!cancelled) setReady(true);
      })
      .catch(() => {
        if (!cancelled) setError("Could not generate the QR code.");
      });

    return () => {
      cancelled = true;
    };
  }, [checkinUrl, size]);

  const handleDownload = () => {
    const canvas = canvasRef.current;
    if (canvas === null) return;

    // Re-render at a larger size for print rather than upscaling the on-screen
    // canvas, which would come out blurry.
    const printCanvas = document.createElement("canvas");
    QRCode.toCanvas(printCanvas, checkinUrl, {
      width: 1024,
      margin: 2,
      errorCorrectionLevel: "M",
      color: { dark: "#2d2d2d", light: "#ffffff" },
    })
      .then(() => {
        const link = document.createElement("a");
        link.href = printCanvas.toDataURL("image/png");
        link.download = "st-anthony-adoration-checkin-qr.png";
        link.click();
      })
      .catch(() => setError("Could not prepare the download."));
  };

  return (
    <div className="flex flex-col items-center gap-4">
      <div className="rounded-lg border border-border bg-white p-3">
        <canvas ref={canvasRef} aria-label={`QR code linking to ${checkinUrl}`} />
      </div>

      {error !== null && <p className="text-sm text-red-600">{error}</p>}

      <p className="break-all text-center text-xs text-muted">{checkinUrl}</p>

      <Button variant="outline" onClick={handleDownload} disabled={!ready}>
        {ready ? (
          <>
            <Download className="h-4 w-4" aria-hidden="true" />
            Download print-ready PNG
          </>
        ) : (
          <>
            <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
            Preparing…
          </>
        )}
      </Button>
    </div>
  );
}
